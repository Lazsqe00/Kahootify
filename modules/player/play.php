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

if ($roomId <= 0 || $playerId <= 0 || !$player) {
    redirect('modules/player/join.php');
}

$room = db_fetch_one("SELECT * FROM room WHERE id_room = :r", [':r' => $roomId]);

if (!$room) {
    redirect('modules/player/join.php');
}

$status = $room['status'];

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
               COALESCE(v.points_earned, 0) as points_gained,
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
    foreach ($leaderboard as $idx => $p) {
        if ($p['id_player'] == $playerId) {
            $myRank = $idx + 1;
            break;
        }
    }
}

$pageTitle = "Kahoot! | " . htmlspecialchars($nickname);
$bodyClass = "backgroundColorChanges";
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center px-3 py-2 text-white shadow-sm" style="background-color: var(--kahoot-purple-dark);">
    <div class="font-weight-bold">
        <i class="fas fa-user mr-1 text-warning"></i> <?= htmlspecialchars($nickname) ?>
    </div>
    <div class="font-weight-bold badge badge-light text-dark px-3 py-2" style="font-size: 0.95rem;">
        Score: <?= number_format($player['score']) ?>
    </div>
</div>

<?php if ($isResultPhase): ?>

    <?php
    $isCorrect = ($myVote && $myVote['is_correct'] == 1);
    $pointsEarned = $myVote ? intval($myVote['points_earned']) : 0;
    ?>
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
                        <p class="mb-0 font-weight-bold" style="font-size: 1.15rem; color: #fff;">
                            <?= $isCorrect ? ('+' . number_format($pointsEarned) . ' pts') : '+0 pts' ?>
                        </p>
                    </div>
                </div>
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
        setTimeout(function() {
            document.querySelectorAll('.score-jump').forEach(function(el) {
                const startVal = parseInt(el.getAttribute('data-start')) || 0;
                const targetVal = parseInt(el.getAttribute('data-target')) || 0;
                const gainedVal = parseInt(el.getAttribute('data-gained')) || 0;

                if (gainedVal > 0 && targetVal > startVal) {
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

    $('.player-btn').css('pointer-events', 'none').css('opacity', '0.7');

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

setInterval(function() {
    $.getJSON('<?= BASE_URL ?>modules/api/game_state.php?action=get_room_status&id_room=' + roomId, function(res) {
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
