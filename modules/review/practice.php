<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/ReviewManager.php';

$pin = trim($_GET['pin'] ?? '');
$quiz = null;
$questions = [];
$savedQuestionIds = [];
$isUserLoggedIn = !empty($_SESSION['id_creator']);

if ($pin !== '') {
    $quiz = ReviewManager::getQuizByPin($pin);
    if ($quiz) {
        $questions = ReviewManager::getReviewQuestions(intval($quiz['id_quiz']));
        if ($isUserLoggedIn) {
            try {
                $sqRows = db_fetch_all(
                    "SELECT id_question FROM saved_review_questions WHERE id_creator = :c",
                    [':c' => (int)$_SESSION['id_creator']]
                );
                $savedQuestionIds = array_map('intval', array_column($sqRows, 'id_question'));
            } catch (Throwable $e) {
                $savedQuestionIds = [];
            }
        }
    }
}

$pageTitle = $quiz ? ("Ôn tập: " . htmlspecialchars($quiz['name'])) : "Chế độ Ôn tập | Kahootify";
$bodyClass = "review-practice-body";
require_once __DIR__ . '/../../includes/header.php';
?>
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/final-results.css?v=<?= file_exists(__DIR__ . '/../../assets/css/final-results.css') ? filemtime(__DIR__ . '/../../assets/css/final-results.css') : time() ?>">

<style>
.review-practice-body {
    background-color: #f0f2f5;
    min-height: 100vh;
    font-family: 'Nunito', sans-serif;
    color: #1e293b;
    display: flex;
    flex-direction: column;
}

/* Header */
.review-topbar {
    background: #ffffff;
    border-bottom: 2px solid #e2e8f0;
    padding: 12px 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    position: sticky;
    top: 0;
    z-index: 1020;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
}

.review-logo {
    height: 36px;
}

.review-title-badge {
    background: #fef3c7;
    color: #92400e;
    font-weight: 800;
    font-size: 0.75rem;
    padding: 4px 10px;
    border-radius: 9999px;
    letter-spacing: 0.5px;
    text-transform: uppercase;
}

.review-progress-pill {
    background: #e0e7ff;
    color: #3730a3;
    font-weight: 800;
    font-size: 0.95rem;
    padding: 6px 16px;
    border-radius: 9999px;
}

/* Palette Drawer / Modal */
.palette-modal .modal-dialog {
    max-width: 440px;
    margin: 1.75rem auto;
}

.palette-modal .modal-content {
    border-radius: 20px;
    border: none;
    overflow: hidden;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
}

.palette-grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 10px;
    max-height: 320px;
    overflow-y: auto;
    padding: 16px;
    background: #f8fafc;
}

.palette-cell {
    aspect-ratio: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    font-size: 1.05rem;
    border-radius: 12px;
    border: 2px solid #cbd5e1;
    background: #ffffff;
    color: #334155;
    cursor: pointer;
    transition: all 0.18s ease;
    user-select: none;
}

.palette-cell:hover {
    transform: translateY(-2px);
    border-color: #6366f1;
    box-shadow: 0 4px 8px rgba(99, 102, 241, 0.15);
}

.palette-cell.current {
    border: 3px solid #6366f1 !important;
    background: #eef2ff !important;
    color: #4338ca !important;
    transform: scale(1.06);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.25);
}

.palette-cell.correct {
    background: #22c55e !important;
    border-color: #16a34a !important;
    color: #ffffff !important;
}

.palette-cell.incorrect {
    background: #ef4444 !important;
    border-color: #dc2626 !important;
    color: #ffffff !important;
}

.palette-sticky-footer {
    position: sticky;
    bottom: 0;
    background: #ffffff;
    padding: 14px 16px;
    border-top: 1px solid #e2e8f0;
    box-shadow: 0 -4px 12px rgba(0,0,0,0.04);
}

/* Practice Area */
.practice-container {
    max-width: 960px;
    width: 100%;
    margin: 24px auto;
    padding: 0 16px;
    flex: 1;
    display: flex;
    flex-direction: column;
}

.question-card {
    background: #ffffff;
    border-radius: 20px;
    padding: 28px 24px;
    box-shadow: 0 4px 16px rgba(0,0,0,0.06);
    border: 1px solid #e2e8f0;
    margin-bottom: 24px;
    text-align: center;
}

.question-text {
    font-size: 1.55rem;
    font-weight: 800;
    color: #0f172a;
    line-height: 1.35;
    margin-bottom: 16px;
}

.question-media {
    max-height: 280px;
    max-width: 100%;
    border-radius: 14px;
    object-fit: contain;
    margin: 12px auto 20px auto;
    display: block;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
}

/* Dynamic Answer Grid */
.answers-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 16px;
    margin-bottom: 24px;
}
.answers-grid.is-text-mode {
    display: block;
}
.text-ans-practice-card {
    background: #ffffff;
    border-radius: 20px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.06);
    border: 2px solid #e2e8f0;
    max-width: 650px;
    margin: 10px auto;
    padding: 24px;
}
.text-ans-field {
    border-radius: 12px 0 0 12px !important;
    border: 2px solid #cbd5e1 !important;
    border-right: none !important;
    font-size: 1.25rem !important;
    color: #1e293b !important;
}
.text-ans-field:focus {
    border-color: #6366f1 !important;
    box-shadow: none !important;
}
.text-ans-submit-btn {
    border-radius: 0 12px 12px 0 !important;
    padding: 0 24px !important;
    font-size: 1.1rem !important;
}

@media (max-width: 768px) {
    .answers-grid {
        grid-template-columns: 1fr;
        gap: 12px;
    }
    .question-text {
        font-size: 1.25rem;
    }
}

.answer-btn {
    border: none;
    border-radius: 16px;
    padding: 20px 18px;
    font-weight: 800;
    font-size: 1.15rem;
    color: #ffffff;
    cursor: pointer;
    display: flex;
    align-items: center;
    position: relative;
    transition: transform 0.15s ease, box-shadow 0.15s ease, opacity 0.2s ease;
    box-shadow: 0 4px 10px rgba(0,0,0,0.12);
    min-height: 84px;
    text-align: left;
}

.answer-btn:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 8px 18px rgba(0,0,0,0.18);
}

.answer-btn:disabled {
    cursor: default;
}

/* Kahoot Shape & Colors */
.ans-color-0 { background: #e21b3c; } /* Red triangle */
.ans-color-1 { background: #1368ce; } /* Blue diamond */
.ans-color-2 { background: #d89e00; } /* Yellow circle */
.ans-color-3 { background: #26890c; } /* Green square */
.ans-color-fallback { background: #4f46e5; }

.answer-shape-icon {
    font-size: 1.35rem;
    margin-right: 14px;
    flex-shrink: 0;
    opacity: 0.95;
}

.answer-content {
    flex: 1;
    word-break: break-word;
}

.answer-img-thumb {
    max-height: 60px;
    max-width: 90px;
    border-radius: 8px;
    margin-right: 12px;
    object-fit: cover;
    background: #fff;
    padding: 2px;
}

/* Instant Feedback Overrides */
.answer-btn.selected-correct {
    background: #22c55e !important;
    box-shadow: 0 0 0 4px #86efac, 0 8px 20px rgba(34, 197, 94, 0.4) !important;
    transform: scale(1.02);
}

.answer-btn.selected-incorrect {
    background: #ef4444 !important;
    box-shadow: 0 0 0 4px #fca5a5, 0 8px 20px rgba(239, 68, 68, 0.35) !important;
    opacity: 0.9;
}

.answer-btn.highlight-correct {
    background: #16a34a !important;
    box-shadow: 0 0 0 4px #86efac, 0 8px 20px rgba(22, 163, 74, 0.45) !important;
    animation: pulseBorder 1.5s infinite;
}

.answer-btn.faded-out {
    opacity: 0.45;
}

@keyframes pulseBorder {
    0%, 100% { box-shadow: 0 0 0 4px #86efac, 0 8px 20px rgba(22, 163, 74, 0.45); }
    50% { box-shadow: 0 0 0 7px #bbf7d0, 0 10px 24px rgba(22, 163, 74, 0.6); }
}

.feedback-status-pill {
    position: absolute;
    top: -12px;
    right: 16px;
    padding: 3px 12px;
    border-radius: 9999px;
    font-size: 0.8rem;
    font-weight: 800;
    color: #fff;
    box-shadow: 0 2px 6px rgba(0,0,0,0.15);
}

.feedback-status-pill.correct { background: #15803d; }
.feedback-status-pill.incorrect { background: #b91c1c; }

/* Bottom Action Bar */
.bottom-action-bar {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    padding: 16px 0;
    margin-top: auto;
}

#btnNextQuestion {
    font-family: 'Montserrat', sans-serif;
    font-weight: 800;
    font-size: 1.05rem;
    color: #ffffff;
    background: #1368ce;
    border: none;
    border-radius: 8px;
    padding: 12px 28px;
    box-shadow: 0 4px 0 #0a468c;
    transition: all 0.12s ease-in-out;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    outline: none;
}

#btnNextQuestion:hover {
    background: #1976d2;
    box-shadow: 0 4px 0 #0a468c;
    transform: translateY(-2px);
}

#btnNextQuestion:active {
    box-shadow: 0 1px 0 #0a468c;
    transform: translateY(3px);
}

/* Results Screen */
.results-container {
    max-width: 860px;
    width: 100%;
    margin: 32px auto;
    padding: 0 16px;
}

.results-header-card {
    background: #ffffff;
    border-radius: 24px;
    padding: 36px 24px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.08);
    border: 1px solid #e2e8f0;
    text-align: center;
    margin-bottom: 28px;
}

/* Classic review results styling is provided by final-results.css */
</style>

<!-- TOPBAR -->
<header class="review-topbar">
    <div class="d-flex align-items-center">
        <a href="<?= BASE_URL ?>" class="mr-3">
            <img src="<?= BASE_URL ?>assets/img/logo.png" alt="Logo Kahootify" class="review-logo">
        </a>
    </div>

    <?php if ($quiz && !empty($questions)): ?>
    <div class="d-flex align-items-center">
        <span class="review-progress-pill mr-2 shadow-sm" id="progressPill">
            Câu <span id="currentQNumber">1</span> / <?= count($questions) ?>
        </span>
        <button type="button" class="btn btn-outline-primary font-weight-bold rounded-pill px-3 py-2 shadow-sm" data-toggle="modal" data-target="#paletteModal">
            <i class="fas fa-th mr-1"></i> <span class="d-none d-md-inline">Danh sách câu</span>
        </button>
    </div>
    <?php endif; ?>
</header>

<!-- INVALID PIN ERROR -->
<?php if (!$quiz || empty($questions)): ?>
<div class="d-flex align-items-center justify-content-center flex-grow-1 p-3">
    <div class="card border-0 shadow-lg p-4 text-center" style="max-width: 480px; border-radius: 20px;">
        <div class="mb-3">
            <span class="d-inline-flex align-items-center justify-content-center rounded-circle" style="width: 72px; height: 72px; background: #fee2e2; color: #ef4444; font-size: 2rem;">
                <i class="fas fa-exclamation-triangle"></i>
            </span>
        </div>
        <h4 class="font-weight-bold text-dark mb-2">Phiên ôn tập không khả dụng</h4>
        <p class="text-muted mb-4">
            Mã PIN không chính xác hoặc giáo viên đã tắt chế độ ôn tập cho bài quiz này.
        </p>
        <div>
            <a href="<?= BASE_URL ?>modules/player/join.php" class="btn btn-primary font-weight-bold rounded-pill px-4 py-2 shadow-sm">
                <i class="fas fa-arrow-left mr-1"></i> Nhập mã PIN khác
            </a>
        </div>
    </div>
</div>
<?php exit; endif; ?>

<!-- MODAL QUESTION PALETTE (5 COLUMNS GRID) -->
<div class="modal fade palette-modal" id="paletteModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header bg-white px-4 py-3 border-bottom">
                <h5 class="modal-title font-weight-bold text-dark">
                    <i class="fas fa-th-large text-primary mr-2"></i> Danh sách câu hỏi
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <!-- 5 COLUMNS SCROLLABLE GRID -->
            <div class="palette-grid" id="paletteGrid">
                <!-- Dynamically generated buttons 1..N -->
            </div>
        </div>
    </div>
</div>

<!-- PRACTICE MAIN AREA -->
<main class="practice-container" id="practiceScreen">
    <div class="question-card" id="questionCard">
        <div class="small text-muted font-weight-bold text-uppercase mb-1" id="questionSubTitle">
            Câu hỏi 1
        </div>
        <h2 class="question-text" id="questionText">
            <!-- Question text -->
        </h2>
        <img id="questionMedia" class="question-media d-none" alt="Question Image">
    </div>

    <!-- DYNAMIC ANSWER OPTIONS (2, 3, 4, etc.) -->
    <div class="answers-grid" id="answersGrid">
        <!-- Dynamically populated answer buttons -->
    </div>

    <!-- BOTTOM BAR: NEXT BUTTON -->
    <div class="bottom-action-bar">
        <button type="button" id="btnNextQuestion" style="display: none;">
            <span>Câu tiếp theo</span> <i class="fas fa-arrow-right ml-1"></i>
        </button>
    </div>
</main>

<!-- RESULTS / SUMMARY SCREEN -->
<main class="results-container" id="resultsScreen" style="display: none;">
    <div class="results-header-card">
        <div class="mb-3">
            <span class="d-inline-flex align-items-center justify-content-center rounded-circle" style="width: 80px; height: 80px; background: #dcfce7; color: #16a34a; font-size: 2.4rem;">
                <i class="fas fa-award"></i>
            </span>
        </div>
        <h3 class="font-weight-bold text-dark mb-3">Hoàn thành bài ôn tập!</h3>
        
        <div class="d-inline-block px-4 py-2 rounded-pill mb-4" style="background: #f1f5f9; border: 2px solid #e2e8f0;">
            <span class="h4 font-weight-bold mb-0" id="resultsScoreText" style="color: #0f172a;">
                0 / 0 Đúng (0%)
            </span>
        </div>

        <!-- 2 ACTION BUTTONS: RESTART & RETURN -->
        <div class="d-flex justify-content-center flex-wrap" style="gap: 14px;">
            <button type="button" class="btn btn-primary font-weight-bold rounded-pill px-4 py-2 shadow-sm" id="btnRestart">
                <i class="fas fa-redo mr-2"></i> Làm lại toàn bộ
            </button>
            <a href="<?= BASE_URL ?>modules/player/join.php" class="btn btn-outline-secondary font-weight-bold rounded-pill px-4 py-2 shadow-sm" id="btnReturn">
                <i class="fas fa-arrow-left mr-2"></i> Trở về
            </a>
        </div>
    </div>

    <!-- REVIEW YOUR ANSWERS (MATCHING CLASSIC FINAL RESULTS UI) -->
    <section class="results-review" aria-labelledby="resultsReviewTitle">
        <div class="results-review-heading">
            <div>
                <h2 id="resultsReviewTitle">Review your answers</h2>
                <p>See your choice and the correct answer for each question you answered.</p>
            </div>
            <span id="resultsAnsweredCount">0 answered</span>
        </div>

        <section class="results-saved-review" aria-labelledby="resultsSavedReviewTitle">
            <div class="results-saved-review-heading">
                <h3 id="resultsSavedReviewTitle"><i class="far fa-bookmark" aria-hidden="true"></i> Saved questions</h3>
                <span id="resultsSavedReviewCount">0</span>
            </div>
            <p class="results-saved-review-empty" id="resultsSavedReviewEmpty">Save questions here to revisit them later.</p>
            <ul class="results-saved-review-list" id="resultsSavedReviewList"></ul>
        </section>

        <div class="results-review-list" id="resultsReviewList">
            <!-- Dynamically populated review cards -->
        </div>
    </section>
</main>

<!-- DATA INJECTION & CLIENT LOGIC -->
<script>
const QUIZ_DATA = {
    title: <?= json_encode($quiz['name'] ?? '', JSON_UNESCAPED_UNICODE) ?>,
    pin: <?= json_encode($quiz['review_pin'] ?? '', JSON_UNESCAPED_UNICODE) ?>,
    questions: <?= json_encode($questions, JSON_UNESCAPED_UNICODE) ?>,
    isLoggedIn: <?= $isUserLoggedIn ? 'true' : 'false' ?>,
    savedQuestionIds: <?= json_encode($savedQuestionIds) ?>
};

const SHAPES = [
    { icon: 'fas fa-play', colorClass: 'ans-color-0', shape: '▲' },   // Red Triangle
    { icon: 'fas fa-square', colorClass: 'ans-color-1', shape: '◆' }, // Blue Diamond
    { icon: 'fas fa-circle', colorClass: 'ans-color-2', shape: '●' }, // Yellow Circle
    { icon: 'fas fa-star', colorClass: 'ans-color-3', shape: '■' }    // Green Square
];

let currentIndex = 0;
// answersState[questionIndex] = { selectedAnswerId: number|null, isCorrect: bool|null }
let answersState = new Array(QUIZ_DATA.questions.length).fill(null);

document.addEventListener('DOMContentLoaded', function () {
    renderPaletteGrid();
    loadQuestion(0);

    document.getElementById('btnNextQuestion').addEventListener('click', onNextClicked);
    const skipBtn = document.getElementById('btnSkipToEnd');
    if (skipBtn) {
        skipBtn.addEventListener('click', function () {
            $('#paletteModal').modal('hide');
            showResults();
        });
    }
    document.getElementById('btnRestart').addEventListener('click', restartReview);
});

function renderPaletteGrid() {
    const grid = document.getElementById('paletteGrid');
    grid.innerHTML = '';

    QUIZ_DATA.questions.forEach((q, idx) => {
        const cell = document.createElement('div');
        cell.className = 'palette-cell';
        cell.id = `paletteCell_${idx}`;
        cell.textContent = idx + 1;

        updateCellState(cell, idx);

        cell.addEventListener('click', function () {
            $('#paletteModal').modal('hide');
            loadQuestion(idx);
        });

        grid.appendChild(cell);
    });
}

function updateCellState(cell, idx) {
    cell.classList.remove('current', 'correct', 'incorrect');
    if (idx === currentIndex) {
        cell.classList.add('current');
    }

    const state = answersState[idx];
    if (state !== null) {
        if (state.isCorrect) {
            cell.classList.add('correct');
        } else {
            cell.classList.add('incorrect');
        }
    }
}

function loadQuestion(index) {
    if (index < 0 || index >= QUIZ_DATA.questions.length) return;
    currentIndex = index;

    // Update Topbar
    document.getElementById('currentQNumber').textContent = index + 1;
    document.getElementById('questionSubTitle').textContent = `Câu hỏi ${index + 1} / ${QUIZ_DATA.questions.length}`;

    // Highlight palette cell
    QUIZ_DATA.questions.forEach((_, idx) => {
        const c = document.getElementById(`paletteCell_${idx}`);
        if (c) updateCellState(c, idx);
    });

    const q = QUIZ_DATA.questions[index];
    document.getElementById('questionText').textContent = q.question_text || q.text || 'Câu hỏi trắc nghiệm';

    // Media
    const mediaEl = document.getElementById('questionMedia');
    if (q.image) {
        mediaEl.src = (q.image.startsWith('http://') || q.image.startsWith('https://')) ? q.image : ('<?= BASE_URL ?>' + q.image.replace(/^\//, ''));
        mediaEl.classList.remove('d-none');
        mediaEl.onerror = function() { this.classList.add('d-none'); };
    } else {
        mediaEl.classList.add('d-none');
    }

    // Answers Grid
    const answersGrid = document.getElementById('answersGrid');
    answersGrid.innerHTML = '';

    const answers = q.answers || [];
    const currentState = answersState[index];
    const isAnswered = (currentState !== null);

    if (q.question_type === 'text') {
        answersGrid.classList.add('is-text-mode');
        const correctAns = (answers[0]?.answer_text || answers[0]?.text || '').trim();
        const userAns = currentState ? (currentState.userTextAnswer || '') : '';
        const isCorrect = currentState ? Boolean(currentState.isCorrect) : false;

        const textCard = document.createElement('div');
        textCard.className = 'text-ans-practice-card';

        let html = `
            <label for="practiceTextInput" class="font-weight-bold text-muted small text-uppercase mb-2 d-block" style="letter-spacing: 0.5px;">
                <i class="fas fa-keyboard mr-1 text-primary"></i> Nhập câu trả lời của bạn:
            </label>
            <div class="input-group input-group-lg mb-3">
                <input type="text" id="practiceTextInput" class="form-control text-ans-field font-weight-bold px-3"
                       placeholder="Gõ đáp án vào đây…"
                       value="${escapeHtml(userAns)}"
                       ${isAnswered ? 'disabled' : ''}
                       autocomplete="off">
                <div class="input-group-append">
                    <button type="button" class="btn btn-primary font-weight-bold text-ans-submit-btn" id="btnSubmitTextInput"
                            ${isAnswered ? 'disabled' : ''}>
                        <i class="fas fa-paper-plane mr-1"></i> Gửi
                    </button>
                </div>
            </div>
            <div id="textFeedbackContainer">
        `;

        if (isAnswered) {
            if (isCorrect) {
                html += `
                    <div class="alert alert-success d-flex align-items-center mb-0 py-2 px-3 font-weight-bold" style="border-radius: 10px; font-size: 1.05rem;">
                        <i class="fas fa-check-circle mr-2" style="font-size: 1.3rem;"></i>
                        <div>Chính xác! Đáp án của bạn: <u>${escapeHtml(userAns)}</u></div>
                    </div>
                `;
            } else {
                html += `
                    <div class="alert alert-danger mb-0 py-2 px-3 font-weight-bold" style="border-radius: 10px; font-size: 1rem;">
                        <div class="d-flex align-items-center mb-1">
                            <i class="fas fa-times-circle mr-2 text-danger" style="font-size: 1.2rem;"></i>
                            <div>Chưa chính xác! Bạn đã nhập: <span class="badge badge-light border text-danger ml-1">${escapeHtml(userAns || '(Trống)')}</span></div>
                        </div>
                        <div class="text-success small ml-4">
                            <i class="fas fa-check mr-1"></i> Đáp án đúng là: <strong>${escapeHtml(correctAns)}</strong>
                        </div>
                    </div>
                `;
            }
        }

        html += `
            </div>
        `;

        textCard.innerHTML = html;
        answersGrid.appendChild(textCard);

        if (!isAnswered) {
            const inputEl = document.getElementById('practiceTextInput');
            const submitBtn = document.getElementById('btnSubmitTextInput');

            const handleTextSubmit = function() {
                const entered = inputEl.value.trim();
                if (!entered) {
                    inputEl.focus();
                    inputEl.classList.add('is-invalid');
                    setTimeout(() => inputEl.classList.remove('is-invalid'), 1200);
                    return;
                }
                onTextAnswerSubmitted(index, entered, correctAns);
            };

            submitBtn.addEventListener('click', handleTextSubmit);
            inputEl.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    handleTextSubmit();
                }
            });

            setTimeout(() => { if (inputEl) inputEl.focus(); }, 100);
        }
    } else {
        answersGrid.classList.remove('is-text-mode');
        answers.forEach((ans, aIdx) => {
            const shapeMeta = SHAPES[aIdx % SHAPES.length];
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = `answer-btn ${shapeMeta.colorClass}`;
            btn.id = `ansBtn_${ans.id_answer}`;

            // Build Inner Content (Shape, Image, Text)
            let html = `<span class="answer-shape-icon">${shapeMeta.shape}</span>`;
            if (ans.image) {
                const imgSrc = (ans.image.startsWith('http://') || ans.image.startsWith('https://')) ? ans.image : ('<?= BASE_URL ?>' + ans.image.replace(/^\//, ''));
                html += `<img src="${escapeHtml(imgSrc)}" class="answer-img-thumb" alt="Option Image">`;
            }
            if (ans.answer_text) {
                html += `<span class="answer-content">${escapeHtml(ans.answer_text)}</span>`;
            } else if (!ans.image) {
                html += `<span class="answer-content">Đáp án ${aIdx + 1}</span>`;
            }
            btn.innerHTML = html;

            if (isAnswered) {
                btn.disabled = true;
                applyAnswerStyles(btn, ans, currentState.selectedAnswerId);
            } else {
                btn.addEventListener('click', function () {
                    onAnswerSelected(index, ans.id_answer);
                });
            }

            answersGrid.appendChild(btn);
        });
    }

    // Next Button State
    const nextBtn = document.getElementById('btnNextQuestion');
    if (isAnswered) {
        nextBtn.style.display = 'inline-flex';
        if (index === QUIZ_DATA.questions.length - 1) {
            nextBtn.innerHTML = '<span>Xem kết quả tổng kết</span> <i class="fas fa-check-circle ml-1"></i>';
        } else {
            nextBtn.innerHTML = '<span>Câu tiếp theo</span> <i class="fas fa-arrow-right ml-1"></i>';
        }
    } else {
        nextBtn.style.display = 'none';
    }
}

function normalizePracticeText(str) {
    if (!str) return '';
    return str.toString()
        .normalize('NFC')
        .toLowerCase()
        .replace(/[“”‘’`]/g, '"')
        .replace(/\s+/g, ' ')
        .trim();
}

function isPracticeTextCorrect(userText, correctText) {
    const normUser = normalizePracticeText(userText);
    if (!normUser) return false;
    const normUserNoPunct = normUser.replace(/^[.,!?;:'"]+|[.,!?;:'"]+$/g, '');

    const rawCorrect = (correctText || '').toString();
    const variants = [rawCorrect];
    if (/[;\/|]/.test(rawCorrect)) {
        rawCorrect.split(/[;\/|]+/).forEach(v => variants.push(v));
    }

    for (let variant of variants) {
        const normCorrect = normalizePracticeText(variant);
        if (!normCorrect) continue;
        if (normUser === normCorrect) return true;
        const normCorrectNoPunct = normCorrect.replace(/^[.,!?;:'"]+|[.,!?;:'"]+$/g, '');
        if (normUserNoPunct && normUserNoPunct === normCorrectNoPunct) return true;
    }
    return false;
}

function onTextAnswerSubmitted(qIndex, enteredText, correctText) {
    if (answersState[qIndex] !== null) return;

    const isCorrect = isPracticeTextCorrect(enteredText, correctText);

    answersState[qIndex] = {
        selectedAnswerId: null,
        userTextAnswer: enteredText,
        isCorrect: isCorrect
    };

    const cell = document.getElementById(`paletteCell_${qIndex}`);
    if (cell) updateCellState(cell, qIndex);

    loadQuestion(qIndex);
}

function onAnswerSelected(qIndex, selectedAnsId) {
    if (answersState[qIndex] !== null) return; // already answered

    const q = QUIZ_DATA.questions[qIndex];
    const chosenAns = (q.answers || []).find(a => a.id_answer === selectedAnsId);
    const isCorrect = chosenAns ? (chosenAns.is_correct === true) : false;

    // Record answer
    answersState[qIndex] = {
        selectedAnswerId: selectedAnsId,
        isCorrect: isCorrect
    };

    // Update Palette Cell
    const cell = document.getElementById(`paletteCell_${qIndex}`);
    if (cell) updateCellState(cell, qIndex);

    // Apply Instant Feedback styling to all answer buttons of current question
    (q.answers || []).forEach(ans => {
        const btn = document.getElementById(`ansBtn_${ans.id_answer}`);
        if (!btn) return;
        btn.disabled = true;
        applyAnswerStyles(btn, ans, selectedAnsId);
    });

    // Show Next Button
    const nextBtn = document.getElementById('btnNextQuestion');
    nextBtn.style.display = 'inline-flex';
    if (qIndex === QUIZ_DATA.questions.length - 1) {
        nextBtn.innerHTML = '<span>Xem kết quả tổng kết</span> <i class="fas fa-check-circle ml-1"></i>';
    } else {
        nextBtn.innerHTML = '<span>Câu tiếp theo</span> <i class="fas fa-arrow-right ml-1"></i>';
    }
}

function applyAnswerStyles(btn, ans, selectedId) {
    const isSelected = (ans.id_answer === selectedId);
    const isCorrect = (ans.is_correct === true);

    if (isSelected && isCorrect) {
        btn.classList.add('selected-correct');
        const pill = document.createElement('span');
        pill.className = 'feedback-status-pill correct';
        pill.innerHTML = '<i class="fas fa-check mr-1"></i> Đúng rồi!';
        btn.appendChild(pill);
    } else if (isSelected && !isCorrect) {
        btn.classList.add('selected-incorrect');
        const pill = document.createElement('span');
        pill.className = 'feedback-status-pill incorrect';
        pill.innerHTML = '<i class="fas fa-times mr-1"></i> Chưa chính xác';
        btn.appendChild(pill);
    } else if (!isSelected && isCorrect) {
        // Highlight the correct answer so student learns it immediately!
        btn.classList.add('highlight-correct');
        const pill = document.createElement('span');
        pill.className = 'feedback-status-pill correct';
        pill.innerHTML = '<i class="fas fa-check-circle mr-1"></i> Đáp án đúng';
        btn.appendChild(pill);
    } else {
        btn.classList.add('faded-out');
    }
}

function onNextClicked() {
    if (currentIndex < QUIZ_DATA.questions.length - 1) {
        loadQuestion(currentIndex + 1);
    } else {
        showResults();
    }
}

let savedQuestionsSet = new Set(QUIZ_DATA.savedQuestionIds || []);
try {
    const cachedSaved = JSON.parse(localStorage.getItem('kahootify_saved_review_' + QUIZ_DATA.pin) || '[]');
    if (Array.isArray(cachedSaved)) {
        cachedSaved.forEach(id => savedQuestionsSet.add(Number(id)));
    }
} catch (e) {}

function updateSavedListUI() {
    const countEl = document.getElementById('resultsSavedReviewCount');
    const emptyEl = document.getElementById('resultsSavedReviewEmpty');
    const listEl = document.getElementById('resultsSavedReviewList');
    if (!countEl || !emptyEl || !listEl) return;

    listEl.innerHTML = '';
    let savedCount = 0;

    QUIZ_DATA.questions.forEach((q, idx) => {
        if (savedQuestionsSet.has(Number(q.id_question))) {
            savedCount++;
            const li = document.createElement('li');
            li.dataset.savedQuestion = String(q.id_question);
            const a = document.createElement('a');
            a.href = '#review-question-' + q.id_question;
            const strong = document.createElement('strong');
            strong.textContent = `Question ${idx + 1}: `;
            a.appendChild(strong);
            a.appendChild(document.createTextNode(q.question_text || q.text || ''));
            li.appendChild(a);
            listEl.appendChild(li);
        }
    });

    countEl.textContent = String(savedCount);
    emptyEl.hidden = (savedCount > 0);
}

function toggleSaveQuestion(btn) {
    const questionId = Number(btn.dataset.questionId);
    const questionNumber = btn.dataset.questionNumber;
    const isSaved = btn.getAttribute('aria-pressed') === 'true';
    const shouldSave = !isSaved;

    btn.setAttribute('aria-pressed', shouldSave ? 'true' : 'false');
    btn.classList.toggle('is-saved', shouldSave);
    const icon = btn.querySelector('i');
    if (icon) icon.className = (shouldSave ? 'fas' : 'far') + ' fa-bookmark';
    const label = btn.querySelector('span');
    if (label) label.textContent = shouldSave ? 'Saved' : 'Save for review';

    if (shouldSave) {
        savedQuestionsSet.add(questionId);
    } else {
        savedQuestionsSet.delete(questionId);
    }

    try {
        localStorage.setItem('kahootify_saved_review_' + QUIZ_DATA.pin, JSON.stringify(Array.from(savedQuestionsSet)));
    } catch (e) {}

    updateSavedListUI();

    if (QUIZ_DATA.isLoggedIn) {
        fetch('<?= BASE_URL ?>modules/api/toggle_saved_review_question.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id_question: questionId,
                saved: shouldSave
            })
        }).catch(err => console.error('Save error', err));
    }
}

function showResults() {
    document.getElementById('practiceScreen').style.display = 'none';
    document.getElementById('resultsScreen').style.display = 'block';

    const total = QUIZ_DATA.questions.length;
    let correctCount = 0;
    let answeredCount = 0;

    answersState.forEach(st => {
        if (st !== null) {
            answeredCount++;
            if (st.isCorrect) correctCount++;
        }
    });

    const percent = total > 0 ? Math.round((correctCount / total) * 100) : 0;
    document.getElementById('resultsScoreText').textContent = `${correctCount} / ${total} Đúng (${percent}%)`;
    document.getElementById('resultsAnsweredCount').textContent = `${answeredCount} answered`;

    updateSavedListUI();

    const container = document.getElementById('resultsReviewList');
    container.innerHTML = '';

    const symbols = ['▲', '◆', '●', '■'];

    QUIZ_DATA.questions.forEach((q, idx) => {
        const state = answersState[idx];
        const isAnswered = (state !== null);
        const isCorrect = isAnswered ? Boolean(state.isCorrect) : false;
        const qNum = idx + 1;
        const qId = Number(q.id_question);
        const isSaved = savedQuestionsSet.has(qId);

        const card = document.createElement('article');
        card.className = 'results-review-question';
        card.id = `review-question-${qId}`;

        let outcomeClass = 'is-incorrect';
        let outcomeText = 'Incorrect';
        if (isAnswered && isCorrect) {
            outcomeClass = 'is-correct';
            outcomeText = 'Correct';
        } else if (!isAnswered) {
            outcomeClass = 'is-incorrect';
            outcomeText = 'Unanswered';
        }

        let cardHtml = `
            <div class="results-review-question-heading">
                <span class="results-review-number">Question ${qNum}</span>
                <div class="results-review-question-actions">
                    <span class="results-review-outcome ${outcomeClass}">${outcomeText}</span>
                    <button class="results-review-save ${isSaved ? 'is-saved' : ''}" type="button"
                        data-question-id="${qId}"
                        data-question-number="${qNum}"
                        aria-pressed="${isSaved ? 'true' : 'false'}">
                        <i class="${isSaved ? 'fas' : 'far'} fa-bookmark" aria-hidden="true"></i>
                        <span>${isSaved ? 'Saved' : 'Save for review'}</span>
                    </button>
                </div>
            </div>
            <h3>${escapeHtml(q.question_text || q.text || '')}</h3>
        `;

        if (q.image) {
            const mediaSrc = (q.image.startsWith('http://') || q.image.startsWith('https://')) ? q.image : ('<?= BASE_URL ?>' + q.image.replace(/^\//, ''));
            cardHtml += `
                <div class="results-review-media">
                    <img src="${escapeHtml(mediaSrc)}" alt="Question illustration" loading="lazy">
                </div>
            `;
        }

        if (q.question_type === 'text') {
            const correctAns = (q.answers && q.answers[0]) ? (q.answers[0].answer_text || q.answers[0].text || '') : '';
            const userAns = state ? (state.userTextAnswer || '') : '';

            cardHtml += '<div class="results-review-answers" style="grid-template-columns: repeat(2, minmax(0, 1fr));">';
            if (isAnswered && isCorrect) {
                cardHtml += `
                    <div class="results-review-answer is-correct-answer is-player-choice">
                        <span class="results-review-answer-symbol" style="background:#26890c;"><i class="fas fa-check"></i></span>
                        <div class="results-review-answer-content">
                            <span>${escapeHtml(correctAns)}</span>
                        </div>
                        <div class="results-review-answer-labels">
                            <span class="results-review-choice-label">Your answer</span>
                            <span class="results-review-correct-label">Correct answer</span>
                        </div>
                    </div>
                `;
            } else {
                if (isAnswered) {
                    cardHtml += `
                        <div class="results-review-answer is-player-choice">
                            <span class="results-review-answer-symbol" style="background:#e21b3c;"><i class="fas fa-times"></i></span>
                            <div class="results-review-answer-content">
                                <span>${escapeHtml(userAns || '(Trống)')}</span>
                            </div>
                            <div class="results-review-answer-labels">
                                <span class="results-review-choice-label" style="background:#ffebee; color:#b31630;">Your answer</span>
                            </div>
                        </div>
                    `;
                }
                cardHtml += `
                    <div class="results-review-answer is-correct-answer">
                        <span class="results-review-answer-symbol" style="background:#26890c;"><i class="fas fa-check"></i></span>
                        <div class="results-review-answer-content">
                            <span>${escapeHtml(correctAns)}</span>
                        </div>
                        <div class="results-review-answer-labels">
                            <span class="results-review-correct-label">Correct answer</span>
                        </div>
                    </div>
                `;
            }
            cardHtml += '</div>';
        } else {
            cardHtml += '<div class="results-review-answers">';
            (q.answers || []).forEach((ans, aIdx) => {
                const isCorrectAnswer = (ans.is_correct === true || ans.is_correct === 1);
                const isPlayerChoice = isAnswered && (ans.id_answer === state.selectedAnswerId);
                const symbol = symbols[aIdx % 4];

                let ansClass = 'results-review-answer';
                if (isCorrectAnswer) ansClass += ' is-correct-answer';
                if (isPlayerChoice) ansClass += ' is-player-choice';

                const ansText = ans.answer_text || ans.text || '';
                let ansImgHtml = '';
                if (ans.image) {
                    const aImgSrc = (ans.image.startsWith('http://') || ans.image.startsWith('https://')) ? ans.image : ('<?= BASE_URL ?>' + ans.image.replace(/^\//, ''));
                    ansImgHtml = `<img src="${escapeHtml(aImgSrc)}" alt="Answer illustration" loading="lazy">`;
                }

                cardHtml += `
                    <div class="${ansClass}">
                        <span class="results-review-answer-symbol">${symbol}</span>
                        <div class="results-review-answer-content">
                            ${ansText ? `<span>${escapeHtml(ansText)}</span>` : ''}
                            ${ansImgHtml}
                        </div>
                        <div class="results-review-answer-labels">
                            ${isPlayerChoice ? '<span class="results-review-choice-label">Your answer</span>' : ''}
                            ${isCorrectAnswer ? '<span class="results-review-correct-label">Correct answer</span>' : ''}
                        </div>
                    </div>
                `;
            });
            cardHtml += '</div>';
        }

        card.innerHTML = cardHtml;

        const saveBtn = card.querySelector('.results-review-save');
        if (saveBtn) {
            saveBtn.addEventListener('click', function () {
                toggleSaveQuestion(saveBtn);
            });
        }

        container.appendChild(card);
    });

    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function restartReview() {
    answersState = new Array(QUIZ_DATA.questions.length).fill(null);
    document.getElementById('resultsScreen').style.display = 'none';
    document.getElementById('practiceScreen').style.display = 'flex';
    renderPaletteGrid();
    loadQuestion(0);
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
