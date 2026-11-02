<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

$creatorId = intval($_SESSION['id_creator']);
$data      = json_decode(file_get_contents('php://input'), true);
$quizId    = intval($data['quiz_id'] ?? 0);
$order     = $data['order'] ?? [];

if (!$quizId || empty($order)) {
    echo json_encode(['success' => false]);
    exit;
}

$quiz = db_fetch_one("SELECT id_quiz, game_type FROM quiz WHERE id_quiz = :q AND id_creator = :c", [':q' => $quizId, ':c' => $creatorId]);
if (!$quiz) {
    echo json_encode(['success' => false]);
    exit;
}

$isObstacle = (($quiz['game_type'] ?? 'classic') === 'obstacle');

foreach ($order as $item) {
    $idx1 = intval($item['order']) + 1;
    if ($isObstacle) {
        db_execute(
            "UPDATE questions SET sort_order = :o, piece_index = :pi WHERE id_question = :q AND id_quiz = :qz",
            [':o' => $idx1, ':pi' => $idx1, ':q' => intval($item['id']), ':qz' => $quizId]
        );
    } else {
        db_execute(
            "UPDATE questions SET sort_order = :o WHERE id_question = :q AND id_quiz = :qz",
            [':o' => $idx1, ':q' => intval($item['id']), ':qz' => $quizId]
        );
    }
}

echo json_encode(['success' => true]);
