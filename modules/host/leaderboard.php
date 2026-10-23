<?php

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/database.php';

$roomId = intval($_SESSION['id_room'] ?? 0);
$room = db_fetch_one("SELECT * FROM room WHERE id_room = :r AND id_creator = :creator", [':r' => $roomId, ':creator' => $_SESSION['id_creator']]);

if (!$room) {
    redirect('modules/auth/dashboard.php');
}

$isFinal = $room['status'] === 'finished';

$currentQId = intval($_GET['qid'] ?? 0);
if ($currentQId <= 0 && !$isFinal) {
    $currentQId = intval(str_replace('res_', '', $room['status']));
}

$allQuestions = db_fetch_all("SELECT id_question FROM questions WHERE id_quiz = :q ORDER BY id_question ASC", [':q' => $room['id_quiz']]);
$hasNextQuestion = false;
$foundCurrent = false;
foreach ($allQuestions as $q) {
    if ($foundCurrent) {
        $hasNextQuestion = true;
        break;
    }
    if ($q['id_question'] == $currentQId) {
        $foundCurrent = true;
    }
}

if (!$isFinal && $currentQId > 0) {
    $sql = "SELECT p.*,
                   COALESCE(v.points_earned, 0) as points_gained,
                   COALESCE(a.is_correct, 0) as is_round_correct,
                   (SELECT COUNT(*) FROM votes v2 JOIN answers a2 ON v2.id_answer = a2.id_answer WHERE v2.id_player = p.id_player AND a2.is_correct = 1) as correct_count
            FROM players p
            LEFT JOIN votes v ON v.id_player = p.id_player AND v.id_answer IN (SELECT id_answer FROM answers WHERE id_question = :qid)
            LEFT JOIN answers a ON v.id_answer = a.id_answer
            WHERE p.id_room = :id_room
            ORDER BY p.score DESC, p.playeradd ASC, p.id_player ASC";
    $leaderboard = db_fetch_all($sql, [':id_room' => $roomId, ':qid' => $currentQId]);
} else {
    $leaderboard = get_room_leaderboard($roomId);
}

if ($isFinal) {
    $resultsPlayerId = 0;
    require __DIR__ . '/../../includes/final_results.php';
    exit;
}

$pageTitle = 'Score Leaderboard';
$bodyClass = "backgroundColorChanges";
require_once __DIR__ . '/../../includes/header.php';
?>

<nav class="navbar navbar-dark bg-dark px-4 py-2 d-flex justify-content-between">
    <div class="d-flex align-items-center">
        <img src="<?= BASE_URL ?>assets/img/logo.png" alt="Logo" style="max-height: 32px; filter: brightness(100);" class="mr-3">
        <span class="badge badge-light font-weight-bold mr-2">PIN: <?= htmlspecialchars($room['pin']) ?></span>
        <span class="text-white font-weight-bold">LEADERBOARD</span>
    </div>
    <div>
        <a href="<?= BASE_URL ?>modules/host/play.php?action=next_question" class="btn btn-kahoot btn-kahoot-green font-weight-bold">
            <?= $hasNextQuestion ? 'Next Question &raquo;' : 'Final Results &raquo;' ?>
        </a>
    </div>
</nav>

<div class="container my-4" style="max-width: 850px;">
        <div class="text-center text-white mb-3">
            <h2 class="font-weight-bold" style="text-shadow: 2px 2px 4px rgba(0,0,0,0.4);">
                <i class="fas fa-chart-line mr-2"></i>Interim Leaderboard
            </h2>
        </div>

        <div class="card border-0 shadow-sm mx-auto mb-3" style="max-width: 600px; background: rgba(255,255,255,0.95); border-radius: 14px;">
            <div class="card-body py-2 px-4 text-center">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="font-weight-bold text-dark" style="font-size: 1.05rem;">
                        <i class="fas fa-hourglass-half text-warning mr-2"></i>
                        <?= $hasNextQuestion ? 'Next question in: ' : 'Final results in: ' ?>
                        <span id="autoNextTimer" class="badge badge-success px-2 py-1 font-weight-bold" style="font-size: 1.1rem;">5</span>s
                    </span>
                    <a href="<?= BASE_URL ?>modules/host/play.php?action=next_question" class="btn btn-sm btn-primary font-weight-bold">
                        <?= $hasNextQuestion ? 'Next now &raquo;' : 'View results &raquo;' ?>
                    </a>
                </div>
                <div class="progress" style="height: 6px; border-radius: 4px; background: #e9ecef;">
                    <div id="autoNextProgress" class="progress-bar bg-success progress-bar-striped progress-bar-animated" role="progressbar" style="width: 100%; transition: width 1s linear;"></div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-lg p-3" style="border-radius: 14px; background: rgba(255,255,255,0.95);">
            <?php if (empty($leaderboard)): ?>
                <div class="text-center py-4 text-muted font-weight-bold">
                    No scores recorded yet.
                </div>
            <?php else: ?>
                <?php foreach ($leaderboard as $idx => $p):
                    $rank = $idx + 1;
                    $badgeClass = ($rank == 1) ? 'badge-warning text-dark' : (($rank == 2) ? 'badge-secondary' : (($rank == 3) ? 'badge-danger' : 'badge-light text-dark'));
                    $currScore = intval($p['score']);
                    $gained = intval($p['points_gained'] ?? 0);
                    $prevScore = max(0, $currScore - $gained);
                ?>
                    <div class="leaderboard-item d-flex justify-content-between align-items-center py-2 px-3 mb-2" style="background: #f8f9fa; border-radius: 10px;">
                        <div class="d-flex align-items-center">
                            <span class="badge <?= $badgeClass ?> font-weight-bold mr-3" style="font-size: 1.2rem; width: 38px; height: 38px; line-height: 26px; border-radius: 50%;">
                                <?= $rank ?>
                            </span>
                            <span class="font-weight-bold text-dark" style="font-size: 1.25rem;">
                                <?= htmlspecialchars($p['nickname']) ?>
                            </span>
                            <?php if ($gained > 0): ?>
                                <span class="badge badge-success ml-2 py-1 px-2 font-weight-bold gained-badge" style="opacity: 0; transform: scale(0.6); transition: all 0.4s ease; font-size: 0.95rem;">
                                    +<?= number_format($gained) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="text-right">
                            <span class="leaderboard-score score-counter font-weight-bold text-primary"
                                  style="font-size: 1.4rem;"
                                  data-start="<?= $prevScore ?>"
                                  data-target="<?= $currScore ?>"
                                  data-gained="<?= $gained ?>">
                                <?= number_format($prevScore) ?>
                            </span>
                            <span class="text-muted small font-weight-bold">pts</span>
                            <small class="text-muted d-block font-weight-bold"><?= $p['correct_count'] ?> correct</small>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>

<script>
$(document).ready(function() {

    setTimeout(function() {

        $('.gained-badge').css({
            'opacity': '1',
            'transform': 'scale(1)'
        });

        $('.score-counter').each(function() {
            const el = this;
            const startVal = parseInt($(el).attr('data-start')) || 0;
            const targetVal = parseInt($(el).attr('data-target')) || 0;
            const gainedVal = parseInt($(el).attr('data-gained')) || 0;

            if (gainedVal > 0 && targetVal > startVal) {
                $(el).addClass('text-success').removeClass('text-primary');
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
    }, 500);

    let autoNextSec = 5;
    const autoNextTimer = setInterval(function() {
        autoNextSec--;
        if (autoNextSec <= 0) {
            clearInterval(autoNextTimer);
            window.location.href = '<?= BASE_URL ?>modules/host/play.php?action=next_question';
        } else {
            $('#autoNextTimer').text(autoNextSec);
            $('#autoNextProgress').css('width', (autoNextSec / 5 * 100) + '%');
        }
    }, 1000);
});
</script>

</body>
</html>
