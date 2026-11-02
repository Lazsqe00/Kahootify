<?php

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/database.php';

$creatorId = intval($_SESSION['id_creator']);
$quizId    = intval($_GET['id'] ?? 0);

if ($quizId > 0) {
    try {
        // Proactively remove votes for this quiz's answers in case DB cascade is not yet updated
        db_execute("DELETE v FROM votes v 
                    INNER JOIN answers a ON v.id_answer = a.id_answer 
                    INNER JOIN questions q ON a.id_question = q.id_question 
                    WHERE q.id_quiz = :q", [
            ':q' => $quizId
        ]);

        db_execute("DELETE FROM quiz WHERE id_quiz = :q AND id_creator = :c", [
            ':q' => $quizId,
            ':c' => $creatorId
        ]);
        set_flash_message('success', 'Quiz deleted successfully!');
    } catch (Exception $e) {
        set_flash_message('danger', 'Error deleting quiz: ' . $e->getMessage());
    }
}

$ref = $_GET['ref'] ?? '';
redirect('modules/quiz/list.php');
