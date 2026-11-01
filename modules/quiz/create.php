<?php

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/modes/QuestionModeRegistry.php';

$creatorId = intval($_SESSION['id_creator']);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $mode = trim($_POST['mode'] ?? 'normal');
    $gameType = ($_POST['game_type'] ?? 'classic') === 'obstacle' ? 'obstacle' : 'classic';

    if (!QuestionModeRegistry::has($mode)) {
        $mode = 'normal';
    }

    if (empty($name)) {
        set_flash_message('danger', 'Please enter a title for the quiz!');
        redirect('modules/quiz/create.php');
    }

    $obstacleImage = null;
    $obstacleAnswer = null;
    $totalPieces = 4;

    if ($gameType === 'obstacle') {
        $obstacleAnswer = trim($_POST['obstacle_answer'] ?? '');
        $totalPieces = intval($_POST['total_pieces'] ?? 4);
        if (!in_array($totalPieces, [4, 6, 8, 9])) {
            $totalPieces = 4;
        }

        if (empty($obstacleAnswer)) {
            set_flash_message('danger', 'Please enter the answer keyword for the secret image!');
            redirect('modules/quiz/create.php');
        }

        if (isset($_FILES['obstacle_image']) && $_FILES['obstacle_image']['error'] === UPLOAD_ERR_OK) {
            $fileInfo = $_FILES['obstacle_image'];
            $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (!in_array($fileInfo['type'], $allowedMimes)) {
                set_flash_message('danger', 'Obstacle image must be JPG, PNG, WEBP, or GIF!');
                redirect('modules/quiz/create.php');
            }

            $uploadDir = __DIR__ . '/../../assets/img/obstacles/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $ext = strtolower(pathinfo($fileInfo['name'], PATHINFO_EXTENSION));
            $filename = 'obs_' . uniqid() . '_' . time() . '.' . $ext;
            $targetPath = $uploadDir . $filename;

            if (move_uploaded_file($fileInfo['tmp_name'], $targetPath)) {
                $obstacleImage = 'assets/img/obstacles/' . $filename;
            } else {
                set_flash_message('danger', 'Failed to upload secret obstacle image!');
                redirect('modules/quiz/create.php');
            }
        } else {
            set_flash_message('danger', 'Please upload a secret image for the Obstacle Puzzle mode!');
            redirect('modules/quiz/create.php');
        }
    }

    try {
        $newQuizId = db_insert(
            "INSERT INTO quiz (id_creator, name, description, mode, game_type, obstacle_image, obstacle_answer, total_pieces) 
             VALUES (:c, :n, :d, :m, :gt, :img, :ans, :tp)",
            [
                ':c'   => $creatorId,
                ':n'   => $name,
                ':d'   => $description,
                ':m'   => $mode,
                ':gt'  => $gameType,
                ':img' => $obstacleImage,
                ':ans' => $obstacleAnswer,
                ':tp'  => $totalPieces
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

<style>
.game-type-card {
    border: 2px solid #e9ecef;
    border-radius: 14px;
    padding: 16px 18px;
    cursor: pointer;
    transition: all 0.2s ease;
    background: #ffffff;
    height: 100%;
    position: relative;
}
.game-type-card:hover {
    border-color: var(--kahoot-purple);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(70, 23, 143, 0.12);
}
.game-type-card.selected {
    border-color: var(--kahoot-purple);
    background: #fbf9ff;
    box-shadow: 0 4px 14px rgba(70, 23, 143, 0.18);
}
.game-type-card.selected::after {
    content: "✓";
    position: absolute;
    top: 10px;
    right: 12px;
    width: 22px;
    height: 22px;
    background: var(--kahoot-purple);
    color: #ffffff;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: bold;
}
.piece-options-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 8px;
}
.piece-option-btn {
    border: 1px solid #ced4da;
    border-radius: 8px;
    padding: 8px 6px;
    text-align: center;
    cursor: pointer;
    background: #ffffff;
    transition: all 0.15s ease;
    margin: 0;
}
.piece-option-btn input {
    display: none;
}
.piece-option-btn:hover {
    border-color: #f39c12;
}
.piece-option-btn:has(input:checked) {
    border-color: #f39c12;
    background: #fff8e7;
    font-weight: bold;
    color: #d35400;
}
</style>

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
                    <form action="" method="POST" enctype="multipart/form-data">
                        
                        <!-- GAME FORMAT SELECTION -->
                        <div class="form-group mb-4">
                            <label class="font-weight-bold d-block mb-2 text-dark" style="font-size: 1.05rem;">
                                Select Game Format <span class="text-danger">*</span>
                            </label>
                            <input type="hidden" name="game_type" id="inputGameType" value="classic">

                            <div class="row" style="gap: 12px 0;">
                                <div class="col-md-6">
                                    <div class="game-type-card selected" id="cardClassic" onclick="selectGameType('classic')">
                                        <div class="d-flex align-items-center mb-2">
                                            <span style="font-size: 1.8rem;" class="mr-2">🎯</span>
                                            <div>
                                                <h6 class="font-weight-bold mb-0 text-dark">Classic Kahoot</h6>
                                                <small class="text-muted">Standard live quiz</small>
                                            </div>
                                        </div>
                                        <p class="text-muted small mb-0">
                                            Players race to answer live questions shown on the host screen.
                                        </p>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="game-type-card" id="cardObstacle" onclick="selectGameType('obstacle')">
                                        <div class="d-flex align-items-center mb-2">
                                            <span style="font-size: 1.8rem;" class="mr-2">🧩</span>
                                            <div>
                                                <h6 class="font-weight-bold mb-0 text-dark">Obstacle Puzzle</h6>
                                                <small class="text-warning font-weight-bold">Secret Image</small>
                                            </div>
                                        </div>
                                        <p class="text-muted small mb-0">
                                            Players answer questions to flip puzzle tiles and buzz in to guess the secret image!
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- OBSTACLE SETTINGS (Collapsible) -->
                        <div id="obstacleSettings" style="display: none;" class="p-3 mb-4 rounded border" style="background: #fffcf4; border-color: #ffe082 !important;">
                            <h6 class="font-weight-bold text-dark mb-3">
                                <i class="fas fa-puzzle-piece text-warning mr-1"></i> Obstacle Puzzle Setup
                            </h6>

                            <div class="form-group mb-3">
                                <label class="font-weight-bold text-dark">
                                    Secret Image <span class="text-danger">*</span>
                                </label>
                                <div class="custom-file">
                                    <input type="file" name="obstacle_image" class="custom-file-input" id="obstacleImageInput" accept="image/*" onchange="previewObstacleImage(this)">
                                    <label class="custom-file-label text-truncate" for="obstacleImageInput" id="obstacleImageLabel">Choose image (JPG, PNG, WEBP)...</label>
                                </div>
                                <div class="mt-2 text-center">
                                    <img id="obstacleImgPreview" src="" alt="Preview" style="max-height: 180px; max-width: 100%; border-radius: 8px; display: none; margin: 0 auto; box-shadow: 0 2px 8px rgba(0,0,0,0.15);">
                                </div>
                            </div>

                            <div class="form-group mb-3">
                                <label class="font-weight-bold text-dark">
                                    Answer Keyword / Title <span class="text-danger">*</span>
                                </label>
                                <input type="text" name="obstacle_answer" id="obstacleAnswerInput" class="form-control" placeholder="e.g. Mona Lisa, Eiffel Tower, Ha Long Bay...">
                                <small class="text-muted">Keywords are matched case-insensitively.</small>
                            </div>

                            <div class="form-group mb-2">
                                <label class="font-weight-bold text-dark d-block mb-1">
                                    Number of Puzzle Tiles:
                                </label>
                                <div class="piece-options-grid">
                                    <label class="piece-option-btn">
                                        <input type="radio" id="pieces4" name="total_pieces" value="4" checked>
                                        <span class="piece-option-label">4 tiles <small>(2×2)</small></span>
                                    </label>
                                    <label class="piece-option-btn">
                                        <input type="radio" id="pieces6" name="total_pieces" value="6">
                                        <span class="piece-option-label">6 tiles <small>(2×3)</small></span>
                                    </label>
                                    <label class="piece-option-btn">
                                        <input type="radio" id="pieces8" name="total_pieces" value="8">
                                        <span class="piece-option-label">8 tiles <small>(2×4)</small></span>
                                    </label>
                                    <label class="piece-option-btn">
                                        <input type="radio" id="pieces9" name="total_pieces" value="9">
                                        <span class="piece-option-label">9 tiles <small>(3×3)</small></span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="form-group mb-3">
                            <label class="font-weight-bold">Quiz Title <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control form-control-lg" placeholder="e.g. Science Trivia, English Vocab..." required autofocus>
                        </div>

                        <div class="form-group mb-3">
                            <label class="font-weight-bold">Short Description</label>
                            <textarea name="description" class="form-control" rows="3" placeholder="Brief summary of what this quiz is about..."></textarea>
                        </div>

                        <!-- DEFAULT QUESTION MODE -->
                        <div class="form-group mb-4">
                            <label class="font-weight-bold d-block mb-1 text-dark" style="font-size: 1rem;">
                                <i class="fas fa-gamepad text-primary mr-1"></i> Default Question Mode <span class="text-danger">*</span>
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
                            <div id="modeDescriptionBox" class="mt-2 p-2 px-3 rounded bg-light border text-muted small" style="line-height: 1.45;"></div>
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
function selectGameType(type) {
    document.getElementById('inputGameType').value = type;
    const cardClassic = document.getElementById('cardClassic');
    const cardObstacle = document.getElementById('cardObstacle');
    const obsSettings = document.getElementById('obstacleSettings');
    const fileInput = document.getElementById('obstacleImageInput');
    const ansInput = document.getElementById('obstacleAnswerInput');

    if (type === 'obstacle') {
        cardClassic.classList.remove('selected');
        cardObstacle.classList.add('selected');
        obsSettings.style.display = 'block';
        fileInput.setAttribute('required', 'required');
        ansInput.setAttribute('required', 'required');
    } else {
        cardObstacle.classList.remove('selected');
        cardClassic.classList.add('selected');
        obsSettings.style.display = 'none';
        fileInput.removeAttribute('required');
        ansInput.removeAttribute('required');
    }
}

function previewObstacleImage(input) {
    const preview = document.getElementById('obstacleImgPreview');
    const label = document.getElementById('obstacleImageLabel');
    if (input.files && input.files[0]) {
        const file = input.files[0];
        label.textContent = file.name;
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.src = e.target.result;
            preview.style.display = 'block';
        };
        reader.readAsDataURL(file);
    } else {
        preview.style.display = 'none';
        label.textContent = 'Choose image (JPG, PNG, WEBP)...';
    }
}

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