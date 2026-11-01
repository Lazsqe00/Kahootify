<?php

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/modes/QuestionModeRegistry.php';

$creatorId = intval($_SESSION['id_creator']);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $mode = trim($_POST['mode'] ?? 'normal');

    if (!QuestionModeRegistry::has($mode)) {
        $mode = 'normal';
    }

    if (empty($name)) {
        set_flash_message('danger', 'Please enter a title for the quiz!');
        redirect('modules/quiz/create.php');
    }

    try {
        $newQuizId = db_insert(
            "INSERT INTO quiz (id_creator, name, description, mode) VALUES (:c, :n, :d, :m)",
            [
                ':c' => $creatorId,
                ':n' => $name,
                ':d' => $description,
                ':m' => $mode
            ]
        );

        $_SESSION['last_question_mode_' . $newQuizId] = $mode;

        set_flash_message('success', 'Quiz created successfully! Now add questions below.');
        redirect('modules/quiz/edit.php?id=' . $newQuizId . '&initial_mode=' . urlencode($mode));

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
            <div class="card border-0 shadow" style="border-radius: 16px;">
                <div class="card-header text-white py-3" style="background-color: var(--kahoot-purple); border-top-left-radius: 16px; border-top-right-radius: 16px;">
                    <h5 class="font-weight-bold mb-0">
                        <i class="fas fa-plus-circle mr-2"></i>Create New Kahoot! Quiz
                    </h5>
                </div>
                <div class="card-body p-4">
                    <form action="" method="POST">
                        <div class="form-group mb-3">
                            <label class="font-weight-bold">Quiz Title <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control form-control-lg" placeholder="e.g. Science Trivia, English Vocab..." required autofocus>
                        </div>

                        <div class="form-group mb-3">
                            <label class="font-weight-bold">Short Description</label>
                            <textarea name="description" class="form-control" rows="3" placeholder="Brief summary of what this quiz is about..."></textarea>
                        </div>

                        <!-- CHOOSE DEFAULT QUESTION MODE (DROPDOWN LIST) -->
                        <div class="form-group mb-4">
                            <label class="font-weight-bold d-block mb-1 text-dark" style="font-size: 1rem;">
                                <i class="fas fa-gamepad text-primary mr-1"></i> Chế độ câu hỏi mặc định (Question Mode) <span class="text-danger">*</span>
                            </label>
                            <select name="mode" id="quizModeSelect" class="form-control form-control-lg font-weight-bold text-dark" style="border-radius: 10px; height: 50px; font-size: 1.05rem; cursor: pointer;">
                                <?php 
                                $allModes = QuestionModeRegistry::getAll();
                                foreach ($allModes as $m): 
                                    $isDefault = ($m->getId() === 'normal');
                                ?>
                                <option value="<?= htmlspecialchars($m->getId()) ?>" 
                                        data-desc="<?= htmlspecialchars($m->getDescription()) ?>"
                                        <?= $isDefault ? 'selected' : '' ?>>
                                    <?= $m->getIcon() ?> <?= htmlspecialchars($m->getLabel()) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <div id="modeDescriptionBox" class="mt-2 p-2 px-3 rounded bg-light border text-muted small" style="line-height: 1.45;">
                                <!-- Populated dynamically by JS -->
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top">
                            <a href="<?= BASE_URL ?>modules/quiz/list.php" class="btn btn-secondary font-weight-bold px-3">
                                <i class="fas fa-arrow-left mr-1"></i> Back
                            </a>
                            <button type="submit" class="btn btn-kahoot btn-kahoot-green px-4 py-2 font-weight-bold" style="font-size: 1.1rem; border-radius: 10px;">
                                <i class="fas fa-check mr-1"></i> Continue: Add Questions &raquo;
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var modeSelect = document.getElementById('quizModeSelect');
    var descBox = document.getElementById('modeDescriptionBox');
    if (modeSelect && descBox) {
        function updateDesc() {
            var opt = modeSelect.options[modeSelect.selectedIndex];
            descBox.textContent = opt ? opt.getAttribute('data-desc') : '';
        }
        modeSelect.addEventListener('change', updateDesc);
        updateDesc();
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
