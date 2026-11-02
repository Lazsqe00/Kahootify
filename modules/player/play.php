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

$playerStatus = strtoupper((string)($player['status'] ?? 'ACTIVE'));
$isEliminated = ($playerStatus === 'ELIMINATED');
$isSpectator = ($playerStatus === 'SPECTATOR');
$currentScore = intval($player['score'] ?? 0);
$isObstacleMode = (($room['game_type'] ?? 'classic') === 'obstacle');
$isBuzzerLocked = ($isObstacleMode && !empty($player['is_disqualified_obstacle']));
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
$remainingSeconds = max(0, $timeLimit - $elapsed);

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
if ($isResultPhase) {
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
    $rankedBoard = WagerCalculator::calculateFinalRanking($leaderboard);
    foreach ($rankedBoard as $p) {
        if ($p['id_player'] == $playerId) {
            $myRank = $p['rank'] ?? '-';
            break;
        }
    }
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

<audio id="submitSfx" preload="auto" src="<?= BASE_URL ?>assets/audio/<?= rawurlencode('submit_answer_tension_002.mp3') ?>"></audio>

<div class="d-flex justify-content-between align-items-center px-3 py-2 text-white shadow-sm flex-wrap" style="background-color: var(--kahoot-purple-dark); gap: 10px;">
    <div class="font-weight-bold d-flex align-items-center">
        <i class="fas fa-user mr-1 text-warning"></i> <?= htmlspecialchars($nickname) ?>
        <span class="badge badge-light text-dark font-weight-bold ml-2 px-2 py-1 shadow-sm" style="font-size: 0.85rem;">
            Question <?= $questionNumber ?> / <?= $totalQuestions ?>
        </span>
        <?php if ($questionMode === 'wager'): ?>
            <span class="badge badge-warning text-dark font-weight-bold ml-1 px-2 py-1 shadow-sm" style="font-size: 0.85rem;">
                <i class="fas fa-dice mr-1"></i> Wager
            </span>
        <?php endif; ?>
        <?php if ($isObstacleMode && !empty($currentQuestion['piece_index'])): ?>
            <span class="badge badge-warning text-dark font-weight-bold ml-2 px-2 py-1 shadow-sm vcnv-badge" style="font-size: 0.85rem;">
                <i class="fas fa-puzzle-piece mr-1"></i> Mảnh <?= intval($currentQuestion['piece_index']) ?>
            </span>
        <?php endif; ?>
        <?php if ($isBuzzerLocked): ?>
            <span class="badge badge-warning text-dark font-weight-bold ml-2 px-2 py-1 shadow-sm" style="font-size: 0.85rem;" title="Tạm khóa chuông trong các mảnh ghép">
                <i class="fas fa-lock mr-1"></i> Tạm khóa chuông
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
            Score: <?= number_format($player['score']) ?>
        </div>
        <a href="<?= BASE_URL ?>index.php" class="btn btn-sm btn-outline-light font-weight-bold rounded-pill px-3" onclick="return confirm('Bạn có chắc muốn rời phòng và quay về trang chủ không?');" title="Rời phòng về trang chủ">
            <i class="fas fa-home mr-1"></i> Trang chủ
        </a>
    </div>
</div>

<?php
// =========================================================================
// VIEW A: RESULT PHASE
// =========================================================================
if ($isResultPhase):
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
                        Điểm số của bạn đã về 0 sau khi thua cược. Bạn sẽ theo dõi các câu hỏi còn lại với tư cách khán giả.
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
                            <h3 class="font-weight-bold mb-0">Bỏ qua câu này</h3>
                            <p class="mb-0 font-weight-bold" style="font-size: 1.15rem; color: #fff;">
                                +0 pts (Không đặt cược - Điểm số giữ nguyên)
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
                                <?= $wagerWon ? 'Thắng Cược!' : 'Thua Cược!' ?>
                            </h3>
                            <p class="mb-0 font-weight-bold" style="font-size: 1.25rem; color: #fff;">
                                <?= $wagerWon ? '+' : '' ?><?= number_format($wagerPointsChange) ?> pts
                            </p>
                            <small style="opacity: 0.9;" class="font-weight-bold">
                                Mức cược: <?= htmlspecialchars($myWagerTierLabel ?? ($myWagerTier ?? '')) ?> (<?= number_format($myWagerAmount) ?> pts)
                            </small>
                        </div>
                    </div>
                </div>
            </div>

        <?php elseif ($isEliminated || $isSpectator): ?>
            <div class="card border-0 shadow text-white text-center mb-3"
                 style="background: linear-gradient(135deg, #495057, #343a40); border-radius: 16px;">
                <div class="card-body py-3 px-4">
                    <div style="font-size: 2.5rem;" class="mb-1">👀</div>
                    <h4 class="font-weight-bold mb-0">Chế độ khán giả</h4>
                    <small>Bạn đang theo dõi kết quả của các người chơi khác.</small>
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

        <!-- Rank Card -->
        <div class="card border-0 shadow-sm mb-3 text-center" style="border-radius: 14px; background: #ffffff;">
            <div class="card-body py-2 px-3">
                <div class="d-flex justify-content-around align-items-center">
                    <div>
                        <span class="text-muted small text-uppercase font-weight-bold d-block">Your Rank</span>
                        <span class="font-weight-bold text-warning" style="font-size: 1.6rem;">#<?= $myRank ?></span>
                        <span class="text-muted small">/ <?= count($leaderboard) ?></span>
                    </div>
                    <div style="height: 35px; width: 1px; background: #e9ecef;"></div>
                    <div>
                        <span class="text-muted small text-uppercase font-weight-bold d-block">Current Score</span>
                        <span class="font-weight-bold text-success" style="font-size: 1.6rem;">
                            <?= number_format($player['score']) ?>
                        </span>
                        <span class="text-muted small">pts</span>
                    </div>
                </div>
            </div>
        </div>

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
            <div class="card-body p-2" style="max-height: 360px; overflow-y: auto;">
                <?php foreach ($leaderboard as $idx => $p):
                    $rank = $idx + 1;
                    $isMe = ($p['id_player'] == $playerId);
                    $gained = intval($p['points_gained'] ?? 0);
                    $startScore = max(0, intval($p['score']) - $gained);
                    $pStatus = strtoupper((string)($p['status'] ?? 'ACTIVE'));
                ?>
                    <div class="d-flex align-items-center justify-content-between p-2 mb-2 rounded"
                         style="border: <?= $isMe ? '2px solid #f59f00' : '1px solid #e9ecef' ?>; background-color: <?= $isMe ? '#fff9db' : '#f8f9fa' ?>;">
                        <div class="d-flex align-items-center" style="gap: 8px; overflow: hidden;">
                            <div class="font-weight-bold text-center" style="width: 30px; font-size: 1.1rem;">
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
                                  data-target="<?= intval($p['score']) ?>"
                                  data-gained="<?= $gained ?>"
                                  style="font-size: 1.05rem;">
                                <?= number_format($p['score']) ?>
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
    </div>

    <script>
    window.addEventListener('DOMContentLoaded', function() {
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
    $wagerTimeLimit = intval($modeConfig['wager_time_sec'] ?? 10);
    if ($wagerTimeLimit <= 0) $wagerTimeLimit = 10;
    $remainingWager = max(0, $wagerTimeLimit - $elapsed);

    $tier10 = WagerCalculator::calculateWagerAmount($currentScore, 0.10, $modeConfig);
    $tier25 = WagerCalculator::calculateWagerAmount($currentScore, 0.25, $modeConfig);
    $tier50 = WagerCalculator::calculateWagerAmount($currentScore, 0.50, $modeConfig);
    $tier100 = WagerCalculator::calculateWagerAmount($currentScore, 1.00, $modeConfig);
    ?>

    <div class="container my-3" style="max-width: 650px;">
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
        <?php else: ?>
            <!-- ACTIVE PLAYER WAGER UI -->
            <div class="card border-0 shadow text-center p-3 mb-3" style="border-radius: 16px; background: #ffffff;">
                <div class="d-flex justify-content-between align-items-center mb-2 px-2">
                    <span class="badge badge-warning text-dark font-weight-bold px-3 py-2" style="font-size: 0.95rem;">
                        <i class="fas fa-dice mr-1"></i> WAGER POINTS
                    </span>
                    <div id="wagerTimerBadge" class="badge badge-dark px-3 py-2 font-weight-bold <?= $remainingWager <= 3 ? 'bg-danger' : '' ?>" style="font-size: 1.1rem; border-radius: 20px;">
                        ⏱️ <span id="wagerTimer"><?= $remainingWager ?></span>s
                    </div>
                </div>

                <h3 class="font-weight-bold text-dark mb-1">CHOOSE WAGER AMOUNT</h3>
                <p class="text-muted small mb-2">
                    The question will appear after wagering time ends. You must wager at least 10% of your score. An incorrect answer deducts your wagered points!
                </p>

                <?php if ($myWagerTier !== null): ?>
                    <div class="alert alert-success py-2 font-weight-bold mb-3 shadow-sm" id="wagerStatusAlert">
                        <i class="fas fa-check-circle mr-1"></i> Selected: <strong id="selectedTierLabel"><?= htmlspecialchars($myWagerTierLabel ?? $myWagerTier) ?></strong> (<?= number_format($myWagerAmount) ?> pts)
                        <div class="small font-weight-normal text-muted mt-1">You can choose a different amount before time runs out.</div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info py-2 font-weight-bold mb-3 shadow-sm" id="wagerStatusAlert">
                        <i class="fas fa-info-circle mr-1"></i> Please choose a wager amount before time runs out!
                    </div>
                <?php endif; ?>

                <div class="row" style="gap: 12px 0;">
                    <!-- 10% -->
                    <div class="col-6">
                        <button type="button" class="btn btn-block py-3 font-weight-bold text-white wager-tier-btn <?= in_array($myWagerTier, ['tier_10', '10%'], true) ? 'active-tier' : '' ?>"
                                data-tier="tier_10" onclick="submitWagerTier('tier_10')"
                                style="background: #1368ce; border-radius: 14px; font-size: 1.15rem; box-shadow: 0 4px 10px rgba(19, 104, 206, 0.3);">
                            <div>10%</div>
                            <div class="small font-weight-normal"><?= number_format($tier10) ?> pts</div>
                        </button>
                    </div>


                    <!-- 25% -->
                    <div class="col-6">
                        <button type="button" class="btn btn-block py-3 font-weight-bold text-white wager-tier-btn <?= in_array($myWagerTier, ['tier_25', '25%'], true) ? 'active-tier' : '' ?>"
                                data-tier="tier_25" onclick="submitWagerTier('tier_25')"
                                style="background: #26890c; border-radius: 14px; font-size: 1.15rem; box-shadow: 0 4px 10px rgba(38, 137, 12, 0.3);">
                            <div>25%</div>
                            <div class="small font-weight-normal"><?= number_format($tier25) ?> pts</div>
                        </button>
                    </div>

                    <!-- 50% -->
                    <div class="col-6">
                        <button type="button" class="btn btn-block py-3 font-weight-bold text-dark wager-tier-btn <?= in_array($myWagerTier, ['tier_50', '50%'], true) ? 'active-tier' : '' ?>"
                                data-tier="tier_50" onclick="submitWagerTier('tier_50')"
                                style="background: #ffa602; border-radius: 14px; font-size: 1.15rem; box-shadow: 0 4px 10px rgba(255, 166, 2, 0.3);">
                            <div>50%</div>
                            <div class="small font-weight-normal"><?= number_format($tier50) ?> pts</div>
                        </button>
                    </div>

                    <!-- ALL-IN (100%) -->
                    <div class="col-6">
                        <button type="button" class="btn btn-block py-3 font-weight-bold text-white wager-tier-btn <?= in_array($myWagerTier, ['tier_100', '100%', 'ALL-IN'], true) ? 'active-tier' : '' ?>"
                                data-tier="tier_100" onclick="submitWagerTier('tier_100')"
                                style="background: linear-gradient(135deg, #e21b3c, #ff3366); border-radius: 14px; font-size: 1.15rem; box-shadow: 0 4px 14px rgba(226, 27, 60, 0.45);">
                            <div>🔥 ALL-IN</div>
                            <div class="small font-weight-normal"><?= number_format($tier100) ?> pts</div>
                        </button>
                    </div>
                </div>
            </div>

            <style>
            .wager-tier-btn {
                transition: transform 0.15s ease, box-shadow 0.15s ease;
                border: 3px solid transparent;
            }
            .wager-tier-btn:hover {
                transform: translateY(-2px);
            }
            .wager-tier-btn.active-tier {
                border-color: #ffd700 !important;
                box-shadow: 0 0 16px rgba(255, 215, 0, 0.8) !important;
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
            if (wagerSeconds <= 3 && wagerTimerBadge) {
                wagerTimerBadge.classList.add('bg-danger');
            }
        }
    }, 1000);

    function submitWagerTier(tierId) {
        $('.wager-tier-btn').removeClass('active-tier');
        $('[data-tier="' + tierId + '"]').addClass('active-tier');

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
                if (res && res.success) {
                    const fallbackMap = {
                        'tier_10': '10%',
                        'tier_25': '25%',
                        'tier_50': '50%',
                        'tier_100': '100% (ALL-IN)'
                    };
                    const tierLabel = res.tier || res.tier_label || fallbackMap[tierId] || tierId;
                    $('#wagerStatusAlert').removeClass('alert-info').addClass('alert-success')
                        .html('<i class="fas fa-check-circle mr-1"></i> Selected: <strong>' + tierLabel + '</strong> (' + res.wager_amount.toLocaleString() + ' pts)<div class="small font-weight-normal text-muted mt-1">You can choose a different amount before time runs out.</div>');
                } else {
                    alert(res.message || 'Unable to place wager');
                }
            },
            error: function() {
                alert('Connection error while placing wager.');
            }
        });
    }
    </script>

<?php
// =========================================================================
// VIEW C: QUESTION PHASE
// =========================================================================
elseif ($phase === 'question'):
    if ($isEliminated): ?>
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
        <!-- SITTING OUT: Player missed wager time window -->
        <div class="d-flex align-items-center justify-content-center text-center text-white backgroundColorChanges" style="min-height: calc(100vh - 55px);">
            <div class="p-4" style="max-width: 500px;">
                <div style="font-size: 4rem;" class="mb-3">⏸️</div>
                <h2 class="font-weight-bold mb-2">You did not place a wager, skipping this question</h2>
                <p class="lead font-weight-bold mb-4">Your score remains unchanged. Get ready for the next question!</p>
                <div class="spinner-grow text-light" role="status"></div>
            </div>
        </div>

    <?php elseif ($myVote): ?>
        <!-- ALREADY VOTED -->
        <div class="d-flex align-items-center justify-content-center text-center text-white backgroundColorChanges" style="min-height: calc(100vh - 55px);">
            <div class="p-4">
                <div style="font-size: 4rem;" class="mb-3">⏳</div>
                <h2 class="font-weight-bold mb-2">Answer Submitted!</h2>
                <?php if ($questionMode === 'wager'): ?>
                    <p class="font-weight-bold text-warning mb-2" style="font-size: 1.15rem;">
                        <i class="fas fa-dice mr-1"></i> Wager: <?= htmlspecialchars($myWagerTierLabel ?? ($myWagerTier ?? '')) ?> (<?= number_format($myWagerAmount) ?> pts)
                    </p>
                <?php endif; ?>
                <p class="lead font-weight-bold mb-4">Look up at the host screen for results when time is up!</p>
                <div class="spinner-grow text-light" role="status"></div>
            </div>
        </div>

    <?php else: ?>
        <!-- ACTIVE PARTICIPANT ANSWERING VIEW -->
        <?php if ($questionMode === 'wager'): ?>
            <div class="container mt-2 mb-0" style="max-width: 1050px;">
                <div class="alert alert-warning text-center font-weight-bold py-2 mb-2 shadow-sm" style="border-radius: 12px; font-size: 1.05rem;">
                    <i class="fas fa-dice mr-1"></i> Your wager for this question:
                    <span class="badge badge-dark ml-1 px-3 py-1"><?= htmlspecialchars($myWagerTierLabel ?? ($myWagerTier ?? '')) ?> (<?= number_format($myWagerAmount) ?> pts)</span>
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

    <?php endif; ?>

    <?php if ($isObstacleMode): ?>
        <div class="text-center my-4">
            <?php if ($isBuzzerLocked): ?>
                <button type="button" class="btn btn-secondary font-weight-bold shadow-sm rounded-pill px-4 py-2" disabled>
                    <i class="fas fa-lock mr-1"></i> TẠM KHÓA CHUÔNG (CHỜ MỞ HẾT MẢNH)
                </button>
            <?php else: ?>
                <button type="button" class="btn btn-danger font-weight-bold shadow rounded-pill px-4 py-3" style="font-size: 1.2rem; font-family: 'Montserrat', sans-serif;" onclick="claimBuzzerFromQuestion()">
                    <i class="fas fa-bell mr-2"></i> ĐOÁN BỨC TRANH
                </button>
            <?php endif; ?>
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

function claimBuzzerFromQuestion() {
    if (isBuzzerLocked) {
        alert('Bạn đang bị tạm khóa chuông trong các mảnh ghép này!');
        return;
    }
    $.post('<?= BASE_URL ?>modules/api/obstacle_action.php?action=buzz&id_room=' + roomId, {
        id_player: playerId,
        token: playerToken
    }, function(res) {
        if (res && res.success && res.your_turn) {
            var guess = prompt('🔔 LƯỢT ĐOÁN CỦA BẠN (20 giây):\nNhập từ khóa bức tranh bí mật:');
            if (guess && guess.trim()) {
                $.post('<?= BASE_URL ?>modules/api/obstacle_action.php?action=submit_guess&id_room=' + roomId, {
                    id_player: playerId,
                    token: playerToken,
                    guess: guess.trim()
                }, function(res2) {
                    if (res2 && res2.success && res2.correct) {
                        alert('🏆 CHÚC MỪNG! Bạn đã đoán chính xác từ khóa bức tranh!');
                    } else if (res2 && res2.all_disqualified) {
                        alert('Tất cả người chơi đều đã đoán sai! Chuyển sang bảng vinh danh.');
                    } else {
                        alert(res2.message || 'Đáp án chưa chính xác!');
                    }
                    refreshPlayerPage();
                }, 'json');
            } else {
                $.post('<?= BASE_URL ?>modules/api/obstacle_action.php?action=buzzer_timeout&id_room=' + roomId);
                refreshPlayerPage();
            }
        } else {
            alert(res.message || 'Không thể bấm chuông lúc này');
        }
    }, 'json').fail(function() {
        alert('Lỗi kết nối máy chủ');
    });
}

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

<?php if ($showOnDevice && $phase === 'question' && !$myVote && !$isSittingOut && !$isEliminated && !$isSpectator): ?>
let playerSeconds = <?= intval($remainingSeconds) ?>;
const timerEl = document.getElementById('playerTimer');
const timerBox = document.getElementById('playerTimerBox');

if (playerSeconds <= 0) {
    $('.answer-box, .player-btn').css('pointer-events', 'none').css('opacity', '0.6');
} else if (timerEl) {
    const playerCountdown = setInterval(function() {
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
<?php endif; ?>

setInterval(function() {
    $.getJSON('<?= BASE_URL ?>modules/api/game_state.php?action=get_room_status&id_room=' + roomId + '&pid=' + playerId + '&token=' + encodeURIComponent(playerToken), function(res) {
        if (res && res.kicked) {
            refreshPlayerPage();
            return;
        }
        if (res && res.success) {
            if (res.status !== currentStatus) {
                refreshPlayerPage();
            }
        }
    });
}, 1000);
</script>
</body>
</html>