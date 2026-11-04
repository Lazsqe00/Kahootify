<?php

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/ReviewManager.php';

$creatorId = intval($_SESSION['id_creator']);

$quizzes = db_fetch_all(
    "SELECT q.*, 
            (SELECT COUNT(*) FROM questions WHERE id_quiz = q.id_quiz) as question_count
     FROM quiz q
     WHERE q.id_creator = :c
     ORDER BY q.id_quiz DESC",
    [':c' => $creatorId]
);

$pageTitle = "My Kahoots | Quiz Management";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';

$flash = get_flash_message();
?>

<style>
.quiz-card {
    border-radius: 14px;
    transition: transform 0.25s ease, box-shadow 0.25s ease;
    overflow: visible !important;
    background: #fff;
    position: relative;
}
.quiz-card .card-body {
    overflow: visible !important;
}
.quiz-card .dropdown-menu {
    z-index: 1060 !important;
    border-radius: 10px;
    box-shadow: 0 12px 28px rgba(0,0,0,0.2) !important;
}
.col-quiz-item {
    position: relative;
}
.col-quiz-item:focus-within,
.col-quiz-item:has(.dropdown.show) {
    z-index: 1050 !important;
}
.quiz-card:focus-within,
.quiz-card:has(.dropdown.show) {
    z-index: 1050 !important;
}
.quiz-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 14px 28px rgba(0, 0, 0, 0.12) !important;
}

.quiz-cover-box {
    height: 165px;
    position: relative;
    overflow: hidden;
    border-top-left-radius: 14px;
    border-top-right-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #1e1136;
}

/* OBSTACLE PUZZLE CARD HEADER */
.obstacle-cover-bg {
    position: absolute;
    top: 0; left: 0; right: 0; bottom: 0;
    background-size: cover;
    background-position: center;
    transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}
.quiz-card:hover .obstacle-cover-bg {
    transform: scale(1.07);
}

.obstacle-cover-overlay {
    position: absolute;
    top: 0; left: 0; right: 0; bottom: 0;
    background: linear-gradient(180deg, rgba(16, 9, 32, 0.45) 0%, rgba(16, 9, 32, 0.76) 55%, rgba(14, 7, 28, 0.94) 100%);
    z-index: 2;
}

/* PUZZLE GRID OVERLAY LINES */
.obstacle-card-grid {
    position: absolute;
    top: 0; left: 0; right: 0; bottom: 0;
    display: grid;
    pointer-events: none;
    z-index: 3;
}
.grid-2x2 { grid-template-columns: repeat(2, 1fr); grid-template-rows: repeat(2, 1fr); }
.grid-3x2 { grid-template-columns: repeat(3, 1fr); grid-template-rows: repeat(2, 1fr); }
.grid-4x2 { grid-template-columns: repeat(4, 1fr); grid-template-rows: repeat(2, 1fr); }
.grid-3x3 { grid-template-columns: repeat(3, 1fr); grid-template-rows: repeat(3, 1fr); }
.grid-5x2 { grid-template-columns: repeat(5, 1fr); grid-template-rows: repeat(2, 1fr); }
.grid-4x3 { grid-template-columns: repeat(4, 1fr); grid-template-rows: repeat(3, 1fr); }
.grid-5x3 { grid-template-columns: repeat(5, 1fr); grid-template-rows: repeat(3, 1fr); }
.grid-4x4 { grid-template-columns: repeat(4, 1fr); grid-template-rows: repeat(4, 1fr); }
.grid-5x4 { grid-template-columns: repeat(5, 1fr); grid-template-rows: repeat(4, 1fr); }
.grid-5x5 { grid-template-columns: repeat(5, 1fr); grid-template-rows: repeat(5, 1fr); }

.obstacle-card-cell {
    border: 1px dashed rgba(255, 255, 255, 0.22);
    box-sizing: border-box;
    display: flex;
    align-items: flex-start;
    justify-content: flex-start;
    padding: 6px 10px;
}
.obstacle-card-cell .piece-num-pill {
    font-size: 0.68rem;
    font-weight: 800;
    color: rgba(255, 255, 255, 0.65);
    background: rgba(0, 0, 0, 0.35);
    padding: 1px 6px;
    border-radius: 4px;
    font-family: 'Montserrat', sans-serif;
    letter-spacing: 0.5px;
}

/* CONTENT ON TOP */
.quiz-cover-content {
    position: relative;
    z-index: 4;
    text-align: center;
    padding: 14px 18px;
    width: 100%;
}
.vcnv-card-tag {
    background: linear-gradient(135deg, #f59f00 0%, #e67700 100%);
    color: #1a0f30 !important;
    font-family: 'Montserrat', sans-serif;
    font-weight: 800;
    font-size: 0.72rem;
    letter-spacing: 0.6px;
    padding: 3px 10px;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(245, 159, 0, 0.4);
    display: inline-block;
    margin-bottom: 6px;
    text-transform: uppercase;
}
.quiz-card-title {
    font-family: 'Montserrat', sans-serif;
    font-size: 1.15rem;
    font-weight: 800;
    color: #fff;
    margin-bottom: 8px;
    text-shadow: 0 2px 8px rgba(0, 0, 0, 0.8);
    line-height: 1.25;
}

/* CLASSIC CARD HEADER */
.classic-cover-bg {
    position: absolute;
    top: 0; left: 0; right: 0; bottom: 0;
    background: linear-gradient(135deg, #46178f 0%, #250954 100%);
}
</style>

<div class="container my-5">
    <?php if ($flash): ?>
        <div class="alert alert-<?= htmlspecialchars($flash['type']) ?> alert-dismissible fade show mb-4" role="alert">
            <?= htmlspecialchars($flash['message']) ?>
            <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
        </div>
    <?php endif; ?>

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <div>
            <h2 class="font-weight-bold mb-1" style="color: var(--kahoot-purple);">
                <i class="fas fa-th-large mr-2"></i>My Kahoots
            </h2>
            <p class="text-muted mb-0">View, edit questions, or host a live game session.</p>
        </div>
        <div>
            <a href="<?= BASE_URL ?>modules/quiz/create.php" class="btn btn-kahoot btn-kahoot-green shadow-sm">
                <i class="fas fa-plus-circle mr-1"></i> Create New Kahoot
            </a>
        </div>
    </div>

    <div class="row">
        <?php if (empty($quizzes)): ?>
            <div class="col-12 text-center py-5">
                <div class="card border-0 shadow-sm p-5" style="border-radius: 12px;">
                    <i class="fas fa-folder-open fa-4x text-muted mb-3"></i>
                    <h4>You have no quizzes yet!</h4>
                    <p class="text-muted">Get started by creating your very first trivia quiz.</p>
                    <div>
                        <a href="<?= BASE_URL ?>modules/quiz/create.php" class="btn btn-kahoot btn-kahoot-purple">
                            <i class="fas fa-plus-circle mr-1"></i> Create Kahoot Now
                        </a>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($quizzes as $q): 
                $gameType = $q['game_type'] ?? 'classic';
                $quizMode = $q['mode'] ?? 'normal';
                $isObstacle = ($gameType === 'obstacle');
                $isWager = ($quizMode === 'wager');

                $totalPieces = intval($q['total_pieces'] ?? 4);
                if (!in_array($totalPieces, [4, 6, 8, 9, 10, 12, 15, 16, 20, 25])) $totalPieces = 4;

                // Grid layout class
                $gridClass = 'grid-2x2';
                if ($totalPieces === 6) $gridClass = 'grid-3x2';
                elseif ($totalPieces === 8) $gridClass = 'grid-4x2';
                elseif ($totalPieces === 9) $gridClass = 'grid-3x3';
                elseif ($totalPieces === 10) $gridClass = 'grid-5x2';
                elseif ($totalPieces === 12) $gridClass = 'grid-4x3';
                elseif ($totalPieces === 15) $gridClass = 'grid-5x3';
                elseif ($totalPieces === 16) $gridClass = 'grid-4x4';
                elseif ($totalPieces === 20) $gridClass = 'grid-5x4';
                elseif ($totalPieces === 25) $gridClass = 'grid-5x5';

                $hasObstacleImg = false;
                $obstacleImgUrl = '';
                if ($isObstacle && !empty($q['obstacle_image'])) {
                    $hasObstacleImg = true;
                    $obstacleImgUrl = BASE_URL . ltrim($q['obstacle_image'], '/');
                }
            ?>
                <div class="col-md-6 col-lg-4 mb-4 col-quiz-item">
                    <div class="card h-100 border-0 shadow-sm quiz-card">
                        <div class="quiz-cover-box">
                            <?php if ($isObstacle): ?>
                                <!-- OBSTACLE PUZZLE BACKGROUND -->
                                <?php if ($hasObstacleImg): ?>
                                    <div class="obstacle-cover-bg" style="background-image: url('<?= htmlspecialchars($obstacleImgUrl) ?>');"></div>
                                <?php else: ?>
                                    <div class="obstacle-cover-bg" style="background: radial-gradient(circle at 85% 15%, rgba(245, 159, 0, 0.22) 0%, transparent 45%), radial-gradient(circle at 15% 85%, rgba(121, 75, 196, 0.35) 0%, transparent 50%), linear-gradient(135deg, #130a28 0%, #251147 50%, #120726 100%);">
                                        <i class="fas fa-puzzle-piece" style="position: absolute; right: -15px; bottom: -20px; font-size: 6.5rem; color: rgba(255, 255, 255, 0.05); transform: rotate(-15deg);"></i>
                                    </div>
                                <?php endif; ?>

                                <!-- DARK GRADIENT OVERLAY -->
                                <div class="obstacle-cover-overlay"></div>

                                <!-- PUZZLE PIECES GRID -->
                                <div class="obstacle-card-grid <?= $gridClass ?>">
                                    <?php for ($p = 1; $p <= $totalPieces; $p++): ?>
                                        <div class="obstacle-card-cell">
                                            <span class="piece-num-pill">🧩 <?= $p ?></span>
                                        </div>
                                    <?php endfor; ?>
                                </div>

                                <!-- CARD CONTENT -->
                                <div class="quiz-cover-content">
                                    <div>
                                        <span class="vcnv-card-tag">
                                            <i class="fas fa-puzzle-piece mr-1"></i> Obstacle Puzzle
                                        </span>
                                    </div>
                                    <?php if (!empty($q['is_review_mode'])): ?>
                                        <div class="mb-1"><span class="badge badge-warning text-dark font-weight-bold px-2 py-1 shadow-sm"><i class="fas fa-book-reader mr-1"></i> Review Mode</span></div>
                                    <?php endif; ?>
                                    <h5 class="quiz-card-title text-truncate" title="<?= htmlspecialchars($q['name']) ?>">
                                        <?= htmlspecialchars($q['name']) ?>
                                    </h5>
                                    <div class="d-flex justify-content-center flex-wrap" style="gap: 6px;">
                                        <span class="badge badge-warning text-dark font-weight-bold px-2 py-1 shadow-sm" style="font-size: 0.8rem;">
                                            <i class="fas fa-question-circle mr-1"></i> <?= $q['question_count'] ?> <?= $q['question_count'] == 1 ? 'question' : 'questions' ?>
                                        </span>
                                        <span class="badge badge-light text-dark font-weight-bold px-2 py-1 shadow-sm" style="font-size: 0.8rem;">
                                            <i class="fas fa-th mr-1 text-primary"></i> <?= $totalPieces ?> <?= $totalPieces == 1 ? 'piece' : 'pieces' ?>
                                        </span>
                                    </div>
                                </div>

                            <?php elseif ($isWager): ?>
                                <!-- WAGER MODE -->
                                <div class="classic-cover-bg" style="background-color: #7c2d12;"></div>
                                <img src="<?= BASE_URL ?>assets/img/mode-wager.jpg" alt="Wager Mode" style="max-height: 100%; object-fit: cover; opacity: 0.85; width: 100%; position: absolute; top:0; left:0;">
                                <div class="classic-cover-overlay" style="background: linear-gradient(180deg, rgba(124, 45, 18, 0.25) 0%, rgba(124, 45, 18, 0.75) 100%);"></div>
                                <div class="quiz-cover-content">
                                    <div>
                                        <span class="badge badge-warning text-dark font-weight-bold px-2 py-1 shadow-sm mb-1" style="font-size: 0.72rem; letter-spacing: 0.5px; background-color: #ffedd5; border: 1px solid #fdba74;">
                                            <i class="fas fa-coins mr-1 text-warning"></i> WAGER MODE
                                        </span>
                                    </div>
                                    <?php if (!empty($q['is_review_mode'])): ?>
                                        <div class="mb-1"><span class="badge badge-warning text-dark font-weight-bold px-2 py-1 shadow-sm"><i class="fas fa-book-reader mr-1"></i> Review Mode</span></div>
                                    <?php endif; ?>
                                    <h5 class="quiz-card-title text-truncate" title="<?= htmlspecialchars($q['name']) ?>">
                                        <?= htmlspecialchars($q['name']) ?>
                                    </h5>
                                    <div class="d-flex justify-content-center flex-wrap">
                                        <span class="badge badge-warning text-dark font-weight-bold px-2 py-1 shadow-sm" style="font-size: 0.8rem;">
                                            <i class="fas fa-question-circle mr-1"></i> <?= $q['question_count'] ?> questions
                                        </span>
                                    </div>
                                </div>

                            <?php else: ?>
                                <!-- CLASSIC QUIZ HEADER -->
                                <div class="classic-cover-bg"></div>
                                <img src="<?= BASE_URL ?>assets/img/cover.png" alt="Cover" style="max-height: 100%; object-fit: cover; opacity: 0.3; width: 100%; position: absolute; top:0; left:0;">
                                <div class="quiz-cover-content">
                                    <div>
                                        <span class="badge badge-light text-dark font-weight-bold px-2 py-1 shadow-sm mb-1" style="font-size: 0.72rem; letter-spacing: 0.5px;">
                                            <i class="fas fa-gamepad mr-1 text-primary"></i> CLASSIC QUIZ
                                        </span>
                                    </div>
                                    <?php if (!empty($q['is_review_mode'])): ?>
                                        <div class="mb-1"><span class="badge badge-warning text-dark font-weight-bold px-2 py-1 shadow-sm"><i class="fas fa-book-reader mr-1"></i> Review Mode</span></div>
                                    <?php endif; ?>
                                    <h5 class="quiz-card-title text-truncate" title="<?= htmlspecialchars($q['name']) ?>">
                                        <?= htmlspecialchars($q['name']) ?>
                                    </h5>
                                    <div class="d-flex justify-content-center flex-wrap">
                                        <span class="badge badge-warning text-dark font-weight-bold px-2 py-1 shadow-sm" style="font-size: 0.8rem;">
                                            <i class="fas fa-question-circle mr-1"></i> <?= $q['question_count'] ?> questions
                                        </span>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="card-body d-flex flex-column justify-content-between p-3">
                            <p class="text-muted small mb-2">
                                <?= !empty($q['description']) ? htmlspecialchars(mb_strimwidth($q['description'], 0, 100, '...')) : '<i>No description</i>' ?>
                            </p>
                            <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                                <small class="text-muted"><?= date('M d, Y', strtotime($q['quizadd'] ?? $q['created_at'] ?? 'now')) ?></small>
                                <div class="d-flex align-items-center">
                                    <?php if (!empty($q['is_review_mode'])): ?>
                                        <button type="button" class="btn btn-sm btn-warning font-weight-bold mr-2 text-dark" data-toggle="modal" data-target="#reviewModal_<?= $q['id_quiz'] ?>" title="View Review Mode">
                                            <i class="fas fa-eye mr-1"></i> View
                                        </button>
                                    <?php else: ?>
                                        <a href="<?= BASE_URL ?>modules/host/create_room.php?id_quiz=<?= $q['id_quiz'] ?>" class="btn btn-sm btn-primary font-weight-bold mr-2" title="Play">
                                            <i class="fas fa-play mr-1"></i> Play
                                        </a>
                                    <?php endif; ?>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary font-weight-bold dropdown-toggle" type="button" id="quizActions_<?= $q['id_quiz'] ?>" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" title="Actions">
                                            <i class="fas fa-ellipsis-v mr-1"></i> Actions
                                        </button>
                                        <div class="dropdown-menu dropdown-menu-right shadow-sm" aria-labelledby="quizActions_<?= $q['id_quiz'] ?>">
                                            <a class="dropdown-item" href="<?= BASE_URL ?>modules/quiz/edit.php?id=<?= $q['id_quiz'] ?>">
                                                <i class="fas fa-edit text-success mr-2"></i> Edit
                                            </a>
                                            <a class="dropdown-item" href="<?= BASE_URL ?>modules/quiz/duplicate.php?id=<?= $q['id_quiz'] ?>">
                                                <i class="fas fa-copy text-info mr-2"></i> Duplicate
                                            </a>
                                            <div class="dropdown-divider"></div>
                                            <a class="dropdown-item text-danger" href="<?= BASE_URL ?>modules/quiz/delete.php?id=<?= $q['id_quiz'] ?>" onclick="return confirm('Are you sure you want to delete this quiz?');">
                                                <i class="fas fa-trash-alt mr-2"></i> Delete
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
window.addEventListener('load', function() {
    if (window.jQuery) {
        $('.dropdown').on('show.bs.dropdown', function () {
            $(this).closest('.col-quiz-item').css('z-index', 1050);
            $(this).closest('.quiz-card').css('z-index', 1050);
        });
        $('.dropdown').on('hidden.bs.dropdown', function () {
            $(this).closest('.col-quiz-item').css('z-index', '');
            $(this).closest('.quiz-card').css('z-index', '');
        });
    }
});
</script>


<!-- REVIEW MODE VIEW MODALS -->
<?php foreach ($quizzes as $q): ?>
    <?php if (!empty($q['is_review_mode'])): 
        $rPin = $q['review_pin'] ?? '';
        $rUrl = ReviewManager::buildReviewUrl($rPin);
    ?>
    <div class="modal fade" id="reviewModal_<?= $q['id_quiz'] ?>" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 20px; overflow: hidden;">
                <div class="modal-header text-white px-4 py-3" style="background: linear-gradient(135deg, #1e1b4b 0%, #312e81 100%);">
                    <div class="d-flex align-items-center">
                        <span class="badge badge-warning text-dark font-weight-bold mr-2 px-2 py-1" style="border-radius: 6px;">
                            <i class="fas fa-book-reader mr-1"></i> REVIEW MODE
                        </span>
                        <h5 class="modal-title font-weight-bold mb-0 text-truncate text-white" style="max-width: 480px;">
                            <?= htmlspecialchars($q['name']) ?>
                        </h5>
                    </div>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close" style="opacity: 0.85;">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body p-4 bg-light">
                    <div class="row align-items-center bg-white rounded-xl shadow-sm p-3 mx-0" style="border-radius: 16px; border: 1px solid #e2e8f0;">
                        <!-- LEFT COLUMN: Large QR Code -->
                        <div class="col-md-5 d-flex flex-column align-items-center justify-content-center p-3 border-right border-light">
                            <div class="p-3 bg-white rounded-lg shadow-sm border text-center" style="border-radius: 16px;">
                                <div id="reviewQrCode_<?= $q['id_quiz'] ?>" class="d-flex align-items-center justify-content-center" data-url="<?= htmlspecialchars($rUrl) ?>" style="width: 200px; height: 200px;">
                                    <div class="spinner-border text-primary" role="status"></div>
                                </div>
                            </div>
                            <span class="small font-weight-bold text-muted mt-2">
                                <i class="fas fa-qrcode mr-1"></i> Quét mã để vào ôn tập ngay
                            </span>
                        </div>

                        <!-- RIGHT COLUMN: PIN & Direct Link -->
                        <div class="col-md-7 p-3 pl-md-4">
                            <div class="mb-4">
                                <label class="text-uppercase small font-weight-bold text-muted mb-1" style="letter-spacing: 0.5px;">
                                    <i class="fas fa-key text-warning mr-1"></i> Mã PIN Ôn tập
                                </label>
                                <div class="d-flex align-items-center">
                                    <div class="font-weight-bold text-dark px-3 py-2 rounded shadow-sm mr-2" style="font-size: 2.1rem; letter-spacing: 4px; font-family: monospace; background: #f8fafc; border: 2px dashed #cbd5e1;">
                                        <?= htmlspecialchars($rPin) ?>
                                    </div>
                                    <button type="button" class="btn btn-outline-secondary font-weight-bold px-3 py-2 rounded-lg" onclick="copyTextToClipboard('<?= htmlspecialchars($rPin) ?>', this)" title="Sao chép PIN">
                                        <i class="fas fa-copy mr-1"></i> Copy
                                    </button>
                                </div>
                                <small class="text-muted d-block mt-1">
                                    Học sinh có thể nhập mã này tại trang chủ <code>join.php</code>
                                </small>
                            </div>

                            <div class="mb-3">
                                <label class="text-uppercase small font-weight-bold text-muted mb-1" style="letter-spacing: 0.5px;">
                                    <i class="fas fa-link text-primary mr-1"></i> Link ôn tập trực tiếp
                                </label>
                                <div class="input-group">
                                    <input type="text" class="form-control font-weight-bold text-dark bg-white" readonly value="<?= htmlspecialchars($rUrl) ?>">
                                    <div class="input-group-append">
                                        <button class="btn btn-primary font-weight-bold px-3" type="button" onclick="copyTextToClipboard('<?= htmlspecialchars($rUrl) ?>', this)">
                                            <i class="fas fa-copy mr-1"></i> Copy Link
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="pt-2 d-flex align-items-center">
                                <a href="<?= htmlspecialchars($rUrl) ?>" target="_blank" class="btn btn-success font-weight-bold rounded-pill px-4 shadow-sm mr-2">
                                    <i class="fas fa-external-link-alt mr-1"></i> Mở bài làm
                                </a>
                                <button type="button" class="btn btn-secondary font-weight-bold rounded-pill px-3" data-dismiss="modal">
                                    Đóng
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
<?php endforeach; ?>

<script src="<?= BASE_URL ?>assets/js/qrcodegen.js"></script>
<script>
function renderQrSvg(containerId, url) {
    const el = document.getElementById(containerId);
    if (!el || !window.qrcodegen) return;
    try {
        const qr = qrcodegen.QrCode.encodeText(url, qrcodegen.QrCode.Ecc.MEDIUM);
        const border = 4;
        const size = qr.size + border * 2;
        const namespace = "http://www.w3.org/2000/svg";
        const svg = document.createElementNS(namespace, "svg");
        svg.setAttribute("viewBox", "0 0 " + size + " " + size);
        svg.setAttribute("shape-rendering", "crispEdges");
        svg.setAttribute("role", "img");

        const background = document.createElementNS(namespace, "rect");
        background.setAttribute("width", "100%");
        background.setAttribute("height", "100%");
        background.setAttribute("fill", "#fff");
        svg.appendChild(background);

        const modules = [];
        for (let y = 0; y < qr.size; y++) {
            for (let x = 0; x < qr.size; x++) {
                if (qr.getModule(x, y)) {
                    modules.push("M" + (x + border) + "," + (y + border) + "h1v1h-1z");
                }
            }
        }
        const path = document.createElementNS(namespace, "path");
        path.setAttribute("d", modules.join(""));
        path.setAttribute("fill", "#000");
        svg.appendChild(path);

        el.replaceChildren(svg);
    } catch (e) {
        console.error("QR Error", e);
    }
}

function copyTextToClipboard(text, btn) {
    navigator.clipboard.writeText(text).then(() => {
        const origHtml = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-check text-success mr-1"></i> Copied!';
        setTimeout(() => { btn.innerHTML = origHtml; }, 2000);
    }).catch(() => {
        alert("Copied: " + text);
    });
}

window.addEventListener("DOMContentLoaded", function () {
    $(".modal").on("shown.bs.modal", function () {
        const qrBox = this.querySelector("[id^='reviewQrCode_']");
        if (qrBox && qrBox.dataset.url) {
            renderQrSvg(qrBox.id, qrBox.dataset.url);
        }
    });
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
