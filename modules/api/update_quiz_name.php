<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

$creatorId = intval($_SESSION['id_creator']);
$data      = json_decode(file_get_contents('php://input'), true);
$quizId    = intval($data['quiz_id'] ?? 0);
$name      = trim($data['name'] ?? '');

if (!$quizId || $name === '') {
    echo json_encode(['success' => false, 'error' => 'Invalid input']);
    exit;
}

$quiz = db_fetch_one("SELECT id_quiz FROM quiz WHERE id_quiz = :q AND id_creator = :c", [':q' => $quizId, ':c' => $creatorId]);
if (!$quiz) {
    echo json_encode(['success' => false, 'error' => 'Not found']);
    exit;
}

db_execute("UPDATE quiz SET name = :n WHERE id_quiz = :q", [':n' => $name, ':q' => $quizId]);
echo json_encode(['success' => true]);
