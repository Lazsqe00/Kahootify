<?php

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/database.php';

$creatorId = intval($_SESSION['id_creator']);
$quizId    = intval($_GET['id_quiz'] ?? 0);

$questions = db_fetch_all("SELECT id_question FROM questions WHERE id_quiz = :q ORDER BY id_question ASC", [':q' => $quizId]);

if (empty($questions)) {
    set_flash_message('warning', 'This quiz has no questions! Please add questions before hosting a game.');
    redirect('modules/quiz/edit.php?id=' . $quizId);
}

do {
    $pin = sprintf("%06d", mt_rand(100000, 999999));
    $existing = db_fetch_one("SELECT id_room FROM room WHERE pin = :pin AND status != 'finished'", [':pin' => $pin]);
} while ($existing);

db_execute("UPDATE room SET status = 'finished' WHERE id_creator = :c AND status != 'finished'", [':c' => $creatorId]);

$roomId = db_insert(
    "INSERT INTO room (id_quiz, id_creator, pin, status) VALUES (:q, :c, :pin, 'waiting')",
    [
        ':q'   => $quizId,
        ':c'   => $creatorId,
        ':pin' => $pin
    ]
);

$_SESSION['id_room'] = $roomId;
$_SESSION['pin'] = $pin;
$_SESSION['id_quiz'] = $quizId;
$_SESSION['question_index'] = 0;

redirect('modules/host/lobby.php');
