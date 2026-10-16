<?php
/**
 * API tiếp nhận và chấm điểm câu trả lời của Người chơi
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

$playerId = intval($data['id_player'] ?? 0);
$answerId = intval($data['id_answer'] ?? 0);
$roomId   = intval($data['id_room'] ?? 0);

if ($playerId <= 0 || $answerId <= 0 || $roomId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Missing required data']);
    exit;
}

// Kiểm tra người chơi có thuộc đúng phòng này không
$playerCheck = db_fetch_one("SELECT id_player FROM players WHERE id_player = :p AND id_room = :r", [':p' => $playerId, ':r' => $roomId]);
if (!$playerCheck) {
    echo json_encode(['success' => false, 'message' => 'Player not found in this room']);
    exit;
}

try {
    // 1. Lấy thông tin đáp án và câu hỏi
    $ans = db_fetch_one(
        "SELECT a.*, q.points, q.time_limit, r.question_start_time, r.status 
         FROM answers a 
         JOIN questions q ON a.id_question = q.id_question 
         JOIN room r ON r.id_room = :r 
         WHERE a.id_answer = :aid",
        [':r' => $roomId, ':aid' => $answerId]
    );

    if (!$ans) {
        echo json_encode(['success' => false, 'message' => 'Invalid answer or room']);
        exit;
    }

    $qId = intval($ans['id_question']);

    // 2. Kiểm tra xem người chơi đã nộp câu này chưa (chống gian lận nộp 2 lần)
    $alreadyVoted = db_fetch_one(
        "SELECT v.id_vote FROM votes v JOIN answers a ON v.id_answer = a.id_answer WHERE v.id_player = :p AND a.id_question = :q LIMIT 1",
        [':p' => $playerId, ':q' => $qId]
    );

    if ($alreadyVoted) {
        echo json_encode(['success' => false, 'message' => 'You have already answered this question']);
        exit;
    }

    // 3. Tính điểm số (Thuật toán Kahoot: điểm giảm dần theo thời gian trả lời)
    $isCorrect = ($ans['is_correct'] == 1);
    $pointsEarned = 0;

    if ($isCorrect) {
        $basePoints = intval($ans['points'] ?: 1000);
        $timeLimit  = intval($ans['time_limit'] ?: 20);
        $startTime  = intval($ans['question_start_time'] ?: time());
        $elapsed    = max(0, time() - $startTime);
        
        $ratio = min(1, $elapsed / max(1, $timeLimit));
        // Trả lời lập tức = 100% điểm, trả lời sát nút = 50% điểm
        $pointsEarned = intval(round($basePoints * (1 - ($ratio / 2))));
    }

    // 4. Lưu lượt vote
    db_insert(
        "INSERT INTO votes (id_player, id_answer, points_earned) VALUES (:p, :a, :pts)",
        [
            ':p'   => $playerId,
            ':a'   => $answerId,
            ':pts' => $pointsEarned
        ]
    );

    // 5. Cập nhật tổng điểm và số câu đúng của người chơi
    if ($isCorrect) {
        db_execute(
            "UPDATE players SET score = score + :pts, answers_correct = answers_correct + 1 WHERE id_player = :p",
            [':pts' => $pointsEarned, ':p' => $playerId]
        );
    }

    // 6. Kiểm tra nếu tất cả người chơi trong phòng đều đã nộp bài cho câu này -> Tự động chuyển sang kết quả
    $totalPlayers = db_count('players', 'id_room = :r', [':r' => $roomId]);
    $votedCount = db_fetch_one(
        "SELECT COUNT(DISTINCT v.id_player) as total 
         FROM votes v JOIN answers a ON v.id_answer = a.id_answer 
         WHERE a.id_question = :qid AND v.id_player IN (SELECT id_player FROM players WHERE id_room = :r)",
        [':qid' => $qId, ':r' => $roomId]
    )['total'] ?? 0;

    if ($totalPlayers > 0 && $votedCount >= $totalPlayers) {
        db_execute("UPDATE room SET status = :s WHERE id_room = :r AND status = :curQ", [
            ':s'    => 'res_' . $qId,
            ':r'    => $roomId,
            ':curQ' => (string)$qId
        ]);
    }

    echo json_encode([
        'success'       => true,
        'is_correct'    => $isCorrect,
        'points_earned' => $pointsEarned
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
