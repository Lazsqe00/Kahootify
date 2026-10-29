<?php

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/database.php';

$creatorId = intval($_SESSION['id_creator']);
$quizId    = intval($_GET['id'] ?? 0);

if ($quizId > 0) {
    $srcQuiz = db_fetch_one("SELECT * FROM quiz WHERE id_quiz = :q AND id_creator = :c", [
        ':q' => $quizId,
        ':c' => $creatorId
    ]);

    if ($srcQuiz) {
        $newName = $srcQuiz['name'] . ' (Copy)';
        if (mb_strlen($newName) > 100) {
            $newName = mb_substr($srcQuiz['name'], 0, 93) . ' (Copy)';
        }

        $newQuizId = db_insert(
            "INSERT INTO quiz (id_creator, name, description) VALUES (:c, :n, :d)",
            [
                ':c' => $creatorId,
                ':n' => $newName,
                ':d' => $srcQuiz['description']
            ]
        );

        if ($newQuizId) {
            $questions = db_fetch_all(
                "SELECT * FROM questions WHERE id_quiz = :q ORDER BY sort_order ASC, id_question ASC",
                [':q' => $quizId]
            );

            foreach ($questions as $q) {
                $newQId = db_insert(
                    "INSERT INTO questions (id_quiz, text, points, time_limit, image, question_type, media_type, sort_order) VALUES (:qz, :t, :p, :tl, :img, :qt, :mt, :so)",
                    [
                        ':qz'  => $newQuizId,
                        ':t'   => $q['text'],
                        ':p'   => $q['points'],
                        ':tl'  => $q['time_limit'],
                        ':img' => $q['image'],
                        ':qt'  => $q['question_type'] ?? 'multiple_choice',
                        ':mt'  => $q['media_type'] ?? null,
                        ':so'  => $q['sort_order'] ?? 0
                    ]
                );

                if ($newQId) {
                    $answers = db_fetch_all(
                        "SELECT * FROM answers WHERE id_question = :qid ORDER BY id_answer ASC",
                        [':qid' => $q['id_question']]
                    );

                    foreach ($answers as $a) {
                        db_insert(
                            "INSERT INTO answers (id_question, is_correct, text, image_path) VALUES (:qid, :c, :t, :img)",
                            [
                                ':qid' => $newQId,
                                ':c'   => $a['is_correct'],
                                ':t'   => $a['text'],
                                ':img' => $a['image_path'] ?? null
                            ]
                        );
                    }
                }
            }
            set_flash_message('success', 'Quiz duplicated successfully!');
        } else {
            set_flash_message('danger', 'Failed to duplicate quiz.');
        }
    } else {
        set_flash_message('danger', 'Quiz not found.');
    }
}

$ref = $_GET['ref'] ?? '';
if ($ref === 'dashboard') {
    redirect('modules/auth/dashboard.php');
} else {
    redirect('modules/quiz/list.php');
}
