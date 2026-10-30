<?php

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/database.php';

$creatorId = intval($_SESSION['id_creator']);

// Lấy danh sách tất cả câu hỏi đã lưu của người dùng hiện tại
$savedQuestions = db_fetch_all(
    "SELECT sq.id_saved, sq.saved_at, q.*, qz.name as quiz_name, qz.id_quiz
     FROM saved_review_questions sq
     JOIN questions q ON sq.id_question = q.id_question
     JOIN quiz qz ON q.id_quiz = qz.id_quiz
     WHERE sq.id_creator = :c
     ORDER BY sq.saved_at DESC",
    [':c' => $creatorId]
);

// Lấy danh sách câu trả lời cho từng câu hỏi
foreach ($savedQuestions as &$sq) {
    $sq['answers'] = db_fetch_all(
        "SELECT * FROM answers WHERE id_question = :qid ORDER BY id_answer ASC",
        [':qid' => $sq['id_question']]
    );
}
unset($sq);

$totalSaved = count($savedQuestions);

$pageTitle = "Câu hỏi ôn tập đã lưu | Saved Questions - Kahoot!";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';

$colors = [KAHOOT_COLOR_RED, KAHOOT_COLOR_BLUE, KAHOOT_COLOR_YELLOW, KAHOOT_COLOR_GREEN];
$icons  = [KAHOOT_ICON_RED, KAHOOT_ICON_BLUE, KAHOOT_ICON_YELLOW, KAHOOT_ICON_GREEN];

$reviewMediaUrl = static function (?string $path): string {
    if (!$path) return '';
    if (preg_match('#^https?://#i', $path)) return $path;
    $filename = basename($path);
    $diskPath = dirname(__DIR__, 2) . '/assets/img/questions/' . $filename;
    return is_file($diskPath) ? BASE_URL . 'assets/img/questions/' . rawurlencode($filename) : '';
};
?>

<div class="container my-4" style="max-width: 1050px;">

    <!-- Thanh tiêu đề và nút quay lại -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 pb-3 border-bottom">
        <div>
            <div class="d-flex align-items-center mb-1">
                <a href="<?= BASE_URL ?>modules/auth/dashboard.php" class="btn btn-outline-secondary btn-sm mr-3 font-weight-bold" style="border-radius: 8px;">
                    <i class="fas fa-arrow-left mr-1"></i> Dashboard
                </a>
                <h2 class="font-weight-bold mb-0" style="color: var(--kahoot-purple);">
                    <i class="fas fa-bookmark text-warning mr-2"></i>Câu hỏi đã lưu
                </h2>
                <span id="savedCountBadge" class="badge badge-warning text-dark font-weight-bold ml-3 px-3 py-2" style="font-size: 1rem; border-radius: 20px;">
                    <?= $totalSaved ?> câu hỏi
                </span>
            </div>
            <p class="text-muted mb-0">Các câu hỏi bạn đã đánh dấu lưu từ các ván đấu để ôn tập lại kiến thức.</p>
        </div>
        <div class="mt-2 mt-md-0">
            <a href="<?= BASE_URL ?>modules/player/join.php" class="btn btn-success font-weight-bold shadow-sm" style="border-radius: 8px;">
                <i class="fas fa-gamepad mr-1"></i> Vào phòng thi đấu
            </a>
        </div>
    </div>

    <?php if ($totalSaved > 0): ?>
        <!-- Thanh tìm kiếm nhanh -->
        <div class="card border-0 shadow-sm mb-4" style="border-radius: 12px; background: #faf7fe;">
            <div class="card-body p-3">
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text bg-white border-right-0"><i class="fas fa-search text-muted"></i></span>
                    </div>
                    <input type="text" id="searchInput" class="form-control border-left-0" placeholder="Tìm kiếm theo nội dung câu hỏi hoặc tên bài Kahoot..." autofocus>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Khung thông báo khi không có câu hỏi nào -->
    <div id="emptyState" class="text-center py-5 <?= $totalSaved > 0 ? 'd-none' : '' ?>">
        <div class="card border-0 shadow-sm p-5 mx-auto" style="max-width: 600px; border-radius: 16px; background: #ffffff;">
            <div class="mb-3 text-warning">
                <i class="fas fa-bookmark fa-4x" style="opacity: 0.6;"></i>
            </div>
            <h4 class="font-weight-bold text-dark mb-2">Chưa có câu hỏi nào được lưu</h4>
            <p class="text-muted mb-4">
                Khi tham gia các ván đấu Kahoot, ở màn hình <b>Kết quả cuối cùng (Review your answers)</b>, bạn có thể nhấn nút <span class="badge badge-light border text-primary"><i class="far fa-bookmark mr-1"></i>Save for review</span> để lưu câu hỏi về trang này và xem lại bất cứ lúc nào!
            </p>
            <div>
                <a href="<?= BASE_URL ?>modules/player/join.php" class="btn btn-kahoot btn-kahoot-green font-weight-bold mr-2">
                    <i class="fas fa-play mr-1"></i> Chơi Kahoot ngay
                </a>
                <a href="<?= BASE_URL ?>modules/auth/dashboard.php" class="btn btn-outline-secondary font-weight-bold">
                    <i class="fas fa-home mr-1"></i> Về Dashboard
                </a>
            </div>
        </div>
    </div>

    <!-- Danh sách câu hỏi đã lưu -->
    <div id="questionsContainer" class="row">
        <?php foreach ($savedQuestions as $index => $q):
            $mediaUrl = $reviewMediaUrl($q['image'] ?? null);
            $ext = strtolower(pathinfo(parse_url($mediaUrl, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
            $isVideo = in_array($ext, ['mp4', 'webm'], true) || ($q['media_type'] ?? '') === 'video';
            $savedDateFormatted = date('d/m/Y H:i', strtotime($q['saved_at']));
        ?>
            <div class="col-12 mb-4 question-item" id="saved-card-<?= (int)$q['id_question'] ?>" data-text="<?= htmlspecialchars(mb_strtolower($q['text'] . ' ' . $q['quiz_name'])) ?>">
                <div class="card border-0 shadow-sm" style="border-radius: 14px; overflow: hidden; border-left: 5px solid var(--kahoot-purple) !important;">
                    
                    <!-- Header của câu hỏi -->
                    <div class="card-header bg-white py-3 px-4 d-flex flex-wrap justify-content-between align-items-center border-bottom">
                        <div class="d-flex align-items-center flex-wrap" style="gap: 8px;">
                            <span class="badge badge-dark px-3 py-1 font-weight-bold" style="font-size: 0.95rem; border-radius: 6px;">
                                Câu #<?= $totalSaved - $index ?>
                            </span>
                            <span class="badge badge-primary px-3 py-1 font-weight-bold" style="font-size: 0.85rem; border-radius: 6px;" title="Bộ câu hỏi gốc">
                                <i class="fas fa-layer-group mr-1"></i><?= htmlspecialchars($q['quiz_name']) ?>
                            </span>
                            <span class="badge badge-light border text-muted px-2 py-1" style="font-size: 0.8rem; border-radius: 6px;">
                                <i class="far fa-clock mr-1"></i>Lưu: <?= $savedDateFormatted ?>
                            </span>
                            <span class="badge badge-light border text-muted px-2 py-1" style="font-size: 0.8rem; border-radius: 6px;">
                                <i class="fas fa-stopwatch mr-1"></i><?= intval($q['time_limit']) ?>s
                            </span>
                            <span class="badge badge-light border text-muted px-2 py-1" style="font-size: 0.8rem; border-radius: 6px;">
                                <i class="fas fa-star mr-1 text-warning"></i><?= intval($q['points']) ?> pts
                            </span>
                        </div>
                        <div class="mt-2 mt-sm-0">
                            <button type="button" class="btn btn-outline-danger btn-sm font-weight-bold btn-unsave"
                                    data-question-id="<?= (int)$q['id_question'] ?>"
                                    data-card-id="saved-card-<?= (int)$q['id_question'] ?>"
                                    style="border-radius: 6px;">
                                <i class="fas fa-trash-alt mr-1"></i> Bỏ lưu
                            </button>
                        </div>
                    </div>

                    <div class="card-body p-4">
                        <!-- Nội dung câu hỏi -->
                        <h4 class="font-weight-bold text-dark mb-3" style="line-height: 1.4;">
                            <?= htmlspecialchars($q['text'] ?? '') ?>
                        </h4>

                        <!-- Hình ảnh / Video đính kèm nếu có -->
                        <?php if ($mediaUrl): ?>
                            <div class="text-center my-3 p-2 bg-light rounded" style="max-height: 260px;">
                                <?php if ($isVideo): ?>
                                    <video src="<?= htmlspecialchars($mediaUrl, ENT_QUOTES, 'UTF-8') ?>" controls style="max-height: 240px; max-width: 100%; border-radius: 8px;"></video>
                                <?php else: ?>
                                    <img src="<?= htmlspecialchars($mediaUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Media" class="img-fluid rounded" style="max-height: 240px; object-fit: contain;">
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <!-- Danh sách đáp án -->
                        <div class="row mt-3">
                            <?php foreach ($q['answers'] as $aIdx => $a):
                                $cIdx = $aIdx % 4;
                                $isCorrect = ((int)$a['is_correct'] === 1);
                                $aImg = $reviewMediaUrl($a['image_path'] ?? null);
                            ?>
                                <div class="col-md-6 mb-2">
                                    <div class="p-3 rounded d-flex align-items-center justify-content-between shadow-sm"
                                         style="background-color: <?= $colors[$cIdx] ?>; color: #fff; border: <?= $isCorrect ? '3px solid #28a745' : '1px solid rgba(0,0,0,0.1)' ?>; position: relative;">
                                        
                                        <div class="d-flex align-items-center flex-grow-1 mr-2">
                                            <span class="mr-3" style="font-size: 1.4rem; line-height: 1;"><?= $icons[$cIdx] ?></span>
                                            <?php if ($aImg): ?>
                                                <img src="<?= htmlspecialchars($aImg, ENT_QUOTES, 'UTF-8') ?>" alt="Answer" class="rounded mr-2 bg-white p-1" style="max-height: 40px; max-width: 60px; object-fit: contain;">
                                            <?php endif; ?>
                                            <span class="font-weight-bold" style="font-size: 1.1rem;"><?= htmlspecialchars($a['text'] ?? '') ?></span>
                                        </div>

                                        <?php if ($isCorrect): ?>
                                            <span class="badge badge-light text-success font-weight-bold px-2 py-1 shadow-sm" style="font-size: 0.9rem; border-radius: 6px; white-space: nowrap;">
                                                <i class="fas fa-check-circle mr-1"></i> ĐÁP ÁN ĐÚNG
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

</div>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script>
$(document).ready(function() {
    // 1. Tìm kiếm nhanh theo nội dung
    $('#searchInput').on('keyup', function() {
        const query = $(this).val().toLowerCase().trim();
        let matchCount = 0;
        $('.question-item').each(function() {
            const itemText = $(this).data('text');
            if (!query || itemText.indexOf(query) !== -1) {
                $(this).show();
                matchCount++;
            } else {
                $(this).hide();
            }
        });
    });

    // 2. Xử lý nút "Bỏ lưu"
    $('.btn-unsave').on('click', async function() {
        const btn = $(this);
        const questionId = btn.data('question-id');
        const cardId = btn.data('card-id');

        if (!confirm('Bạn có chắc muốn bỏ lưu câu hỏi này không?')) {
            return;
        }

        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Đang xóa...');

        try {
            const response = await fetch('<?= BASE_URL ?>modules/api/toggle_saved_review_question.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    id_question: questionId,
                    saved: false
                })
            });

            const res = await response.json();
            if (response.ok && res.success) {
                $('#' + cardId).fadeOut(350, function() {
                    $(this).remove();
                    const remaining = $('.question-item').length;
                    $('#savedCountBadge').text(remaining + ' câu hỏi');

                    if (remaining === 0) {
                        $('#searchInput').closest('.card').remove();
                        $('#emptyState').removeClass('d-none').hide().fadeIn(300);
                    }
                });
            } else {
                alert(res.message || 'Không thể bỏ lưu câu hỏi. Vui lòng thử lại.');
                btn.prop('disabled', false).html('<i class="fas fa-trash-alt mr-1"></i> Bỏ lưu');
            }
        } catch (err) {
            alert('Lỗi kết nối máy chủ. Vui lòng thử lại.');
            btn.prop('disabled', false).html('<i class="fas fa-trash-alt mr-1"></i> Bỏ lưu');
        }
    });
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
