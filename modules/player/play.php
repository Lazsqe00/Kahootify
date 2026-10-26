<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

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

if ($status === 'finished') {
    $leaderboard = get_room_leaderboard($roomId);
    $resultsPlayerId = $playerId;
    require __DIR__ . '/../../includes/final_results.php';
    exit;
}

$isResultPhase = (strpos($status, 'res_') === 0);
$qId = $isResultPhase ? intval(str_replace('res_', '', $status)) : intval($status);

$answers = db_fetch_all("SELECT * FROM answers WHERE id_question = :qid ORDER BY id_answer ASC", [':qid' => $qId]);

$currentQuestion = db_fetch_one("SELECT * FROM questions WHERE id_question = :qid", [':qid' => $qId]);
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
if (!empty($currentQuestion['image'])) {
    $imgFilename = basename($currentQuestion['image']);
    $imgPath = dirname(__DIR__, 2) . '/assets/img/questions/' . $imgFilename;
    if (file_exists($imgPath)) {
        $hasQuestionImage = true;
        $questionImageUrl = BASE_URL . 'assets/img/questions/' . rawurlencode($imgFilename);
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
               COALESCE(v.points_earned + v.streak_bonus, 0) as points_gained,
               COALESCE(a.is_correct, 0) as is_round_correct
        FROM players p
        LEFT JOIN votes v ON v.id_player = p.id_player
             AND v.id_answer IN (SELECT id_answer FROM answers WHERE id_question = :qid)
        LEFT JOIN answers a ON v.id_answer = a.id_answer
        WHERE p.id_room = :r
        ORDER BY p.score DESC, p.playeradd ASC, p.id_player ASC
    ";
    $leaderboard = db_fetch_all($leaderboardSql, [':r' => $roomId, ':qid' => $qId]);
    if (empty($leaderboard)) {
        $leaderboard = db_fetch_all("SELECT * FROM players WHERE id_room = :r ORDER BY score DESC, playeradd ASC, id_player ASC", [':r' => $roomId]);
    }
    $leaderboard = filter_room_players($roomId, $leaderboard);
    foreach ($leaderboard as $idx => $p) {
        if ($p['id_player'] == $playerId) {
            $myRank = $idx + 1;
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
$currentQNum = array_search($qId, $selectedQIds);
$questionNumber = ($currentQNum !== false) ? ($currentQNum + 1) : 1;

$pageTitle = "Question " . $questionNumber . "/" . $totalQuestions . " - Kahoot! | " . htmlspecialchars($nickname);
$bodyClass = "backgroundColorChanges";
require_once __DIR__ . '/../../includes/header.php';
?>

<audio id="submitSfx" preload="auto" src="<?= BASE_URL ?>assets/audio/<?= rawurlencode('submit_answer_tension_002.mp3') ?>"></audio>

<div class="d-flex justify-content-between align-items-center px-3 py-2 text-white shadow-sm" style="background-color: var(--kahoot-purple-dark);">
    <div class="font-weight-bold d-flex align-items-center">
        <i class="fas fa-user mr-1 text-warning"></i> <?= htmlspecialchars($nickname) ?>
        <span class="badge badge-light text-dark font-weight-bold ml-2 px-2 py-1 shadow-sm" style="font-size: 0.85rem;">
            Question <?= $questionNumber ?> / <?= $totalQuestions ?>
        </span>
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

<?php if ($isResultPhase): ?>

    <?php
    $isCorrect = ($myVote && $myVote['is_correct'] == 1);
    $pointsEarned = $myVote ? intval($myVote['points_earned']) : 0;
    $streakBonus = $myVote ? intval($myVote['streak_bonus'] ?? 0) : 0;
    $totalRoundPoints = $pointsEarned + $streakBonus;
    $currentStreak = intval($player['current_streak'] ?? 0);

    // Audio công bố kết quả câu hỏi (SFX)
    $correctAudios = ['correct_01.mp3', 'correct_02.mp3', 'correct_03.mp3', 'correct_04.mp3', 'correct_high.mp3'];
    $incorrectAudios = ['incorrect_01.mp3', 'incorrect_02.mp3', 'incorrect_03.mp3', 'incorrect_04.mp3', 'incorrect_low.mp3'];
    $resultAudioFile = $isCorrect
        ? $correctAudios[array_rand($correctAudios)]
        : $incorrectAudios[array_rand($incorrectAudios)];

    // Kiểm tra mất chuỗi (Streak Lost): Nếu câu này sai và trước đó có chuỗi >= 2
    $streakLost = false;
    if (!$isCorrect && $myVote) {
        $prevVotes = db_fetch_all(
            "SELECT a.is_correct
             FROM votes v
             JOIN answers a ON v.id_answer = a.id_answer
             WHERE v.id_player = :pid AND a.id_question != :qid
             ORDER BY v.id_vote DESC",
            [':pid' => $playerId, ':qid' => $qId]
        );
        $prevStreak = 0;
        foreach ($prevVotes as $pv) {
            if ($pv['is_correct'] == 1) {
                $prevStreak++;
            } else {
                break;
            }
        }
        if ($prevStreak >= 2) {
            $streakLost = true;
        }
    }
    ?>
    <audio id="resultSfx" preload="auto" src="<?= BASE_URL ?>assets/audio/<?= rawurlencode($resultAudioFile) ?>"></audio>
    <audio id="pointsSfx" preload="auto" src="<?= BASE_URL ?>assets/audio/<?= rawurlencode('points.mp3') ?>"></audio>

    <div class="container py-3" style="max-width: 650px;">

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
                            <?php if ($streakLost): ?>
                                <div class="text-danger font-weight-bold small mt-1 bg-white px-2 py-1 rounded d-inline-block shadow-sm">
                                    <i class="fas fa-heart-broken mr-1"></i> Answer streak lost!
                                </div>
                            <?php endif; ?>
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
                                <?php if (intval($p['current_streak'] ?? 0) >= 2): ?>
                                    <span class="badge badge-danger ml-1 font-weight-bold" style="font-size: 0.75rem;" title="<?= intval($p['current_streak']) ?> câu đúng liên tiếp!">
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

        setTimeout(function() {
            let pointsAudioPlayed = false;
            document.querySelectorAll('.score-jump').forEach(function(el) {
                const startVal = parseInt(el.getAttribute('data-start')) || 0;
                const targetVal = parseInt(el.getAttribute('data-target')) || 0;
                const gainedVal = parseInt(el.getAttribute('data-gained')) || 0;

                if (gainedVal > 0 && targetVal > startVal) {
                    if (!pointsAudioPlayed) {
                        pointsAudioPlayed = true;
                        const pointsAudio = document.getElementById('pointsSfx');
                        if (pointsAudio) {
                            pointsAudio.volume = 0.7;
                            pointsAudio.currentTime = 0;
                            pointsAudio.play().catch(function() {});
                        }
                    }
                    el.classList.add('text-success');
                    let startTime = null;
                    const duration = 1200;

                    function step(timestamp) {
                        if (!startTime) startTime = timestamp;
                        const progress = Math.min((timestamp - startTime) / duration, 1);
                        const ease = 1 - Math.pow(1 - progress, 3);
                        const currentVal = Math.round(startVal + (targetVal - startVal) * ease);
                        el.textContent = currentVal.toLocaleString('en-US');

                        if (progress < 1) {
                            window.requestAnimationFrame(step);
                        } else {
                            el.textContent = targetVal.toLocaleString('en-US');
                        }
                    }
                    window.requestAnimationFrame(step);
                }
            });
        }, 300);
    });
    </script>

<?php elseif ($myVote): ?>

    <div class="d-flex align-items-center justify-content-center text-center text-white backgroundColorChanges" style="min-height: calc(100vh - 55px);">
        <div class="p-4">
            <div style="font-size: 4rem;" class="mb-3">⏳</div>
            <h2 class="font-weight-bold mb-2">Answer Submitted!</h2>
            <p class="lead font-weight-bold mb-4">Look up at the host screen for results when time is up!</p>
            <div class="spinner-grow text-light" role="status"></div>
        </div>
    </div>

<?php else: ?>

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
                        <img src="<?= htmlspecialchars($questionImageUrl) ?>" alt="Question Illustration" class="img-fluid rounded player-thumb-img shadow-sm" style="max-height: 280px; object-fit: contain;">
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
                        <i class="fas fa-hand-pointer text-primary mr-1"></i> Chọn 1 đáp án
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
                ?>
                    <div class="col-md-6 mb-3">
                        <div class="answer-box shadow-sm d-flex justify-content-between align-items-center <?= $colorClasses[$cIdx] ?>"
                             onclick="submitAnswer(<?= $ansId ?>)">
                            <div class="d-flex align-items-center">
                                <span class="symbol mr-3" style="font-size: 2rem;"><?= $icons[$cIdx] ?></span>
                                <span style="font-size: 1.3rem;"><?= htmlspecialchars($a['text'] ?? '') ?></span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
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

sessionStorage.setItem('kahoot_pid', playerId);
sessionStorage.setItem('kahoot_token', playerToken);

function refreshPlayerPage() {
    window.location.href = 'play.php?pid=' + playerId + '&token=' + encodeURIComponent(playerToken);
}

function submitAnswer(answerId) {
    if (!answerId) return;

    // Phát âm thanh submit tension ngay khi chạm/click vào đáp án
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

<?php if ($showOnDevice && !$isResultPhase && !$myVote): ?>
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
