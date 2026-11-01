<?php

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/database.php';

$creatorId = intval($_SESSION['id_creator']);

$savedQuestions = db_fetch_all(
    "SELECT sq.id_saved, sq.saved_at, q.*, qz.name as quiz_name, qz.id_quiz
     FROM saved_review_questions sq
     JOIN questions q ON sq.id_question = q.id_question
     JOIN quiz qz ON q.id_quiz = qz.id_quiz
     WHERE sq.id_creator = :c
     ORDER BY sq.id_saved DESC",
    [':c' => $creatorId]
);

foreach ($savedQuestions as &$sq) {
    $sq['answers'] = db_fetch_all(
        "SELECT * FROM answers WHERE id_question = :qid ORDER BY id_answer ASC",
        [':qid' => $sq['id_question']]
    );
}
unset($sq);

$totalSaved = count($savedQuestions);

$pageTitle = "Questions Review | Kahoot!";
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

<style>
body {
    background-color: #f8fafc;
}

.review-container {
    max-width: 920px;
    margin: 28px auto 60px;
    padding: 0 16px;
}

.review-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 16px;
    margin-bottom: 24px;
    padding-bottom: 16px;
    border-bottom: 1px solid #e2e8f0;
}

.review-nav-left {
    display: flex;
    align-items: center;
    gap: 12px;
}

.back-link {
    color: #64748b;
    font-size: 0.92rem;
    font-weight: 600;
    text-decoration: none;
    transition: color 0.15s ease;
}

.back-link:hover {
    color: #0f172a;
    text-decoration: none;
}

.toolbar-divider {
    color: #cbd5e1;
    font-size: 0.9rem;
}

.toolbar-title {
    font-size: 1.15rem;
    font-weight: 700;
    color: #0f172a;
    margin: 0;
}

.count-pill {
    display: inline-block;
    padding: 2px 8px;
    font-size: 0.78rem;
    font-weight: 700;
    color: #475569;
    background: #e2e8f0;
    border-radius: 12px;
}

.search-box {
    position: relative;
    min-width: 240px;
}

.search-input {
    width: 100%;
    padding: 8px 14px;
    font-size: 0.88rem;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    background: #ffffff;
    color: #0f172a;
    outline: none;
    transition: all 0.2s ease;
}

.search-input:focus {
    border-color: #46178f;
    box-shadow: 0 0 0 3px rgba(70, 23, 143, 0.12);
}

.q-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    margin-bottom: 16px;
    overflow: hidden;
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.q-card:hover {
    border-color: #cbd5e1;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.04);
}

.q-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 20px;
    background: #f8fafc;
    border-bottom: 1px solid #f1f5f9;
    font-size: 0.82rem;
    color: #64748b;
}

.q-meta {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
}

.q-quiz-name {
    font-weight: 600;
    color: #334155;
}

.meta-dot {
    color: #cbd5e1;
}

.btn-remove-clean {
    background: transparent;
    border: none;
    color: #94a3b8;
    font-size: 0.82rem;
    font-weight: 600;
    cursor: pointer;
    padding: 4px 8px;
    border-radius: 6px;
    transition: all 0.15s ease;
}

.btn-remove-clean:hover {
    color: #e11d48;
    background: #fff1f2;
}

.q-body {
    padding: 20px;
}

.q-text {
    font-size: 1.15rem;
    font-weight: 600;
    color: #0f172a;
    line-height: 1.5;
    margin-bottom: 16px;
}

.answers-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 10px;
}

.ans-item {
    padding: 12px 16px;
    border-radius: 8px;
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 0.95rem;
    font-weight: 600;
}

.ans-item.is-correct {
    outline: 2px solid #16a34a;
    outline-offset: 1px;
}

.ans-shape {
    font-size: 1.1rem;
    margin-right: 12px;
    opacity: 0.9;
    display: inline-block;
    width: 18px;
    text-align: center;
}

.ans-correct-badge {
    background: rgba(255, 255, 255, 0.95);
    color: #15803d;
    font-size: 0.75rem;
    font-weight: 700;
    padding: 3px 8px;
    border-radius: 6px;
    letter-spacing: 0.3px;
    text-transform: uppercase;
    margin-left: 10px;
    white-space: nowrap;
}

.empty-review {
    text-align: center;
    padding: 60px 20px;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
}

.empty-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 6px;
}

.empty-sub {
    font-size: 0.9rem;
    color: #64748b;
    margin-bottom: 20px;
}

.empty-btn {
    display: inline-block;
    padding: 8px 18px;
    background: #0f172a;
    color: #ffffff;
    font-size: 0.88rem;
    font-weight: 600;
    border-radius: 8px;
    text-decoration: none;
    transition: background 0.15s ease;
}

.empty-btn:hover {
    background: #334155;
    color: #ffffff;
    text-decoration: none;
}
</style>

<div class="review-container">

    <div class="review-toolbar">
        <div class="review-nav-left">
            <span class="toolbar-title">Questions</span>
            <span id="savedCountBadge" class="count-pill"><?= $totalSaved ?></span>
        </div>
        <div class="toolbar-right">
            <?php if ($totalSaved > 0): ?>
                <div class="search-box">
                    <input type="text" id="searchInput" class="search-input" placeholder="Search questions or quiz..." autocomplete="off">
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div id="emptyState" class="empty-review <?= $totalSaved > 0 ? 'd-none' : '' ?>">
        <div class="empty-title">No questions found</div>
        <p class="empty-sub">Questions bookmarked after games will appear here.</p>
        <a href="<?= BASE_URL ?>modules/quiz/list.php" class="empty-btn">
            Back to Kahoots
        </a>
    </div>

    <div id="questionsContainer">
        <?php foreach ($savedQuestions as $index => $q):
            $mediaUrl = $reviewMediaUrl($q['image'] ?? null);
            $ext = strtolower(pathinfo(parse_url($mediaUrl, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
            $isVideo = in_array($ext, ['mp4', 'webm'], true) || ($q['media_type'] ?? '') === 'video';
            $savedDateFormatted = date('M d, Y', strtotime($q['saved_at']));
        ?>
            <div class="q-card question-item" id="saved-card-<?= (int)$q['id_question'] ?>" data-text="<?= htmlspecialchars(mb_strtolower($q['text'] . ' ' . $q['quiz_name'])) ?>">
                
                <div class="q-header">
                    <div class="q-meta">
                        <span class="q-quiz-name"><?= htmlspecialchars($q['quiz_name']) ?></span>
                        <span class="meta-dot">&bull;</span>
                        <span><?= intval($q['time_limit']) ?>s</span>
                        <span class="meta-dot">&bull;</span>
                        <span><?= intval($q['points']) ?> pts</span>
                        <span class="meta-dot">&bull;</span>
                        <span><?= $savedDateFormatted ?></span>
                    </div>
                    <div>
                        <button type="button" class="btn-remove-clean btn-unsave"
                                data-question-id="<?= (int)$q['id_question'] ?>"
                                data-card-id="saved-card-<?= (int)$q['id_question'] ?>">
                            Remove
                        </button>
                    </div>
                </div>

                <div class="q-body">
                    <div class="q-text">
                        <?= htmlspecialchars($q['text'] ?? '') ?>
                    </div>

                    <?php if ($mediaUrl): ?>
                        <div class="text-center my-3 p-2 bg-light rounded" style="max-height: 260px;">
                            <?php if ($isVideo): ?>
                                <video src="<?= htmlspecialchars($mediaUrl, ENT_QUOTES, 'UTF-8') ?>" controls style="max-height: 240px; max-width: 100%; border-radius: 8px;"></video>
                            <?php else: ?>
                                <img src="<?= htmlspecialchars($mediaUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Media" class="img-fluid rounded" style="max-height: 240px; object-fit: contain;">
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <div class="answers-grid">
                        <?php foreach ($q['answers'] as $aIdx => $a):
                            $cIdx = $aIdx % 4;
                            $isCorrect = ((int)$a['is_correct'] === 1);
                            $aImg = $reviewMediaUrl($a['image_path'] ?? null);
                        ?>
                            <div class="ans-item <?= $isCorrect ? 'is-correct' : '' ?>" style="background-color: <?= $colors[$cIdx] ?>;">
                                <div class="d-flex align-items-center flex-grow-1 mr-2">
                                    <span class="ans-shape"><?= $icons[$cIdx] ?></span>
                                    <?php if ($aImg): ?>
                                        <img src="<?= htmlspecialchars($aImg, ENT_QUOTES, 'UTF-8') ?>" alt="Answer" class="rounded mr-2 bg-white p-1" style="max-height: 36px; max-width: 50px; object-fit: contain;">
                                    <?php endif; ?>
                                    <span><?= htmlspecialchars($a['text'] ?? '') ?></span>
                                </div>
                                <?php if ($isCorrect): ?>
                                    <span class="ans-correct-badge">Correct</span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>

                </div>
            </div>
        <?php endforeach; ?>
    </div>

</div>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script>
$(document).ready(function() {
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

    $('.btn-unsave').on('click', async function() {
        const btn = $(this);
        const questionId = btn.data('question-id');
        const cardId = btn.data('card-id');

        if (!confirm('Remove this question?')) {
            return;
        }

        btn.prop('disabled', true).text('Removing...');

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
                $('#' + cardId).fadeOut(250, function() {
                    $(this).remove();
                    const remaining = $('.question-item').length;
                    $('#savedCountBadge').text(remaining);

                    if (remaining === 0) {
                        $('#searchInput').closest('.search-box').remove();
                        $('#emptyState').removeClass('d-none').hide().fadeIn(250);
                    }
                });
            } else {
                alert(res.message || 'Unable to remove question.');
                btn.prop('disabled', false).text('Remove');
            }
        } catch (err) {
            alert('Server error. Please try again.');
            btn.prop('disabled', false).text('Remove');
        }
    });
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>