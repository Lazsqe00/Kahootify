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
        if (!in_array($totalPieces, [4, 6, 8, 9, 10, 12, 15, 16, 20, 25], true)) {
            $totalPieces = 4;
        }

        if (empty($obstacleAnswer)) {
            set_flash_message('danger', 'Please enter the secret image keyword!');
            redirect('modules/quiz/create.php');
        }

        if (isset($_FILES['obstacle_image']) && $_FILES['obstacle_image']['error'] === UPLOAD_ERR_OK) {
            $fileInfo = $_FILES['obstacle_image'];
            $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (!in_array($fileInfo['type'], $allowedMimes, true)) {
                set_flash_message('danger', 'Obstacle image must be in JPG, PNG, WEBP, or GIF format!');
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
                set_flash_message('danger', 'Failed to upload obstacle image!');
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

        set_flash_message('success', 'Quiz created successfully! Now add your questions below.');
        redirect('modules/quiz/edit.php?id=' . $newQuizId . '&initial_mode=' . urlencode($mode));

    } catch (Exception $e) {
        set_flash_message('danger', 'Error creating quiz: ' . $e->getMessage());
        redirect('modules/quiz/create.php');
    }
}

$pageTitle = "Create Quiz | Kahoot!";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
$flash = get_flash_message();
?>

<style>
body {
    background-color: #f1f3f9;
}

.create-wrapper {
    max-width: 720px;
    margin: 36px auto 60px;
    padding: 0 16px;
}

.create-header {
    margin-bottom: 22px;
}

.create-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: #ede4fc;
    color: #5817c7;
    font-family: 'Montserrat', sans-serif;
    font-weight: 800;
    font-size: 0.74rem;
    letter-spacing: 0.6px;
    padding: 4px 12px;
    border-radius: 20px;
    margin-bottom: 8px;
    text-transform: uppercase;
}

.create-title {
    font-family: 'Montserrat', sans-serif;
    font-size: 1.65rem;
    font-weight: 800;
    color: #0f172a;
    margin: 0 0 6px 0;
    letter-spacing: -0.3px;
}

.create-subtitle {
    font-family: 'Nunito', sans-serif;
    font-size: 0.95rem;
    color: #64748b;
    margin: 0;
    font-weight: 600;
}

/* TACTILE GAME CONTAINER */
.create-card {
    background: #ffffff;
    border: 2px solid #e2e8f0;
    border-bottom: 5px solid #cbd5e1;
    border-radius: 20px;
    padding: 32px 34px;
    box-shadow: 0 8px 24px rgba(15, 23, 42, 0.04);
}

.field-label {
    display: flex;
    align-items: center;
    gap: 7px;
    font-family: 'Montserrat', sans-serif;
    font-size: 0.88rem;
    font-weight: 800;
    color: #1e293b;
    margin-bottom: 10px;
}

/* GAMIFIED TACTILE MODE CARDS */
.format-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 14px;
    margin-bottom: 24px;
}

@media (max-width: 680px) {
    .format-grid {
        grid-template-columns: 1fr;
    }
}

.format-card {
    border-radius: 16px;
    padding: 16px 16px 14px;
    cursor: pointer;
    transition: transform 0.1s ease, border-color 0.15s ease, box-shadow 0.15s ease, background-color 0.15s ease;
    user-select: none;
    position: relative;
    display: flex;
    flex-direction: column;
}

.format-card:hover {
    transform: translateY(-2px);
}

.format-card:active {
    transform: translateY(2px) !important;
}

/* CLASSIC MODE (Indigo / Kahoot Purple) */
.format-card.card-classic {
    background: #faf8ff;
    border: 2px solid #e0d4fc;
    border-bottom: 4.5px solid #ccbbf7;
}
.format-card.card-classic .card-icon-bubble {
    background: #ede4fc;
    color: #6b21a8;
}
.format-card.card-classic .format-title {
    color: #2e1065;
}
.format-card.card-classic.is-active {
    background: #ffffff;
    border: 2.5px solid #6b21a8;
    border-bottom: 5px solid #4a1577;
    box-shadow: 0 8px 20px rgba(107, 33, 168, 0.15);
}
.format-card.card-classic.is-active .card-icon-bubble {
    background: linear-gradient(135deg, #7c3aed, #5817c7);
    color: #ffffff;
    box-shadow: 0 3px 8px rgba(88, 23, 199, 0.3);
}
.format-card.card-classic.is-active .card-status-pill {
    background: #ede4fc;
    color: #5817c7;
    border: 1px solid #d8b4fe;
}

/* WAGER MODE (Warm Orange / Amber) */
.format-card.card-wager {
    background: #fffcf7;
    border: 2px solid #fed7aa;
    border-bottom: 4.5px solid #fcd34d;
}
.format-card.card-wager .card-icon-bubble {
    background: #fef3c7;
    color: #d97706;
}
.format-card.card-wager .format-title {
    color: #7c2d12;
}
.format-card.card-wager.is-active {
    background: #ffffff;
    border: 2.5px solid #ea580c;
    border-bottom: 5px solid #9a3412;
    box-shadow: 0 8px 20px rgba(234, 88, 12, 0.15);
}
.format-card.card-wager.is-active .card-icon-bubble {
    background: linear-gradient(135deg, #f59e0b, #ea580c);
    color: #ffffff;
    box-shadow: 0 3px 8px rgba(234, 88, 12, 0.3);
}
.format-card.card-wager.is-active .card-status-pill {
    background: #ffedd5;
    color: #c2410c;
    border: 1px solid #fdba74;
}

/* PUZZLE MODE (Emerald / Mint Green) */
.format-card.card-puzzle {
    background: #f4fbf7;
    border: 2px solid #bbf7d0;
    border-bottom: 4.5px solid #86efac;
}
.format-card.card-puzzle .card-icon-bubble {
    background: #dcfce7;
    color: #059669;
}
.format-card.card-puzzle .format-title {
    color: #064e3b;
}
.format-card.card-puzzle.is-active {
    background: #ffffff;
    border: 2.5px solid #059669;
    border-bottom: 5px solid #065f46;
    box-shadow: 0 8px 20px rgba(5, 150, 105, 0.15);
}
.format-card.card-puzzle.is-active .card-icon-bubble {
    background: linear-gradient(135deg, #10b981, #059669);
    color: #ffffff;
    box-shadow: 0 3px 8px rgba(5, 150, 105, 0.3);
}
.format-card.card-puzzle.is-active .card-status-pill {
    background: #dcfce7;
    color: #047857;
    border: 1px solid #86efac;
}

/* CARD INNER ELEMENTS */
.card-top-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.card-icon-bubble {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.95rem;
    transition: all 0.15s ease;
}

.card-status-pill {
    font-family: 'Montserrat', sans-serif;
    font-size: 0.7rem;
    font-weight: 800;
    padding: 3px 8px;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    opacity: 0;
    transform: scale(0.8);
    transition: all 0.15s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.format-card.is-active .card-status-pill {
    opacity: 1;
    transform: scale(1);
}

.format-title {
    font-family: 'Montserrat', sans-serif;
    font-weight: 800;
    font-size: 1.02rem;
    margin-bottom: 4px;
}

.format-desc {
    font-family: 'Nunito', sans-serif;
    font-size: 0.8rem;
    color: #64748b;
    line-height: 1.35;
    font-weight: 600;
}

/* PUZZLE SETUP SECTION */
.puzzle-box {
    background: #f0fdf4;
    border: 2px solid #bbf7d0;
    border-bottom: 4.5px solid #86efac;
    border-radius: 16px;
    padding: 22px 24px;
    margin-bottom: 24px;
    animation: fadeInSlide 0.2s ease-out;
}

@keyframes fadeInSlide {
    from { opacity: 0; transform: translateY(-8px); }
    to { opacity: 1; transform: translateY(0); }
}

.puzzle-box-title {
    font-family: 'Montserrat', sans-serif;
    font-size: 0.95rem;
    font-weight: 800;
    color: #065f46;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 8px;
}

/* FILE UPLOAD STYLING */
.upload-tactile-box {
    border: 2px dashed #86efac;
    border-radius: 12px;
    padding: 14px 18px;
    background: #ffffff;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
}

.custom-file-input-hidden {
    position: absolute;
    width: 0.1px;
    height: 0.1px;
    opacity: 0;
    overflow: hidden;
    z-index: -1;
}

.btn-upload-file {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 8px 16px;
    background: #059669;
    color: #ffffff !important;
    font-family: 'Montserrat', sans-serif;
    font-size: 0.82rem;
    font-weight: 700;
    border: none;
    border-bottom: 3px solid #064e3b;
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.1s ease;
    margin: 0;
    user-select: none;
}
.btn-upload-file:hover {
    background: #047857;
    color: #ffffff !important;
    transform: translateY(-1px);
}
.btn-upload-file:active {
    transform: translateY(2px);
    border-bottom-width: 1px;
}

.upload-file-name {
    font-family: 'Nunito', sans-serif;
    font-size: 0.88rem;
    font-weight: 600;
    color: #475569;
}

/* TILE PILLS */
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
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 16px;
    border: 2px solid #cbd5e1;
    border-bottom: 4px solid #94a3b8;
    border-radius: 12px;
    background: #ffffff;
    font-family: 'Montserrat', sans-serif;
    font-size: 0.84rem;
    font-weight: 700;
    color: #334155;
    transition: all 0.1s ease;
    user-select: none;
}

.tile-pill-label:hover .tile-pill-text {
    transform: translateY(-1px);
    border-color: #94a3b8;
}

.tile-pill-label input:checked + .tile-pill-text {
    background: #059669;
    border-color: #047857;
    border-bottom: 4px solid #064e3b;
    color: #ffffff;
}

.tile-pill-label:active .tile-pill-text {
    transform: translateY(2px);
    border-bottom-width: 2px;
}

/* INPUTS & TEXTAREA */
.custom-input {
    width: 100%;
    padding: 12px 16px;
    font-family: 'Nunito', sans-serif;
    font-size: 0.95rem;
    font-weight: 600;
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    background: #f8fafc;
    color: #0f172a;
    outline: none;
    transition: border-color 0.15s ease, box-shadow 0.15s ease, background 0.15s ease;
}

.custom-input:focus {
    border-color: #5817c7;
    background: #ffffff;
    box-shadow: 0 0 0 4px rgba(88, 23, 199, 0.12);
}

.custom-textarea {
    width: 100%;
    padding: 12px 16px;
    font-family: 'Nunito', sans-serif;
    font-size: 0.92rem;
    font-weight: 600;
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    background: #f8fafc;
    color: #0f172a;
    outline: none;
    resize: vertical;
    transition: border-color 0.15s ease, box-shadow 0.15s ease, background 0.15s ease;
}

.custom-textarea:focus {
    border-color: #5817c7;
    background: #ffffff;
    box-shadow: 0 0 0 4px rgba(88, 23, 199, 0.12);
}

/* TACTILE BUTTONS */
.btn-submit {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 28px;
    background: #5817c7;
    color: #ffffff;
    font-family: 'Montserrat', sans-serif;
    font-size: 0.95rem;
    font-weight: 800;
    border: none;
    border-bottom: 4px solid #370b80;
    border-radius: 14px;
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(88, 23, 199, 0.25);
    transition: all 0.1s ease;
}

.btn-submit:hover {
    background: #621ad9;
    color: #ffffff;
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(88, 23, 199, 0.3);
}

.btn-submit:active {
    transform: translateY(2px);
    border-bottom-width: 1px;
    box-shadow: 0 2px 6px rgba(88, 23, 199, 0.2);
}

.btn-cancel {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 11px 22px;
    background: #ffffff;
    color: #64748b;
    font-family: 'Montserrat', sans-serif;
    font-size: 0.92rem;
    font-weight: 700;
    border: 2px solid #e2e8f0;
    border-bottom: 3.5px solid #cbd5e1;
    border-radius: 14px;
    text-decoration: none;
    transition: all 0.1s ease;
}

.btn-cancel:hover {
    color: #1e293b;
    background: #f8fafc;
    border-color: #cbd5e1;
    text-decoration: none;
    transform: translateY(-1px);
}

.btn-cancel:active {
    transform: translateY(2px);
    border-bottom-width: 1px;
}
</style>

<div class="create-wrapper">

    <?php if ($flash): ?>
        <div class="alert alert-<?= htmlspecialchars($flash['type']) ?> alert-dismissible fade show mb-4 shadow-sm" role="alert" style="border-radius: 14px; font-weight: 700;">
            <i class="fas <?= $flash['type'] === 'danger' ? 'fa-exclamation-circle' : 'fa-check-circle' ?> mr-2"></i>
            <?= htmlspecialchars($flash['message']) ?>
            <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
        </div>
    <?php endif; ?>

    <div class="create-header">
        <div class="create-badge">
            <i class="fas fa-magic"></i> Setup Game
        </div>
        <h2 class="create-title">New Quiz</h2>
        <p class="create-subtitle">Choose a game mode and configure initial settings for your quiz</p>
    </div>

    <div class="create-card">
        <form action="" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="game_format" id="inputGameFormat" value="classic">

            <!-- GAME MODE SELECTOR -->
            <label class="field-label">
                <i class="fas fa-gamepad text-primary"></i> Game Mode
            </label>
            <div class="format-grid">
                <!-- CLASSIC -->
                <div class="format-card card-classic is-active" id="card-classic" onclick="setFormat('classic')">
                    <div class="card-top-row">
                        <div class="card-icon-bubble">
                            <i class="fas fa-bolt"></i>
                        </div>
                        <span class="card-status-pill">
                            <i class="fas fa-check"></i> Selected
                        </span>
                    </div>
                    <div class="format-title">Classic</div>
                    <div class="format-desc">Standard live quiz with timed multiple-choice questions</div>
                </div>

                <!-- WAGER -->
                <div class="format-card card-wager" id="card-wager" onclick="setFormat('wager')">
                    <div class="card-top-row">
                        <div class="card-icon-bubble">
                            <i class="fas fa-coins"></i>
                        </div>
                        <span class="card-status-pill">
                            <i class="fas fa-check"></i> Selected
                        </span>
                    </div>
                    <div class="format-title">Star of hope</div>
                    <div class="format-desc">Players bet a portion of their points on each answer</div>
                </div>

                <!-- PUZZLE -->
                <div class="format-card card-puzzle" id="card-puzzle" onclick="setFormat('puzzle')">
                    <div class="card-top-row">
                        <div class="card-icon-bubble">
                            <i class="fas fa-puzzle-piece"></i>
                        </div>
                        <span class="card-status-pill">
                            <i class="fas fa-check"></i> Selected
                        </span>
                    </div>
                    <div class="format-title">Puzzle</div>
                    <div class="format-desc">Answers reveal hidden tiles of a secret picture</div>
                </div>
            </div>

            <!-- PUZZLE SETUP (Only visible when Puzzle is selected) -->
            <div id="puzzleSection" class="puzzle-box" style="display: none;">
                <div class="puzzle-box-title">
                    <i class="fas fa-puzzle-piece text-success"></i> Puzzle Settings
                </div>

                <div class="form-group mb-3">
                    <label class="field-label" style="font-size: 0.85rem; color: #065f46;">
                        <i class="fas fa-image mr-1 text-success"></i> Secret Picture <span class="text-danger">*</span>
                    </label>
                    <div class="upload-tactile-box">
                        <div class="d-flex align-items-center" style="gap: 12px;">
                            <input type="file" name="obstacle_image" id="obstacleImageInput" class="custom-file-input-hidden" accept="image/*" onchange="previewSecretImage(this)">
                            <label for="obstacleImageInput" class="btn-upload-file">
                                <i class="fas fa-cloud-upload-alt"></i> Choose Image File
                            </label>
                            <span id="uploadFileName" class="upload-file-name text-muted">No file chosen</span>
                        </div>
                    </div>
                    <div id="imgPreviewWrapper" class="mt-3" style="display: none;">
                        <img id="imgPreview" src="" alt="Secret picture preview" style="max-height: 160px; max-width: 100%; border-radius: 12px; border: 2.5px solid #86efac; object-fit: contain; box-shadow: 0 4px 12px rgba(0,0,0,0.08);">
                    </div>
                </div>

                <div class="form-group mb-3">
                    <label class="field-label" style="font-size: 0.85rem; color: #065f46;">
                        <i class="fas fa-key mr-1 text-success"></i> Answer Keyword <span class="text-danger">*</span>
                    </label>
                    <input type="text" name="obstacle_answer" id="obstacleAnswerInput" class="custom-input" placeholder="e.g. Eiffel Tower, Mona Lisa" style="background:#ffffff; border-color:#a7f3d0;">
                </div>

                <div class="form-group mb-0">
                    <label class="field-label" style="font-size: 0.85rem; color: #065f46;">
                        <i class="fas fa-th mr-1 text-success"></i> Number of Tiles
                    </label>
                    <div class="tile-pills">
                        <label class="tile-pill-label">
                            <input type="radio" name="total_pieces" value="4" checked>
                            <span class="tile-pill-text"><i class="fas fa-th-large mr-1"></i> 4 tiles (2×2)</span>
                        </label>
                        <label class="tile-pill-label">
                            <input type="radio" name="total_pieces" value="6">
                            <span class="tile-pill-text"><i class="fas fa-th mr-1"></i> 6 tiles (3×2)</span>
                        </label>
                        <label class="tile-pill-label">
                            <input type="radio" name="total_pieces" value="8">
                            <span class="tile-pill-text"><i class="fas fa-th mr-1"></i> 8 tiles (4×2)</span>
                        </label>
                        <label class="tile-pill-label">
                            <input type="radio" name="total_pieces" value="9">
                            <span class="tile-pill-text"><i class="fas fa-border-all mr-1"></i> 9 tiles (3×3)</span>
                        </label>
                        <label class="tile-pill-label">
                            <input type="radio" name="total_pieces" value="10">
                            <span class="tile-pill-text"><i class="fas fa-th mr-1"></i> 10 tiles (5×2)</span>
                        </label>
                        <label class="tile-pill-label">
                            <input type="radio" name="total_pieces" value="12">
                            <span class="tile-pill-text"><i class="fas fa-th mr-1"></i> 12 tiles (4×3)</span>
                        </label>
                        <label class="tile-pill-label">
                            <input type="radio" name="total_pieces" value="15">
                            <span class="tile-pill-text"><i class="fas fa-th mr-1"></i> 15 tiles (5×3)</span>
                        </label>
                        <label class="tile-pill-label">
                            <input type="radio" name="total_pieces" value="16">
                            <span class="tile-pill-text"><i class="fas fa-border-all mr-1"></i> 16 tiles (4×4)</span>
                        </label>
                        <label class="tile-pill-label">
                            <input type="radio" name="total_pieces" value="20">
                            <span class="tile-pill-text"><i class="fas fa-th mr-1"></i> 20 tiles (5×4)</span>
                        </label>
                        <label class="tile-pill-label">
                            <input type="radio" name="total_pieces" value="25">
                            <span class="tile-pill-text"><i class="fas fa-border-all mr-1"></i> 25 tiles (5×5)</span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- TITLE & DESCRIPTION -->
            <div class="form-group mb-3">
                <label class="field-label">
                    <i class="fas fa-heading mr-1 text-primary"></i> Title <span class="text-danger">*</span>
                </label>
                <input type="text" name="name" class="custom-input" placeholder="e.g. Science Trivia, World Geography" required autofocus>
            </div>

            <div class="form-group mb-4">
                <label class="field-label">
                    <i class="fas fa-align-left mr-1 text-secondary"></i> Description <span class="text-muted font-weight-normal" style="font-size: 0.82rem;">(optional)</span>
                </label>
                <textarea name="description" class="custom-textarea" rows="3" placeholder="Brief summary of what this quiz covers..."></textarea>
            </div>

            <div class="d-flex justify-content-between align-items-center pt-3 border-top" style="border-color: #f1f5f9 !important;">
                <a href="<?= BASE_URL ?>modules/quiz/list.php" class="btn-cancel">
                    <i class="fas fa-arrow-left mr-1"></i> Cancel
                </a>
                <button type="submit" class="btn-submit">
                    <span>Continue to Questions</span>
                    <i class="fas fa-arrow-right"></i>
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
    const nameEl  = document.getElementById('uploadFileName');

    if (input.files && input.files[0]) {
        if (nameEl) nameEl.textContent = input.files[0].name;
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.src = e.target.result;
            wrapper.style.display = 'block';
        };
        reader.readAsDataURL(input.files[0]);
    } else {
        if (nameEl) nameEl.textContent = 'No file chosen';
        wrapper.style.display = 'none';
    }
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
