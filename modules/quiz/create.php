<?php

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/database.php';

$creatorId = intval($_SESSION['id_creator']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $gameType = ($_POST['game_type'] ?? 'classic') === 'obstacle' ? 'obstacle' : 'classic';

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
            set_flash_message('danger', 'Vui lòng nhập từ khóa đáp án của bức tranh bí mật!');
            redirect('modules/quiz/create.php');
        }

        // Handle secret obstacle image upload
        if (isset($_FILES['obstacle_image']) && $_FILES['obstacle_image']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['obstacle_image'];
            $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (!in_array($file['type'], $allowedMimes)) {
                set_flash_message('danger', 'Ảnh chướng ngại vật phải là định dạng JPG, PNG, WEBP hoặc GIF!');
                redirect('modules/quiz/create.php');
            }

            $uploadDir = __DIR__ . '/../../assets/img/obstacles/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $filename = 'obs_' . uniqid() . '_' . time() . '.' . $ext;
            $targetPath = $uploadDir . $filename;

            if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                $obstacleImage = 'assets/img/obstacles/' . $filename;
            } else {
                set_flash_message('danger', 'Không thể tải lên ảnh bức tranh bí mật!');
                redirect('modules/quiz/create.php');
            }
        } else {
            set_flash_message('danger', 'Vui lòng tải lên bức tranh bí mật cho chế độ Vượt chướng ngại vật!');
            redirect('modules/quiz/create.php');
        }
    }

    try {
        if ($gameType === 'obstacle') {
            $newQuizId = db_insert(
                "INSERT INTO quiz (id_creator, name, description, game_type, obstacle_image, obstacle_answer, total_pieces) 
                 VALUES (:c, :n, :d, :gt, :img, :ans, :tp)",
                [
                    ':c'   => $creatorId,
                    ':n'   => $name,
                    ':d'   => $description,
                    ':gt'  => $gameType,
                    ':img' => $obstacleImage,
                    ':ans' => $obstacleAnswer,
                    ':tp'  => $totalPieces
                ]
            );
        } else {
            $newQuizId = db_insert(
                "INSERT INTO quiz (id_creator, name, description, game_type) 
                 VALUES (:c, :n, :d, 'classic')",
                [
                    ':c' => $creatorId,
                    ':n' => $name,
                    ':d' => $description
                ]
            );
        }

        set_flash_message('success', 'Tạo trò chơi thành công! Bây giờ hãy thêm câu hỏi bên dưới.');
        redirect('modules/quiz/edit.php?id=' . $newQuizId);

    } catch (Exception $e) {
        set_flash_message('danger', 'Lỗi khi tạo quiz: ' . $e->getMessage());
        redirect('modules/quiz/create.php');
    }
}

$pageTitle = "Create New Quiz | Kahoot!";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
$flash = get_flash_message();
?>

<style>
.game-type-card {
    border: 1.5px solid #e2e8f0;
    border-radius: 12px;
    padding: 16px 18px;
    cursor: pointer;
    transition: all 0.2s ease;
    background: #fff;
    position: relative;
    height: 100%;
}
.game-type-card:hover {
    border-color: #cbd5e1;
    background: #f8fafc;
}
.game-type-card.selected {
    border-color: var(--kahoot-purple);
    background: #fdfaff;
    box-shadow: 0 0 0 1.5px var(--kahoot-purple);
}
.game-type-card .card-check-icon {
    position: absolute;
    top: 14px;
    right: 14px;
    font-size: 1.15rem;
    color: var(--kahoot-purple);
    display: none;
}
.game-type-card.selected .card-check-icon {
    display: block;
}
.game-type-icon {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.3rem;
    flex-shrink: 0;
}
.icon-classic {
    background: #eff6ff;
    color: #2563eb;
}
.icon-obstacle {
    background: #fff7ed;
    color: #ea580c;
}
.obstacle-settings-box {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 20px;
    animation: fadeIn 0.25s ease;
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-6px); }
    to { opacity: 1; transform: translateY(0); }
}
.piece-options-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 10px;
}
@media (max-width: 576px) {
    .piece-options-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}
.piece-option-btn {
    margin: 0;
    cursor: pointer;
}
.piece-option-btn input {
    display: none;
}
.piece-option-label {
    display: block;
    text-align: center;
    padding: 10px 8px;
    border: 1.5px solid #cbd5e1;
    border-radius: 8px;
    background: #fff;
    font-weight: 600;
    font-size: 0.95rem;
    color: #334155;
    transition: all 0.2s ease;
    user-select: none;
}
.piece-option-label small {
    color: #64748b;
    display: block;
    font-weight: 500;
}
.piece-option-btn input:checked + .piece-option-label {
    border-color: var(--kahoot-purple);
    background: #f5f0ff;
    color: var(--kahoot-purple);
    box-shadow: 0 0 0 1px var(--kahoot-purple);
}
.preview-box {
    max-width: 220px;
    max-height: 140px;
    border-radius: 8px;
    border: 1px solid #ddd;
    object-fit: cover;
    display: none;
    margin-top: 10px;
}
</style>

<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-md-9 col-lg-8">
            <?php if ($flash): ?>
                <div class="alert alert-<?= htmlspecialchars($flash['type']) ?> alert-dismissible fade show mb-4" role="alert">
                    <?= htmlspecialchars($flash['message']) ?>
                    <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                </div>
            <?php endif; ?>

            <div class="card border-0 shadow" style="border-radius: 14px; overflow: hidden;">
                <div class="card-header text-white py-3" style="background-color: var(--kahoot-purple);">
                    <h5 class="font-weight-bold mb-0">
                        <i class="fas fa-plus-circle mr-2"></i>Tạo Trò Chơi Mới
                    </h5>
                </div>
                <div class="card-body p-4">
                    <form action="" method="POST" enctype="multipart/form-data" id="createQuizForm">
                        <input type="hidden" name="game_type" id="inputGameType" value="classic">

                        <div class="form-group mb-4">
                            <label class="font-weight-bold text-dark" style="font-size: 1.05rem;">
                                Tên trò chơi <span class="text-danger">*</span>
                            </label>
                            <input type="text" name="name" class="form-control form-control-lg" placeholder="Ví dụ: Đố vui lịch sử, Khám phá kỳ quan..." required autofocus>
                        </div>

                        <!-- CHỌN LOẠI TRÒ CHƠI -->
                        <div class="form-group mb-4">
                            <label class="font-weight-bold text-dark d-block" style="font-size: 1.05rem;">
                                Chọn loại trò chơi <span class="text-danger">*</span>
                            </label>
                            <div class="row">
                                <!-- Card 1: Classic Quiz -->
                                <div class="col-sm-6 mb-3">
                                    <div class="game-type-card selected" id="cardClassic" onclick="selectGameType('classic')">
                                        <i class="fas fa-check-circle card-check-icon"></i>
                                        <div class="d-flex align-items-center">
                                            <div class="game-type-icon icon-classic mr-3">
                                                <i class="fas fa-bolt"></i>
                                            </div>
                                            <div>
                                                <div class="font-weight-bold text-dark" style="font-size: 1.05rem;">Trắc nghiệm cổ điển</div>
                                                <small class="text-muted">Tính điểm tốc độ & xếp hạng</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Card 2: Obstacle Puzzle -->
                                <div class="col-sm-6 mb-3">
                                    <div class="game-type-card" id="cardObstacle" onclick="selectGameType('obstacle')">
                                        <i class="fas fa-check-circle card-check-icon"></i>
                                        <div class="d-flex align-items-center">
                                            <div class="game-type-icon icon-obstacle mr-3">
                                                <i class="fas fa-puzzle-piece"></i>
                                            </div>
                                            <div>
                                                <div class="font-weight-bold text-dark" style="font-size: 1.05rem;">Vượt chướng ngại vật</div>
                                                <small class="text-muted">Mở mảnh ghép & đoán từ khóa</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- CÀI ĐẶT RIÊNG CHO VƯỢT CHƯỚNG NGẠI VẬT -->
                        <div id="obstacleSettings" class="obstacle-settings-box mb-4" style="display: none;">
                            <h6 class="font-weight-bold text-dark mb-3">
                                <i class="fas fa-sliders-h text-primary mr-1"></i> Thiết lập chướng ngại vật
                            </h6>

                            <div class="form-group mb-3">
                                <label class="font-weight-bold text-dark small mb-1">
                                    Bức tranh bí mật <span class="text-danger">*</span>
                                </label>
                                <div class="custom-file">
                                    <input type="file" name="obstacle_image" class="custom-file-input" id="obstacleImageInput" accept="image/*" onchange="previewObstacleImage(this)">
                                    <label class="custom-file-label" id="obstacleImageLabel" for="obstacleImageInput">Chọn file ảnh...</label>
                                </div>
                                <img id="obstacleImgPreview" class="preview-box" alt="Xem trước ảnh bí mật">
                            </div>

                            <div class="form-group mb-3">
                                <label class="font-weight-bold text-dark small mb-1">
                                    Từ khóa bức tranh <span class="text-danger">*</span>
                                </label>
                                <input type="text" name="obstacle_answer" id="obstacleAnswerInput" class="form-control" placeholder="Nhập từ khóa đáp án...">
                            </div>

                            <div class="form-group mb-0">
                                <label class="font-weight-bold text-dark small mb-2 d-block">
                                    Số lượng mảnh ghép <span class="text-danger">*</span>
                                </label>
                                <div class="piece-options-grid">
                                    <label class="piece-option-btn">
                                        <input type="radio" id="pieces4" name="total_pieces" value="4" checked>
                                        <span class="piece-option-label">4 mảnh <small>(2×2)</small></span>
                                    </label>
                                    <label class="piece-option-btn">
                                        <input type="radio" id="pieces6" name="total_pieces" value="6">
                                        <span class="piece-option-label">6 mảnh <small>(2×3)</small></span>
                                    </label>
                                    <label class="piece-option-btn">
                                        <input type="radio" id="pieces8" name="total_pieces" value="8">
                                        <span class="piece-option-label">8 mảnh <small>(2×4)</small></span>
                                    </label>
                                    <label class="piece-option-btn">
                                        <input type="radio" id="pieces9" name="total_pieces" value="9">
                                        <span class="piece-option-label">9 mảnh <small>(3×3)</small></span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="font-weight-bold">Mô tả ngắn</label>
                            <textarea name="description" class="form-control" rows="3" placeholder="Nhập mô tả hoặc lưu ý cho trò chơi này (tùy chọn)..."></textarea>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top">
                            <a href="<?= BASE_URL ?>modules/quiz/list.php" class="btn btn-secondary font-weight-bold">
                                <i class="fas fa-arrow-left mr-1"></i> Quay lại
                            </a>
                            <button type="submit" class="btn btn-kahoot btn-kahoot-green px-4">
                                <i class="fas fa-check mr-1"></i> Tiếp tục: Soạn câu hỏi &raquo;
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
        label.textContent = 'Chọn file ảnh (JPG, PNG, WEBP)...';
    }
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
