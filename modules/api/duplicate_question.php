<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

$creatorId  = intval($_SESSION['id_creator']);
$data       = json_decode(file_get_contents('php://input'), true);
$questionId = intval($data['question_id'] ?? 0);
$quizId     = intval($data['quiz_id'] ?? 0);

if (!$questionId || !$quizId) {
    echo json_encode(['success' => false]);
    exit;
}

$quiz = db_fetch_one("SELECT id_quiz FROM quiz WHERE id_quiz = :q AND id_creator = :c", [':q' => $quizId, ':c' => $creatorId]);
if (!$quiz) {
    echo json_encode(['success' => false]);
    exit;
}

$srcQ = db_fetch_one("SELECT * FROM questions WHERE id_question = :q AND id_quiz = :qz", [':q' => $questionId, ':qz' => $quizId]);
if (!$srcQ) {
    echo json_encode(['success' => false]);
    exit;
}

$maxSo  = db_fetch_one("SELECT COALESCE(MAX(sort_order), 0) AS mo FROM questions WHERE id_quiz = :qz", [':qz' => $quizId]);
$newSo  = intval($maxSo['mo']) + 1;
$qType  = $srcQ['question_type'] ?? 'multiple_choice';
$mType  = $srcQ['media_type']    ?? null;

$newQId = db_insert(
    "INSERT INTO questions (id_quiz, text, points, time_limit, image, question_type, media_type, sort_order) VALUES (:qz,:t,:p,:tl,:img,:qt,:mt,:so)",
    [':qz'=>$quizId,':t'=>$srcQ['text'],':p'=>$srcQ['points'],':tl'=>$srcQ['time_limit'],':img'=>$srcQ['image'],':qt'=>$qType,':mt'=>$mType,':so'=>$newSo]
);

$srcAnswers = db_fetch_all("SELECT * FROM answers WHERE id_question = :q ORDER BY id_answer ASC", [':q' => $questionId]);
$newAnswers = [];
foreach ($srcAnswers as $a) {
    $imgPath = $a['image_path'] ?? null;
    $newAId  = db_insert(
        "INSERT INTO answers (id_question, is_correct, text, image_path) VALUES (:qid,:c,:t,:img)",
        [':qid'=>$newQId,':c'=>$a['is_correct'],':t'=>$a['text'],':img'=>$imgPath]
    );
    $newAnswers[] = [
        'id_answer'  => $newAId,
        'is_correct' => $a['is_correct'],
        'text'       => $a['text'],
        'image_path' => $imgPath,
    ];
}

$newQ             = db_fetch_one("SELECT * FROM questions WHERE id_question = :q", [':q' => $newQId]);
$newQ['answers']  = $newAnswers;

echo json_encode(['success' => true, 'question' => $newQ]);
