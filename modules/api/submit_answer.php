<?php

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

if (!player_request_is_valid($playerId, $roomId, $data['token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid player session']);
    exit;
}
$room = db_fetch_one('SELECT id_room, pin FROM room WHERE id_room = :r', [':r' => $roomId]);
if (!$room) {
    echo json_encode(['success' => false, 'message' => 'Room does not exist']);
    exit;
}

$playerCheck = db_fetch_one("SELECT id_player FROM players WHERE id_player = :p AND id_room = :r", [':p' => $playerId, ':r' => $roomId]);
if (!$playerCheck) {
    echo json_encode(['success' => false, 'message' => 'Player not found in this room']);
    exit;
}

try {
    // Serialize kick and answer requests until the vote and score are saved.
    $result = with_room_access_lock($room, function ($handle) use ($room, $playerId, $answerId, $roomId) {
        $state = read_room_access_state($handle);
        if (!empty($state['kicked'][$playerId])) {
            return ['success' => false, 'kicked' => true, 'message' => 'You were removed from the game by the host.'];
        }

        $ans = db_fetch_one(
            "SELECT a.*, q.points, q.time_limit, r.question_start_time, r.status
             FROM answers a
             JOIN questions q ON a.id_question = q.id_question
             JOIN room r ON r.id_room = :r
             WHERE a.id_answer = :aid",
            [':r' => $roomId, ':aid' => $answerId]
        );

        if (!$ans) {
            return ['success' => false, 'message' => 'Invalid answer or room'];
        }

        $qId = intval($ans['id_question']);

        $alreadyVoted = db_fetch_one(
            "SELECT v.id_vote FROM votes v JOIN answers a ON v.id_answer = a.id_answer WHERE v.id_player = :p AND a.id_question = :q LIMIT 1",
            [':p' => $playerId, ':q' => $qId]
        );

        if ($alreadyVoted) {
            return ['success' => false, 'message' => 'You have already answered this question'];
        }

        $isCorrect = ($ans['is_correct'] == 1);
        $pointsEarned = 0;

        if ($isCorrect) {
            $basePoints = intval($ans['points'] ?: 1000);
            $timeLimit  = intval($ans['time_limit'] ?: 20);
            $startTime  = intval($ans['question_start_time'] ?: time());
            $elapsed    = max(0, time() - $startTime);

            $ratio = min(1, $elapsed / max(1, $timeLimit));

            $pointsEarned = intval(round($basePoints * (1 - ($ratio / 2))));
        }

        db_insert(
            "INSERT INTO votes (id_player, id_answer, points_earned) VALUES (:p, :a, :pts)",
            [
                ':p'   => $playerId,
                ':a'   => $answerId,
                ':pts' => $pointsEarned
            ]
        );

        if ($isCorrect) {
            db_execute(
                "UPDATE players SET score = score + :pts, answers_correct = answers_correct + 1 WHERE id_player = :p",
                [':pts' => $pointsEarned, ':p' => $playerId]
            );
        }

        $counts = room_question_counts($room, $qId, $state);
        $totalPlayers = $counts['total_players'];
        $votedCount = $counts['voted_count'];

        if ($totalPlayers > 0 && $votedCount >= $totalPlayers) {
            db_execute("UPDATE room SET status = :s WHERE id_room = :r AND status = :curQ", [
                ':s'    => 'res_' . $qId,
                ':r'    => $roomId,
                ':curQ' => (string)$qId
            ]);
        }

        return [
            'success'       => true,
            'is_correct'    => $isCorrect,
            'points_earned' => $pointsEarned
        ];
    });
    echo json_encode($result);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to submit your answer. Please try again.']);
}
