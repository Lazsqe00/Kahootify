<?php

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/modes/QuestionModeRegistry.php';
require_once __DIR__ . '/../../includes/TeamManager.php';
require_once __DIR__ . '/../../includes/ReviewManager.php';

$creatorId = intval($_SESSION['id_creator']);
$quizId    = intval($_GET['id'] ?? 0);

$quiz = db_fetch_one("SELECT * FROM quiz WHERE id_quiz = :q AND id_creator = :c", [':q' => $quizId, ':c' => $creatorId]);
if (!$quiz) {
    set_flash_message('danger', 'Quiz not found or access denied.');
    redirect('modules/quiz/list.php');
}
$isObstacle = (($quiz['game_type'] ?? 'classic') === 'obstacle');
$isReviewMode = !empty($quiz['is_review_mode']);
$reviewPin    = $isReviewMode ? ReviewManager::getOrGeneratePin($quizId, $quiz['review_pin'] ?? null) : ($quiz['review_pin'] ?? '');
$reviewUrl    = ReviewManager::buildReviewUrl($reviewPin);

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
        set_flash_message('success', "Applied '{$modeObj->getLabel()}' mode to all eligible questions!");
        redirect('modules/quiz/edit.php?id=' . $quizId);
    }

    if ($action === 'update_team_settings') {
        $isTeam = !empty($_POST['is_team_mode']) ? 1 : 0;
        $teamCount = max(2, min(10, intval($_POST['team_count'] ?? 2)));
        $playerLimit = max(0, intval($_POST['team_player_limit'] ?? 0));

        db_execute(
            "UPDATE quiz SET is_team_mode = :tm, team_count = :tc, team_player_limit = :tpl WHERE id_quiz = :q AND id_creator = :c",
            [
                ':tm'  => $isTeam,
                ':tc'  => $teamCount,
                ':tpl' => $playerLimit,
                ':q'   => $quizId,
                ':c'   => $creatorId
            ]
        );

        if ($isTeam) {
            ReviewManager::disableReviewMode($quizId, $creatorId);
        }

        set_flash_message('success', 'Team Mode settings saved successfully!' . ($isTeam ? ' (Review Mode has been disabled)' : ''));
        redirect('modules/quiz/edit.php?id=' . $quizId);
    }

    if ($action === 'update_review_settings') {
        if (!$isClassicQuiz) {
            set_flash_message('danger', 'Review Mode is only available in Classic Mode.');
            redirect('modules/quiz/edit.php?id=' . $quizId);
        }

        $enable = !empty($_POST['is_review_mode']) ? 1 : 0;
        if ($enable) {
            db_execute("UPDATE quiz SET is_team_mode = 0 WHERE id_quiz = :q AND id_creator = :c", [':q' => $quizId, ':c' => $creatorId]);
            $res = ReviewManager::enableReviewMode($quizId, $creatorId);
            set_flash_message('success', 'Review Mode enabled! PIN: ' . $res['pin'] . ' (Team Mode has been disabled)');
        } else {
            ReviewManager::disableReviewMode($quizId, $creatorId);
            set_flash_message('success', 'Review Mode disabled successfully.');
        }
        redirect('modules/quiz/edit.php?id=' . $quizId);
    }

    // Obstacle Puzzle settings update
    if ($action === 'update_obstacle_settings' && ($quiz['game_type'] ?? 'classic') === 'obstacle') {
        $newAns = trim($_POST['obstacle_answer'] ?? '');
        $newTp  = intval($_POST['total_pieces'] ?? 4);
        if (!in_array($newTp, [4, 6, 8, 9, 10, 12, 15, 16, 20, 25])) $newTp = 4;

        $newObsImg = null;
        if (isset($_FILES['obstacle_image']) && $_FILES['obstacle_image']['error'] === UPLOAD_ERR_OK) {
            $f = $_FILES['obstacle_image'];
            $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (in_array($f['type'], $allowed)) {
                $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
                $name = 'obs_' . uniqid() . '_' . time() . '.' . $ext;
                $dir = __DIR__ . '/../../assets/img/obstacles/';
                if (!is_dir($dir)) mkdir($dir, 0777, true);
                if (move_uploaded_file($f['tmp_name'], $dir . $name)) {
                    $newObsImg = 'assets/img/obstacles/' . $name;
                }
            }
        }

        if ($newObsImg) {
            db_execute("UPDATE quiz SET obstacle_answer = :ans, total_pieces = :tp, obstacle_image = :img WHERE id_quiz = :q", [
                ':ans' => $newAns,
                ':tp'  => $newTp,
                ':img' => $newObsImg,
                ':q'   => $quizId
            ]);
        } else {
            db_execute("UPDATE quiz SET obstacle_answer = :ans, total_pieces = :tp WHERE id_quiz = :q", [
                ':ans' => $newAns,
                ':tp'  => $newTp,
                ':q'   => $quizId
            ]);
        }
        set_flash_message('success', 'Obstacle settings updated!');
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
            $wTime = intval($_POST['wager_time_sec'] ?? 25);
            $modeConfig = json_encode($modeObj->validateConfig([
                'wagerTimeSec' => $wTime,
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
        } else {
            $rawCorrect = $_POST['correct_answer_idx'] ?? [];
            if (!is_array($rawCorrect)) {
                $rawCorrect = strlen((string)$rawCorrect) > 0 ? [$rawCorrect] : [];
            }
            $correctIdxs = array_map('intval', $rawCorrect);
            // Strictly enforce only 1 correct answer per question
            if (count($correctIdxs) > 1) {
                $correctIdxs = [reset($correctIdxs)];
            }
            if (empty($correctIdxs)) {
                set_flash_message('danger', 'Please mark exactly one answer as correct.');
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

        $pieceIndex = 0;
        if (($quiz['game_type'] ?? 'classic') === 'obstacle') {
            $totalAllowed = intval($quiz['total_pieces'] ?? 4);
            if ($action === 'add_question') {
                $curCount = db_count('questions', 'id_quiz = :qz', [':qz' => $quizId]);
                if ($curCount >= $totalAllowed) {
                    set_flash_message('danger', "This Puzzle quiz only has {$totalAllowed} tiles. You have already created all {$totalAllowed} questions!");
                    redirect('modules/quiz/edit.php?id=' . $quizId);
                }
                $existingPieces = db_fetch_all("SELECT piece_index FROM questions WHERE id_quiz = :qz", [':qz' => $quizId]);
                $used = array_map(function($row) { return intval($row['piece_index']); }, $existingPieces);
                $pieceIndex = 1;
                for ($p = 1; $p <= $totalAllowed; $p++) {
                    if (!in_array($p, $used, true)) {
                        $pieceIndex = $p;
                        break;
                    }
                }
            } else {
                $pieceIndex = intval($_POST['piece_index'] ?? ($oldQ['piece_index'] ?? 1));
                if ($pieceIndex <= 0) $pieceIndex = 1;
            }
        }

        if ($action === 'add_question') {
            $maxSo = db_fetch_one("SELECT COALESCE(MAX(sort_order), 0) AS mo FROM questions WHERE id_quiz = :qz", [':qz' => $quizId]);
            $so    = intval($maxSo['mo']) + 1;
            $qId   = db_insert(
                "INSERT INTO questions (id_quiz, text, points, time_limit, image, question_type, media_type, sort_order, mode, mode_config, piece_index) 
                 VALUES (:qz,:t,:p,:tl,:img,:qt,:mt,:so,:m,:mc,:pi)",
                [':qz'=>$quizId,':t'=>$qText,':p'=>$pts,':tl'=>$tl,':img'=>$imgCol,':qt'=>$qType,':mt'=>$mtCol,':so'=>$so,':m'=>$qMode,':mc'=>$modeConfig,':pi'=>$pieceIndex]
            );
        } else {
            $qId = $editQId;
            db_execute(
                "UPDATE questions SET text=:t, points=:p, time_limit=:tl, image=:img, question_type=:qt, media_type=:mt, mode=:m, mode_config=:mc, piece_index=:pi WHERE id_question=:qid AND id_quiz=:qz",
                [':t'=>$qText,':p'=>$pts,':tl'=>$tl,':img'=>$imgCol,':qt'=>$qType,':mt'=>$mtCol,':m'=>$qMode,':mc'=>$modeConfig,':pi'=>$pieceIndex,':qid'=>$qId,':qz'=>$quizId]
            );
            db_execute("DELETE FROM answers WHERE id_question = :qid", [':qid' => $qId]);
        }

        // $correctIdxs is already parsed and validated to contain strictly 1 correct answer
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
    if ($isObstacle) {
        $remaining = db_fetch_all("SELECT id_question FROM questions WHERE id_quiz = :qz ORDER BY sort_order ASC, id_question ASC", [':qz' => $quizId]);
        foreach ($remaining as $idx => $rq) {
            db_execute("UPDATE questions SET piece_index = :pi, sort_order = :so WHERE id_question = :qid", [
                ':pi' => $idx + 1,
                ':so' => $idx + 1,
                ':qid' => $rq['id_question']
            ]);
        }
    }
    set_flash_message('info', 'Question deleted.');
    redirect('modules/quiz/edit.php?id=' . $quizId);
}

$questions = db_fetch_all("SELECT * FROM questions WHERE id_quiz = :q ORDER BY (CASE WHEN sort_order > 0 THEN sort_order ELSE 999999 END) ASC, id_question ASC", [':q' => $quizId]);
if ($isObstacle) {
    foreach ($questions as $idx => &$q) {
        $expected = $idx + 1;
        if (intval($q['piece_index'] ?? 0) !== $expected || intval($q['sort_order'] ?? 0) !== $expected) {
            db_execute("UPDATE questions SET piece_index = :pi, sort_order = :so WHERE id_question = :qid", [
                ':pi'  => $expected,
                ':so'  => $expected,
                ':qid' => $q['id_question']
            ]);
            $q['piece_index'] = $expected;
            $q['sort_order']  = $expected;
        }
    }
    unset($q);
} else {
    $needsSortFix = false;
    foreach ($questions as $qCheck) {
        if (intval($qCheck['sort_order'] ?? 0) <= 0) {
            $needsSortFix = true;
            break;
        }
    }
    if ($needsSortFix) {
        foreach ($questions as $idx => &$qFix) {
            $expected = $idx + 1;
            db_execute("UPDATE questions SET sort_order = :so WHERE id_question = :qid", [
                ':so'  => $expected,
                ':qid' => $qFix['id_question']
            ]);
            $qFix['sort_order'] = $expected;
        }
        unset($qFix);
    }
}
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
$lastQ = !empty($questions) ? end($questions) : null;
$defaultQType = $_SESSION['last_question_type_' . $quizId]
    ?? ($lastQ['question_type'] ?? 'multiple_choice');
$defaultTimeLimit = $_SESSION['last_time_limit_' . $quizId]
    ?? ($lastQ['time_limit'] ?? 20);
$defaultPoints = $_SESSION['last_points_' . $quizId]
    ?? ($lastQ['points'] ?? 1000);

$pageTitle      = 'Edit Quiz: ' . htmlspecialchars($quiz['name']);
$extraCss       = BASE_URL . 'assets/css/edit-quiz.css?v=' . (file_exists(__DIR__ . '/../../assets/css/edit-quiz.css') ? filemtime(__DIR__ . '/../../assets/css/edit-quiz.css') : time());
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
?>

<style>
/* Kahoot 2x2 Mini Grid Answers */
.q-thumb-answers {
    margin-top: 6px;
    width: 100%;
}
.q-answers-grid-mini {
    display: grid !important;
    grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
    gap: 4px !important;
    width: 100% !important;
}
.q-mini-ans {
    display: flex !important;
    align-items: center !important;
    justify-content: space-between !important;
    gap: 3px !important;
    padding: 3px 6px !important;
    border-radius: 5px !important;
    font-size: 11px !important;
    line-height: 1.2 !important;
    min-width: 0 !important;
    box-sizing: border-box !important;
    overflow: hidden !important;
    border: 1px solid transparent !important;
}
.q-mini-shape {
    font-size: 8px !important;
    opacity: 0.85 !important;
    flex-shrink: 0 !important;
    line-height: 1 !important;
}
/* Incorrect answer: grayed out */
.q-mini-ans.is-wrong {
    background: #f1f5f9 !important;
    color: #64748b !important;
    border-color: #cbd5e1 !important;
}
.q-mini-ans.is-wrong .q-mini-ans-text,
.q-mini-ans.is-wrong .q-mini-icon,
.q-mini-ans.is-wrong .q-mini-shape {
    color: #64748b !important;
}
/* Correct answer: Highlighted dark green */
.q-mini-ans.is-correct {
    background: #16a34a !important;
    color: #ffffff !important;
    border-color: #15803d !important;
    font-weight: 700 !important;
    box-shadow: 0 1px 2px rgba(22, 163, 74, 0.25) !important;
}
.q-mini-ans.is-correct .q-mini-ans-text,
.q-mini-ans.is-correct .q-mini-icon,
.q-mini-ans.is-correct .q-mini-shape,
.q-mini-ans.is-correct .q-mini-check {
    color: #ffffff !important;
}
.q-mini-ans-text {
    flex: 1 !important;
    white-space: nowrap !important;
    overflow: hidden !important;
    text-overflow: ellipsis !important;
}
.q-mini-check {
    font-size: 10px !important;
    flex-shrink: 0 !important;
    margin-left: 2px !important;
}
.q-mini-icon {
    font-size: 10px !important;
    flex-shrink: 0 !important;
}
/* Text question mode: 1 row full width green */
.q-thumb-answers.text-mode .q-mini-ans {
    width: 100% !important;
    padding: 4px 8px !important;
}
/* Clean Question Card in List */
.q-list-item {
    display: flex;
    align-items: flex-start;
    gap: 6px;
    padding: 9px 8px;
    border-radius: 8px;
    margin-bottom: 7px;
    border: 1.5px solid #e2e8f0;
    background: #ffffff;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
    transition: all 0.18s ease;
    cursor: pointer;
    user-select: none;
}
.q-list-item:hover {
    background: #fbfaff;
    border-color: #c4b0ee;
    box-shadow: 0 2px 6px rgba(70, 23, 143, 0.08);
}
.q-list-item.active-edit {
    background: #faf8fe;
    border-color: #46178f;
    box-shadow: 0 0 0 1px #46178f, 0 3px 8px rgba(70, 23, 143, 0.12);
}
.q-thumb-content {
    flex: 1;
    min-width: 0;
    cursor: pointer;
}
.q-thumb-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 3px;
}
.q-thumb-num {
    font-size: 11px;
    font-weight: 800;
    color: #1e293b;
    letter-spacing: 0.3px;
}
.q-thumb-meta {
    font-size: 10px;
    color: #64748b;
    font-weight: 500;
}
.q-thumb-text {
    font-size: 12px;
    font-weight: 600;
    color: #334155;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    line-height: 1.35;
    margin-bottom: 2px;
}
.q-thumb-media-preview {
    width: 100%;
    height: 56px;
    border-radius: 5px;
    overflow: hidden;
    margin: 5px 0 4px;
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
    display: flex;
    align-items: center;
    justify-content: center;
}
.q-thumb-media-preview img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}
.q-thumb-media-video {
    font-size: 11px;
    font-weight: 600;
    color: #64748b;
    display: flex;
    align-items: center;
}
#modeSettingsPanel {
    background: #fffdf5 !important;
    border: 1px solid #fde68a !important;
    border-radius: 8px !important;
    padding: 10px 12px !important;
}
#modeSettingsPanel .prop-label {
    white-space: nowrap !important;
    font-size: 11px !important;
    letter-spacing: 0.3px !important;
    display: flex !important;
    align-items: center !important;
}
.props-panel {
    width: 204px !important;
}
.editor-layout {
    max-width: 1200px !important;
    gap: 14px !important;
}
/* Topbar Clean Design */
.topbar-back-btn {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 6px 12px;
    border-radius: 8px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    color: #475569 !important;
    font-family: 'Montserrat', sans-serif;
    font-size: 13px;
    font-weight: 700;
    text-decoration: none !important;
    transition: all 0.15s ease;
}
.topbar-back-btn:hover {
    background: #ede9fe;
    border-color: #c4b0ee;
    color: #46178f !important;
    transform: translateX(-1px);
}
.topbar-divider {
    width: 1px;
    height: 22px;
    background: #e2e8f0;
    margin: 0 4px;
}
.quiz-title-wrapper {
    display: flex;
    align-items: center;
    gap: 6px;
    min-width: 0;
}
#quizTitleDisplay {
    font-size: 1.15rem;
    font-weight: 800;
    color: #1e293b;
    cursor: pointer;
    border-bottom: 2px dashed transparent;
    padding: 2px 6px;
    border-radius: 4px;
    transition: all 0.15s;
    max-width: 320px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
#quizTitleDisplay:hover {
    background: #f1f5f9;
    border-bottom-color: #94a3b8;
}
.btn-topbar-puzzle {
    display: inline-flex;
    align-items: center;
    font-family: 'Montserrat', sans-serif;
    font-size: 12px;
    font-weight: 700;
    padding: 5px 10px;
    border-radius: 6px;
    background: #f8fafc;
    border: 1px solid #cbd5e1;
    color: #334155;
    cursor: pointer;
    transition: all 0.15s ease;
}
.btn-topbar-puzzle:hover {
    background: #f1f5f9;
    border-color: #94a3b8;
    color: #0f172a;
}
.topbar-settings-btn {
    border-radius: 8px;
    padding: 6px 12px;
    font-size: 13px;
    color: #475569;
    border-color: #cbd5e1;
    background: #fff;
}
.topbar-settings-btn:hover {
    background: #f8fafc;
    color: #0f172a;
    border-color: #94a3b8;
}
.btn-host-game {
    background: #46178f !important;
    border: 1px solid #381072 !important;
    color: #ffffff !important;
    font-family: 'Montserrat', sans-serif;
    font-size: 13px;
    font-weight: 700;
    padding: 6px 15px;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    gap: 7px;
    box-shadow: 0 2px 4px rgba(70, 23, 143, 0.2);
    transition: all 0.15s ease;
    text-decoration: none !important;
}
.btn-host-game:hover {
    background: #391177 !important;
    transform: translateY(-1px);
    box-shadow: 0 3px 8px rgba(70, 23, 143, 0.3);
    color: #ffffff !important;
}
.btn-host-game.disabled {
    opacity: 0.6;
    cursor: not-allowed;
    background: #64748b !important;
    border-color: #475569 !important;
    box-shadow: none !important;
    transform: none !important;
}
</style>

<?php
$flash = get_flash_message();
$isObstacle = ($quiz['game_type'] ?? 'classic') === 'obstacle';
$isClassicQuiz = (($quiz['game_type'] ?? 'classic') === 'classic') && (($quiz['mode'] ?? 'normal') === 'normal') && empty($hasWagerInQuiz);

$isTeamMode = !empty($quiz['is_team_mode']);
$isReviewEligible = ($isClassicQuiz && !$isTeamMode && !empty($quiz['is_review_mode']));
$teamCount = TeamManager::getTeamCount($quiz);
$teamLimit = TeamManager::getTeamPlayerLimit($quiz);
$availableTeams = TeamManager::getTeamsForEntity($quiz);
?>

<div class="editor-page-wrapper">

    <div class="editor-topbar">
        <div class="d-flex align-items-center" style="gap: 10px; min-width: 0;">
            <a href="<?= BASE_URL ?>modules/quiz/list.php" class="topbar-back-btn" title="Back to My Kahoots">
                <i class="fas fa-arrow-left"></i>
                <span class="d-none d-sm-inline">My Kahoots</span>
            </a>

            <div class="topbar-divider"></div>

            <div class="quiz-title-wrapper">
                <span id="quizTitleDisplay" title="Click to rename"><?= htmlspecialchars($quiz['name']) ?></span>
                <input type="text" id="quizTitleInput" value="<?= htmlspecialchars($quiz['name']) ?>" maxlength="100">
                <i class="fas fa-pencil-alt title-edit-icon" id="editTitleIcon" title="Rename quiz"></i>
            </div>

            <?php if ($isObstacle): 
                $reqPieces = intval($quiz['total_pieces'] ?? 4);
                $curQCount = count($questions);
            ?>
                <button type="button" class="btn-topbar-puzzle" data-toggle="modal" data-target="#obstacleSettingsModal" title="Secret Image & Puzzle Settings (<?= $curQCount ?>/<?= $reqPieces ?> tiles ready)">
                    <i class="fas fa-puzzle-piece text-warning mr-1"></i>
                    <span><?= $curQCount ?>/<?= $reqPieces ?> Tiles</span>
                </button>
            <?php endif; ?>
        </div>

        <div class="d-flex align-items-center" style="gap: 10px;">
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

            <div class="dropdown">
                <button class="btn btn-sm btn-outline-secondary font-weight-bold dropdown-toggle topbar-settings-btn" type="button" id="quizOptionsDropdown" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" title="Quiz Settings">
                    <i class="fas fa-cog mr-1"></i> Settings
                </button>
                <div class="dropdown-menu dropdown-menu-right shadow-sm border" aria-labelledby="quizOptionsDropdown" style="min-width: 230px; border-radius: 10px; padding: 6px;">
                    <div class="dropdown-header text-uppercase font-weight-bold text-muted px-2 py-1" style="font-size: 0.7rem; letter-spacing: 0.5px;">Game Settings</div>


                    <?php if ($isClassicQuiz): ?>
                    <button class="dropdown-item d-flex align-items-center py-2 px-2 rounded mb-1" type="button" data-toggle="modal" data-target="#reviewModeModal" onclick="$('#reviewModeModal').modal('show')">
                        <i class="fas fa-book-reader mr-2 text-warning"></i>
                        <span class="font-weight-bold">Review Mode</span>
                        <?php if ($isReviewEligible): ?>
                            <span class="badge badge-warning ml-auto font-weight-bold text-dark">ON</span>
                        <?php else: ?>
                            <span class="badge badge-light border ml-auto text-muted font-weight-bold">OFF</span>
                        <?php endif; ?>
                    </button>
                    <?php endif; ?>

                    <button class="dropdown-item d-flex align-items-center py-2 px-2 rounded" type="button" data-toggle="modal" data-target="#teamModeModal" onclick="$('#teamModeModal').modal('show')">
                        <i class="fas fa-users mr-2 text-primary"></i>
                        <span class="font-weight-bold">Team Mode</span>
                        <?php if ($isTeamMode): ?>
                            <span class="badge badge-success ml-auto font-weight-bold">ON (<?= $teamCount ?>)</span>
                        <?php else: ?>
                            <span class="badge badge-light border ml-auto text-muted font-weight-bold">OFF</span>
                        <?php endif; ?>
                    </button>
                </div>
            </div>

            <?php if ($isReviewEligible): ?>
                <button type="button" class="btn btn-sm font-weight-bold btn-warning text-dark px-3 shadow-sm mr-2" data-toggle="modal" data-target="#reviewViewModal" title="View Review Link & QR Code">
                    <i class="fas fa-qrcode mr-1"></i> Review QR
                </button>
            <?php endif; ?>

            <?php if ($isObstacle && count($questions) < intval($quiz['total_pieces'] ?? 4)): ?>
                <button type="button" class="btn btn-sm btn-host-game disabled" onclick="alert('This Puzzle quiz requires exactly <?= intval($quiz['total_pieces'] ?? 4) ?> questions for all <?= intval($quiz['total_pieces'] ?? 4) ?> tiles before hosting! Currently has <?= count($questions) ?> questions.')" title="Complete all tiles to host">
                    <i class="fas fa-play"></i> Host Game
                </button>
            <?php else: ?>
                <a href="<?= BASE_URL ?>modules/host/create_room.php?id_quiz=<?= $quiz['id_quiz'] ?>" class="btn btn-sm btn-host-game" title="Host live game session">
                    <i class="fas fa-play"></i> Host Game
                </a>
            <?php endif; ?>
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
                    <li class="q-list-item" data-id="<?= $q['id_question'] ?>" data-mode="<?= htmlspecialchars($curMode) ?>" data-piece="<?= intval($q['piece_index'] ?? ($idx + 1)) ?>">
                        <span class="drag-handle"><i class="fas fa-grip-vertical"></i></span>
                        <div class="q-thumb-content" onclick="loadQuestionForEdit(<?= $q['id_question'] ?>)">
                            <div class="q-thumb-header">
                                <span class="q-thumb-num">
                                    <?= $isObstacle ? ('Tile ' . ($idx + 1)) : ('Q' . ($idx + 1)) ?>
                                </span>
                                <span class="q-thumb-meta">
                                    <?= intval($q['time_limit']) ?>s &bull; <?= number_format(intval($q['points'])) ?> pts
                                </span>
                            </div>
                            <div class="q-thumb-text" title="<?= htmlspecialchars($q['text'] ?? '') ?>">
                                <?= htmlspecialchars(mb_strimwidth($q['text'] ?? '', 0, 38, '…')) ?>
                            </div>

                            <?php if (!empty($q['image'])): ?>
                                <?php
                                $imgSrc = (strpos($q['image'], 'http://') === 0 || strpos($q['image'], 'https://') === 0)
                                    ? $q['image']
                                    : (BASE_URL . ltrim($q['image'], '/'));
                                $isVid = ($q['media_type'] ?? '') === 'video' || preg_match('/\.(mp4|webm)$/i', $q['image']);
                                ?>
                                <div class="q-thumb-media-preview">
                                    <?php if ($isVid): ?>
                                        <div class="q-thumb-media-video"><i class="fas fa-play-circle mr-1"></i> Video</div>
                                    <?php else: ?>
                                        <img src="<?= htmlspecialchars($imgSrc) ?>" alt="Question Media" loading="lazy">
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <div class="q-thumb-media-preview">
                                    <img src="<?= BASE_URL ?>assets/img/cover.png" alt="No image" loading="lazy">
                                </div>
                            <?php endif; ?>

                            <?php
                            $qType = $q['question_type'] ?? 'multiple_choice';
                            $answers = $q['answers'] ?? [];
                            ?>
                            <?php if ($qType === 'text'): ?>
                                <?php
                                $correctText = '';
                                foreach ($answers as $a) {
                                    if (!empty($a['is_correct'])) {
                                        $correctText = $a['text'];
                                        break;
                                    }
                                }
                                if ($correctText === '' && !empty($answers)) {
                                    $correctText = $answers[0]['text'];
                                }
                                ?>
                                <?php if ($correctText !== ''): ?>
                                <div class="q-thumb-answers text-mode">
                                    <div class="q-mini-ans is-correct" title="<?= htmlspecialchars($correctText) ?>">
                                        <i class="fas fa-font q-mini-icon"></i>
                                        <span class="q-mini-ans-text"><?= htmlspecialchars(mb_strimwidth($correctText, 0, 24, '…')) ?></span>
                                        <i class="fas fa-check q-mini-check"></i>
                                    </div>
                                </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <?php
                                $validAnswers = array_filter($answers, function($a) {
                                    return !empty($a['image_path']) || (isset($a['text']) && trim($a['text']) !== '');
                                });
                                $miniShapes = ['▲', '◆', '●', '■'];
                                $shapeIdx = 0;
                                ?>
                                <?php if (!empty($validAnswers)): ?>
                                <div class="q-thumb-answers">
                                    <div class="q-answers-grid-mini">
                                        <?php foreach ($validAnswers as $a): ?>
                                            <?php
                                            $shape = $miniShapes[$shapeIdx % 4];
                                            $shapeIdx++;
                                            $isCorrect = !empty($a['is_correct']);
                                            $hasImg = !empty($a['image_path']);
                                            $ansText = trim($a['text'] ?? '');
                                            $statusClass = $isCorrect ? 'is-correct' : 'is-wrong';
                                            $titleText = $hasImg ? ('[Image]' . ($ansText !== '' ? ': ' . $ansText : '')) : $ansText;
                                            ?>
                                            <div class="q-mini-ans <?= $statusClass ?>" title="<?= htmlspecialchars($titleText) ?>">
                                                <span class="q-mini-shape"><?= $shape ?></span>
                                                <?php if ($hasImg): ?>
                                                    <i class="fas fa-image q-mini-icon q-mini-icon-img"></i>
                                                    <span class="q-mini-ans-text"><?= htmlspecialchars($ansText !== '' ? $ansText : 'Image') ?></span>
                                                <?php else: ?>
                                                    <span class="q-mini-ans-text"><?= htmlspecialchars(mb_strimwidth($ansText, 0, 11, '…')) ?></span>
                                                <?php endif; ?>
                                                <?php if ($isCorrect): ?>
                                                    <i class="fas fa-check q-mini-check"></i>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                            <?php endif; ?>
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
                <?php if ($isObstacle && count($questions) >= intval($quiz['total_pieces'] ?? 4)): ?>
                    <button type="button" class="btn-add-question" id="btnAddNewQuestion" disabled style="opacity: 0.7; cursor: not-allowed;" title="All <?= intval($quiz['total_pieces'] ?? 4) ?> questions for <?= intval($quiz['total_pieces'] ?? 4) ?> tiles created">
                        <i class="fas fa-check-circle mr-1 text-success"></i> All <?= intval($quiz['total_pieces'] ?? 4) ?> tiles ready
                    </button>
                <?php else: ?>
                    <button type="button" class="btn-add-question" id="btnAddNewQuestion">
                        <i class="fas fa-plus"></i> <?= $isObstacle ? ('Add Question for Tile ' . (count($questions) + 1)) : 'New question' ?>
                    </button>
                <?php endif; ?>
                <?php if (!empty($questions)): ?>
                <button type="button" class="btn btn-sm btn-outline-light btn-block mt-2 font-weight-bold" data-toggle="modal" data-target="#bulkModeModal" style="border-radius: 8px; font-size: 11px;">
                    <i class="fas fa-layer-group mr-1"></i> Apply Bulk Mode
                </button>
                <?php endif; ?>
            </div>
        </div>

        <div class="q-canvas">
            <div class="canvas-header">
                <span id="canvasModeLabel" class="canvas-mode-label"><?= $isObstacle ? ('Create Question for Tile ' . (count($questions) + 1)) : 'New Question' ?></span>
                <span style="color:rgba(255,255,255,0.65);font-size:12px;"><?= count($questions) ?> question<?= count($questions) !== 1 ? 's' : '' ?></span>
            </div>
            <form id="questionForm" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action"               id="formAction"              value="add_question">
                <input type="hidden" name="editing_question_id"  id="editingQuestionId"       value="">
                <input type="hidden" name="question_type"        id="hiddenQuestionType"      value="<?= htmlspecialchars($defaultQType) ?>">
                <input type="hidden" name="question_mode"        id="hiddenQuestionMode"      value="normal">
                <input type="hidden" name="wager_time_sec"       id="hiddenWagerTimeSec"       value="25">
                <input type="hidden" name="time_limit"           id="hiddenTimeLimit"         value="<?= htmlspecialchars($defaultTimeLimit) ?>">
                <input type="hidden" name="points"               id="hiddenPoints"            value="<?= htmlspecialchars($defaultPoints) ?>">
                <input type="hidden" name="question_media_remove" id="questionMediaRemoveFlag" value="0">
                <input type="hidden" name="piece_index"          id="hiddenPieceIndex"        value="<?= $isObstacle ? (count($questions) + 1) : 1 ?>">

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
                                    <input type="radio" class="answer-correct-cb"
                                           name="correct_answer_idx"
                                           value="<?= $i ?>"
                                           id="answerCorrect_<?= $i ?>"
                                           title="Mark as correct (only 1 answer)">
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
                        Save
                    </button>
                </div>
            </form>
        </div>

        <div class="props-panel">
            <div id="modeSettingsPanel" class="prop-group" style="<?= $defaultQMode === 'wager' ? 'display:block;' : 'display:none;' ?> background: #fffdf5; border: 1.5px solid #fde68a; border-radius: 8px; padding: 10px 12px;">
                <div class="prop-label mb-2 text-dark font-weight-bold d-flex align-items-center" style="font-size: 11px; letter-spacing: 0.2px;">
                    <i class="fas fa-star mr-1 text-warning"></i> Star of Hope
                </div>
                <div class="form-group mb-0">
                    <label class="small text-muted font-weight-bold mb-1 d-block" style="font-size: 10px; text-transform: uppercase; letter-spacing: 0.2px;">Duration</label>
                    <select class="prop-select" id="propWagerTimeSec" style="height: 34px; font-size: 13px;">
                        <option value="15">15s</option>
                        <option value="20">20s</option>
                        <option value="25" selected>25s (Default)</option>
                        <option value="30">30s</option>
                        <option value="45">45s</option>
                    </select>
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
                    <h5 class="modal-title font-weight-bold text-dark"><i class="fas fa-layer-group text-primary mr-2"></i>Apply Mode to All Questions</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body py-3">
                    <p class="text-muted small">Select the question mode to apply to <strong>all questions</strong> in this quiz:</p>
                    <div class="form-group mb-0">
                        <label class="font-weight-bold small text-dark">Target Mode:</label>
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
                    <button type="button" class="btn btn-sm btn-secondary font-weight-bold px-3" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary font-weight-bold px-4">Apply Now</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if ($isObstacle): ?>
<!-- MODAL OBSTACLE SETTINGS -->
<div class="modal fade" id="obstacleSettingsModal" tabindex="-1" role="dialog" aria-labelledby="obstacleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content border-0 shadow">
            <div class="modal-header text-white" style="background-color: var(--kahoot-purple);">
                <h5 class="modal-title font-weight-bold" id="obstacleModalLabel">
                    <i class="fas fa-puzzle-piece mr-2"></i>Secret Image &amp; Obstacle Settings
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form action="" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="update_obstacle_settings">
                <div class="modal-body p-4">
                    <div class="form-group text-center">
                        <label class="font-weight-bold d-block text-left">Current Secret Image:</label>
                        <?php if (!empty($quiz['obstacle_image'])): ?>
                            <img src="<?= BASE_URL . htmlspecialchars($quiz['obstacle_image']) ?>" class="img-fluid rounded border shadow-sm" style="max-height: 200px; object-fit: contain;" alt="Obstacle Image">
                        <?php else: ?>
                            <div class="alert alert-warning">No secret image uploaded yet</div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label class="font-weight-bold">Upload New Image (optional):</label>
                        <input type="file" name="obstacle_image" class="form-control-file" accept="image/*">
                    </div>

                    <div class="form-group">
                        <label class="font-weight-bold">Secret Keyword (Exact Answer):</label>
                        <input type="text" name="obstacle_answer" class="form-control" value="<?= htmlspecialchars($quiz['obstacle_answer'] ?? '') ?>" required>
                    </div>

                    <div class="form-group mb-0">
                        <label class="font-weight-bold">Number of Puzzle Tiles:</label>
                        <select name="total_pieces" class="form-control">
                            <option value="4" <?= ($quiz['total_pieces'] ?? 4) == 4 ? 'selected' : '' ?>>4 tiles (2x2 grid)</option>
                            <option value="6" <?= ($quiz['total_pieces'] ?? 4) == 6 ? 'selected' : '' ?>>6 tiles (3x2 grid)</option>
                            <option value="8" <?= ($quiz['total_pieces'] ?? 4) == 8 ? 'selected' : '' ?>>8 tiles (4x2 grid)</option>
                            <option value="9" <?= ($quiz['total_pieces'] ?? 4) == 9 ? 'selected' : '' ?>>9 tiles (3x3 grid)</option>
                            <option value="10" <?= ($quiz['total_pieces'] ?? 4) == 10 ? 'selected' : '' ?>>10 tiles (5x2 grid)</option>
                            <option value="12" <?= ($quiz['total_pieces'] ?? 4) == 12 ? 'selected' : '' ?>>12 tiles (4x3 grid)</option>
                            <option value="15" <?= ($quiz['total_pieces'] ?? 4) == 15 ? 'selected' : '' ?>>15 tiles (5x3 grid)</option>
                            <option value="16" <?= ($quiz['total_pieces'] ?? 4) == 16 ? 'selected' : '' ?>>16 tiles (4x4 grid)</option>
                            <option value="20" <?= ($quiz['total_pieces'] ?? 4) == 20 ? 'selected' : '' ?>>20 tiles (5x4 grid)</option>
                            <option value="25" <?= ($quiz['total_pieces'] ?? 4) == 25 ? 'selected' : '' ?>>25 tiles (5x5 grid)</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary font-weight-bold" data-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-kahoot btn-kahoot-green font-weight-bold">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- MODAL REVIEW MODE SETTINGS -->
<div class="modal fade" id="reviewModeModal" tabindex="-1" role="dialog" aria-labelledby="reviewModeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content border-0 shadow" style="border-radius: 16px; overflow: hidden;">
            <div class="modal-header text-dark" style="background: linear-gradient(135deg, #fef3c7, #fde68a); border-bottom: 1px solid #fcd34d;">
                <h5 class="modal-title font-weight-bold" id="reviewModeModalLabel">
                    <i class="fas fa-book-reader mr-2 text-warning"></i>Review Mode Settings
                </h5>
                <button type="button" class="close text-dark" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form action="" method="POST">
                <input type="hidden" name="action" value="update_review_settings">
                <div class="modal-body p-4">
                    <div class="d-flex align-items-center justify-content-between p-3 mb-3 rounded" style="background-color: #fffbeb; border: 1px solid #fef3c7; cursor: pointer;" onclick="toggleReviewCheckbox(event)">
                        <div style="max-width: 75%;">
                            <h6 class="font-weight-bold mb-1 text-dark">
                                <i class="fas fa-toggle-on text-warning mr-1"></i> Enable Review Mode
                            </h6>
                            <p class="text-muted small mb-0">
                                When enabled, students can practice independently at their own pace without host control, timer, or leaderboard.
                            </p>
                        </div>
                        <div class="custom-control custom-switch pl-4" style="font-size: 1.25rem;">
                            <input type="checkbox" class="custom-control-input" id="is_review_mode" name="is_review_mode" value="1" <?= $isReviewMode ? 'checked' : '' ?>>
                            <label class="custom-control-label" for="is_review_mode" style="cursor: pointer;"></label>
                        </div>
                    </div>

                    <?php if ($isReviewMode && !empty($reviewPin)): ?>
                    <div class="p-3 bg-light rounded" style="border: 1px solid #e2e8f0;">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="small font-weight-bold text-muted text-uppercase">Current Game PIN:</span>
                            <span class="h5 font-weight-bold text-dark font-monospace mb-0"><?= htmlspecialchars($reviewPin) ?></span>
                        </div>
                        <div class="text-right">
                            <button type="button" class="btn btn-sm btn-outline-warning text-dark font-weight-bold" onclick="$('#reviewModeModal').modal('hide'); $('#reviewViewModal').modal('show');">
                                <i class="fas fa-qrcode mr-1"></i> View PIN & QR Code
                            </button>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-secondary font-weight-bold rounded-pill px-3" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning font-weight-bold rounded-pill px-4 text-dark shadow-sm">
                        <i class="fas fa-save mr-1"></i> Save Settings
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL REVIEW VIEW (KAHOOT LOBBY STYLE) -->
<?php 
$joinPageUrl = rtrim(BASE_URL, '/') . '/modules/player/join.php';
$displayJoinUrl = preg_replace('#^https?://#', '', $joinPageUrl);
?>
<div class="modal fade" id="reviewViewModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document" style="max-width: 720px;">
        <div class="modal-content border-0 bg-transparent">
            <!-- PURPLE OUTER CONTAINER -->
            <div class="p-3 p-md-4 position-relative" style="background: #46178f; border-radius: 26px; box-shadow: 0 25px 50px -12px rgba(70, 23, 143, 0.45);">
                <!-- INNER WHITE CARD -->
                <div class="bg-white text-dark p-4" style="border-radius: 18px; box-shadow: 0 10px 25px rgba(0,0,0,0.12);">


                    <div class="row align-items-center">
                        <!-- LEFT COLUMN: Practice Link & Open Button -->
                        <div class="col-md-7 pr-md-4">
                            <label class="small font-weight-bold text-muted text-uppercase mb-1" style="font-size: 0.75rem; letter-spacing: 0.5px;">
                                Đường link ôn tập
                            </label>
                            <div class="input-group mb-2 shadow-sm" style="border-radius: 10px; overflow: hidden; border: 1.5px solid #cbd5e1;">
                                <input type="text" class="form-control border-0 bg-light text-truncate font-weight-bold text-primary" value="<?= htmlspecialchars($reviewUrl) ?>" readonly onclick="window.open(this.value, '_blank')" style="cursor: pointer; font-size: 0.88rem; height: 42px;" title="Nhấp vào để mở trang ôn tập">
                                <div class="input-group-append">
                                    <button type="button" class="btn btn-primary font-weight-bold px-3 d-flex align-items-center" onclick="copyTextToClipboard('<?= htmlspecialchars($reviewUrl) ?>', this)" style="background: #46178f; border-color: #46178f; font-size: 0.88rem;">
                                        <i class="fas fa-copy mr-1"></i> Sao chép
                                    </button>
                                </div>
                            </div>
                            <p class="text-muted small mb-3" style="font-size: 0.82rem;">
                                <i class="fas fa-info-circle text-info mr-1"></i> Nhấp vào link hoặc nút bên dưới để vào làm bài ngay.
                            </p>

                            <div class="pt-2 border-top">
                                <a href="<?= htmlspecialchars($reviewUrl) ?>" target="_blank" rel="noopener" class="btn btn-outline-primary btn-sm font-weight-bold px-3 py-2 rounded-pill shadow-sm" style="border-color: #46178f; color: #46178f;">
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
                                <button type="button" class="border p-2 bg-white shadow-sm qr-zoom-trigger" data-toggle="modal" data-target="#qrZoomModalEdit" title="Nhấp để phóng to mã QR" style="cursor: zoom-in; outline: none; border-radius: 12px; border-color: #e2e8f0;">
                                    <div id="reviewViewQrCode" class="review-qr-code mx-auto" data-url="<?= htmlspecialchars($reviewUrl) ?>" style="width: 145px; height: 145px;">
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

<!-- ENLARGE QR MODAL EDIT -->
<div class="modal fade" id="qrZoomModalEdit" tabindex="-1" role="dialog" aria-hidden="true" style="z-index: 1070;">
    <div class="modal-dialog modal-dialog-centered" role="document" style="max-width: 460px;">
        <div class="modal-content border-0 p-4 text-center shadow-lg" style="border-radius: 24px;">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="font-weight-bold text-dark mb-0"><i class="fas fa-qrcode mr-2 text-primary"></i> Quét mã để ôn tập</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="outline: none;">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div id="reviewViewQrCodeLarge" class="mx-auto my-2" data-url="<?= htmlspecialchars($reviewUrl) ?>" style="width: 320px; height: 320px;">
                <div class="spinner-border text-primary mt-5" role="status"></div>
            </div>
            
            <button type="button" class="btn btn-secondary font-weight-bold rounded-pill px-4 mx-auto" data-dismiss="modal">Đóng</button>
        </div>
    </div>
</div>

<!-- MODAL TEAM MODE SETTINGS -->
<div class="modal fade" id="teamModeModal" tabindex="-1" role="dialog" aria-labelledby="teamModeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content border-0 shadow" style="border-radius: 16px; overflow: hidden;">
            <div class="modal-header text-white" style="background: linear-gradient(135deg, #1368ce, #46178f);">
                <h5 class="modal-title font-weight-bold" id="teamModeModalLabel">
                    <i class="fas fa-users-cog mr-2"></i>Team Mode Settings
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form action="" method="POST">
                <input type="hidden" name="action" value="update_team_settings">
                <div class="modal-body p-4">
                    <div class="d-flex align-items-center justify-content-between p-3 mb-3 rounded" style="background-color: #f3f0fb; border: 1px solid #e0d7f5; cursor: pointer;" onclick="toggleTeamModeCheckbox(event)">
                        <div style="max-width: 75%;">
                            <h6 class="font-weight-bold mb-1 text-dark">
                                <i class="fas fa-users text-primary mr-1"></i> Enable Team Mode
                            </h6>
                            <p class="text-muted small mb-0">
                                When enabled, players choose a team before entering their nickname. Individual points contribute to team total.
                            </p>
                        </div>
                        <div class="custom-control custom-switch pl-4" style="font-size: 1.25rem;">
                            <input type="checkbox" class="custom-control-input" id="is_team_mode" name="is_team_mode" value="1" <?= $isTeamMode ? 'checked' : '' ?> onchange="toggleTeamModeInputs(this.checked)">
                            <label class="custom-control-label" for="is_team_mode" style="cursor: pointer;"></label>
                        </div>
                    </div>

                    <div id="teamModeFields" style="<?= $isTeamMode ? '' : 'opacity: 0.45; pointer-events: none;' ?>">
                        <div class="form-group mb-3">
                            <label class="font-weight-bold text-dark d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-flag text-primary mr-1"></i> Team Count:</span>
                                <span class="badge badge-primary font-weight-bold px-2 py-1" id="teamCountBadge"><?= $teamCount ?> Teams</span>
                            </label>
                            <select name="team_count" id="team_count" class="form-control font-weight-bold" onchange="onTeamCountChange(this.value)">
                                <?php for ($tc = 2; $tc <= 10; $tc++): ?>
                                    <option value="<?= $tc ?>" <?= $teamCount == $tc ? 'selected' : '' ?>><?= $tc ?> Teams</option>
                                <?php endfor; ?>
                            </select>
                            <small class="text-muted">Choose between 2 and 10 teams.</small>
                        </div>

                        <div class="form-group mb-3">
                            <label class="font-weight-bold text-dark d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-user-friends text-info mr-1"></i> Player limit per team:</span>
                                <span class="badge badge-info font-weight-bold px-2 py-1" id="teamLimitBadge"><?= $teamLimit > 0 ? $teamLimit . ' players/team' : 'Unlimited' ?></span>
                            </label>
                            <input type="number" name="team_player_limit" id="team_player_limit" class="form-control font-weight-bold" min="0" max="100" value="<?= $teamLimit ?>" placeholder="0 = Unlimited" oninput="onTeamLimitChange(this.value)">
                            <small class="text-muted">Set to <strong>0</strong> for unlimited players per team.</small>
                        </div>

                        <div class="form-group mb-0">
                            <label class="font-weight-bold text-dark mb-2">Team Preview:</label>
                            <div class="d-flex flex-wrap" id="teamPreviewContainer" style="gap: 8px;">
                                <?php foreach ($availableTeams as $t): ?>
                                    <span class="badge font-weight-bold shadow-sm" style="background: <?= $t['color'] ?>; color: <?= $t['text_color'] ?>; padding: 6px 12px; border-radius: 12px;">
                                        <i class="<?= $t['icon'] ?> mr-1"></i> <?= htmlspecialchars($t['name']) ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-secondary font-weight-bold rounded-pill px-3" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary font-weight-bold rounded-pill px-4" style="background-color: var(--kahoot-purple); border-color: var(--kahoot-purple);">
                        <i class="fas fa-save mr-1"></i> Save Settings
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const ALL_TEAMS_CATALOG = <?= json_encode(array_values(TeamManager::getTeamsForEntity(['team_count' => 10]))) ?>;

function toggleTeamModeCheckbox(e) {
    if (e && e.target && (e.target.id === 'is_team_mode' || e.target.classList.contains('custom-control-label'))) {
        return;
    }
    const chk = document.getElementById('is_team_mode');
    if (chk) {
        chk.checked = !chk.checked;
        toggleTeamModeInputs(chk.checked);
    }
}

function toggleTeamModeInputs(enabled) {
    const fields = document.getElementById('teamModeFields');
    if (!fields) return;
    if (enabled) {
        fields.style.opacity = '1';
        fields.style.pointerEvents = 'auto';
    } else {
        fields.style.opacity = '0.45';
        fields.style.pointerEvents = 'none';
    }
}

function onTeamCountChange(val) {
    const count = parseInt(val) || 2;
    const badge = document.getElementById('teamCountBadge');
    if (badge) badge.textContent = count + ' Teams';

    const container = document.getElementById('teamPreviewContainer');
    if (!container) return;
    container.innerHTML = '';
    const slice = ALL_TEAMS_CATALOG.slice(0, count);
    slice.forEach(t => {
        const span = document.createElement('span');
        span.className = 'badge font-weight-bold shadow-sm';
        span.style.background = t.color;
        span.style.color = t.text_color;
        span.style.padding = '6px 12px';
        span.style.borderRadius = '12px';
        span.innerHTML = '<i class="' + t.icon + ' mr-1"></i> ' + t.name;
        container.appendChild(span);
    });
}

function onTeamLimitChange(val) {
    const limit = parseInt(val) || 0;
    const badge = document.getElementById('teamLimitBadge');
    if (badge) {
        badge.textContent = limit > 0 ? (limit + ' players/team') : 'Unlimited';
    }
}
</script>

<script>
window.KAHOOT_EDIT = {
    quizId:      <?= intval($quizId) ?>,
    baseUrl:     <?= json_encode(BASE_URL) ?>,
    defaultMode: <?= json_encode($defaultQMode) ?>,
    gameType:    <?= json_encode($quiz['game_type'] ?? 'classic') ?>,
    totalPieces: <?= intval($quiz['total_pieces'] ?? 4) ?>,
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
<script src="<?= BASE_URL ?>assets/js/edit-quiz.js?v=<?= file_exists(__DIR__ . '/../../assets/js/edit-quiz.js') ? filemtime(__DIR__ . '/../../assets/js/edit-quiz.js') : time() ?>"></script>

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

function toggleReviewCheckbox(e) {
    if (e && e.target && (e.target.id === 'is_review_mode' || e.target.classList.contains('custom-control-label'))) {
        return;
    }
    const chk = document.getElementById('is_review_mode');
    if (chk) chk.checked = !chk.checked;
}

window.addEventListener("DOMContentLoaded", function () {
    $("#reviewViewModal").on("shown.bs.modal", function () {
        const qrBox = document.getElementById("reviewViewQrCode");
        if (qrBox && qrBox.dataset.url) {
            renderQrSvg("reviewViewQrCode", qrBox.dataset.url);
        }
    });
    $("#qrZoomModalEdit").on("shown.bs.modal", function () {
        const qrBoxLarge = document.getElementById("reviewViewQrCodeLarge");
        if (qrBoxLarge && qrBoxLarge.dataset.url) {
            renderQrSvg("reviewViewQrCodeLarge", qrBoxLarge.dataset.url);
        }
    });
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
