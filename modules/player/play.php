<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/room_access.php';
require_once __DIR__ . '/../../includes/modes/GameStateManager.php';
require_once __DIR__ . '/../../includes/modes/QuestionModeRegistry.php';
require_once __DIR__ . '/../../includes/modes/WagerCalculator.php';

$playerId = intval($_GET['pid'] ?? ($_SESSION['id_player'] ?? 0));
$token    = trim($_GET['token'] ?? '');
$roomId   = intval($_SESSION['player_room_id'] ?? 0);
$nickname = $_SESSION['player_nickname'] ?? 'Player';

$player = null;
if ($playerId > 0) {
    $player = db_fetch_one("SELECT * FROM players WHERE id_player = :p", [':p' => $playerId]);
    if ($player) {
        $roomId   = intval($player['id_room']);
        $nickname = $player['nickname'];
    }
}

if ($roomId <= 0 || $playerId <= 0 || !$player || !player_request_is_valid($playerId, $roomId, $token)) {
    redirect('modules/player/join.php');
}

$room = db_fetch_one("SELECT * FROM room WHERE id_room = :r", [':r' => $roomId]);

if (!$room) {
    redirect('modules/player/join.php');
}

$status = $room['status'];
if (player_is_kicked($room, $playerId)) redirect_removed_player($playerId);

if ($status === 'waiting') {
    redirect('modules/player/waiting.php?pid=' . $playerId . '&token=' . urlencode($token));
}

if (($room['game_type'] ?? 'classic') === 'obstacle' && $status === 'board') {
    require __DIR__ . '/../../includes/player_obstacle_board.php';
    exit;
}

if ($status === 'finished') {
    $leaderboard = get_room_leaderboard($roomId);
    $leaderboard = WagerCalculator::calculateFinalRanking($leaderboard);
    $resultsPlayerId = $playerId;
    require __DIR__ . '/../../includes/final_results.php';
    exit;
}

$parsed = GameStateManager::parseStatus($status);
$phase = $parsed['phase'];
$qId = $parsed['question_id'];
$isResultPhase = ($phase === 'result');
$isLeaderboardPhase = ($phase === 'leaderboard');

$playerStatus = strtoupper((string)($player['status'] ?? 'ACTIVE'));
$isEliminated = ($playerStatus === 'ELIMINATED');
$isSpectator = ($playerStatus === 'SPECTATOR');
$currentScore = intval($player['score'] ?? 0);
$isObstacleMode = (($room['game_type'] ?? 'classic') === 'obstacle');
$isBuzzerLocked = ($isObstacleMode && !empty($player['is_disqualified_obstacle']));
$openedPieces = !empty($room['opened_pieces']) ? json_decode($room['opened_pieces'], true) : [];
if (!is_array($openedPieces)) $openedPieces = [];
$hasOpenedAnyPiece = (count($openedPieces) > 0);
$isDisqualified = false;

$currentQuestion = db_fetch_one("SELECT * FROM questions WHERE id_question = :qid", [':qid' => $qId]);
$questionMode = $currentQuestion['mode'] ?? 'normal';
$modeConfig = !empty($currentQuestion['mode_config']) ? json_decode($currentQuestion['mode_config'], true) : [];

$playerLog = db_fetch_one("SELECT * FROM player_question_logs WHERE id_room = :r AND id_question = :q AND id_player = :p", [
    ':r' => $roomId,
    ':q' => $qId,
    ':p' => $playerId
]);
$isSittingOut = !empty($playerLog['is_sitting_out']);
$myWagerTier = $playerLog['wager_tier'] ?? null;
$myWagerTierLabel = $myWagerTier ? WagerCalculator::getTierLabel($myWagerTier) : null;
$myWagerAmount = intval($playerLog['wager_amount'] ?? 0);

$answers = db_fetch_all("SELECT * FROM answers WHERE id_question = :qid ORDER BY id_answer ASC", [':qid' => $qId]);
$timeLimit = intval($currentQuestion['time_limit'] ?? 20);
if ($timeLimit <= 0) {
    $timeLimit = 20;
}

$startTime = !empty($room['question_start_time']) ? intval($room['question_start_time']) : time();
$elapsed = max(0, time() - $startTime);
$getReadyDuration = 3;
$getReadyRemaining = max(0, $getReadyDuration - $elapsed);
$isGetReadyActive = ($getReadyRemaining > 0);
$answerElapsed = max(0, $elapsed - $getReadyDuration);
$remainingSeconds = max(0, $timeLimit - $answerElapsed);

$showOnDevice = !empty($room['show_questions_on_players']);

$hasQuestionImage = false;
$questionImageUrl = '';
$questionMediaType = 'image';
if (!empty($currentQuestion['image'])) {
    if (preg_match('#^https?://#i', $currentQuestion['image'])) {
        $hasQuestionImage = true;
        $questionImageUrl = $currentQuestion['image'];
        $ext = strtolower(pathinfo(parse_url($questionImageUrl, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
        $questionMediaType = (in_array($ext, ['mp4', 'webm']) || ($currentQuestion['media_type'] ?? '') === 'video') ? 'video' : 'image';
    } else {
        $imgFilename = basename($currentQuestion['image']);
        $imgPath = dirname(__DIR__, 2) . '/assets/img/questions/' . $imgFilename;
        if (file_exists($imgPath)) {
            $hasQuestionImage = true;
            $questionImageUrl = BASE_URL . 'assets/img/questions/' . rawurlencode($imgFilename);
            $ext = strtolower(pathinfo($imgFilename, PATHINFO_EXTENSION));
            $questionMediaType = (in_array($ext, ['mp4', 'webm']) || ($currentQuestion['media_type'] ?? '') === 'video') ? 'video' : 'image';
        }
    }
}

$myVote = db_fetch_one(
    "SELECT v.*, a.is_correct
     FROM votes v JOIN answers a ON v.id_answer = a.id_answer
     WHERE v.id_player = :pid AND a.id_question = :qid LIMIT 1",
    [':pid' => $playerId, ':qid' => $qId]
);

$leaderboard = [];
$myRank = 1;
if ($isResultPhase || $isLeaderboardPhase) {
    $leaderboardSql = "
        SELECT p.*,
               COALESCE(l.points_change, l.delta_score, COALESCE(v.points_earned + v.streak_bonus, 0)) as points_gained,
               COALESCE(a.is_correct, 0) as is_round_correct
        FROM players p
        LEFT JOIN player_question_logs l ON l.id_player = p.id_player AND l.id_question = :qid AND l.id_room = :r
        LEFT JOIN votes v ON v.id_player = p.id_player
             AND v.id_answer IN (SELECT id_answer FROM answers WHERE id_question = :qid2)
        LEFT JOIN answers a ON v.id_answer = a.id_answer
        WHERE p.id_room = :r2
        ORDER BY p.score DESC, p.playeradd ASC, p.id_player ASC
    ";
    $leaderboard = db_fetch_all($leaderboardSql, [':r' => $roomId, ':qid' => $qId, ':qid2' => $qId, ':r2' => $roomId]);
    if (empty($leaderboard)) {
        $leaderboard = db_fetch_all("SELECT * FROM players WHERE id_room = :r ORDER BY score DESC, playeradd ASC, id_player ASC", [':r' => $roomId]);
    }
    $leaderboard = filter_room_players($roomId, $leaderboard);

    // Compute previous scores, final scores, and final ranks for FLIP animation
    foreach ($leaderboard as &$p) {
        $p['final_score'] = max(0, intval($p['score']));
        $gained = intval($p['points_gained'] ?? 0);
        $p['prev_score'] = max(0, $p['final_score'] - $gained);
    }
    unset($p);

    $finalSorted = $leaderboard;
    usort($finalSorted, function($a, $b) {
        if ($b['final_score'] !== $a['final_score']) {
            return $b['final_score'] <=> $a['final_score'];
        }
        return ($a['id_player'] ?? 0) <=> ($b['id_player'] ?? 0);
    });
    $finalRankMap = [];
    foreach ($finalSorted as $fIdx => $fP) {
        $finalRankMap[$fP['id_player']] = $fIdx + 1;
    }

    $prevSorted = $leaderboard;
    usort($prevSorted, function($a, $b) {
        if ($b['prev_score'] !== $a['prev_score']) {
            return $b['prev_score'] <=> $a['prev_score'];
        }
        return ($a['id_player'] ?? 0) <=> ($b['id_player'] ?? 0);
    });
    $prevRankMap = [];
    foreach ($prevSorted as $pIdx => $pP) {
        $prevRankMap[$pP['id_player']] = $pIdx + 1;
    }

    $myInitialRank = $prevRankMap[$playerId] ?? 1;
    $myFinalRank   = $finalRankMap[$playerId] ?? 1;
    $myRank        = $myInitialRank;

    // Initially sort by prev_score so student sees previous rank position
    $leaderboard = $prevSorted;
}

$selectedQIds = !empty($room['question_order']) ? json_decode($room['question_order'], true) : null;
if (!is_array($selectedQIds) || empty($selectedQIds)) {
    $fallbackQuestions = db_fetch_all("SELECT id_question FROM questions WHERE id_quiz = :q ORDER BY id_question ASC", [':q' => $room['id_quiz']]);
    $selectedQIds = array_column($fallbackQuestions, 'id_question');
}
$totalQuestions = count($selectedQIds);
$currentQNum = array_search((int)$qId, $selectedQIds);
$questionNumber = ($currentQNum !== false) ? ($currentQNum + 1) : 1;

$pageTitle = "Question " . $questionNumber . "/" . $totalQuestions . " - Kahoot! | " . htmlspecialchars($nickname);
$bodyClass = "backgroundColorChanges";
require_once __DIR__ . '/../../includes/header.php';
?>
<style>
.player-get-ready-wrapper {
    max-width: 580px;
    margin: 25px auto;
    padding: 0 15px;
}
.player-get-ready-card {
    background: #ffffff;
    border-radius: 20px;
    padding: 30px 24px 25px;
    border-bottom: 5px solid #cbd5e1;
    box-shadow: 0 12px 35px rgba(0,0,0,0.25);
}
.player-get-ready-pill {
    display: inline-block;
    background: #46178f;
    color: #ffffff;
    font-family: 'Montserrat', sans-serif;
    font-weight: 800;
    font-size: 0.8rem;
    letter-spacing: 1.5px;
    padding: 5px 18px;
    border-radius: 50px;
    margin-bottom: 15px;
}
.player-get-ready-circle {
    width: 85px;
    height: 85px;
    border-radius: 50%;
    background: #46178f;
    color: #ffffff;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-family: 'Montserrat', sans-serif;
    font-weight: 900;
    font-size: 2.8rem;
    box-shadow: 0 6px 20px rgba(70,23,143,0.35);
    border: 4px solid #ffffff;
    outline: 4px solid #46178f;
    transition: transform 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}
.player-get-ready-title {
    font-family: 'Montserrat', sans-serif;
    font-weight: 900;
    font-size: 1.8rem;
    color: #1e1136;
    margin-top: 15px;
    margin-bottom: 6px;
    letter-spacing: 0.5px;
}
.player-get-ready-desc {
    color: #64748b;
    font-weight: 600;
    font-size: 1rem;
    margin-bottom: 12px;
}
.player-get-ready-qtext {
    background: #f8fafc;
    border: 2px dashed #cbd5e1;
    border-radius: 12px;
    padding: 14px 18px;
    font-size: 1.1rem;
    font-weight: 700;
    color: #334155;
    margin-top: 15px;
}
</style>

<audio id="submitSfx" preload="auto" src="<?= BASE_URL ?>assets/audio/<?= rawurlencode('submit_answer_tension_002.mp3') ?>"></audio>

<div class="d-flex justify-content-between align-items-center px-3 py-2 text-white shadow-sm flex-wrap" style="background-color: var(--kahoot-purple-dark); gap: 10px;">
    <div class="font-weight-bold d-flex align-items-center">
        <i class="fas fa-user mr-1 text-warning"></i> <?= htmlspecialchars($nickname) ?>
        <span class="badge badge-light text-dark font-weight-bold ml-2 px-2 py-1 shadow-sm" style="font-size: 0.85rem;">
            Question <?= $questionNumber ?> / <?= $totalQuestions ?>
        </span>
        <?php if ($questionMode === 'wager'): ?>
            <span class="badge badge-warning text-dark font-weight-bold ml-1 px-2 py-1 shadow-sm" style="font-size: 0.85rem;">
                <i class="fas fa-star mr-1"></i> Star of Hope
            </span>
        <?php endif; ?>
        <?php if ($isObstacleMode && !empty($currentQuestion['piece_index'])): ?>
            <span class="badge badge-warning text-dark font-weight-bold ml-2 px-2 py-1 shadow-sm vcnv-badge" style="font-size: 0.85rem;">
                <i class="fas fa-puzzle-piece mr-1"></i> Piece <?= intval($currentQuestion['piece_index']) ?>
            </span>
        <?php endif; ?>
        <?php if ($isBuzzerLocked): ?>
            <span class="badge badge-warning text-dark font-weight-bold ml-2 px-2 py-1 shadow-sm" style="font-size: 0.85rem;" title="Buzzer temporarily locked during pieces">
                <i class="fas fa-lock mr-1"></i> Buzzer Locked
            </span>
        <?php elseif ($isEliminated): ?>
            <span class="badge badge-danger ml-1 px-2 py-1 font-weight-bold shadow-sm" style="font-size: 0.85rem;">
                <i class="fas fa-skull mr-1"></i> Eliminated
            </span>
        <?php elseif ($isSpectator): ?>
            <span class="badge badge-secondary ml-1 px-2 py-1 font-weight-bold shadow-sm" style="font-size: 0.85rem;">
                <i class="fas fa-eye mr-1"></i> Spectator
            </span>
        <?php endif; ?>
    </div>
    <div class="d-flex align-items-center" style="gap: 10px;">
        <div class="font-weight-bold badge badge-light text-dark px-3 py-2" style="font-size: 0.95rem;">
            Score: <?= number_format(max(0, intval($player['score']))) ?>
        </div>
        <a href="<?= BASE_URL ?>index.php" class="btn btn-sm btn-outline-light font-weight-bold rounded-pill px-3" onclick="return confirm('Are you sure you want to leave the room and return home?');" title="Return to Home">
            <i class="fas fa-home mr-1"></i> Home
        </a>
    </div>
</div>

<?php
// =========================================================================
// VIEW A: RESULT PHASE
// =========================================================================
if ($isResultPhase || $isLeaderboardPhase):
    $isWagerQuestion = ($questionMode === 'wager');
    $wagerPointsChange = $playerLog ? intval($playerLog['points_change'] ?? ($playerLog['delta_score'] ?? 0)) : 0;
    $justEliminated = ($isEliminated && intval($player['eliminated_at_question'] ?? 0) === $qId);

    $isCorrect = ($myVote && $myVote['is_correct'] == 1);
    $pointsEarned = $myVote ? intval($myVote['points_earned']) : 0;
    $streakBonus = $myVote ? intval($myVote['streak_bonus'] ?? 0) : 0;
    $currentStreak = intval($player['current_streak'] ?? 0);

    // Audio SFX
    $correctAudios = ['correct_01.mp3', 'correct_02.mp3', 'correct_03.mp3', 'correct_04.mp3', 'correct_high.mp3'];
    $incorrectAudios = ['incorrect_01.mp3', 'incorrect_02.mp3', 'incorrect_03.mp3', 'incorrect_04.mp3', 'incorrect_low.mp3'];

    if ($isWagerQuestion) {
        $resultAudioFile = ($wagerPointsChange > 0) ? 'correct_high.mp3' : 'incorrect_low.mp3';
    } else {
        $resultAudioFile = $isCorrect
            ? $correctAudios[array_rand($correctAudios)]
            : $incorrectAudios[array_rand($incorrectAudios)];
    }
    ?>
    <audio id="resultSfx" preload="auto" src="<?= BASE_URL ?>assets/audio/<?= rawurlencode($resultAudioFile) ?>"></audio>
    <audio id="pointsSfx" preload="auto" src="<?= BASE_URL ?>assets/audio/<?= rawurlencode('points.mp3') ?>"></audio>

    <div class="container py-3" style="max-width: 650px;">
        <?php if ($isDisqualified): ?>
            <div class="card border-0 shadow text-white text-center mb-3"
                 style="background: linear-gradient(135deg, #495057, #343a40); border-radius: 16px;">
                <div class="card-body py-3 px-4">
                    <div class="d-flex align-items-center justify-content-center" style="gap: 15px;">
                        <div style="font-size: 3rem; line-height: 1;">🚫</div>
                        <div class="text-left">
                            <h3 class="font-weight-bold mb-0">Disqualified</h3>
                            <p class="mb-0 font-weight-bold" style="font-size: 1rem; color: #ced4da;">
                                You guessed the secret picture incorrectly. You are spectating the rest of the game.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        <?php elseif ($justEliminated): ?>
            <div class="card border-0 shadow text-white text-center mb-3"
                 style="background: linear-gradient(135deg, #1f1f1f, #8b0000); border-radius: 16px;">
                <div class="card-body py-4 px-4">
                    <div style="font-size: 4rem; line-height: 1;" class="mb-2">💀</div>
                    <h2 class="font-weight-bold mb-1">YOU HAVE BEEN ELIMINATED!</h2>
                    <p class="font-weight-bold mb-2 text-warning" style="font-size: 1.25rem;">
                        <?= number_format($wagerPointsChange) ?> pts
                    </p>
                    <p class="mb-0" style="font-size: 1rem; opacity: 0.9;">
                        Your score reached 0 after losing the wager. You will spectate the remaining questions.
                    </p>
                </div>
            </div>

        <?php elseif ($isWagerQuestion && $isSittingOut): ?>
            <div class="card border-0 shadow text-white text-center mb-3"
                 style="background: linear-gradient(135deg, #495057, #343a40); border-radius: 16px;">
                <div class="card-body py-3 px-4">
                    <div class="d-flex align-items-center justify-content-center" style="gap: 15px;">
                        <div style="font-size: 3rem; line-height: 1;">⏸️</div>
                        <div class="text-left">
                            <h3 class="font-weight-bold mb-0">Skipped Question</h3>
                            <p class="mb-0 font-weight-bold" style="font-size: 1.15rem; color: #fff;">
                                +0 pts (No wager placed - Score unchanged)
                            </p>
                        </div>
                    </div>
                </div>
            </div>

        <?php elseif ($isWagerQuestion): ?>
            <?php $wagerWon = ($wagerPointsChange > 0); ?>
            <div class="card border-0 shadow text-white text-center mb-3"
                 style="background: <?= $wagerWon ? 'linear-gradient(135deg, #26890c, #1a6207)' : 'linear-gradient(135deg, #e21b3c, #9c1228)' ?>; border-radius: 16px;">
                <div class="card-body py-3 px-4">
                    <div class="d-flex align-items-center justify-content-center" style="gap: 15px;">
                        <div style="font-size: 3rem; line-height: 1;">
                            <?= $wagerWon ? '🎉' : '❌' ?>
                        </div>
                        <div class="text-left">
                            <h3 class="font-weight-bold mb-0">
                                <?= $wagerWon ? 'Correct!' : 'Incorrect!' ?>
                            </h3>
                            <p class="mb-0 font-weight-bold" style="font-size: 1.25rem; color: #fff;">
                                <?= $wagerPointsChange > 0 ? '+' : '' ?><?= number_format($wagerPointsChange) ?> pts
                            </p>
                            <?php if (!empty($myWagerTierLabel)): ?>
                                <small style="opacity: 0.9;" class="font-weight-bold">
                                    <i class="fas fa-star mr-1 text-warning"></i> <?= htmlspecialchars($myWagerTierLabel) ?>
                                </small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

        <?php elseif ($isEliminated || $isSpectator): ?>
            <div class="card border-0 shadow text-white text-center mb-3"
                 style="background: linear-gradient(135deg, #495057, #343a40); border-radius: 16px;">
                <div class="card-body py-3 px-4">
                    <div style="font-size: 2.5rem;" class="mb-1">👀</div>
                    <h4 class="font-weight-bold mb-0">Spectator Mode</h4>
                    <small>You are spectating the other players' results.</small>
                </div>
            </div>

        <?php else: ?>
            <!-- NORMAL QUESTION RESULT -->
            <div class="card border-0 shadow text-white text-center mb-3"
                 style="background: <?= $isCorrect ? 'linear-gradient(135deg, #26890c, #1a6207)' : 'linear-gradient(135deg, #e21b3c, #9c1228)' ?>; border-radius: 16px;">
                <div class="card-body py-3 px-4">
                    <div class="d-flex align-items-center justify-content-center" style="gap: 15px;">
                        <div style="font-size: 3rem; line-height: 1;">
                            <?= $isCorrect ? '🎉' : '❌' ?>
                        </div>
                        <div class="text-left">
                            <h3 class="font-weight-bold mb-0">
                                <?= $isCorrect ? 'Correct!' : 'Incorrect!' ?>
                            </h3>
                            <?php if ($isCorrect): ?>
                                <?php if ($streakBonus > 0): ?>
                                    <p class="mb-0 font-weight-bold" style="font-size: 1.25rem; color: #fff;">
                                        +<?= number_format($pointsEarned) ?> + <?= number_format($streakBonus) ?> Streak Bonus
                                    </p>
                                <?php else: ?>
                                    <p class="mb-0 font-weight-bold" style="font-size: 1.15rem; color: #fff;">
                                        +<?= number_format($pointsEarned) ?> pts
                                    </p>
                                <?php endif; ?>
                            <?php else: ?>
                                <p class="mb-0 font-weight-bold" style="font-size: 1.15rem; color: #fff;">
                                    +0 pts
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ($isCorrect && $currentStreak >= 2): ?>
                        <div class="mt-3">
                            <div class="badge badge-warning text-dark font-weight-bold px-3 py-2 shadow-sm" style="font-size: 0.95rem;">
                                <i class="fas fa-fire text-danger mr-1 streak-fire-anim"></i> Answer Streak: <?= $currentStreak ?> 🔥
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($isLeaderboardPhase): ?>
        <!-- Rank Card (Scoreboard Phase) -->
        <div class="card border-0 shadow-sm mb-3 text-center" style="border-radius: 14px; background: #ffffff;">
            <div class="card-body py-2 px-3">
                <div class="d-flex justify-content-around align-items-center">
                    <div>
                        <span class="text-muted small text-uppercase font-weight-bold d-block">Your Rank</span>
                        <span class="font-weight-bold text-warning" id="myRankDisplay" data-start="<?= $myInitialRank ?? $myRank ?>" data-final="<?= $myFinalRank ?? $myRank ?>" style="font-size: 1.6rem;">#<?= $myRank ?></span>
                        <span class="text-muted small">/ <?= count($leaderboard) ?></span>
                    </div>
                    <div style="height: 35px; width: 1px; background: #e9ecef;"></div>
                    <div>
                        <span class="text-muted small text-uppercase font-weight-bold d-block">Current Score</span>
                        <span class="font-weight-bold text-success" style="font-size: 1.6rem;">
                            <?= number_format(max(0, intval($player['score']))) ?>
                        </span>
                        <span class="text-muted small">pts</span>
                    </div>
                </div>
            </div>
        </div>
        <?php else: ?>
        <!-- Waiting banner while Host reviews results before showing Scoreboard -->
        <div class="card border-0 shadow-sm mb-3 text-center p-3" style="border-radius: 14px; background: rgba(255, 255, 255, 0.95);">
            <div class="d-flex align-items-center justify-content-center" style="gap: 10px;">
                <div class="spinner-grow spinner-grow-sm text-primary" role="status"></div>
                <span class="font-weight-bold text-dark" style="font-size: 1.05rem;">Look up! Host is reviewing question results.</span>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($isLeaderboardPhase): ?>
        <!-- Room Leaderboard -->
        <div class="card border-0 shadow-sm mb-3" style="border-radius: 16px; background: #ffffff;">
            <div class="card-header bg-white py-2 px-3 border-bottom d-flex align-items-center justify-content-between">
                <h6 class="font-weight-bold mb-0 text-dark">
                    <i class="fas fa-chart-line text-primary mr-1"></i> Room Leaderboard
                </h6>
                <small class="text-muted font-weight-bold">
                    <i class="fas fa-sync-alt fa-spin text-info mr-1"></i> Live Sync
                </small>
            </div>
            <div class="card-body p-2" id="playerLeaderboardBody" style="max-height: 360px; overflow-y: auto;">
                <?php foreach ($leaderboard as $idx => $p):
                    $rank = $idx + 1;
                    $isMe = ($p['id_player'] == $playerId);
                    $gained = intval($p['points_gained'] ?? 0);
                    $startScore = max(0, intval($p['score']) - $gained);
                    $pStatus = strtoupper((string)($p['status'] ?? 'ACTIVE'));
                ?>
                    <div class="d-flex align-items-center justify-content-between p-2 mb-2 rounded leaderboard-player-row"
                         data-player-id="<?= $p['id_player'] ?>"
                         data-start-rank="<?= $rank ?>"
                         data-final-rank="<?= $finalRankMap[$p['id_player']] ?? $rank ?>"
                         style="border: <?= $isMe ? '2px solid #f59f00' : '1px solid #e9ecef' ?>; background-color: <?= $isMe ? '#fff9db' : '#f8f9fa' ?>;">
                        <div class="d-flex align-items-center" style="gap: 8px; overflow: hidden;">
                            <div class="font-weight-bold text-center player-rank-badge" style="width: 30px; font-size: 1.1rem;">
                                <?php if ($rank === 1): ?>
                                    <span>🥇</span>
                                <?php elseif ($rank === 2): ?>
                                    <span>🥈</span>
                                <?php elseif ($rank === 3): ?>
                                    <span>🥉</span>
                                <?php else: ?>
                                    <span class="text-muted" style="font-size: 0.95rem;">#<?= $rank ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="text-truncate" style="font-size: 0.95rem;">
                                <span class="font-weight-bold <?= $isMe ? 'text-dark' : 'text-secondary' ?>">
                                    <?= htmlspecialchars($p['nickname']) ?>
                                </span>
                                <?php if ($isMe): ?>
                                    <span class="badge badge-warning text-dark font-weight-bold ml-1 px-2" style="font-size: 0.75rem;">YOU</span>
                                <?php endif; ?>
                                <?php if ($pStatus === 'ELIMINATED'): ?>
                                    <span class="badge badge-dark ml-1 font-weight-bold" style="font-size: 0.72rem;">Eliminated</span>
                                <?php elseif ($pStatus === 'SPECTATOR'): ?>
                                    <span class="badge badge-secondary ml-1 font-weight-bold" style="font-size: 0.72rem;">Spectator</span>
                                <?php endif; ?>
                                <?php if (intval($p['current_streak'] ?? 0) >= 2 && $pStatus === 'ACTIVE'): ?>
                                    <span class="badge badge-danger ml-1 font-weight-bold" style="font-size: 0.75rem;">
                                        <i class="fas fa-fire streak-fire-anim"></i> <?= intval($p['current_streak']) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="text-right pl-2" style="min-width: 85px;">
                            <span class="font-weight-bold text-dark score-jump"
                                  data-start="<?= $startScore ?>"
                                  data-target="<?= max(0, intval($p['score'])) ?>"
                                  data-gained="<?= $gained ?>"
                                  style="font-size: 1.05rem;">
                                <?= number_format(($gained != 0) ? $startScore : max(0, intval($p['score']))) ?>
                            </span>
                            <?php if ($gained > 0): ?>
                                <span class="badge badge-success px-1 ml-1" style="font-size: 0.75rem;">
                                    +<?= $gained ?>
                                </span>
                            <?php elseif ($gained < 0): ?>
                                <span class="badge badge-danger px-1 ml-1" style="font-size: 0.75rem;">
                                    <?= $gained ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="text-center text-white py-2">
            <div class="spinner-grow spinner-grow-sm text-light mr-1" role="status"></div>
            <span class="font-weight-bold" style="font-size: 0.95rem; text-shadow: 1px 1px 2px rgba(0,0,0,0.5);">
                Waiting for the next question from Host...
            </span>
        </div>
        <?php endif; ?>
    </div>

    <script>
    window.addEventListener('DOMContentLoaded', function() {
        setTimeout(function() {
            $('.score-jump').each(function() {
                const el = this;
                const startVal = parseInt($(el).attr('data-start')) || 0;
                const targetVal = parseInt($(el).attr('data-target')) || 0;
                const gainedVal = parseInt($(el).attr('data-gained')) || 0;

                if (gainedVal > 0 && targetVal > startVal) {
                    $(el).addClass('text-success');
                    let startTime = null;
                    const duration = 1200;
                    function step(timestamp) {
                        if (!startTime) startTime = timestamp;
                        const progress = Math.min((timestamp - startTime) / duration, 1);
                        const ease = 1 - Math.pow(1 - progress, 3);
                        const currentVal = Math.round(startVal + (targetVal - startVal) * ease);
                        el.textContent = currentVal.toLocaleString('en-US');
                        if (progress < 1) window.requestAnimationFrame(step);
                        else el.textContent = targetVal.toLocaleString('en-US');
                    }
                    window.requestAnimationFrame(step);
                } else if (gainedVal < 0 && targetVal < startVal) {
                    $(el).addClass('text-danger');
                    let startTime = null;
                    const duration = 1200;
                    function step(timestamp) {
                        if (!startTime) startTime = timestamp;
                        const progress = Math.min((timestamp - startTime) / duration, 1);
                        const ease = 1 - Math.pow(1 - progress, 3);
                        const currentVal = Math.round(startVal - (startVal - targetVal) * ease);
                        el.textContent = Math.max(0, currentVal).toLocaleString('en-US');
                        if (progress < 1) window.requestAnimationFrame(step);
                        else el.textContent = Math.max(0, targetVal).toLocaleString('en-US');
                    }
                    window.requestAnimationFrame(step);
                }
            });

            // FLIP Animation: Re-order rows smoothly if any ranks changed
            setTimeout(function() {
                const container = document.getElementById('playerLeaderboardBody');
                if (!container) return;
                const rows = Array.from(container.querySelectorAll('.leaderboard-player-row'));
                if (rows.length <= 1) return;

                let hasChange = false;
                rows.forEach(function(r) {
                    if (r.dataset.startRank !== r.dataset.finalRank) hasChange = true;
                });
                if (!hasChange) return;

                // 1. Record initial tops
                const firstTops = new Map();
                rows.forEach(function(r) {
                    firstTops.set(r, r.getBoundingClientRect().top);
                });

                // 2. Sort rows by final rank and re-append to DOM
                rows.sort(function(a, b) {
                    const rA = parseInt(a.dataset.finalRank, 10) || 0;
                    const rB = parseInt(b.dataset.finalRank, 10) || 0;
                    return rA - rB;
                });
                rows.forEach(function(r) { container.appendChild(r); });

                // 3. Invert (apply deltaY immediately with no transition)
                rows.forEach(function(r) {
                    const newTop = r.getBoundingClientRect().top;
                    const oldTop = firstTops.get(r);
                    const deltaY = oldTop - newTop;
                    if (deltaY !== 0) {
                        r.style.transform = 'translateY(' + deltaY + 'px)';
                        r.style.transition = 'none';
                        r.style.zIndex = '5';
                    }
                });

                // 4. Play (glide smoothly to target position)
                window.requestAnimationFrame(function() {
                    window.requestAnimationFrame(function() {
                        rows.forEach(function(r) {
                            r.style.transition = 'transform 0.8s cubic-bezier(0.2, 0.8, 0.2, 1)';
                            r.style.transform = '';
                        });
                    });
                });

                function getPlayerRankSymbol(rank) {
                    if (rank === 1) return '🥇';
                    if (rank === 2) return '🥈';
                    if (rank === 3) return '🥉';
                    return '#' + rank;
                }

                setTimeout(function() {
                    rows.forEach(function(r) {
                        r.style.zIndex = '';
                        const finalRank = parseInt(r.dataset.finalRank, 10) || 1;
                        const badgeEl = r.querySelector('.player-rank-badge');
                        if (badgeEl) {
                            badgeEl.innerHTML = (finalRank <= 3) ? '<span>' + getPlayerRankSymbol(finalRank) + '</span>' : '<span class="text-muted" style="font-size: 0.95rem;">#' + finalRank + '</span>';
                        }
                    });
                    const myRankEl = document.getElementById('myRankDisplay');
                    if (myRankEl && myRankEl.dataset.final) {
                        myRankEl.textContent = '#' + myRankEl.dataset.final;
                    }
                }, 800);
            }, 1100);
        }, 500);

        const resAudio = document.getElementById('resultSfx');
        if (resAudio) {
            resAudio.volume = 0.8;
            resAudio.play().catch(function() {
                $(document).one('click touchstart', function() {
                    if (resAudio) resAudio.play().catch(function() {});
                });
            });
        }
    });
    </script>

<?php
// =========================================================================
// VIEW B: WAGER PHASE (Active players select wager tier, Spectators/Eliminated wait)
// =========================================================================
elseif ($phase === 'wager'):
    $wagerTimeLimit = max(25, intval($modeConfig['wager_time_sec'] ?? ($modeConfig['wagerTimeSec'] ?? 25)));
    $remainingWager = max(0, $wagerTimeLimit - $elapsed);

    $qBasePoints = intval($currentQuestion['points'] ?? 1000);
    if ($qBasePoints <= 0) $qBasePoints = 1000;

    $star15 = (int)round($qBasePoints * 0.15);
    $star30 = (int)round($qBasePoints * 0.30);
    $star40 = (int)round($qBasePoints * 0.40);

    $effectiveTier = $myWagerTier;
    ?>

    <div class="container my-3" style="max-width: 680px;">
        <?php if ($isEliminated): ?>
            <div class="card border-0 shadow text-white text-center py-4 px-3 mb-3" style="background: linear-gradient(135deg, #1f1f1f, #434343); border-radius: 16px;">
                <div style="font-size: 3.5rem;">💀</div>
                <h3 class="font-weight-bold mt-2">YOU HAVE BEEN ELIMINATED</h3>
                <p class="mb-0 text-muted">You have been eliminated. Watch other players finish the match!</p>
            </div>
        <?php elseif ($isSpectator): ?>
            <div class="card border-0 shadow text-white text-center py-4 px-3 mb-3" style="background: linear-gradient(135deg, #2c3e50, #3498db); border-radius: 16px;">
                <div style="font-size: 3.5rem;">👀</div>
                <h3 class="font-weight-bold mt-2">YOU ARE A SPECTATOR</h3>
                <p class="mb-0">You joined late after the match had already started.</p>
            </div>
        <?php elseif ($myWagerTier !== null): ?>
            <!-- CONFIRMATION SCREEN AFTER CHOOSING STAR OF HOPE (GREEN LIKE SUBMITTED ANSWER) -->
            <div class="d-flex align-items-center justify-content-center text-center text-white" style="min-height: calc(100vh - 120px); background: #26890c; border-radius: 20px; box-shadow: 0 12px 30px rgba(0,0,0,0.25);">
                <div class="p-4" style="max-width: 520px;">
                    <div style="font-size: 4.5rem;" class="mb-3">⭐</div>
                    <h2 class="font-weight-bold mb-2" style="font-size: 2.2rem;">Star of Hope Chosen!</h2>
                    <div class="badge badge-warning text-dark font-weight-bold px-4 py-2 my-3 shadow-sm" style="font-size: 1.3rem; border-radius: 50px;">
                        <?= htmlspecialchars($myWagerTierLabel ?? $myWagerTier) ?>
                    </div>
                    <p class="lead font-weight-bold mb-4" style="font-size: 1.15rem; opacity: 0.95;">
                        Look up at the host screen for the question when time is up!
                    </p>
                    <div class="spinner-grow text-light" role="status"></div>
                </div>
            </div>
        <?php else: ?>
            <!-- ACTIVE PLAYER STAR OF HOPE UI -->
            <div class="card border-0 shadow text-center p-3 mb-3" style="border-radius: 18px; background: #ffffff;">
                <div class="d-flex justify-content-between align-items-center mb-3 px-2">
                    <span class="badge badge-warning text-dark font-weight-bold px-3 py-2" style="font-size: 0.95rem; border-radius: 8px;">
                        <i class="fas fa-star mr-1"></i> STAR OF HOPE
                    </span>
                    <div id="wagerTimerBadge" class="badge badge-dark px-3 py-2 font-weight-bold <?= $remainingWager <= 5 ? 'bg-danger' : '' ?>" style="font-size: 1.15rem; border-radius: 20px;">
                        ⏱️ <span id="wagerTimer"><?= $remainingWager ?></span>s
                    </div>
                </div>

                <h3 class="font-weight-bold text-dark mb-3" style="font-size: 1.6rem;">Choose your Star of Hope</h3>

                <div class="row" style="gap: 12px 0;">
                    <!-- Option 1: No Star (0%) -->
                    <div class="col-md-6 col-12">
                        <button type="button" class="btn btn-block p-3 font-weight-bold text-left wager-tier-btn <?= ($effectiveTier === 'tier_0') ? 'active-tier' : '' ?>"
                                data-tier="tier_0" onclick="submitWagerTier('tier_0')"
                                style="background: #f8f9fa; border: 2px solid #dee2e6; border-radius: 14px; box-shadow: 0 3px 6px rgba(0,0,0,0.06);">
                            <div class="mb-1">
                                <span class="font-weight-bold text-dark" style="font-size: 1.15rem;">🛡️ No Star</span>
                            </div>
                            <div class="small font-weight-bold text-success mb-1">Correct: +<?= number_format($qBasePoints) ?> pts</div>
                            <div class="small font-weight-bold text-muted">Wrong: 0 pts</div>
                        </button>
                    </div>

                    <!-- Option 2: Star 15% -->
                    <div class="col-md-6 col-12">
                        <button type="button" class="btn btn-block p-3 font-weight-bold text-left wager-tier-btn <?= ($effectiveTier === 'tier_15') ? 'active-tier' : '' ?>"
                                data-tier="tier_15" onclick="submitWagerTier('tier_15')"
                                style="background: #eef6ff; border: 2px solid #b6d4fe; border-radius: 14px; box-shadow: 0 3px 6px rgba(19,104,206,0.1);">
                            <div class="mb-1">
                                <span class="font-weight-bold text-primary" style="font-size: 1.15rem;">⭐ Star 15%</span>
                            </div>
                            <div class="small font-weight-bold text-success mb-1">Correct: +<?= number_format($qBasePoints + $star15) ?> pts</div>
                            <div class="small font-weight-bold text-danger">Wrong: -<?= number_format($star15) ?> pts</div>
                        </button>
                    </div>

                    <!-- Option 3: Star 30% -->
                    <div class="col-md-6 col-12">
                        <button type="button" class="btn btn-block p-3 font-weight-bold text-left wager-tier-btn <?= ($effectiveTier === 'tier_30') ? 'active-tier' : '' ?>"
                                data-tier="tier_30" onclick="submitWagerTier('tier_30')"
                                style="background: #fbf5ff; border: 2px solid #d8b4fe; border-radius: 14px; box-shadow: 0 3px 6px rgba(121,75,196,0.12);">
                            <div class="mb-1">
                                <span class="font-weight-bold" style="font-size: 1.15rem; color: #6f42c1;">⭐⭐ Star 30%</span>
                            </div>
                            <div class="small font-weight-bold text-success mb-1">Correct: +<?= number_format($qBasePoints + $star30) ?> pts</div>
                            <div class="small font-weight-bold text-danger">Wrong: -<?= number_format($star30) ?> pts</div>
                        </button>
                    </div>

                    <!-- Option 4: Star 40% -->
                    <div class="col-md-6 col-12">
                        <button type="button" class="btn btn-block p-3 font-weight-bold text-left wager-tier-btn <?= ($effectiveTier === 'tier_40') ? 'active-tier' : '' ?>"
                                data-tier="tier_40" onclick="submitWagerTier('tier_40')"
                                style="background: #fff8f0; border: 2px solid #fed7aa; border-radius: 14px; box-shadow: 0 3px 6px rgba(234,88,12,0.15);">
                            <div class="mb-1">
                                <span class="font-weight-bold text-warning" style="font-size: 1.15rem; color: #ea580c !important;">⭐⭐⭐ Star 40%</span>
                            </div>
                            <div class="small font-weight-bold text-success mb-1">Correct: +<?= number_format($qBasePoints + $star40) ?> pts</div>
                            <div class="small font-weight-bold text-danger">Wrong: -<?= number_format($star40) ?> pts</div>
                        </button>
                    </div>
                </div>
            </div>

            <style>
            .wager-tier-btn {
                transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease;
            }
            .wager-tier-btn:hover {
                transform: translateY(-2px);
            }
            .wager-tier-btn.active-tier {
                border-color: #ffd700 !important;
                box-shadow: 0 0 0 3px #ffd700, 0 6px 16px rgba(255, 215, 0, 0.45) !important;
                transform: scale(1.02);
            }
            </style>
        <?php endif; ?>
    </div>

    <script>
    let wagerSeconds = <?= intval($remainingWager) ?>;
    const wagerTimerEl = document.getElementById('wagerTimer');
    const wagerTimerBadge = document.getElementById('wagerTimerBadge');

    const wagerCountdown = setInterval(function() {
        wagerSeconds--;
        if (wagerSeconds <= 0) {
            wagerSeconds = 0;
            clearInterval(wagerCountdown);
            if (wagerTimerEl) wagerTimerEl.textContent = '0';
            $('.wager-tier-btn').css('pointer-events', 'none').css('opacity', '0.7');
        } else {
            if (wagerTimerEl) wagerTimerEl.textContent = wagerSeconds;
            if (wagerSeconds <= 5 && wagerTimerBadge) {
                wagerTimerBadge.classList.add('bg-danger');
            }
        }
    }, 1000);

    function submitWagerTier(tierId) {
        $('.wager-tier-btn').css('pointer-events', 'none').css('opacity', '0.6');

        $.ajax({
            url: '<?= BASE_URL ?>modules/api/submit_wager.php',
            type: 'POST',
            dataType: 'json',
            contentType: 'application/json',
            data: JSON.stringify({
                id_player: <?= $playerId ?>,
                id_room: <?= $roomId ?>,
                id_question: <?= $qId ?>,
                tier_id: tierId,
                token: '<?= htmlspecialchars($token) ?>'
            }),
            success: function(res) {
                refreshPlayerPage();
            },
            error: function() {
                refreshPlayerPage();
            }
        });
    }
    </script>

<?php
// =========================================================================
// VIEW C: QUESTION PHASE
// =========================================================================
elseif ($phase === 'question'):
    if ($myVote): ?>
        <!-- ALREADY VOTED (Always show Answer Submitted! during question so answer/elimination is never leaked to others!) -->
        <div class="d-flex align-items-center justify-content-center text-center text-white backgroundColorChanges" style="min-height: calc(100vh - 55px);">
            <div class="p-4">
                <div style="font-size: 4rem;" class="mb-3">⏳</div>
                <h2 class="font-weight-bold mb-2">Answer Submitted!</h2>
                <?php if ($questionMode === 'wager'): ?>
                    <p class="font-weight-bold text-warning mb-2" style="font-size: 1.15rem;">
                        <i class="fas fa-star mr-1"></i> <?= htmlspecialchars($myWagerTierLabel ?? ($myWagerTier ?? '')) ?>
                    </p>
                <?php endif; ?>
                <p class="lead font-weight-bold mb-4">Look up at the host screen for results when time is up!</p>
                <div class="spinner-grow text-light" role="status"></div>
            </div>
        </div>

    <?php elseif ($isEliminated): ?>
        <!-- ELIMINATED FROM A PREVIOUS QUESTION -->
        <div class="d-flex align-items-center justify-content-center text-center text-white backgroundColorChanges" style="min-height: calc(100vh - 55px);">
            <div class="p-4">
                <div style="font-size: 4rem;" class="mb-3">💀</div>
                <h2 class="font-weight-bold mb-2">You have been eliminated</h2>
                <p class="lead font-weight-bold mb-4">You are spectating the match.</p>
                <div class="spinner-grow text-light" role="status"></div>
            </div>
        </div>

    <?php elseif ($isSpectator): ?>
        <div class="d-flex align-items-center justify-content-center text-center text-white backgroundColorChanges" style="min-height: calc(100vh - 55px);">
            <div class="p-4">
                <div style="font-size: 4rem;" class="mb-3">👀</div>
                <h2 class="font-weight-bold mb-2">You are a spectator</h2>
                <p class="lead font-weight-bold mb-4">You joined after the match had already started.</p>
                <div class="spinner-grow text-light" role="status"></div>
            </div>
        </div>

    <?php elseif ($questionMode === 'wager' && $isSittingOut): ?>
        <div class="d-flex align-items-center justify-content-center text-center text-white backgroundColorChanges" style="min-height: calc(100vh - 55px);">
            <div class="p-4" style="max-width: 500px;">
                <div style="font-size: 4rem;" class="mb-3">⏸️</div>
                <h2 class="font-weight-bold mb-2">You did not place a wager, skipping this question</h2>
                <p class="lead font-weight-bold mb-4">Your score remains unchanged. Get ready for the next question!</p>
                <div class="spinner-grow text-light" role="status"></div>
            </div>
        </div>

    <?php else: ?>
        <!-- ACTIVE PARTICIPANT ANSWERING VIEW -->
        <!-- PLAYER GET READY 3-2-1 SCREEN -->
        <div id="playerGetReadyView" class="player-get-ready-wrapper text-center my-4" style="<?= $isGetReadyActive ? '' : 'display: none;' ?>">
            <div class="player-get-ready-card shadow-lg mx-auto">
                <div class="player-get-ready-pill">
                    QUESTION <?= $questionNumber ?> OF <?= $totalQuestions ?>
                </div>
                
                <div class="my-3">
                    <div class="player-get-ready-circle" id="playerGetReadyCircle">
                        <span id="playerGetReadyNum"><?= $getReadyRemaining ?></span>
                    </div>
                </div>

                <h2 class="player-get-ready-title">GET READY!</h2>
                <p class="player-get-ready-desc">Look up at the screen to read the question...</p>
                
                <?php if ($showOnDevice): ?>
                    <div class="player-get-ready-qtext">
                        <?= htmlspecialchars($currentQuestion['text'] ?? '') ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div id="playerAnswersView" style="<?= $isGetReadyActive ? 'display: none;' : '' ?>">
        <?php if ($questionMode === 'wager'): ?>
            <div class="container mt-2 mb-0" style="max-width: 1050px;">
                <div class="alert alert-warning text-center font-weight-bold py-2 mb-2 shadow-sm" style="border-radius: 12px; font-size: 1.05rem;">
                    <i class="fas fa-star mr-1"></i> Star of Hope:
                    <span class="badge badge-dark ml-1 px-3 py-1"><?= htmlspecialchars($myWagerTierLabel ?? ($myWagerTier ?? '')) ?></span>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!$showOnDevice): ?>
            <div class="player-buttons-grid">
                <div class="player-btn red" onclick="submitAnswer(<?= $answers[0]['id_answer'] ?? 0 ?>)">
                    <span class="text-white">🔺</span>
                </div>

                <div class="player-btn blue" onclick="submitAnswer(<?= $answers[1]['id_answer'] ?? 0 ?>)">
                    <span class="text-white">🔷</span>
                </div>

                <div class="player-btn yellow" onclick="submitAnswer(<?= $answers[2]['id_answer'] ?? 0 ?>)">
                    <span class="text-white">🟡</span>
                </div>

                <div class="player-btn green" onclick="submitAnswer(<?= $answers[3]['id_answer'] ?? 0 ?>)">
                    <span class="text-white">🟩</span>
                </div>
            </div>
        <?php else: ?>
            <div class="container my-3" style="max-width: 1050px;">
                <div class="question-card-banner">
                    <h2 class="font-weight-bold text-dark mb-0">
                        <?= htmlspecialchars($currentQuestion['text'] ?? '') ?>
                    </h2>
                    <?php if ($hasQuestionImage): ?>
                        <div class="text-center mt-3">
                            <?php if ($questionMediaType === 'video'): ?>
                                <video src="<?= htmlspecialchars($questionImageUrl) ?>" controls autoplay muted loop class="img-fluid rounded player-thumb-img shadow-sm" style="max-height: 280px; max-width: 100%; border-radius: 12px;"></video>
                            <?php else: ?>
                                <img src="<?= htmlspecialchars($questionImageUrl) ?>" alt="Question Illustration" class="img-fluid rounded player-thumb-img shadow-sm" style="max-height: 280px; object-fit: contain; border-radius: 12px;">
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="d-flex justify-content-between align-items-center my-3 px-2">
                    <div>
                        <div id="playerTimerBox" class="timer-circle <?= $remainingSeconds <= 5 ? 'warning' : '' ?>">
                            <span id="playerTimer"><?= $remainingSeconds ?></span>
                        </div>
                    </div>

                    <div class="text-right">
                        <span class="badge badge-light px-4 py-2 font-weight-bold text-dark shadow-sm" style="font-size: 1.1rem; border-radius: 50px;">
                            <i class="fas fa-hand-pointer text-primary mr-1"></i> Choose an answer
                        </span>
                    </div>
                </div>

                <div class="row">
                    <?php
                    $colors = [KAHOOT_COLOR_RED, KAHOOT_COLOR_BLUE, KAHOOT_COLOR_YELLOW, KAHOOT_COLOR_GREEN];
                    $icons  = [KAHOOT_ICON_RED, KAHOOT_ICON_BLUE, KAHOOT_ICON_YELLOW, KAHOOT_ICON_GREEN];
                    $colorClasses = ['red', 'blue', 'yellow', 'green'];

                    foreach ($answers as $idx => $a):
                        $cIdx = $idx % 4;
                        $ansId = intval($a['id_answer'] ?? 0);
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
                            <div class="answer-box shadow-sm d-flex justify-content-between align-items-center <?= $colorClasses[$cIdx] ?>"
                                 onclick="submitAnswer(<?= $ansId ?>)">
                                <div class="d-flex align-items-center flex-grow-1 mr-2">
                                    <span class="symbol mr-3" style="font-size: 2rem;"><?= $icons[$cIdx] ?></span>
                                    <?php if ($ansImgUrl): ?>
                                        <img src="<?= htmlspecialchars($ansImgUrl) ?>" alt="Answer" class="img-fluid rounded mr-3 bg-white p-1" style="max-height: 50px; max-width: 75px; object-fit: contain; box-shadow: 0 2px 4px rgba(0,0,0,0.15);">
                                    <?php endif; ?>
                                    <?php if (!empty($a['text'])): ?>
                                        <span style="font-size: 1.3rem;"><?= htmlspecialchars($a['text'] ?? '') ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
        </div> <!-- Closing playerAnswersView -->

    <?php endif; ?>

    <?php if ($isObstacleMode): ?>

        <!-- MODAL NOTIFYING PAUSE TO OTHER PLAYERS -->
        <div class="player-modal-backdrop" id="obstacleRoomLockedModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.85); backdrop-filter: blur(8px); z-index: 99999; align-items: center; justify-content: center; padding: 20px;">
            <div class="player-modal-card" style="background: #1e1136; border-radius: 24px; border: 3px solid #e21b3c; max-width: 520px; width: 100%; padding: 30px; color: #fff; text-align: center; box-shadow: 0 24px 60px rgba(0,0,0,0.7);">
                <i class="fas fa-bell fa-4x text-danger mb-3"></i>
                <h4 class="font-weight-bold text-white mb-2" id="lockedPlayerNotice">
                    A PLAYER HAS BUZZED IN!
                </h4>
                <p class="text-white-50 mb-3" id="lockedPlayerSubtext">
                    Game and timer are paused for the player to answer and Host to verify. Please wait...
                </p>
                <div class="spinner-border text-danger" role="status"></div>
            </div>
        </div>
    <?php endif; ?>

<?php endif; ?>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script>
const roomId = <?= $roomId ?>;
const playerId = <?= $playerId ?>;
const playerToken = '<?= htmlspecialchars($token) ?>';
const currentStatus = '<?= $status ?>';
const isBuzzerLocked = <?= $isBuzzerLocked ? 'true' : 'false' ?>;

sessionStorage.setItem('kahoot_pid', playerId);
sessionStorage.setItem('kahoot_token', playerToken);

function refreshPlayerPage() {
    window.location.href = 'play.php?pid=' + playerId + '&token=' + encodeURIComponent(playerToken);
}

let isPausedByBuzzer = false;



function submitAnswer(answerId) {
    if (!answerId) return;

    const submitAudio = document.getElementById('submitSfx');
    if (submitAudio) {
        try {
            submitAudio.volume = 0.8;
            submitAudio.currentTime = 0;
            submitAudio.play().catch(function() {});
        } catch(e) {}
    }

    $('.answer-box, .player-btn').css('pointer-events', 'none').css('opacity', '0.7');

    $.ajax({
        url: '<?= BASE_URL ?>modules/api/submit_answer.php',
        type: 'POST',
        dataType: 'json',
        contentType: 'application/json',
        data: JSON.stringify({
            id_player: playerId,
            id_answer: answerId,
            id_room: roomId,
            token: playerToken
        }),
        success: function(res) {
            refreshPlayerPage();
        },
        error: function() {
            refreshPlayerPage();
        }
    });
}

let playerGetReadyRemaining = <?= intval($getReadyRemaining) ?>;
let playerSeconds = <?= intval($remainingSeconds) ?>;
let playerTimerStarted = false;

function startPlayerTimer() {
    if (playerTimerStarted) return;
    playerTimerStarted = true;
    const timerEl = document.getElementById('playerTimer');
    const timerBox = document.getElementById('playerTimerBox');

    if (playerSeconds <= 0) {
        $('.answer-box, .player-btn').css('pointer-events', 'none').css('opacity', '0.6');
    } else if (timerEl) {
        const playerCountdown = setInterval(function() {
            if (isPausedByBuzzer) return; // Freeze player countdown during buzzer

            playerSeconds--;
            if (playerSeconds <= 0) {
                playerSeconds = 0;
                clearInterval(playerCountdown);
                timerEl.textContent = '0';
                $('.answer-box, .player-btn').css('pointer-events', 'none').css('opacity', '0.6');
            } else {
                timerEl.textContent = playerSeconds;
                if (playerSeconds <= 5 && timerBox) {
                    timerBox.classList.add('warning');
                }
            }
        }, 1000);
    }
}

function startPlayerQuestionPhase() {
    $('#playerGetReadyView').fadeOut(200, function() {
        $('#playerAnswersView').fadeIn(200);
        <?php if ($showOnDevice && $phase === 'question' && !$myVote && !$isSittingOut && !$isEliminated && !$isSpectator): ?>
        startPlayerTimer();
        <?php endif; ?>
    });
}

$(document).ready(function() {
    if (playerGetReadyRemaining > 0) {
        const pGrInterval = setInterval(function() {
            playerGetReadyRemaining--;
            if (playerGetReadyRemaining <= 0) {
                clearInterval(pGrInterval);
                $('#playerGetReadyCircle').css('transform', 'scale(1.2)');
                $('#playerGetReadyNum').text('GO!');
                setTimeout(function() {
                    startPlayerQuestionPhase();
                }, 350);
            } else {
                $('#playerGetReadyCircle').css('transform', 'scale(1.2)');
                $('#playerGetReadyNum').text(playerGetReadyRemaining);
                setTimeout(function() {
                    $('#playerGetReadyCircle').css('transform', 'scale(1)');
                }, 200);
            }
        }, 1000);
    } else {
        <?php if ($showOnDevice && $phase === 'question' && !$myVote && !$isSittingOut && !$isEliminated && !$isSpectator): ?>
        startPlayerTimer();
        <?php endif; ?>
    }
});

setInterval(function() {
    $.getJSON('<?= BASE_URL ?>modules/api/game_state.php?action=get_room_status&id_room=' + roomId + '&pid=' + playerId + '&token=' + encodeURIComponent(playerToken), function(res) {
        if (res && res.kicked) {
            refreshPlayerPage();
            return;
        }
        if (res && res.success) {
            // Check buzzer paused status in obstacle mode
            if (res.game_type === 'obstacle' && res.buzzer_status) {
                if (res.buzzer_status === 'buzzing' || res.buzzer_status === 'pending_review') {
                    if (res.buzzer_player_id !== playerId) {
                        isPausedByBuzzer = true;
                        $('.answer-box, .player-btn').css('pointer-events', 'none').css('opacity', '0.4');
                        $('#lockedPlayerNotice').text((res.buzzer_player_name || 'ANOTHER PLAYER') + ' IS BUZZING IN!');
                        $('#obstacleRoomLockedModal').css('display', 'flex');
                    }
                } else if (res.buzzer_status === 'idle') {
                    if (isPausedByBuzzer) {
                        isPausedByBuzzer = false;
                        $('#obstacleRoomLockedModal').hide();
                        $('#obstacleGuessModal').hide();
                        $('.answer-box, .player-btn').css('pointer-events', 'auto').css('opacity', '1');
                        if (res.paused_remaining_seconds && timerEl) {
                            playerSeconds = res.paused_remaining_seconds;
                            timerEl.textContent = playerSeconds;
                        }
                        if (res.is_disqualified_obstacle) {
                            $('#btnBuzzQuestion').replaceWith('<button type="button" class="btn btn-secondary font-weight-bold shadow-sm rounded-pill px-4 py-2" disabled id="btnBuzzQuestion"><i class="fas fa-lock mr-1"></i> BUZZER LOCKED (WAIT FOR ALL PIECES)</button>');
                        }
                    }
                }
            }

            if (res.status !== currentStatus) {
                refreshPlayerPage();
            }
        }
    });
}, 800);
</script>
</body>
</html>