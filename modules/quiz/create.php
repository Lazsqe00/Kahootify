<?php

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/modes/QuestionModeRegistry.php';

$creatorId = intval($_SESSION['id_creator']);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $gameFormat = trim($_POST['game_format'] ?? 'classic');

    if ($gameFormat === 'puzzle' || ($_POST['game_type'] ?? '') === 'obstacle') {
        $gameType = 'obstacle';
        $mode = 'normal';
    } elseif ($gameFormat === 'wager' || ($_POST['mode'] ?? '') === 'wager') {
        $gameType = 'classic';
        $mode = 'wager';
    } else {
        $gameType = 'classic';
        $mode = 'normal';
    }

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
        if (!in_array($totalPieces, [4, 6, 8, 9], true)) {
            $totalPieces = 4;
        }

        if (empty($obstacleAnswer)) {
            set_flash_message('danger', 'Please enter the answer keyword for the secret image!');
            redirect('modules/quiz/create.php');
        }

        if (isset($_FILES['obstacle_image']) && $_FILES['obstacle_image']['error'] === UPLOAD_ERR_OK) {
            $fileInfo = $_FILES['obstacle_image'];
            $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (!in_array($fileInfo['type'], $allowedMimes, true)) {
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
            set_flash_message('danger', 'Please upload a secret image for Puzzle mode!');
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

$pageTitle = "Create Quiz | Kahoot!";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
?>

<style>
body {
    background-color: #f8fafc;
}

.create-wrapper {
    max-width: 680px;
    margin: 36px auto 60px;
    padding: 0 16px;
}

.create-header {
    margin-bottom: 20px;
}

.create-title {
    font-size: 1.35rem;
    font-weight: 700;
    color: #0f172a;
    margin: 0;
}

.create-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 28px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
}

.field-label {
    display: block;
    font-size: 0.88rem;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 8px;
}

.format-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 10px;
    margin-bottom: 22px;
}

@media (max-width: 600px) {
    .format-grid {
        grid-template-columns: 1fr;
    }
}

.format-card {
    border: 1.5px solid #e2e8f0;
    border-radius: 10px;
    padding: 12px 14px;
    cursor: pointer;
    background: #ffffff;
    transition: all 0.15s ease;
    user-select: none;
}

.format-card:hover {
    border-color: #cbd5e1;
    background: #f8fafc;
}

.format-card.is-active {
    border-color: #46178f;
    background: #faf7ff;
    box-shadow: 0 0 0 1px #46178f;
}

.format-title {
    font-weight: 700;
    font-size: 0.92rem;
    color: #0f172a;
    margin-bottom: 3px;
}

.format-card.is-active .format-title {
    color: #46178f;
}

.format-desc {
    font-size: 0.78rem;
    color: #64748b;
    line-height: 1.35;
}

.puzzle-box {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 18px 20px;
    margin-bottom: 22px;
}

.puzzle-box-title {
    font-size: 0.9rem;
    font-weight: 700;
    color: #0f172a;
    margin-bottom: 12px;
}

.tile-pills {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.tile-pill-label {
    margin: 0;
    cursor: pointer;
}

.tile-pill-label input {
    display: none;
}

.tile-pill-text {
    display: inline-block;
    padding: 6px 12px;
    border: 1px solid #cbd5e1;
    border-radius: 7px;
    background: #ffffff;
    font-size: 0.82rem;
    font-weight: 600;
    color: #334155;
    transition: all 0.15s ease;
}

.tile-pill-label input:checked + .tile-pill-text {
    border-color: #46178f;
    background: #46178f;
    color: #ffffff;
}

.custom-input {
    width: 100%;
    padding: 9px 12px;
    font-size: 0.92rem;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    background: #ffffff;
    color: #0f172a;
    outline: none;
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.custom-input:focus {
    border-color: #46178f;
    box-shadow: 0 0 0 3px rgba(70, 23, 143, 0.1);
}

.custom-textarea {
    width: 100%;
    padding: 9px 12px;
    font-size: 0.9rem;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    background: #ffffff;
    color: #0f172a;
    outline: none;
    resize: vertical;
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.custom-textarea:focus {
    border-color: #46178f;
    box-shadow: 0 0 0 3px rgba(70, 23, 143, 0.1);
}

.btn-submit {
    display: inline-block;
    padding: 10px 22px;
    background: #46178f;
    color: #ffffff;
    font-size: 0.92rem;
    font-weight: 700;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    transition: background 0.15s ease;
}

.btn-submit:hover {
    background: #361170;
}

.btn-cancel {
    display: inline-block;
    padding: 9px 18px;
    background: transparent;
    color: #64748b;
    font-size: 0.9rem;
    font-weight: 600;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    text-decoration: none;
    transition: all 0.15s ease;
}

.btn-cancel:hover {
    color: #0f172a;
    border-color: #94a3b8;
    text-decoration: none;
}
</style>

<div class="create-wrapper">

    <div class="create-header">
        <h2 class="create-title">New Quiz</h2>
    </div>

    <div class="create-card">
        <form action="" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="game_format" id="inputGameFormat" value="classic">

            <!-- MODE SELECTOR -->
            <label class="field-label">Game Mode</label>
            <div class="format-grid">
                <div class="format-card is-active" id="card-classic" onclick="setFormat('classic')">
                    <div class="format-title">Classic</div>
                    <div class="format-desc">Standard live quiz with timed multiple-choice questions</div>
                </div>
                <div class="format-card" id="card-wager" onclick="setFormat('wager')">
                    <div class="format-title">Wager</div>
                    <div class="format-desc">Players bet a portion of their points on each answer</div>
                </div>
                <div class="format-card" id="card-puzzle" onclick="setFormat('puzzle')">
                    <div class="format-title">Puzzle</div>
                    <div class="format-desc">Answers reveal hidden tiles of a secret picture</div>
                </div>
            </div>

            <!-- PUZZLE SETUP (Only visible when Puzzle is selected) -->
            <div id="puzzleSection" class="puzzle-box" style="display: none;">
                <div class="puzzle-box-title">Puzzle Settings</div>

                <div class="form-group mb-3">
                    <label class="field-label" style="font-size: 0.82rem;">Secret Picture <span class="text-danger">*</span></label>
                    <input type="file" name="obstacle_image" id="obstacleImageInput" class="form-control-file" accept="image/*" onchange="previewSecretImage(this)">
                    <div id="imgPreviewWrapper" class="mt-2" style="display: none;">
                        <img id="imgPreview" src="" alt="Secret picture preview" style="max-height: 150px; border-radius: 8px; border: 1px solid #cbd5e1; object-fit: contain;">
                    </div>
                </div>

                <div class="form-group mb-3">
                    <label class="field-label" style="font-size: 0.82rem;">Answer Keyword <span class="text-danger">*</span></label>
                    <input type="text" name="obstacle_answer" id="obstacleAnswerInput" class="custom-input" placeholder="e.g. Eiffel Tower, Mona Lisa">
                    <small class="text-muted d-block mt-1">Matched case-insensitively when players buzz in.</small>
                </div>

                <div class="form-group mb-0">
                    <label class="field-label" style="font-size: 0.82rem;">Number of Tiles</label>
                    <div class="tile-pills">
                        <label class="tile-pill-label">
                            <input type="radio" name="total_pieces" value="4" checked>
                            <span class="tile-pill-text">4 tiles (2×2)</span>
                        </label>
                        <label class="tile-pill-label">
                            <input type="radio" name="total_pieces" value="6">
                            <span class="tile-pill-text">6 tiles (2×3)</span>
                        </label>
                        <label class="tile-pill-label">
                            <input type="radio" name="total_pieces" value="8">
                            <span class="tile-pill-text">8 tiles (2×4)</span>
                        </label>
                        <label class="tile-pill-label">
                            <input type="radio" name="total_pieces" value="9">
                            <span class="tile-pill-text">9 tiles (3×3)</span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- TITLE & DESCRIPTION -->
            <div class="form-group mb-3">
                <label class="field-label">Title <span class="text-danger">*</span></label>
                <input type="text" name="name" class="custom-input" placeholder="e.g. Science Trivia, World Geography" required autofocus>
            </div>

            <div class="form-group mb-4">
                <label class="field-label">Description <span class="text-muted font-weight-normal">(optional)</span></label>
                <textarea name="description" class="custom-textarea" rows="3" placeholder="Brief summary of what this quiz covers..."></textarea>
            </div>

            <div class="d-flex justify-content-between align-items-center pt-3 border-top">
                <a href="<?= BASE_URL ?>modules/quiz/list.php" class="btn-cancel">
                    Cancel
                </a>
                <button type="submit" class="btn-submit">
                    Continue to Questions &rarr;
                </button>
            </div>

        </form>
    </div>

</div>

<script>
function setFormat(format) {
    document.getElementById('inputGameFormat').value = format;

    document.querySelectorAll('.format-card').forEach(function(el) {
        el.classList.remove('is-active');
    });
    const card = document.getElementById('card-' + format);
    if (card) card.classList.add('is-active');

    const puzzleSection = document.getElementById('puzzleSection');
    const imgInput = document.getElementById('obstacleImageInput');
    const ansInput = document.getElementById('obstacleAnswerInput');

    if (format === 'puzzle') {
        puzzleSection.style.display = 'block';
        imgInput.setAttribute('required', 'required');
        ansInput.setAttribute('required', 'required');
    } else {
        puzzleSection.style.display = 'none';
        imgInput.removeAttribute('required');
        ansInput.removeAttribute('required');
    }
}

function previewSecretImage(input) {
    const preview = document.getElementById('imgPreview');
    const wrapper = document.getElementById('imgPreviewWrapper');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.src = e.target.result;
            wrapper.style.display = 'block';
        };
        reader.readAsDataURL(input.files[0]);
    } else {
        wrapper.style.display = 'none';
    }
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>