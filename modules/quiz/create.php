<?php
/**
 * THÀNH VIÊN 2: Tạo bộ đề trắc nghiệm mới
 */
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/database.php';

$creatorId = intval($_SESSION['id_creator']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if (empty($name)) {
        set_flash_message('danger', 'Please enter a title for the quiz!');
        redirect('modules/quiz/create.php');
    }

    try {
        $newQuizId = db_insert(
            "INSERT INTO quiz (id_creator, name, description) VALUES (:c, :n, :d)",
            [
                ':c' => $creatorId,
                ':n' => $name,
                ':d' => $description
            ]
        );

        set_flash_message('success', 'Quiz created successfully! Now add questions below.');
        redirect('modules/quiz/edit.php?id=' . $newQuizId);

    } catch (Exception $e) {
        set_flash_message('danger', 'Error creating quiz: ' . $e->getMessage());
        redirect('modules/quiz/create.php');
    }
}

$pageTitle = "Create New Quiz | Kahoot!";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
?>

<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card border-0 shadow" style="border-radius: 14px;">
                <div class="card-header text-white py-3" style="background-color: var(--kahoot-purple);">
                    <h5 class="font-weight-bold mb-0">
                        <i class="fas fa-plus-circle mr-2"></i>Create New Kahoot! Quiz
                    </h5>
                </div>
                <div class="card-body p-4">
                    <form action="" method="POST">
                        <div class="form-group">
                            <label class="font-weight-bold">Quiz Title <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control form-control-lg" required autofocus>
                        </div>

                        <div class="form-group">
                            <label class="font-weight-bold">Short Description</label>
                            <textarea name="description" class="form-control" rows="4"></textarea>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top">
                            <a href="<?= BASE_URL ?>modules/quiz/list.php" class="btn btn-secondary font-weight-bold">
                                <i class="fas fa-arrow-left mr-1"></i> Back
                            </a>
                            <button type="submit" class="btn btn-kahoot btn-kahoot-green px-4">
                                <i class="fas fa-check mr-1"></i> Continue: Add Questions &raquo;
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
