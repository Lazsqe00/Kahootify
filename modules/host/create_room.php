<?php

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/database.php';

$creatorId = intval($_SESSION['id_creator']);
$quizId    = intval($_GET['id_quiz'] ?? 0);

$quiz = db_fetch_one("SELECT id_quiz, game_type, is_team_mode, team_count, team_player_limit, is_review_mode, review_start_time, review_end_time FROM quiz WHERE id_quiz = :q AND id_creator = :c", [':q' => $quizId, ':c' => $creatorId]);
if (!$quiz) {
    set_flash_message('danger', 'Quiz not found or access denied.');
    redirect('modules/quiz/list.php');
}

$questions = db_fetch_all("SELECT id_question FROM questions WHERE id_quiz = :q ORDER BY id_question ASC", [':q' => $quizId]);

if (empty($questions)) {
    set_flash_message('warning', 'This quiz has no questions! Please add questions before hosting a game.');
    redirect('modules/quiz/edit.php?id=' . $quizId);
}

// Review Mode handling: redirect to review_lobby.php if an active review room exists
if (!empty($quiz['is_review_mode'])) {
    $activeRoom = db_fetch_one(
        "SELECT * FROM room WHERE id_quiz = :q AND is_review_mode = 1 AND status = 'review_active' LIMIT 1",
        [':q' => $quizId]
    );

    // If active room has expired, finalize it
    if ($activeRoom && !empty($activeRoom['review_end_time']) && strtotime($activeRoom['review_end_time']) < time()) {
        db_execute("UPDATE room SET status = 'finished' WHERE id_room = :r", [':r' => $activeRoom['id_room']]);
        db_execute("UPDATE quiz SET is_review_mode = 0, review_start_time = NULL, review_end_time = NULL WHERE id_quiz = :q", [':q' => $quizId]);
        $activeRoom = null;
        $quiz['is_review_mode'] = 0;
    }

    if ($activeRoom) {
        $reviewRoomId = intval($activeRoom['id_room']);
        $activePin = $activeRoom['pin'];
        $_SESSION['id_room'] = $reviewRoomId;
        $_SESSION['pin'] = $activePin;
        $_SESSION['id_quiz'] = $quizId;
        redirect('modules/host/review_lobby.php?id_room=' . $reviewRoomId);
    } else {
        // No active review session; reset quiz review mode and proceed to normal host
        db_execute("UPDATE quiz SET is_review_mode = 0, review_start_time = NULL, review_end_time = NULL WHERE id_quiz = :q", [':q' => $quizId]);
        $quiz['is_review_mode'] = 0;
    }
}

$gameType = $quiz['game_type'] ?? 'classic';

if ($gameType === 'obstacle') {
    $quizDetail = db_fetch_one("SELECT total_pieces FROM quiz WHERE id_quiz = :q", [':q' => $quizId]);
    $requiredPieces = intval($quizDetail['total_pieces'] ?? 4);
    if (count($questions) < $requiredPieces) {
        set_flash_message('warning', "This Puzzle quiz requires exactly {$requiredPieces} questions for all {$requiredPieces} tiles before hosting! (Currently has " . count($questions) . " questions)");
        redirect('modules/quiz/edit.php?id=' . $quizId);
    }
}

do {
    $pin = sprintf("%06d", mt_rand(100000, 999999));
    $existing = db_fetch_one("SELECT id_room FROM room WHERE pin = :pin AND status != 'finished'", [':pin' => $pin]);
} while ($existing);

db_execute("UPDATE room SET status = 'finished' WHERE id_creator = :c AND status != 'finished' AND is_review_mode = 0", [':c' => $creatorId]);

$roomId = db_insert(
    "INSERT INTO room (id_quiz, id_creator, pin, status, game_type, opened_pieces, buzzer_status, is_team_mode, team_count, team_player_limit) VALUES (:q, :c, :pin, 'waiting', :gt, '[]', 'idle', :tm, :tc, :tpl)",
    [
        ':q'   => $quizId,
        ':c'   => $creatorId,
        ':pin' => $pin,
        ':gt'  => $gameType,
        ':tm'  => intval($quiz['is_team_mode'] ?? 0),
        ':tc'  => max(2, min(10, intval($quiz['team_count'] ?? 2))),
        ':tpl' => max(0, intval($quiz['team_player_limit'] ?? 0))
    ]
);

$_SESSION['id_room'] = $roomId;
$_SESSION['pin'] = $pin;
$_SESSION['id_quiz'] = $quizId;
$_SESSION['question_index'] = 0;

redirect('modules/host/lobby.php');
