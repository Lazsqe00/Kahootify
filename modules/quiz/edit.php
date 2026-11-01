<?php

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/modes/QuestionModeRegistry.php';

$creatorId = intval($_SESSION['id_creator']);
$quizId    = intval($_GET['id'] ?? 0);

$quiz = db_fetch_one("SELECT * FROM quiz WHERE id_quiz = :q AND id_creator = :c", [':q' => $quizId, ':c' => $creatorId]);
if (!$quiz) {
    set_flash_message('danger', 'Quiz not found or access denied.');
    redirect('modules/quiz/list.php');
}

function saveUploadedFile($fileKey) {
    if (!isset($_FILES[$fileKey]) || $_FILES[$fileKey]['error'] !== UPLOAD_ERR_OK) return null;
    $f = $_FILES[$fileKey];
    $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'video/mp4', 'video/webm'];
    if (!in_array($f['type'], $allowed)) return null;
    $ext  = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    $name = 'q_' . uniqid() . '_' . time() . '.' . $ext;
    $dir  = __DIR__ . '/../../assets/img/questions/';
    return move_uploaded_file($f['tmp_name'], $dir . $name) ? 'assets/img/questions/' . $name : null;
}

function detectMediaType($fileKey) {
    if (!isset($_FILES[$fileKey]) || $_FILES[$fileKey]['error'] !== UPLOAD_ERR_OK) return null;
    $mime = $_FILES[$fileKey]['type'];
    if (strpos($mime, 'image/') === 0) return 'image';
    if (strpos($mime, 'video/') === 0) return 'video';
    return null;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'apply_mode_to_all') {
        $targetMode = trim($_POST['target_mode'] ?? 'normal');
        $modeObj = QuestionModeRegistry::get($targetMode);
        $supportedTypes = $modeObj->getSupportedQuestionTypes();
        $inTypes = "'" . implode("','", array_map('addslashes', $supportedTypes)) . "'";
        $cfg = ($targetMode === 'wager') ? json_encode($modeObj->getDefaultConfig()) : null;

        db_execute(
            "UPDATE questions SET mode = :m, mode_config = :mc WHERE id_quiz = :qz AND question_type IN ($inTypes)",
            [':m' => $targetMode, ':mc' => $cfg, ':qz' => $quizId]
        );
        set_flash_message('success', "Đã áp dụng chế độ '{$modeObj->getLabel()}' cho tất cả các câu hỏi hợp lệ trong bài!");
        redirect('modules/quiz/edit.php?id=' . $quizId);
    }

    if (in_array($action, ['add_question', 'update_question'])) {
        $qText = trim($_POST['question_text'] ?? '');
        $qType = ($_POST['question_type'] ?? '') === 'text' ? 'text' : 'multiple_choice';
        $tl    = intval($_POST['time_limit'] ?? 20);
        if (!in_array($tl, [10, 20, 30, 60])) $tl = 20;
        $pts   = intval($_POST['points'] ?? 1000);
        if (!in_array($pts, [500, 1000, 2000])) $pts = 1000;

        $qMode = trim($_POST['question_mode'] ?? 'normal');
        $modeObj = QuestionModeRegistry::get($qMode);
        if (!$modeObj->isQuestionTypeSupported($qType)) {
            $qMode = 'normal';
            $modeObj = QuestionModeRegistry::get('normal');
        }

        $modeConfig = null;
        if ($qMode === 'wager') {
            $wTime = intval($_POST['wager_time_sec'] ?? 10);
            $spBonus = !empty($_POST['wager_speed_bonus']);
            $modeConfig = json_encode($modeObj->validateConfig([
                'wagerTimeSec' => $wTime,
                'speedBonusEnabled' => $spBonus,
            ]));
        }

        if (empty($qText)) {
            set_flash_message('danger', 'Question text is required.');
            redirect('modules/quiz/edit.php?id=' . $quizId);
        }

        if ($qType === 'text') {
            $ta = trim($_POST['text_answer'] ?? '');
            if (empty($ta)) {
                set_flash_message('danger', 'Please enter the correct answer for the text question.');
                redirect('modules/quiz/edit.php?id=' . $quizId);
            }
        }

        $oldImg  = null;
        $oldMt   = null;
        $editQId = null;

        if ($action === 'update_question') {
            $editQId = intval($_POST['editing_question_id'] ?? 0);
            $oldQ    = db_fetch_one("SELECT * FROM questions WHERE id_question = :q AND id_quiz = :qz", [':q' => $editQId, ':qz' => $quizId]);
            if (!$oldQ) {
                set_flash_message('danger', 'Question not found.');
                redirect('modules/quiz/edit.php?id=' . $quizId);
            }
            $oldImg = $oldQ['image'];
            $oldMt  = $oldQ['media_type'] ?? null;
        }

        $newFile = saveUploadedFile('question_media');
        $newMt   = detectMediaType('question_media');

        if ($newFile !== null) {
            $imgCol = $newFile;
            $mtCol  = $newMt;
        } elseif (($_POST['question_media_remove'] ?? '0') === '1') {
            $imgCol = null;
            $mtCol  = null;
        } else {
            $imgCol = $oldImg;
            $mtCol  = $oldMt;
        }

        if ($action === 'add_question') {
            $maxSo = db_fetch_one("SELECT COALESCE(MAX(sort_order), 0) AS mo FROM questions WHERE id_quiz = :qz", [':qz' => $quizId]);
            $so    = intval($maxSo['mo']) + 1;
            $qId   = db_insert(
                "INSERT INTO questions (id_quiz, text, points, time_limit, image, question_type, media_type, sort_order, mode, mode_config) VALUES (:qz,:t,:p,:tl,:img,:qt,:mt,:so,:m,:mc)",
                [':qz'=>$quizId,':t'=>$qText,':p'=>$pts,':tl'=>$tl,':img'=>$imgCol,':qt'=>$qType,':mt'=>$mtCol,':so'=>$so,':m'=>$qMode,':mc'=>$modeConfig]
            );
        } else {
            $qId = $editQId;
            db_execute(
                "UPDATE questions SET text=:t, points=:p, time_limit=:tl, image=:img, question_type=:qt, media_type=:mt, mode=:m, mode_config=:mc WHERE id_question=:qid AND id_quiz=:qz",
                [':t'=>$qText,':p'=>$pts,':tl'=>$tl,':img'=>$imgCol,':qt'=>$qType,':mt'=>$mtCol,':m'=>$qMode,':mc'=>$modeConfig,':qid'=>$qId,':qz'=>$quizId]
            );
            db_execute("DELETE FROM answers WHERE id_question = :qid", [':qid' => $qId]);
        }

        $correctIdxs = array_map('intval', $_POST['correct_answer_idx'] ?? []);
        $useImgArr   = $_POST['answer_use_image']  ?? [];
        $keepImgArr  = $_POST['answer_keep_image'] ?? [];
        $textArr     = $_POST['answer_text']       ?? [];

        if ($qType === 'text') {
            $ta = trim($_POST['text_answer'] ?? '');
            db_insert("INSERT INTO answers (id_question, is_correct, text) VALUES (:qid, 1, :t)", [':qid' => $qId, ':t' => $ta]);
        } else {
            for ($i = 0; $i < 4; $i++) {
                $useImg   = ($useImgArr[$i] ?? '0') === '1';
                $txt      = trim($textArr[$i] ?? '');
                $newAImg  = saveUploadedFile('answer_image_file_' . $i);
                $keepImg  = trim($keepImgArr[$i] ?? '');
                if ($useImg) {
                    $finalImg = $newAImg ?: ($keepImg ?: null);
                    if ($finalImg !== null) {
                        $corr = in_array($i, $correctIdxs) ? 1 : 0;
                        db_insert("INSERT INTO answers (id_question, is_correct, text, image_path) VALUES (:qid,:c,'',:img)", [':qid'=>$qId,':c'=>$corr,':img'=>$finalImg]);
                    }
                } elseif ($txt !== '') {
                    $corr = in_array($i, $correctIdxs) ? 1 : 0;
                    db_insert("INSERT INTO answers (id_question, is_correct, text, image_path) VALUES (:qid,:c,:t,NULL)", [':qid'=>$qId,':c'=>$corr,':t'=>$txt]);
                }
            }
        }

        $_SESSION['last_question_type_' . $quizId] = $qType;
        $_SESSION['last_question_mode_' . $quizId] = $qMode;
        $_SESSION['last_time_limit_' . $quizId]    = $tl;
        $_SESSION['last_points_' . $quizId]        = $pts;

        set_flash_message('success', $action === 'add_question' ? 'Question added!' : 'Question updated!');
        redirect('modules/quiz/edit.php?id=' . $quizId);
    }
}

if (isset($_GET['delete_question'])) {
    $dqId = intval($_GET['delete_question']);
    db_execute("DELETE FROM questions WHERE id_question = :q AND id_quiz = :qz", [':q' => $dqId, ':qz' => $quizId]);
    set_flash_message('info', 'Question deleted.');
    redirect('modules/quiz/edit.php?id=' . $quizId);
}

$questions = db_fetch_all("SELECT * FROM questions WHERE id_quiz = :q ORDER BY sort_order ASC, id_question ASC", [':q' => $quizId]);
foreach ($questions as &$q) {
    $q['answers'] = db_fetch_all("SELECT * FROM answers WHERE id_question = :qid ORDER BY id_answer ASC", [':qid' => $q['id_question']]);
}
unset($q);

$hasWagerInQuiz = false;
foreach ($questions as $qq) {
    if (($qq['mode'] ?? 'normal') === 'wager') {
        $hasWagerInQuiz = true;
        break;
    }
}

$quizMode = $quiz['mode'] ?? 'normal';
if (isset($_GET['initial_mode']) && QuestionModeRegistry::has($_GET['initial_mode'])) {
    $quizMode = $_GET['initial_mode'];
    if (($quiz['mode'] ?? 'normal') !== $quizMode) {
        db_execute("UPDATE quiz SET mode = :m WHERE id_quiz = :q", [':m' => $quizMode, ':q' => $quizId]);
        $quiz['mode'] = $quizMode;
    }
}

$defaultQMode = $quizMode;
$_SESSION['last_question_mode_' . $quizId] = $defaultQMode;
$defaultQType = $_SESSION['last_question_type_' . $quizId]
    ?? (!empty($questions) ? end($questions)['question_type'] : 'multiple_choice');
$defaultTimeLimit = $_SESSION['last_time_limit_' . $quizId]
    ?? (!empty($questions) ? end($questions)['time_limit'] : 20);
$defaultPoints = $_SESSION['last_points_' . $quizId]
    ?? (!empty($questions) ? end($questions)['points'] : 1000);

$pageTitle      = 'Edit Quiz: ' . htmlspecialchars($quiz['name']);
$extraCss       = BASE_URL . 'assets/css/edit-quiz.css?v=' . time();
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';

$flash = get_flash_message();
?>

<div class="editor-page-wrapper">

    <div class="editor-topbar">
        <a href="<?= BASE_URL ?>modules/quiz/list.php" class="btn btn-sm btn-outline-secondary font-weight-bold">
            <i class="fas fa-chevron-left mr-1"></i> My Kahoots
        </a>
        <div class="quiz-title-wrapper">
            <span id="quizTitleDisplay"><?= htmlspecialchars($quiz['name']) ?></span>
            <input type="text" id="quizTitleInput" value="<?= htmlspecialchars($quiz['name']) ?>" maxlength="100">
            <i class="fas fa-pencil-alt title-edit-icon" id="editTitleIcon" title="Edit title"></i>
            <?php if ($defaultQMode === 'wager' || $hasWagerInQuiz): ?>
                <span class="badge badge-warning text-dark font-weight-bold ml-2 px-2 py-1 shadow-sm" style="font-size: 11px;" title="Bài trắc nghiệm này theo chế độ Cược điểm">
                    🎲 Chế độ: Cược điểm (Wager)
                </span>
            <?php endif; ?>
        </div>
        
        <div class="d-flex align-items-center" style="gap: 12px;">
            <?php if ($flash): ?>
            <div class="alert alert-<?= htmlspecialchars($flash['type']) ?> alert-auto-dismiss alert-dismissible fade show mb-0 py-1 px-3 d-flex align-items-center shadow-sm"
                 role="alert"
                 style="font-size: 13px; font-weight: 700; border-radius: 8px; border: 1px solid #c3e6cb; background: #e8f5e9; color: #1b5e20; height: 34px;">
                <i class="fas fa-check-circle mr-1 text-success" style="font-size: 14px;"></i>
                <span><?= htmlspecialchars($flash['message']) ?></span>
                <button type="button" class="close ml-2 text-success" data-dismiss="alert" style="font-size: 16px; line-height: 1; opacity: 0.7; padding: 0;" aria-label="Close">
                    <span>&times;</span>
                </button>
            </div>
            <?php endif; ?>

            <a href="<?= BASE_URL ?>modules/host/create_room.php?id_quiz=<?= $quiz['id_quiz'] ?>" class="btn btn-sm font-weight-bold" style="background:#46178f;color:#fff;">
                <i class="fas fa-play mr-1"></i> Host Game Now
            </a>
        </div>
    </div>

    <div class="editor-layout">

        <div class="q-list-panel">
            <div class="q-list-scroll">
                <ul id="questionListSortable" class="list-unstyled m-0 p-0">
                    <?php if (empty($questions)): ?>
                    <li class="q-list-empty">
                        <i class="fas fa-question-circle fa-2x d-block mb-2"></i>
                        No questions yet
                    </li>
                    <?php endif; ?>
                    <?php foreach ($questions as $idx => $q): 
                        $curMode = $q['mode'] ?? 'normal';
                    ?>
                    <li class="q-list-item" data-id="<?= $q['id_question'] ?>" data-mode="<?= htmlspecialchars($curMode) ?>">
                        <span class="drag-handle"><i class="fas fa-grip-vertical"></i></span>
                        <div class="q-thumb-content">
                            <div class="q-thumb-num">Q<?= $idx + 1 ?></div>
                            <div class="q-thumb-text" title="<?= htmlspecialchars($q['text'] ?? '') ?>">
                                <?= htmlspecialchars(mb_strimwidth($q['text'] ?? '', 0, 36, '…')) ?>
                            </div>
                            <div class="q-thumb-meta">
                                <?= intval($q['time_limit']) ?>s &bull; <?= number_format(intval($q['points'])) ?> pts
                                <?php if ($curMode === 'wager'): ?>
                                    <span class="badge badge-warning text-dark font-weight-bold ml-1" style="font-size:10px;">🎲 Cược</span>
                                <?php else: ?>
                                    <span class="badge badge-light text-secondary font-weight-normal ml-1" style="font-size:10px;">⚡ Thường</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="q-list-actions">
                            <button type="button" class="btn-icon" onclick="loadQuestionForEdit(<?= $q['id_question'] ?>)" title="Edit">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button type="button" class="btn-icon" onclick="duplicateQuestion(<?= $q['id_question'] ?>)" title="Duplicate">
                                <i class="fas fa-copy"></i>
                            </button>
                            <a href="?id=<?= $quizId ?>&delete_question=<?= $q['id_question'] ?>"
                               class="btn-icon btn-delete"
                               onclick="return confirm('Delete this question?')"
                               title="Delete">
                                <i class="fas fa-trash-alt"></i>
                            </a>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div class="q-list-footer">
                <button type="button" class="btn-add-question" id="btnAddNewQuestion">
                    <i class="fas fa-plus"></i> New question
                </button>
                <?php if (!empty($questions)): ?>
                <button type="button" class="btn btn-sm btn-outline-light btn-block mt-2 font-weight-bold" data-toggle="modal" data-target="#bulkModeModal" style="border-radius: 8px; font-size: 11px;">
                    <i class="fas fa-layer-group mr-1"></i> Áp dụng chế độ hàng loạt
                </button>
                <?php endif; ?>
            </div>
        </div>

        <div class="q-canvas">
            <div class="canvas-header">
                <span id="canvasModeLabel" class="canvas-mode-label">New Question</span>
                <span style="color:rgba(255,255,255,0.65);font-size:12px;"><?= count($questions) ?> question<?= count($questions) !== 1 ? 's' : '' ?></span>
            </div>
            <form id="questionForm" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action"               id="formAction"              value="add_question">
                <input type="hidden" name="editing_question_id"  id="editingQuestionId"       value="">
                <input type="hidden" name="question_type"        id="hiddenQuestionType"      value="<?= htmlspecialchars($defaultQType) ?>">
                <input type="hidden" name="question_mode"        id="hiddenQuestionMode"      value="normal">
                <input type="hidden" name="wager_time_sec"       id="hiddenWagerTimeSec"       value="10">
                <input type="hidden" name="wager_speed_bonus"    id="hiddenWagerSpeedBonus"    value="0">
                <input type="hidden" name="time_limit"           id="hiddenTimeLimit"         value="<?= htmlspecialchars($defaultTimeLimit) ?>">
                <input type="hidden" name="points"               id="hiddenPoints"            value="<?= htmlspecialchars($defaultPoints) ?>">
                <input type="hidden" name="question_media_remove" id="questionMediaRemoveFlag" value="0">

                <div class="canvas-body">

                    <textarea id="questionTextInput" name="question_text" rows="2"
                              placeholder="Start typing your question…"></textarea>

                    <div class="media-upload-area" id="questionMediaArea">
                        <input type="file" name="question_media" id="questionMediaFile" accept="image/*,video/*">
                        <div id="questionMediaPlaceholder">
                            <i class="fas fa-photo-video fa-2x d-block mb-1" style="color:#bbb;"></i>
                            <span style="font-size:13px;color:#aaa;">Click or drag to add image / video</span>
                        </div>
                        <div id="questionMediaPreview" style="display:none;"></div>
                        <button type="button" class="media-remove-btn" id="questionMediaRemoveBtn" style="display:none;">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>

                    <div id="answersMultipleChoice" style="<?= $defaultQType === 'text' ? 'display: none;' : '' ?>">
                        <div class="answers-grid">
                            <?php
                            $aColors   = ['color-red', 'color-blue', 'color-yellow', 'color-green'];
                            $aIcons    = ['🔺', '🔷', '🟡', '🟩'];
                            $aLabels   = ['Answer 1', 'Answer 2', 'Answer 3', 'Answer 4'];
                            $aOptional = [false, false, true, true];
                            for ($i = 0; $i < 4; $i++):
                            ?>
                            <div class="answer-tile" id="answerTile_<?= $i ?>">
                                <div class="answer-tile-header <?= $aColors[$i] ?>">
                                    <span><?= $aIcons[$i] ?></span>
                                    <span style="flex:1;font-size:13px;">
                                        <?= $aLabels[$i] ?>
                                        <?php if ($aOptional[$i]): ?>
                                        <span style="opacity:0.75;font-weight:400;font-size:11px;">(optional)</span>
                                        <?php endif; ?>
                                    </span>
                                    <input type="checkbox" class="answer-correct-cb"
                                           name="correct_answer_idx[]"
                                           value="<?= $i ?>"
                                           id="answerCorrect_<?= $i ?>"
                                           title="Mark as correct">
                                </div>
                                <div class="answer-tile-body">
                                    <input type="hidden" name="answer_use_image[<?= $i ?>]"  id="answerUseImage_<?= $i ?>"  value="0">
                                    <input type="hidden" name="answer_keep_image[<?= $i ?>]" id="answerKeepImage_<?= $i ?>" value="">
                                    <input type="text" class="answer-text-input"
                                           name="answer_text[<?= $i ?>]"
                                           id="answerText_<?= $i ?>"
                                           placeholder="Type answer…">
                                    <div class="answer-img-area" id="answerImgArea_<?= $i ?>">
                                        <div id="answerImgPreview_<?= $i ?>" class="answer-img-placeholder">
                                            <i class="fas fa-image" style="color:#ccc;"></i>
                                            <span>Click to upload</span>
                                        </div>
                                        <input type="file" name="answer_image_file_<?= $i ?>"
                                               id="answerImgFile_<?= $i ?>" accept="image/*">
                                    </div>
                                    <button type="button" class="answer-media-toggle" id="answerToggle_<?= $i ?>" data-index="<?= $i ?>">
                                        🖼 Use Image
                                    </button>
                                </div>
                            </div>
                            <?php endfor; ?>
                        </div>
                    </div>

                    <div id="answersText" class="text-ans-section" style="<?= $defaultQType === 'text' ? 'display: block;' : 'display: none;' ?>">
                        <div class="prop-label" style="margin-bottom:8px;">Correct Answer</div>
                        <input type="text" class="text-ans-input" name="text_answer" id="textAnswerInput"
                               placeholder="Type the correct answer…" autocomplete="off">
                        <div class="text-ans-underscores" id="textAnswerUnderscores"></div>
                    </div>

                </div>

                <div class="canvas-footer">
                    <button type="button" class="btn-cancel" id="btnCancelEdit" style="display:none;">
                        <i class="fas fa-times mr-1"></i> Cancel
                    </button>
                    <button type="submit" class="btn-save" id="btnSaveQuestion">
                        <i class="fas fa-plus mr-1"></i> Save &amp; Add Question
                    </button>
                </div>
            </form>
        </div>

        <div class="props-panel">
            <div id="modeSettingsPanel" class="prop-group" style="<?= $defaultQMode === 'wager' ? 'display:block;' : 'display:none;' ?> background: #fff8e1; border: 1px solid #ffe082; border-radius: 8px; padding: 10px;">
                <div class="prop-label mb-2 text-dark font-weight-bold" style="font-size: 12px;">
                    <i class="fas fa-sliders-h mr-1 text-warning"></i> Cài đặt Cược điểm
                </div>
                <div class="form-group mb-2">
                    <label class="small text-muted font-weight-bold mb-1 d-block" style="font-size: 11px;">Thời gian cược</label>
                    <select class="prop-select" id="propWagerTimeSec" style="height: 32px; font-size: 12px;">
                        <option value="5">5 giây</option>
                        <option value="10" selected>10 giây (Mặc định)</option>
                        <option value="15">15 giây</option>
                        <option value="20">20 giây</option>
                        <option value="30">30 giây</option>
                    </select>
                </div>
                <div class="custom-control custom-switch mt-2">
                    <input type="checkbox" class="custom-control-input" id="propWagerSpeedBonus">
                    <label class="custom-control-label small font-weight-bold text-dark" for="propWagerSpeedBonus" style="font-size: 11px;">
                        Bật điểm tốc độ
                    </label>
                </div>
            </div>

            <div class="prop-group">
                <div class="prop-label">Question Type</div>
                <select class="prop-select" id="propQuestionType">
                    <option value="multiple_choice" <?= $defaultQType === 'multiple_choice' ? 'selected' : '' ?>>Multiple Choice</option>
                    <option value="text" <?= $defaultQType === 'text' ? 'selected' : '' ?>>Text Answer</option>
                </select>
            </div>
            <div class="prop-group">
                <div class="prop-label">Time Limit</div>
                <select class="prop-select" id="propTimeLimit">
                    <option value="10" <?= $defaultTimeLimit == 10 ? 'selected' : '' ?>>10 seconds</option>
                    <option value="20" <?= $defaultTimeLimit == 20 ? 'selected' : '' ?>>20 seconds</option>
                    <option value="30" <?= $defaultTimeLimit == 30 ? 'selected' : '' ?>>30 seconds</option>
                    <option value="60" <?= $defaultTimeLimit == 60 ? 'selected' : '' ?>>60 seconds</option>
                </select>
            </div>
            <div class="prop-group">
                <div class="prop-label">Points</div>
                <select class="prop-select" id="propPoints">
                    <option value="500" <?= $defaultPoints == 500 ? 'selected' : '' ?>>500</option>
                    <option value="1000" <?= $defaultPoints == 1000 ? 'selected' : '' ?>>1,000</option>
                    <option value="2000" <?= $defaultPoints == 2000 ? 'selected' : '' ?>>2,000</option>
                </select>
            </div>
        </div>

    </div>
</div>

<!-- Modal Bulk Apply Mode -->
<div class="modal fade" id="bulkModeModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content shadow-lg border-0" style="border-radius: 14px;">
            <form method="POST">
                <input type="hidden" name="action" value="apply_mode_to_all">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title font-weight-bold text-dark"><i class="fas fa-layer-group text-primary mr-2"></i>Áp dụng chế độ hàng loạt</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body py-3">
                    <p class="text-muted small">Chọn chế độ bạn muốn áp dụng cho <strong>tất cả câu hỏi</strong> trong bài trắc nghiệm này:</p>
                    <div class="form-group mb-0">
                        <label class="font-weight-bold small text-dark">Chế độ mục tiêu:</label>
                        <select name="target_mode" class="form-control font-weight-bold">
                            <?php foreach (QuestionModeRegistry::getAll() as $m): ?>
                            <option value="<?= htmlspecialchars($m->getId()) ?>">
                                <?= $m->getIcon() ?> <?= htmlspecialchars($m->getLabel()) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-sm btn-secondary font-weight-bold px-3" data-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-sm btn-primary font-weight-bold px-4">Áp dụng ngay</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
window.KAHOOT_EDIT = {
    quizId:      <?= intval($quizId) ?>,
    baseUrl:     <?= json_encode(BASE_URL) ?>,
    defaultMode: <?= json_encode($defaultQMode) ?>,
    questions:   <?= json_encode(array_values($questions), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
    modes:     <?= json_encode(array_values(array_map(function($m) {
        return [
            'id' => $m->getId(),
            'label' => $m->getLabel(),
            'description' => $m->getDescription(),
            'icon' => $m->getIcon(),
            'supportedQuestionTypes' => $m->getSupportedQuestionTypes(),
            'defaultConfig' => $m->getDefaultConfig(),
        ];
    }, QuestionModeRegistry::getAll())), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>
};
</script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script src="<?= BASE_URL ?>assets/js/edit-quiz.js"></script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
