<?php
/**
 * THÀNH VIÊN 2: Xóa bộ đề (Quiz)
 */
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/database.php';

$creatorId = intval($_SESSION['id_creator']);
$quizId    = intval($_GET['id'] ?? 0);

if ($quizId > 0) {
    db_execute("DELETE FROM quiz WHERE id_quiz = :q AND id_creator = :c", [
        ':q' => $quizId,
        ':c' => $creatorId
    ]);
    set_flash_message('success', 'Quiz deleted successfully!');
}

redirect('modules/quiz/list.php');
