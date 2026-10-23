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

$playerCheck = db_fetch_one("SELECT id_player, current_streak FROM players WHERE id_player = :p AND id_room = :r", [':p' => $playerId, ':r' => $roomId]);
if (!$playerCheck) {
    echo json_encode(['success' => false, 'message' => 'Player not found in this room']);
    exit;
}
$currentStreak = intval($playerCheck['current_streak'] ?? 0);

try {

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

    $alreadyVoted = db_fetch_one(
        "SELECT v.id_vote FROM votes v JOIN answers a ON v.id_answer = a.id_answer WHERE v.id_player = :p AND a.id_question = :q LIMIT 1",
        [':p' => $playerId, ':q' => $qId]
    );

    if ($alreadyVoted) {
        echo json_encode(['success' => false, 'message' => 'You have already answered this question']);
        exit;
    }

    $isCorrect = ($ans['is_correct'] == 1);
    $pointsEarned = 0;
    $streakBonus = 0;
    $newStreak = 0;
    $streakLost = false;

    if ($isCorrect) {
        $basePoints = intval($ans['points'] ?: 1000);
        $timeLimit  = intval($ans['time_limit'] ?: 20);
        $startTime  = intval($ans['question_start_time'] ?: time());
        $elapsed    = max(0, time() - $startTime);

        $ratio = min(1, $elapsed / max(1, $timeLimit));

        $pointsEarned = intval(round($basePoints * (1 - ($ratio / 2))));

        // Tăng chuỗi đúng liên tiếp
        $newStreak = $currentStreak + 1;

        // Tính điểm thưởng chuỗi (Streak Bonus)
        if ($newStreak == 2) {
            $streakBonus = 100;
        } elseif ($newStreak == 3) {
            $streakBonus = 200;
        } elseif ($newStreak == 4) {
            $streakBonus = 300;
        } elseif ($newStreak >= 5) {
            $streakBonus = 500;
        } else {
            $streakBonus = 0;
        }

        $totalPts = $pointsEarned + $streakBonus;

        db_insert(
            "INSERT INTO votes (id_player, id_answer, points_earned, streak_bonus) VALUES (:p, :a, :pts, :bonus)",
            [
                ':p'     => $playerId,
                ':a'     => $answerId,
                ':pts'   => $pointsEarned,
                ':bonus' => $streakBonus
            ]
        );

        db_execute(
            "UPDATE players SET score = score + :totalPts, answers_correct = answers_correct + 1, current_streak = :newStreak WHERE id_player = :p",
            [':totalPts' => $totalPts, ':newStreak' => $newStreak, ':p' => $playerId]
        );
    } else {
        // Trả lời SAI: reset chuỗi về 0
        $streakLost = ($currentStreak >= 2);
        $newStreak = 0;
        $streakBonus = 0;

        db_insert(
            "INSERT INTO votes (id_player, id_answer, points_earned, streak_bonus) VALUES (:p, :a, :pts, :bonus)",
            [
                ':p'     => $playerId,
                ':a'     => $answerId,
                ':pts'   => 0,
                ':bonus' => 0
            ]
        );

        db_execute("UPDATE players SET current_streak = 0 WHERE id_player = :p", [':p' => $playerId]);
    }

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
        'points_earned' => $pointsEarned,
        'streak'        => $newStreak,
        'streak_bonus'  => $streakBonus,
        'streak_lost'   => $streakLost
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
