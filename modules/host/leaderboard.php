<?php

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/database.php';

$roomId = intval($_SESSION['id_room'] ?? 0);
$room = db_fetch_one("SELECT * FROM room WHERE id_room = :r AND id_creator = :c", [':r' => $roomId, ':c' => (int)$_SESSION['id_creator']]);

if (!$room) {
    redirect('modules/auth/dashboard.php');
}

$isFinal = $room['status'] === 'finished';

$currentQId = intval($_GET['qid'] ?? 0);
if ($currentQId <= 0 && !$isFinal) {
    $currentQId = intval(str_replace('res_', '', $room['status']));
}

$selectedQIds = !empty($room['question_order']) ? json_decode($room['question_order'], true) : null;
if (!is_array($selectedQIds) || empty($selectedQIds)) {
    $fallbackQuestions = db_fetch_all("SELECT id_question FROM questions WHERE id_quiz = :q ORDER BY id_question ASC", [':q' => $room['id_quiz']]);
    $selectedQIds = array_column($fallbackQuestions, 'id_question');
}

$hasNextQuestion = false;
$currentIdx = array_search($currentQId, $selectedQIds);
if ($currentIdx !== false && isset($selectedQIds[$currentIdx + 1])) {
    $hasNextQuestion = true;
}

if (!$isFinal && $currentQId > 0) {
    $sql = "SELECT p.*,
                   COALESCE(v.points_earned + v.streak_bonus, 0) as points_gained,
                   COALESCE(a.is_correct, 0) as is_round_correct,
                   (SELECT COUNT(*) FROM votes v2 JOIN answers a2 ON v2.id_answer = a2.id_answer WHERE v2.id_player = p.id_player AND a2.is_correct = 1) as correct_count
            FROM players p
            LEFT JOIN votes v ON v.id_player = p.id_player AND v.id_answer IN (SELECT id_answer FROM answers WHERE id_question = :qid)
            LEFT JOIN answers a ON v.id_answer = a.id_answer
            WHERE p.id_room = :id_room
            ORDER BY p.score DESC, p.playeradd ASC, p.id_player ASC";
    $leaderboard = filter_room_players($roomId, db_fetch_all($sql, [':id_room' => $roomId, ':qid' => $currentQId]));
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

<?php if ($isFinal): ?>
    <audio id="podiumAudio" loop preload="auto" src="<?= BASE_URL ?>assets/audio/<?= rawurlencode('podium.mp3') ?>"></audio>
    <audio id="podium3rdAudio" preload="auto" src="<?= BASE_URL ?>assets/audio/<?= rawurlencode('podium-3rd.mp3') ?>"></audio>
    <audio id="podium2ndAudio" preload="auto" src="<?= BASE_URL ?>assets/audio/<?= rawurlencode('podium-2nd.mp3') ?>"></audio>
    <audio id="podium1stAudio" preload="auto" src="<?= BASE_URL ?>assets/audio/<?= rawurlencode('podium-1st.mp3') ?>"></audio>
    <audio id="crowdCheerAudio" preload="auto" src="<?= BASE_URL ?>assets/audio/<?= rawurlencode('crowd-cheer.mp3') ?>"></audio>
<?php else: ?>
    <audio id="interimAudio" preload="auto" src="<?= BASE_URL ?>assets/audio/<?= rawurlencode('fanfare.mp3') ?>"></audio>
<?php endif; ?>

<style>
.podium-step {
    opacity: 0;
    transform: translateY(40px) scale(0.95);
    transition: opacity 0.7s cubic-bezier(0.175, 0.885, 0.32, 1.275),
                transform 0.7s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}
.podium-step.revealed {
    opacity: 1;
    transform: translateY(0) scale(1);
}
</style>

<nav class="navbar navbar-dark bg-dark px-4 py-2 d-flex justify-content-between align-items-center">
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

<script>
const interimAudio = document.getElementById('interimAudio');

function stopAllAudio() {
    if (interimAudio) {
        try {
            interimAudio.pause();
            interimAudio.currentTime = 0;
        } catch(e) {}
    }
}
window.addEventListener('beforeunload', stopAllAudio);

$(document).ready(function() {
    if (interimAudio) {
        interimAudio.volume = 0.6;
        interimAudio.play().catch(function() {
            $(document).one('click keydown', function() {
                if (interimAudio) interimAudio.play().catch(function() {});
            });
        });
    }

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
            stopAllAudio();
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
