<?php

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/room_access.php';
require_once __DIR__ . '/../../includes/player_characters.php';
require_once __DIR__ . '/../../includes/modes/GameStateManager.php';
require_once __DIR__ . '/../../includes/modes/QuestionModeRegistry.php';

$roomId = intval($_SESSION['id_room'] ?? 0);
$room = db_fetch_one("SELECT r.*, q.name as quiz_name FROM room r JOIN quiz q ON r.id_quiz = q.id_quiz WHERE r.id_room = :r AND r.id_creator = :c", [':r' => $roomId, ':c' => (int)$_SESSION['id_creator']]);

if (!$room) {
    redirect('modules/quiz/list.php');
}

$status = $room['status'];

if (isset($_GET['action']) && $_GET['action'] === 'return_to_board') {
    $openedPieces = !empty($room['opened_pieces']) ? json_decode($room['opened_pieces'], true) : [];
    if (!is_array($openedPieces)) $openedPieces = [];
    $quizDetail = db_fetch_one("SELECT total_pieces FROM quiz WHERE id_quiz = :q", [':q' => $room['id_quiz']]);
    $totalPieces = intval($quizDetail['total_pieces'] ?? 4);
    $gameConfig = !empty($room['game_config']) ? json_decode($room['game_config'], true) : [];
    if (!is_array($gameConfig)) $gameConfig = [];
    if (count($openedPieces) >= $totalPieces) {
        $gameConfig['final_round_reset'] = true;
        db_execute("UPDATE players SET is_disqualified_obstacle = 0 WHERE id_room = :r", [':r' => $roomId]);
    }
    db_execute("UPDATE room SET status = 'board', buzzer_status = 'idle', buzzer_player_id = NULL, buzzer_time = NULL, game_config = :gc WHERE id_room = :r", [
        ':gc' => json_encode($gameConfig),
        ':r'  => $roomId
    ]);
    redirect('modules/host/play.php');
}

if (($room['game_type'] ?? 'classic') === 'obstacle' && $status === 'board') {
    require __DIR__ . '/../../includes/host_obstacle_board.php';
    exit;
}

if ($status === 'waiting') {
    redirect('modules/host/lobby.php');
}

if ($status === 'finished') {
    redirect('modules/host/leaderboard.php?final=true');
}

$parsed = GameStateManager::parseStatus($status);
$phase = $parsed['phase'];
$qId = $parsed['question_id'];

$selectedQIds = !empty($room['question_order']) ? json_decode($room['question_order'], true) : null;
if (!is_array($selectedQIds) || empty($selectedQIds)) {
    $fallbackQuestions = db_fetch_all("SELECT id_question FROM questions WHERE id_quiz = :q ORDER BY id_question ASC", [':q' => $room['id_quiz']]);
    $selectedQIds = array_column($fallbackQuestions, 'id_question');
}
$selectedQIds = array_values(array_filter(array_map('intval', (array)$selectedQIds)));
$totalQuestions = count($selectedQIds);

// CRITICAL: Handle actions FIRST before checking $isResultPhase!
if (isset($_GET['action']) && $_GET['action'] === 'lock_wager') {
    GameStateManager::lockWagerPhaseAndAdvance($roomId, $qId);
    redirect('modules/host/play.php');
}

if (isset($_GET['action']) && $_GET['action'] === 'skip_question') {
    if (($room['game_type'] ?? 'classic') === 'obstacle') {
        redirect('modules/host/play.php?action=return_to_board');
    }
    $currentIdx = array_search((int)$qId, $selectedQIds, true);
    if ($currentIdx === false) {
        $currentIdx = array_search((int)$qId, $selectedQIds);
    }
    if ($currentIdx !== false && isset($selectedQIds[$currentIdx + 1])) {
        $nextQId = intval($selectedQIds[$currentIdx + 1]);
        $_SESSION['question_index'] = $currentIdx + 1;
        GameStateManager::advanceToQuestion($roomId, $nextQId);
        redirect('modules/host/play.php');
    } else {
        db_execute("UPDATE room SET status = 'finished' WHERE id_room = :r", [':r' => $roomId]);
        redirect('modules/host/leaderboard.php?final=true');
    }
}

if (isset($_GET['action']) && $_GET['action'] === 'next_question') {
    $currentQId = 0;
    if (isset($_GET['qid']) && intval($_GET['qid']) > 0) {
        $currentQId = intval($_GET['qid']);
    }
    if ($currentQId <= 0) {
        $currentQId = $qId;
    }
    $currentIdx = array_search((int)$currentQId, $selectedQIds, true);
    if ($currentIdx === false) {
        $currentIdx = array_search((int)$currentQId, $selectedQIds);
    }
    if ($currentIdx === false && isset($_SESSION['question_index'])) {
        $currentIdx = intval($_SESSION['question_index']);
    }

    if ($currentIdx !== false && isset($selectedQIds[$currentIdx + 1])) {
        $nextQId = intval($selectedQIds[$currentIdx + 1]);
        $_SESSION['question_index'] = $currentIdx + 1;
        GameStateManager::advanceToQuestion($roomId, $nextQId);
        redirect('modules/host/play.php');
    } else {
        db_execute("UPDATE room SET status = 'finished' WHERE id_room = :r", [':r' => $roomId]);
        redirect('modules/host/leaderboard.php?final=true');
    }
}

// When show_result is triggered (either all answered or time is up),
// update status to res_<qid> via GameStateManager and stay in play.php to show results!
if (isset($_GET['action']) && $_GET['action'] === 'show_result') {
    GameStateManager::finalizeQuestionPhase($roomId, $qId);
    redirect('modules/host/play.php');
}

// If room is in result phase: render question results on host screen!
if ($phase === 'result') {
    $currentQuestion = db_fetch_one("SELECT * FROM questions WHERE id_question = :qid", [':qid' => $qId]);
    $answers = db_fetch_all("SELECT * FROM answers WHERE id_question = :qid ORDER BY id_answer ASC", [':qid' => $qId]);
    $currentIdx = array_search((int)$qId, $selectedQIds, true);
    $questionNumber = ($currentIdx !== false) ? ($currentIdx + 1) : 1;

    $voteCountsRaw = db_fetch_all(
        "SELECT v.id_answer, COUNT(*) as vote_count 
         FROM votes v 
         JOIN players p ON v.id_player = p.id_player 
         WHERE p.id_room = :r 
           AND v.id_answer IN (SELECT id_answer FROM answers WHERE id_question = :qid)
         GROUP BY v.id_answer",
        [':r' => $roomId, ':qid' => $qId]
    );
    $voteCounts = [];
    $totalVotes = 0;
    foreach ($voteCountsRaw as $vc) {
        $voteCounts[$vc['id_answer']] = intval($vc['vote_count']);
        $totalVotes += intval($vc['vote_count']);
    }

    $colors = ['#e21b3c', '#1368ce', '#ffa602', '#26890c'];
    $icons  = ['🔺', '🔷', '🟡', '🟩'];

    $pageTitle = "Question " . $questionNumber . " Results - Kahoot! Host";
    $bodyClass = "backgroundColorChanges";
    require_once __DIR__ . '/../../includes/header.php';
    ?>
    <nav class="navbar navbar-dark bg-dark px-4 py-3 d-flex justify-content-between align-items-center shadow-sm" style="min-height: 64px;">
        <div class="d-flex align-items-center">
            <img src="<?= BASE_URL ?>assets/img/logo.png" alt="Logo" style="max-height: 34px; filter: brightness(100);" class="mr-3">
            <span class="badge badge-light font-weight-bold font-monospace px-3 py-2 mr-3" style="font-size: 0.95rem; border-radius: 8px; letter-spacing: 0.5px;">PIN: <?= htmlspecialchars($room['pin']) ?></span>
            <span class="text-white font-weight-bold" style="font-size: 1.05rem;">Question <?= $questionNumber ?> / <?= $totalQuestions ?> &bull; Results</span>
        </div>
        <div class="d-flex align-items-center">
            <?php if (($room['game_type'] ?? 'classic') === 'obstacle'): ?>
                <a href="play.php?action=return_to_board" class="btn btn-kahoot btn-kahoot-secondary btn-kahoot-sm mr-2">
                    <i class="fas fa-th mr-1"></i> Return to Board
                </a>
            <?php endif; ?>
        </div>
    </nav>

    <div class="container my-4" style="max-width: 1050px;">
        <div class="card border-0 shadow-sm p-4 mb-4 text-center" style="border-radius: 18px; background: #ffffff;">
            <span class="badge badge-primary px-3 py-1 font-weight-bold mb-2 text-uppercase" style="font-size: 0.85rem; letter-spacing: 0.5px; border-radius: 6px; width: fit-content; margin: 0 auto;">
                Question <?= $questionNumber ?> of <?= $totalQuestions ?>
            </span>
            <h2 class="font-weight-bold text-dark mb-0" style="font-size: 1.85rem; line-height: 1.35;">
                <?= htmlspecialchars($currentQuestion['text']) ?>
            </h2>

            <?php if (!empty($currentQuestion['image'])): 
                $imgUrl = '';
                if (preg_match('#^https?://#i', $currentQuestion['image'])) {
                    $imgUrl = $currentQuestion['image'];
                } else {
                    $fn = basename($currentQuestion['image']);
                    $dp = dirname(__DIR__, 2) . '/assets/img/questions/' . $fn;
                    if (file_exists($dp)) {
                        $imgUrl = BASE_URL . 'assets/img/questions/' . rawurlencode($fn);
                    }
                }
            ?>
                <?php if ($imgUrl): ?>
                    <div class="my-3 text-center">
                        <img src="<?= htmlspecialchars($imgUrl) ?>" alt="Question Media" class="rounded shadow-sm" style="max-height: 240px; max-width: 100%; object-fit: contain;">
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <div class="row">
            <?php foreach ($answers as $idx => $a):
                $cIdx = $idx % 4;
                $isAnsCorrect = ($a['is_correct'] == 1);
                $cnt = $voteCounts[$a['id_answer']] ?? 0;
            ?>
                <div class="col-md-6 mb-3">
                    <div class="answer-box shadow-sm d-flex justify-content-between align-items-center position-relative"
                         style="background-color: <?= $colors[$cIdx] ?>; border-radius: 12px; padding: 18px 22px; <?= $isAnsCorrect ? 'opacity: 1;' : 'opacity: 0.65;' ?>">
                        
                        <div class="d-flex align-items-center flex-grow-1 mr-3 text-truncate">
                            <span class="symbol mr-3" style="font-size: 1.8rem;"><?= $icons[$cIdx] ?></span>
                            <span class="font-weight-bold text-white text-truncate" style="font-size: 1.3rem;">
                                <?= htmlspecialchars($a['text']) ?>
                            </span>
                        </div>

                        <div class="d-flex align-items-center" style="gap: 8px;">
                            <?php if ($isAnsCorrect): ?>
                                <span class="badge badge-success px-3 py-2 text-white font-weight-bold shadow-sm" style="font-size: 1rem; border-radius: 8px;">
                                    <i class="fas fa-check mr-1"></i> Correct
                                </span>
                            <?php endif; ?>
                            <span class="badge badge-light text-dark font-weight-bold px-3 py-2 shadow-sm" style="font-size: 1rem; border-radius: 8px;" title="<?= $cnt ?> player(s) selected this answer">
                                <i class="fas fa-user mr-1 text-muted"></i> <?= $cnt ?>
                            </span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="text-center mt-4">
            <a href="leaderboard.php?qid=<?= $qId ?>&show_scoreboard=1" class="btn btn-kahoot btn-kahoot-blue btn-lg px-5 py-3 font-weight-bold shadow" style="font-size: 1.25rem;">
                <i class="fas fa-trophy mr-2"></i> Show Scoreboard &raquo;
            </a>
        </div>
    </div>
    </body>
    </html>
    <?php
    exit;
}

$currentQuestion = db_fetch_one("SELECT * FROM questions WHERE id_question = :qid", [':qid' => $qId]);
$questionMode = $currentQuestion['mode'] ?? 'normal';
$modeConfig = !empty($currentQuestion['mode_config']) ? json_decode($currentQuestion['mode_config'], true) : [];

$currentIdx = array_search((int)$qId, $selectedQIds, true);
$questionNumber = ($currentIdx !== false) ? ($currentIdx + 1) : 1;

// =========================================================================
// WAGER PHASE HOST VIEW
// =========================================================================
if ($phase === 'wager') {
    $wagerTimeLimit = max(25, intval($modeConfig['wager_time_sec'] ?? ($modeConfig['wagerTimeSec'] ?? 25)));

    $startTime = !empty($room['question_start_time']) ? intval($room['question_start_time']) : time();
    $elapsed = time() - $startTime;
    $remainingWagerSeconds = max(0, $wagerTimeLimit - $elapsed);

    $activeCount = db_count('players', "id_room = :r AND (status = 'ACTIVE' OR status IS NULL) AND is_kicked = 0", [':r' => $roomId]);
    $wageredCount = db_count('player_question_logs', "id_room = :r AND id_question = :q AND is_sitting_out = 0", [':r' => $roomId, ':q' => $qId]);

    $pageTitle = "Wager Phase - Question " . $questionNumber . "/" . $totalQuestions . " - Kahoot! Host";
    $bodyClass = "backgroundColorChanges";
    require_once __DIR__ . '/../../includes/header.php';
    ?>
    <nav class="navbar navbar-dark bg-dark px-4 py-3 d-flex justify-content-between align-items-center shadow-sm" style="min-height: 64px;">
        <div class="d-flex align-items-center">
            <img src="<?= BASE_URL ?>assets/img/logo.png" alt="Logo" style="max-height: 34px; filter: brightness(100);" class="mr-3">
            <span class="badge badge-light font-weight-bold font-monospace px-3 py-2 mr-3" style="font-size: 0.95rem; border-radius: 8px; letter-spacing: 0.5px;">PIN: <?= htmlspecialchars($room['pin']) ?></span>
            <span class="text-white font-weight-bold" style="font-size: 1.05rem;">Question <?= $questionNumber ?> / <?= $totalQuestions ?></span>
        </div>
        <div class="d-flex align-items-center">
            <a href="play.php?action=lock_wager" class="btn btn-kahoot btn-kahoot-blue btn-kahoot-sm mr-2">
                <i class="fas fa-play mr-1"></i> Start Question
            </a>
            <a href="play.php?action=skip_question" class="btn btn-kahoot btn-kahoot-secondary btn-kahoot-sm" onclick="return confirm('Skip this question? Player scores will not change.');">
                Skip Question
            </a>
        </div>
    </nav>

    <div class="container my-4" style="max-width: 950px;">
        <details class="text-white mb-3">
            <summary class="font-weight-bold">Manage players</summary>
            <div class="text-center mt-3"><?php $reloadAfterKick = true; require __DIR__ . '/../../includes/host_players.php'; ?></div>
        </details>

        <div class="question-card-banner text-center py-4 shadow-sm" style="background: rgba(255, 255, 255, 0.96); border-radius: 20px;">
            <div style="font-size: 3.8rem; line-height: 1;" class="mb-2">⭐</div>
            <h2 class="font-weight-bold text-dark mb-1">STAR OF HOPE</h2>
            <p class="text-muted font-weight-bold mb-0" style="font-size: 1.15rem;">
                Players are choosing their Star of Hope
            </p>
        </div>

        <div class="d-flex justify-content-around align-items-center my-4 px-3 flex-wrap" style="gap: 20px;">
            <div>
                <div id="wagerTimerBox" class="timer-circle <?= $remainingWagerSeconds <= 3 ? 'warning' : '' ?>" style="width: 100px; height: 100px; font-size: 2.4rem;">
                    <span id="wagerTimerSeconds"><?= $remainingWagerSeconds ?></span>
                </div>
            </div>

            <div class="text-center">
                <div class="bg-white rounded-pill px-5 py-3 shadow font-weight-bold text-dark" style="font-size: 1.35rem;">
                    <i class="fas fa-star text-warning mr-2"></i>
                    Stars chosen: <span id="wagerCount" class="text-primary font-weight-bold"><?= $wageredCount ?></span> / <span id="activeCount"><?= $activeCount ?></span>
                </div>
                <div class="text-white mt-2 small font-weight-bold" style="text-shadow: 1px 1px 2px rgba(0,0,0,0.4);">
                    The game will automatically advance to the question when all players have chosen or time expires.
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script>
    let remainingWager = <?= $remainingWagerSeconds ?>;
    const roomId = <?= $roomId ?>;
    const qId = <?= $qId ?>;

    const wagerTimerInterval = setInterval(function() {
        remainingWager--;
        if (remainingWager <= 0) {
            clearInterval(wagerTimerInterval);
            clearInterval(wagerPollInterval);
            $('#wagerTimerSeconds').text('0');
            window.location.href = 'play.php?action=lock_wager';
        } else {
            $('#wagerTimerSeconds').text(remainingWager);
            if (remainingWager <= 3) {
                $('#wagerTimerBox').addClass('warning');
            }
        }
    }, 1000);

    const wagerPollInterval = setInterval(function() {
        $.getJSON('<?= BASE_URL ?>modules/api/game_state.php?action=get_wager_status&id_room=' + roomId + '&id_question=' + qId, function(data) {
            if (data && data.success) {
                $('#wagerCount').text(data.wagered_count);
                $('#activeCount').text(data.total_active);

                if (data.is_all_wagered) {
                    clearInterval(wagerPollInterval);
                    clearInterval(wagerTimerInterval);
                    window.location.href = 'play.php?action=lock_wager';
                }
            }
        });
    }, 800);
    </script>
    </body>
    </html>
    <?php
    exit;
}

// =========================================================================
// QUESTION PHASE HOST VIEW (Normal or Wager)
// =========================================================================
$answers = db_fetch_all("SELECT * FROM answers WHERE id_question = :qid ORDER BY id_answer ASC", [':qid' => $qId]);

$questionMediaUrl = '';
$questionMediaType = 'image';
if (!empty($currentQuestion['image'])) {
    if (preg_match('#^https?://#i', $currentQuestion['image'])) {
        $questionMediaUrl = $currentQuestion['image'];
        $ext = strtolower(pathinfo(parse_url($questionMediaUrl, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
        $questionMediaType = (in_array($ext, ['mp4', 'webm']) || ($currentQuestion['media_type'] ?? '') === 'video') ? 'video' : 'image';
    } else {
        $imgFilename = basename($currentQuestion['image']);
        $mediaDiskPath = dirname(__DIR__, 2) . '/assets/img/questions/' . $imgFilename;
        if (file_exists($mediaDiskPath)) {
            $questionMediaUrl = BASE_URL . 'assets/img/questions/' . rawurlencode($imgFilename);
            $ext = strtolower(pathinfo($imgFilename, PATHINFO_EXTENSION));
            $questionMediaType = (in_array($ext, ['mp4', 'webm']) || ($currentQuestion['media_type'] ?? '') === 'video') ? 'video' : 'image';
        }
    }
}

$counts = room_question_counts($room, $qId);
$totalPlayers = $counts['total_players'];
$votedCount = $counts['voted_count'];
$activeCount = (int)db_count('players', "id_room = :r AND status = 'ACTIVE' AND is_kicked = 0", [':r' => $roomId]);
if ($activeCount === 0 && ($room['game_type'] ?? 'classic') !== 'obstacle' && $phase === 'question') {
    GameStateManager::finalizeQuestionPhase($roomId, $qId);
    redirect('modules/host/leaderboard.php?qid=' . $qId);
}

$timeLimit = intval($currentQuestion['time_limit'] ?? 20);

if (!in_array($timeLimit, [10, 20, 30, 60])) {
    if ($timeLimit <= 10) $timeLimit = 10;
    elseif ($timeLimit <= 20) $timeLimit = 20;
    elseif ($timeLimit <= 30) $timeLimit = 30;
    else $timeLimit = 60;
}

$startTime = !empty($room['question_start_time']) ? intval($room['question_start_time']) : time();
$elapsed = time() - $startTime;
$remainingSeconds = max(0, $timeLimit - $elapsed);

$questionAudioFile = null;
$audioDir = __DIR__ . '/../../assets/audio';
if (is_dir($audioDir)) {
    $searchKey = $timeLimit . ' Second';
    $audioFiles = scandir($audioDir);
    $matchedAudios = [];
    foreach ($audioFiles as $af) {
        if ($af === '.' || $af === '..') continue;
        if (stripos($af, $searchKey) !== false && preg_match('/\.(mp3|wav|ogg)$/i', $af)) {
            $matchedAudios[] = $af;
        }
    }
    if (!empty($matchedAudios)) {
        $questionAudioFile = $matchedAudios[($questionNumber - 1) % count($matchedAudios)];
    }
}

$colors = [KAHOOT_COLOR_RED, KAHOOT_COLOR_BLUE, KAHOOT_COLOR_YELLOW, KAHOOT_COLOR_GREEN];
$icons  = [KAHOOT_ICON_RED, KAHOOT_ICON_BLUE, KAHOOT_ICON_YELLOW, KAHOOT_ICON_GREEN];

$pageTitle = "Question " . $questionNumber . "/" . $totalQuestions . " - Kahoot! Host";
$bodyClass = "backgroundColorChanges";

require_once __DIR__ . '/../../includes/header.php';
?>

<?php if ($questionAudioFile): ?>
<audio id="questionAudio" preload="auto">
    <source src="<?= BASE_URL ?>assets/audio/<?= rawurlencode($questionAudioFile) ?>" type="audio/mpeg">
</audio>
<?php endif; ?>

<nav class="navbar navbar-dark bg-dark px-4 py-3 d-flex justify-content-between align-items-center shadow-sm" style="min-height: 64px;">
    <div class="d-flex align-items-center">
        <img src="<?= BASE_URL ?>assets/img/logo.png" alt="Logo" style="max-height: 34px; filter: brightness(100);" class="mr-3">
        <span class="badge badge-light font-weight-bold font-monospace px-3 py-2 mr-3" style="font-size: 0.95rem; border-radius: 8px; letter-spacing: 0.5px;">PIN: <?= htmlspecialchars($room['pin']) ?></span>
        <span class="text-white font-weight-bold" style="font-size: 1.05rem;">Question <?= $questionNumber ?> / <?= $totalQuestions ?></span>
    </div>
    <div class="d-flex align-items-center">
        <?php if ($questionAudioFile): ?>
            <button type="button" id="audioToggleBtn" class="btn btn-kahoot btn-kahoot-white btn-kahoot-sm mr-2" onclick="toggleQuestionAudio()" title="Toggle countdown music">
                <i class="fas fa-volume-up text-success mr-1" id="audioIcon"></i>
                <span id="audioLabel">Music: ON</span>
            </button>
        <?php endif; ?>

        <?php if (($room['game_type'] ?? 'classic') === 'obstacle'): ?>
            <a href="play.php?action=show_result" onclick="stopQuestionAudio()" class="btn btn-kahoot btn-kahoot-blue btn-kahoot-sm mr-2">
                <i class="fas fa-chart-bar mr-1"></i> View Results
            </a>
            <a href="play.php?action=return_to_board" onclick="stopQuestionAudio()" class="btn btn-kahoot btn-kahoot-secondary btn-kahoot-sm mr-2">
                <i class="fas fa-th mr-1"></i> Return to Board
            </a>
            <a href="play.php?action=skip_question" onclick="stopQuestionAudio(); return confirm('Skip this question? Player scores will not change.');" class="btn btn-kahoot btn-kahoot-secondary btn-kahoot-sm">
                Skip Question
            </a>
        <?php else: ?>
            <a href="play.php?action=show_result" onclick="stopQuestionAudio()" class="btn btn-kahoot btn-kahoot-blue btn-kahoot-sm mr-2">
                Leaderboard
            </a>
            <a href="play.php?action=skip_question" onclick="stopQuestionAudio(); return confirm('Skip this question? Player scores will not change.');" class="btn btn-kahoot btn-kahoot-secondary btn-kahoot-sm">
                Skip Question
            </a>
        <?php endif; ?>
    </div>
</nav>

<?php if (($room['game_type'] ?? 'classic') === 'obstacle'): ?>
    <!-- BUZZER ALERT BANNER IN QUESTION -->
    <div class="buzzer-alert-banner" id="hostQuestionBuzzerBanner" style="display: none; position: fixed; top: 15px; left: 50%; transform: translateX(-50%); z-index: 99999; max-width: 820px; width: 95%; background: linear-gradient(135deg, #e21b3c 0%, #b3122c 100%); border: 3px solid #ffcc00; border-radius: 20px; padding: 16px 24px; box-shadow: 0 16px 40px rgba(0,0,0,0.6); color: #fff;">
        <div class="d-flex align-items-center justify-content-between flex-wrap">
            <div class="d-flex align-items-center mb-2 mb-sm-0">
                <i class="fas fa-bell fa-2x mr-3 text-warning"></i>
                <div>
                    <h5 class="font-weight-bold mb-0" id="hostQBuzzTitle">🔔 A PLAYER BUZZED IN!</h5>
                    <span id="hostQBuzzDesc" style="opacity: 0.95;">Player is answering the secret picture keyword...</span>
                </div>
            </div>
            <div class="d-flex align-items-center">
                <span class="badge badge-light font-weight-bold py-2 px-3 mr-2 text-danger" style="font-size: 1rem;" id="hostQBuzzStatus">
                    Question Paused
                </span>
                <button type="button" class="btn btn-sm btn-outline-light font-weight-bold rounded-pill px-3" onclick="hostCancelBuzzFromQuestion()">
                    <i class="fas fa-times mr-1"></i> Cancel Buzzer
                </button>
            </div>
        </div>
    </div>

    <!-- HOST REVIEW MODAL IN QUESTION -->
    <div class="host-modal-backdrop" id="hostQReviewModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.85); backdrop-filter: blur(8px); z-index: 999999; align-items: center; justify-content: center; padding: 20px;">
        <div class="host-modal-card" style="background: #1e1136; border: 3px solid #f2994a; border-radius: 24px; max-width: 580px; width: 100%; padding: 30px; color: #fff; text-align: center; box-shadow: 0 24px 60px rgba(0,0,0,0.7);">
            <h3 class="font-weight-bold text-warning mb-2" style="color: #f2994a !important;">
                <i class="fas fa-gavel mr-2"></i> CONFIRM ANSWER
            </h3>
            <p class="text-white-50 small mb-3">Player has submitted the secret picture keyword guess:</p>
            <div class="p-3 rounded mb-3" style="background: rgba(255,255,255,0.08); border: 2px dashed rgba(255,255,255,0.25);">
                <div class="small text-white-50">PLAYER:</div>
                <div class="h4 font-weight-bold text-info mb-3" id="hostQReviewPlayerName">...</div>
                <div class="small text-white-50">PREDICTED KEYWORD:</div>
                <div class="h3 font-weight-bold text-warning mb-0" id="hostQReviewPlayerGuess" style="letter-spacing: 1px; word-break: break-word;">...</div>
            </div>
            <?php if (!empty($room['obstacle_answer'])): ?>
                <div class="alert alert-dark border border-secondary text-left py-2 px-3 mb-3 small" style="background: #120924;">
                    <span class="text-white-50">Secret picture keyword:</span>
                    <span class="font-weight-bold text-success ml-1"><?= htmlspecialchars($room['obstacle_answer']) ?></span>
                </div>
            <?php endif; ?>
            <div class="d-flex justify-content-between mt-4">
                <button type="button" class="btn btn-danger btn-lg font-weight-bold px-4 py-3 shadow" style="border-radius: 14px; width: 48%; font-size: 1.15rem;" onclick="hostConfirmQGuess(false)">
                    <i class="fas fa-times-circle mr-2"></i> INCORRECT (FALSE)
                </button>
                <button type="button" class="btn btn-success btn-lg font-weight-bold px-4 py-3 shadow" style="border-radius: 14px; width: 48%; font-size: 1.15rem;" onclick="hostConfirmQGuess(true)">
                    <i class="fas fa-check-circle mr-2"></i> CORRECT (TRUE)
                </button>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="container my-3" style="max-width: 1050px;">

    <details class="text-white mb-3">
        <summary class="font-weight-bold">Manage players</summary>
        <div class="text-center mt-3"><?php $reloadAfterKick = true; require __DIR__ . '/../../includes/host_players.php'; ?></div>
    </details>

    <div class="question-card-banner">
        <h2 class="font-weight-bold text-dark mb-0">
            <?= htmlspecialchars($currentQuestion['text']) ?>
        </h2>
        <?php if (!empty($questionMediaUrl)): ?>
            <div class="text-center mt-3">
                <?php if ($questionMediaType === 'video'): ?>
                    <video src="<?= htmlspecialchars($questionMediaUrl) ?>" controls autoplay muted loop class="img-fluid rounded shadow-sm" style="max-height: 280px; max-width: 100%; border-radius: 12px;"></video>
                <?php else: ?>
                    <img src="<?= htmlspecialchars($questionMediaUrl) ?>" alt="Question Media" class="img-fluid rounded shadow-sm" style="max-height: 280px; max-width: 100%; object-fit: contain; border-radius: 12px;">
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="d-flex justify-content-between align-items-center my-3 px-3">
        <div>
            <div id="timerBox" class="timer-circle <?= $remainingSeconds <= 5 ? 'warning' : '' ?>">
                <span id="timerSeconds"><?= $remainingSeconds ?></span>
            </div>
        </div>

        <div class="text-center">
            <div class="bg-white rounded-pill px-4 py-2 shadow-sm font-weight-bold text-dark" style="font-size: 1.2rem;">
                <i class="fas fa-paper-plane text-primary mr-1"></i>
                <span id="voteCount"><?= $votedCount ?></span> / <span id="totalPlayerCount"><?= $totalPlayers ?></span> Answers
            </div>
        </div>
    </div>

    <div class="row">
        <?php foreach ($answers as $idx => $a):
            $cIdx = $idx % 4;
            $ansImgUrl = '';
            if (!empty($a['image_path'])) {
                if (preg_match('#^https?://#i', $a['image_path'])) {
                    $ansImgUrl = $a['image_path'];
                } else {
                    $aImgFile = basename($a['image_path']);
                    $aImgDiskPath = dirname(__DIR__, 2) . '/assets/img/questions/' . $aImgFile;
                    if (file_exists($aImgDiskPath)) {
                        $ansImgUrl = BASE_URL . 'assets/img/questions/' . rawurlencode($aImgFile);
                    }
                }
            }
        ?>
            <div class="col-md-6 mb-3">
                <div class="answer-box shadow-sm d-flex justify-content-between align-items-center"
                     style="background-color: <?= $colors[$cIdx] ?>; cursor: default;">
                    <div class="d-flex align-items-center flex-grow-1 mr-2">
                        <span class="symbol mr-3" style="font-size: 2rem;"><?= $icons[$cIdx] ?></span>
                        <?php if ($ansImgUrl): ?>
                            <img src="<?= htmlspecialchars($ansImgUrl) ?>" alt="Answer" class="img-fluid rounded mr-3 bg-white p-1" style="max-height: 55px; max-width: 85px; object-fit: contain; box-shadow: 0 2px 4px rgba(0,0,0,0.15);">
                        <?php endif; ?>
                        <?php if (!empty($a['text'])): ?>
                            <span style="font-size: 1.3rem;"><?= htmlspecialchars($a['text']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script>
const qAudio = document.getElementById('questionAudio');
let isAudioPlaying = false;

function playQuestionAudio() {
    if (!qAudio) return;

    const elapsed = <?= $elapsed ?>;
    const timeLimit = <?= $timeLimit ?>;
    if (elapsed > 0 && elapsed < timeLimit) {
        try {
            qAudio.currentTime = elapsed;
        } catch(e) {}
    }

    qAudio.play().then(function() {
        isAudioPlaying = true;
        updateAudioUI(true);
    }).catch(function() {
        isAudioPlaying = false;
        updateAudioUI(false);

        $(document).one('click keydown', function() {
            if (qAudio && qAudio.paused) {
                try {
                    qAudio.currentTime = Math.max(0, <?= $timeLimit ?> - remaining);
                } catch(e) {}
                qAudio.play().then(function() {
                    isAudioPlaying = true;
                    updateAudioUI(true);
                }).catch(function() {});
            }
        });
    });
}

function toggleQuestionAudio() {
    if (!qAudio) return;
    if (qAudio.paused) {
        qAudio.play().then(function() {
            isAudioPlaying = true;
            updateAudioUI(true);
        }).catch(function(e) {
            console.log('Audio error:', e);
        });
    } else {
        qAudio.pause();
        isAudioPlaying = false;
        updateAudioUI(false);
    }
}

function updateAudioUI(isPlaying) {
    const icon = document.getElementById('audioIcon');
    const label = document.getElementById('audioLabel');
    const btn = document.getElementById('audioToggleBtn');
    if (!icon || !label || !btn) return;
    if (isPlaying) {
        icon.className = 'fas fa-volume-up text-success mr-1';
        label.textContent = 'Music: ON';
        btn.className = 'btn btn-sm btn-light font-weight-bold shadow-sm rounded-pill mr-2 px-3';
    } else {
        icon.className = 'fas fa-volume-mute text-danger mr-1';
        label.textContent = 'Music: OFF';
        btn.className = 'btn btn-sm btn-outline-light font-weight-bold shadow-sm rounded-pill mr-2 px-3';
    }
}

function stopQuestionAudio() {
    if (qAudio) {
        try {
            qAudio.pause();
            qAudio.currentTime = 0;
        } catch(e) {}
    }
}

$(document).ready(function() {
    playQuestionAudio();
});

let remaining = <?= $remainingSeconds ?>;
const roomId = <?= $roomId ?>;
const qId = <?= $qId ?>;
const isObstacleGame = <?= ($room['game_type'] ?? 'classic') === 'obstacle' ? 'true' : 'false' ?>;
const finishQuestionUrl = 'play.php?action=show_result';
let isPausedByBuzzer = false;

// Countdown timer: when time runs out, auto-transition immediately to leaderboard
const timerInterval = setInterval(function() {
    if (isPausedByBuzzer) return; // Freeze countdown during buzzer

    remaining--;
    if (remaining <= 0) {
        clearInterval(timerInterval);
        clearInterval(pollInterval);
        stopQuestionAudio();
        $('#timerSeconds').text('0');

        window.location.href = finishQuestionUrl;
    } else {
        $('#timerSeconds').text(remaining);
        if (remaining <= 5) {
            $('#timerBox').addClass('warning');
        }
    }
}, 1000);

// Polling: sync answer counts and obstacle buzzer status
const pollInterval = setInterval(function() {
    $.getJSON('<?= BASE_URL ?>modules/api/game_state.php?action=get_question_status&id_room=' + roomId + '&id_question=' + qId, function(data) {
        if (data && data.success) {
            $('#voteCount').text(data.voted_count);
            $('#totalPlayerCount').text(data.total_players);

            // Check buzzer status in question
            if (isObstacleGame && data.buzzer_status) {
                if (data.buzzer_status === 'buzzing' || data.buzzer_status === 'pending_review') {
                    if (!isPausedByBuzzer) {
                        isPausedByBuzzer = true;
                        stopQuestionAudio();
                        $('#timerBox').css('opacity', '0.4');
                    }
                    $('#hostQuestionBuzzerBanner').slideDown(200);

                    if (data.buzzer_status === 'buzzing') {
                        $('#hostQBuzzTitle').text('🔔 ' + (data.buzzer_player_name || 'Player') + ' IS BUZZING IN TO GUESS KEYWORD!');
                        $('#hostQBuzzDesc').text('Game and countdown are paused. Waiting for player to enter answer...');
                        $('#hostQReviewModal').hide();
                    } else if (data.buzzer_status === 'pending_review') {
                        $('#hostQBuzzTitle').text('🔔 ' + (data.buzzer_player_name || 'Player') + ' HAS SUBMITTED AN ANSWER!');
                        $('#hostQBuzzDesc').text('Please review and confirm if the answer is CORRECT or INCORRECT.');
                        $('#hostQReviewPlayerName').text(data.buzzer_player_name || 'Player');
                        $('#hostQReviewPlayerGuess').text(data.buzzer_guess || '...');
                        $('#hostQReviewModal').css('display', 'flex');
                    }
                } else if (data.buzzer_status === 'idle') {
                    if (isPausedByBuzzer) {
                        isPausedByBuzzer = false;
                        $('#hostQuestionBuzzerBanner').slideUp(200);
                        $('#hostQReviewModal').hide();
                        $('#timerBox').css('opacity', '1');
                        if (data.paused_remaining_seconds) {
                            remaining = data.paused_remaining_seconds;
                            $('#timerSeconds').text(remaining);
                        }
                        playQuestionAudio();
                    }
                }
            }

            if (data.status === 'finished') {
                clearInterval(pollInterval);
                clearInterval(timerInterval);
                stopQuestionAudio();
                window.location.href = 'leaderboard.php?final=true';
                return;
            }

            if (!isPausedByBuzzer) {
                const allAnswered = (data.total_players > 0 && data.voted_count >= data.total_players);
                const allEliminated = (data.active_players !== undefined && data.active_players === 0);
                if (allAnswered || allEliminated || (data.status && data.status.startsWith('res_'))) {
                    clearInterval(pollInterval);
                    clearInterval(timerInterval);
                    stopQuestionAudio();
                    window.location.href = finishQuestionUrl;
                }
            }
        }
    });
}, 800);

function hostConfirmQGuess(isCorrect) {
    $.post('<?= BASE_URL ?>modules/api/obstacle_action.php?action=host_confirm_guess&id_room=' + roomId, {
        decision: isCorrect ? 'true' : 'false'
    }, function(res) {
        if (res && res.success && res.correct) {
            window.location.href = 'leaderboard.php?final=true';
        } else {
            isPausedByBuzzer = false;
            $('#hostQReviewModal').hide();
            $('#hostQuestionBuzzerBanner').slideUp(200);
            $('#timerBox').css('opacity', '1');
            playQuestionAudio();
        }
    }, 'json');
}

function hostCancelBuzzFromQuestion() {
    if (!confirm('Are you sure you want to cancel this buzzer turn? The player will forfeit buzzer rights.')) return;
    $.post('<?= BASE_URL ?>modules/api/obstacle_action.php?action=host_cancel_buzz&id_room=' + roomId, function(res) {
        isPausedByBuzzer = false;
        $('#hostQReviewModal').hide();
        $('#hostQuestionBuzzerBanner').slideUp(200);
        $('#timerBox').css('opacity', '1');
        playQuestionAudio();
    }, 'json');
}
</script>
</body>
</html>