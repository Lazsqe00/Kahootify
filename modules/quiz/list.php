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
    border-radius: 12px;
    border: 1px solid #e5e7eb;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    overflow: visible !important;
    background: #fff;
    position: relative;
}
.quiz-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 24px rgba(0, 0, 0, 0.08) !important;
    border-color: #d1d5db;
}
.quiz-card .card-body {
    overflow: visible !important;
    position: relative;
}
.quiz-card .dropdown-menu {
    z-index: 1060 !important;
    border-radius: 10px;
    box-shadow: 0 12px 28px rgba(0,0,0,0.18) !important;
    border: 1px solid #e5e7eb;
    min-width: 190px;
}
.col-quiz-item {
    position: relative;
}
.col-quiz-item:focus-within,
.col-quiz-item.is-dropdown-open {
    z-index: 1050 !important;
}
.quiz-card:focus-within,
.quiz-card.is-dropdown-open {
    z-index: 1050 !important;
}

/* COVER BANNER */
.quiz-cover-box {
    height: 145px;
    position: relative;
    overflow: hidden;
    border-top-left-radius: 11px;
    border-top-right-radius: 11px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #250954;
}

.quiz-cover-bg {
    position: absolute;
    top: 0; left: 0; right: 0; bottom: 0;
    background-size: cover;
    background-position: center;
    transition: transform 0.35s ease;
}
.quiz-card:hover .quiz-cover-bg {
    transform: scale(1.05);
}

.quiz-cover-overlay {
    position: absolute;
    top: 0; left: 0; right: 0; bottom: 0;
    background: linear-gradient(180deg, rgba(20, 8, 45, 0.25) 0%, rgba(20, 8, 45, 0.78) 100%);
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
    border: 1px dashed rgba(255, 255, 255, 0.2);
    box-sizing: border-box;
    display: flex;
    align-items: flex-start;
    justify-content: flex-start;
    padding: 6px 8px;
}
.obstacle-card-cell .piece-num-pill {
    font-size: 0.65rem;
    font-weight: 700;
    color: rgba(255, 255, 255, 0.7);
    background: rgba(0, 0, 0, 0.4);
    padding: 1px 5px;
    border-radius: 4px;
}

/* CONTENT INSIDE BANNER: Clean centered Title */
.quiz-cover-content {
    position: relative;
    z-index: 4;
    text-align: center;
    padding: 16px 20px;
    width: 100%;
}
.quiz-card-title {
    font-family: 'Montserrat', sans-serif;
    font-size: 1.25rem;
    font-weight: 700;
    color: #ffffff;
    margin: 0;
    line-height: 1.3;
    text-shadow: 0 2px 8px rgba(0, 0, 0, 0.75);
}

/* SLEEK CORNER BADGE: Question Count positioned neatly at bottom-right corner */
.quiz-qcount-corner {
    position: absolute;
    bottom: 10px;
    right: 12px;
    z-index: 5;
    display: inline-flex;
    align-items: center;
    background: rgba(0, 0, 0, 0.65);
    color: #ffffff;
    font-family: 'Montserrat', sans-serif;
    font-size: 0.76rem;
    font-weight: 700;
    padding: 3px 10px;
    border-radius: 6px;
    border: 1px solid rgba(255, 255, 255, 0.18);
    backdrop-filter: blur(4px);
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.3);
    letter-spacing: 0.2px;
}

/* METADATA BADGES IN CARD BODY */
.quiz-badge {
    display: inline-flex;
    align-items: center;
    font-family: 'Montserrat', sans-serif;
    font-size: 0.72rem;
    font-weight: 700;
    padding: 3px 8px;
    border-radius: 6px;
    line-height: 1.25;
    letter-spacing: 0.2px;
}
.badge-classic {
    background: #f1f5f9;
    color: #475569;
    border: 1px solid #e2e8f0;
}
.badge-classic i {
    color: #46178F;
}
.badge-wager {
    background: #fff7ed;
    color: #c2410c;
    border: 1px solid #ffedd5;
}
.badge-wager i {
    color: #ea580c;
}
.badge-puzzle {
    background: #faf5ff;
    color: #7e22ce;
    border: 1px solid #f3e8ff;
}
.badge-puzzle i {
    color: #9333ea;
}
.badge-review {
    background: #fefce8;
    color: #854d0e;
    border: 1px solid #fef08a;
}
.badge-review i {
    color: #ca8a04;
}
.badge-team {
    background: #eff6ff;
    color: #1e40af;
    border: 1px solid #dbeafe;
}
.badge-team i {
    color: #2563eb;
}
.badge-qcount {
    background: #f8fafc;
    color: #334155;
    border: 1px solid #e2e8f0;
}
.badge-qcount i {
    color: #64748b;
}

.quiz-desc {
    font-size: 0.84rem;
    line-height: 1.5;
    color: #64748b;
    min-height: 38px;
    margin-bottom: 12px;
}

/* PURPLE CREATE BUTTON */
.btn-create-quiz {
    background: #46178F !important;
    border: 1px solid #381072 !important;
    color: #ffffff !important;
    font-family: 'Montserrat', sans-serif;
    font-weight: 700;
    padding: 9px 18px;
    border-radius: 8px;
    box-shadow: 0 2px 6px rgba(70, 23, 143, 0.25);
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}
.btn-create-quiz:hover {
    background: #391177 !important;
    color: #ffffff !important;
    transform: translateY(-1px);
    box-shadow: 0 4px 10px rgba(70, 23, 143, 0.35);
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
            <!-- Button changed to Kahoot Purple -->
            <a href="<?= BASE_URL ?>modules/quiz/create.php" class="btn btn-create-quiz">
                <i class="fas fa-plus-circle mr-2"></i>
                <span>Create New Kahoot</span>
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
                        <a href="<?= BASE_URL ?>modules/quiz/create.php" class="btn btn-create-quiz">
                            <i class="fas fa-plus-circle mr-2"></i> Create Kahoot Now
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
                $isClassic = (!$isObstacle && !$isWager);
                $isTeam = !empty($q['is_team_mode']);
                $isReviewEligible = ($isClassic && !$isTeam && !empty($q['is_review_mode']));

                $totalPieces = intval($q['total_pieces'] ?? 4);
                if (!in_array($totalPieces, [4, 6, 8, 9, 10, 12, 15, 16, 20, 25])) $totalPieces = 4;

                // Grid layout class for puzzle
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
                    <div class="card h-100 quiz-card shadow-sm">
                        
                        <!-- COVER BANNER: Clean Title in center, Question Count in bottom-right corner -->
                        <div class="quiz-cover-box">
                            <?php if ($isObstacle): ?>
                                <!-- Obstacle Background -->
                                <?php if ($hasObstacleImg): ?>
                                    <div class="quiz-cover-bg" style="background-image: url('<?= htmlspecialchars($obstacleImgUrl) ?>');"></div>
                                <?php else: ?>
                                    <div class="quiz-cover-bg" style="background: radial-gradient(circle at 80% 20%, rgba(245, 159, 0, 0.25) 0%, transparent 45%), linear-gradient(135deg, #180932 0%, #2f125a 100%);"></div>
                                <?php endif; ?>
                                <div class="quiz-cover-overlay"></div>
                                <div class="obstacle-card-grid <?= $gridClass ?>">
                                    <?php for ($p = 1; $p <= $totalPieces; $p++): ?>
                                        <div class="obstacle-card-cell">
                                            <span class="piece-num-pill">🧩 <?= $p ?></span>
                                        </div>
                                    <?php endfor; ?>
                                </div>
<?php elseif ($isWager): ?>
                                <!-- Wager Background -->
                                <div class="quiz-cover-bg" style="background-image: url('<?= BASE_URL ?>assets/img/mode-wager.jpg');"></div>
                                <div class="quiz-cover-overlay" style="background: linear-gradient(180deg, rgba(80, 20, 5, 0.35) 0%, rgba(50, 12, 3, 0.85) 100%);"></div>
                            <?php else: ?>
                                <!-- Classic Background: lighter purple & clearer Kahoot watermark -->
                                <div class="quiz-cover-bg" style="background: linear-gradient(135deg, #5b24b0 0%, #3a137b 100%);"></div>
                                <img src="<?= BASE_URL ?>assets/img/cover.png" alt="Cover" style="max-height: 100%; object-fit: cover; opacity: 0.45; width: 100%; position: absolute; top:0; left:0;">
                                <div class="quiz-cover-overlay" style="background: linear-gradient(180deg, rgba(20, 8, 45, 0.12) 0%, rgba(20, 8, 45, 0.52) 100%);"></div>
                            <?php endif; ?>

                            <!-- Clean Title in Center -->
                            <div class="quiz-cover-content">
                                <h5 class="quiz-card-title text-truncate" title="<?= htmlspecialchars($q['name']) ?>">
                                    <?= htmlspecialchars($q['name']) ?>
                                </h5>
                            </div>

                            <!-- Sleek Corner Badge for Question Count -->
                            <div class="quiz-qcount-corner" title="Number of questions">
                                <i class="fas fa-question-circle mr-1"></i>
                                <?= $q['question_count'] ?> <?= $q['question_count'] == 1 ? 'question' : 'questions' ?>
                            </div>
                        </div>

                        <!-- CARD BODY: Metadata tags, description, footer -->
                        <div class="card-body d-flex flex-column justify-content-between p-3">
                            
                            <!-- Badges row: Game Mode & Active statuses placed neatly here -->
                            <div class="d-flex align-items-center flex-wrap mb-2" style="gap: 6px;">
                                <?php if ($isObstacle): ?>
                                    <span class="quiz-badge badge-puzzle" title="Obstacle Puzzle Mode">
                                        <i class="fas fa-puzzle-piece mr-1"></i> Puzzle
                                    </span>
                                <?php elseif ($isWager): ?>
                                    <span class="quiz-badge badge-wager" title="Wager Mode">
                                        <i class="fas fa-coins mr-1"></i> Wager
                                    </span>
                                <?php else: ?>
                                    <span class="quiz-badge badge-classic" title="Classic Quiz Mode">
                                        <i class="fas fa-gamepad mr-1"></i> Classic
                                    </span>
                                <?php endif; ?>

                                <?php if ($isReviewEligible): ?>
                                    <span class="quiz-badge badge-review" title="Self-paced review mode is active">
                                        <i class="fas fa-book-reader mr-1"></i> Review Active
                                    </span>
                                <?php endif; ?>

                                <?php if (!empty($q['is_team_mode'])): ?>
                                    <span class="quiz-badge badge-team" title="Team mode is configured for this quiz">
                                        <i class="fas fa-users mr-1"></i> Team
                                    </span>
                                <?php endif; ?>
                            </div>

                            <!-- Description -->
                            <p class="quiz-desc">
                                <?= !empty($q['description']) ? htmlspecialchars(mb_strimwidth($q['description'], 0, 95, '...')) : '<em>No description</em>' ?>
                            </p>

                            <!-- Footer Row -->
                            <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                                <small class="text-muted d-flex align-items-center">
                                    <i class="far fa-calendar-alt mr-1"></i>
                                    <?= date('M d, Y', strtotime($q['quizadd'] ?? $q['created_at'] ?? 'now')) ?>
                                </small>
                                <div class="d-flex align-items-center">
                                    <?php if ($isReviewEligible): ?>
                                        <button type="button" class="btn btn-sm btn-warning font-weight-bold mr-2 text-dark" data-toggle="modal" data-target="#reviewModal_<?= $q['id_quiz'] ?>" title="View Review Mode">
                                            <i class="fas fa-eye mr-1"></i> View
                                        </button>
                                    <?php else: ?>
                                        <a href="<?= BASE_URL ?>modules/host/create_room.php?id_quiz=<?= $q['id_quiz'] ?>" class="btn btn-sm btn-primary font-weight-bold mr-2" style="background-color: var(--kahoot-purple); border-color: var(--kahoot-purple);" title="Play">
                                            <i class="fas fa-play mr-1"></i> Play
                                        </a>
                                    <?php endif; ?>
                                    
                                    <!-- Actions Dropdown -->
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary font-weight-bold dropdown-toggle quiz-actions-btn" type="button" id="quizActions_<?= $q['id_quiz'] ?>" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" title="Actions">
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
document.addEventListener('DOMContentLoaded', function() {
    if (window.jQuery) {
        $(document).on('show.bs.dropdown', '.dropdown', function () {
            $(this).closest('.col-quiz-item').addClass('is-dropdown-open');
            $(this).closest('.quiz-card').addClass('is-dropdown-open');
        });
        $(document).on('hidden.bs.dropdown', '.dropdown', function () {
            $(this).closest('.col-quiz-item').removeClass('is-dropdown-open');
            $(this).closest('.quiz-card').removeClass('is-dropdown-open');
        });
    }
});
</script>


<!-- REVIEW MODE VIEW MODALS (KAHOOT LOBBY STYLE) -->
<?php foreach ($quizzes as $q): ?>
    <?php if (!empty($q['is_review_mode']) && empty($q['is_team_mode']) && ($q['game_type'] ?? 'classic') === 'classic' && ($q['mode'] ?? 'normal') === 'normal'): 
        $rPin = ReviewManager::getOrGeneratePin(intval($q['id_quiz']), $q['review_pin'] ?? null);
        $rUrl = ReviewManager::buildReviewUrl($rPin);
        $joinPageUrl = rtrim(BASE_URL, '/') . '/modules/player/join.php';
        $displayJoinUrl = preg_replace('#^https?://#', '', $joinPageUrl);
    ?>
    <div class="modal fade" id="reviewModal_<?= $q['id_quiz'] ?>" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered" role="document" style="max-width: 720px;">
            <div class="modal-content border-0 bg-transparent">
                <!-- PURPLE OUTER CONTAINER -->
                <div class="p-3 p-md-4 position-relative" style="background: #46178f; border-radius: 26px; box-shadow: 0 25px 50px -12px rgba(70, 23, 143, 0.45);">
                    <!-- INNER WHITE CARD -->
                    <div class="bg-white text-dark p-4" style="border-radius: 18px; box-shadow: 0 10px 25px rgba(0,0,0,0.12);">
                        <!-- MODAL HEADER WITH VISIBLE CLOSE (X) BUTTON ON TOP RIGHT OF FORM -->
                        <div class="d-flex align-items-center justify-content-between mb-3 pb-3 border-bottom">
                            <h5 class="font-weight-bold text-dark mb-0" style="font-size: 1.25rem;">
                                <?= htmlspecialchars($q['name'] ?? '') ?>
                            </h5>
                            <button type="button" class="close text-secondary" data-dismiss="modal" aria-label="Close" style="font-size: 32px; font-weight: 300; opacity: 0.75; outline: none; line-height: 0.8; padding: 4px; margin: -6px -4px 0 0;" title="Đóng">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>

                        <div class="row align-items-center">
                            <!-- LEFT COLUMN: Practice Link & Open Button -->
                            <div class="col-md-7 pr-md-4">
                                <label class="small font-weight-bold text-muted text-uppercase mb-1" style="font-size: 0.75rem; letter-spacing: 0.5px;">
                                    Đường link ôn tập
                                </label>
                                <div class="input-group mb-2 shadow-sm" style="border-radius: 10px; overflow: hidden; border: 1.5px solid #cbd5e1;">
                                    <input type="text" class="form-control border-0 bg-light text-truncate font-weight-bold text-primary" value="<?= htmlspecialchars($rUrl) ?>" readonly onclick="window.open(this.value, '_blank')" style="cursor: pointer; font-size: 0.88rem; height: 42px;" title="Nhấp vào để mở trang ôn tập">
                                    <div class="input-group-append">
                                        <button type="button" class="btn btn-primary font-weight-bold px-3 d-flex align-items-center" onclick="copyTextToClipboard('<?= htmlspecialchars($rUrl) ?>', this)" style="background: #46178f; border-color: #46178f; font-size: 0.88rem;">
                                            <i class="fas fa-copy mr-1"></i> Sao chép
                                        </button>
                                    </div>
                                </div>
                                <p class="text-muted small mb-3" style="font-size: 0.82rem;">
                                    <i class="fas fa-info-circle text-info mr-1"></i> Nhấp vào link hoặc nút bên dưới để vào làm bài ngay.
                                </p>

                                <div class="pt-2 border-top">
                                    <a href="<?= htmlspecialchars($rUrl) ?>" target="_blank" rel="noopener" class="btn btn-outline-primary btn-sm font-weight-bold px-3 py-2 rounded-pill shadow-sm" style="border-color: #46178f; color: #46178f;">
                                        <i class="fas fa-external-link-alt mr-1"></i> Vào trang ôn tập ngay
                                    </a>
                                </div>
                            </div>

                            <!-- RIGHT COLUMN: QR Code -->
                            <div class="col-md-5 text-center pl-md-4 mt-4 mt-md-0" style="border-left: 1.5px solid #f1f5f9;">
                                <p class="font-weight-bold text-dark mb-2" style="font-size: 0.95rem;">
                                    <i class="fas fa-qrcode mr-1 text-primary"></i> Quét mã QR để vào ôn tập
                                </p>
                                <div class="d-flex justify-content-center">
                                    <button type="button" class="border p-2 bg-white shadow-sm qr-zoom-trigger" data-toggle="modal" data-target="#qrZoomModal_<?= $q['id_quiz'] ?>" title="Nhấp để phóng to mã QR" style="cursor: zoom-in; outline: none; border-radius: 12px; border-color: #e2e8f0;">
                                        <div id="reviewQrCode_<?= $q['id_quiz'] ?>" class="review-qr-code mx-auto" data-url="<?= htmlspecialchars($rUrl) ?>" style="width: 145px; height: 145px;">
                                            <div class="spinner-border text-primary" role="status"></div>
                                        </div>
                                    </button>
                                </div>
                                <p class="small text-muted mt-2 mb-0" style="font-size: 0.8rem; color: #94a3b8;">
                                    Nhấp mã QR để phóng to
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ENLARGE QR MODAL -->
    <div class="modal fade" id="qrZoomModal_<?= $q['id_quiz'] ?>" tabindex="-1" role="dialog" aria-hidden="true" style="z-index: 1070;">
        <div class="modal-dialog modal-dialog-centered" role="document" style="max-width: 460px;">
            <div class="modal-content border-0 p-4 text-center shadow-lg" style="border-radius: 24px;">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="font-weight-bold text-dark mb-0"><i class="fas fa-qrcode mr-2 text-primary"></i> Quét mã để ôn tập</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="outline: none;">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div id="reviewQrCodeLarge_<?= $q['id_quiz'] ?>" class="mx-auto my-2" data-url="<?= htmlspecialchars($rUrl) ?>" style="width: 320px; height: 320px;">
                    <div class="spinner-border text-primary mt-5" role="status"></div>
                </div>
                
                <button type="button" class="btn btn-secondary font-weight-bold rounded-pill px-4 mx-auto" data-dismiss="modal">Đóng</button>
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
        const qrBoxLarge = this.querySelector("[id^='reviewQrCodeLarge_']");
        if (qrBoxLarge && qrBoxLarge.dataset.url) {
            renderQrSvg(qrBoxLarge.id, qrBoxLarge.dataset.url);
        }
    });
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
