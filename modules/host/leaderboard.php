<?php

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/room_access.php';
require_once __DIR__ . '/../../includes/player_characters.php';

$roomId = intval($_SESSION['id_room'] ?? 0);
$room = db_fetch_one("SELECT * FROM room WHERE id_room = :r AND id_creator = :c", [':r' => $roomId, ':c' => (int)$_SESSION['id_creator']]);

if (!$room) {
    redirect('modules/auth/dashboard.php');
}

$isFinal = ($room['status'] === 'finished') || (isset($_GET['final']) && $_GET['final'] === 'true');
if ($isFinal && $room['status'] !== 'finished') {
    db_execute("UPDATE room SET status = 'finished' WHERE id_room = :r", [':r' => $roomId]);
    $room['status'] = 'finished';
}

$currentQId = intval($_GET['qid'] ?? 0);
if ($currentQId <= 0 && !$isFinal) {
    $currentQId = intval(str_replace('res_', '', $room['status']));
}

$selectedQIds = !empty($room['question_order']) ? json_decode($room['question_order'], true) : null;
if (!is_array($selectedQIds) || empty($selectedQIds)) {
    $fallbackQuestions = db_fetch_all("SELECT id_question FROM questions WHERE id_quiz = :q ORDER BY id_question ASC", [':q' => $room['id_quiz']]);
    $selectedQIds = array_column($fallbackQuestions, 'id_question');
}
$selectedQIds = array_values(array_filter(array_map('intval', (array)$selectedQIds)));
$totalQuestions = count($selectedQIds);

$hasNextQuestion = false;
$currentIdx = array_search((int)$currentQId, $selectedQIds, true);
if ($currentIdx !== false && isset($selectedQIds[$currentIdx + 1])) {
    $hasNextQuestion = true;
}
$questionNumber = ($currentIdx !== false) ? ($currentIdx + 1) : 1;

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

$quiz = db_fetch_one("SELECT name FROM quiz WHERE id_quiz = :q", [':q' => $room['id_quiz']]);
$quizName = $quiz['name'] ?? 'Kahoot';

$pageTitle = 'Scoreboard - Question ' . $questionNumber . '/' . $totalQuestions . ' | ' . htmlspecialchars($quizName);
$bodyClass = "backgroundColorChanges";
require_once __DIR__ . '/../../includes/header.php';
?>

<audio id="interimAudio" preload="auto" src="<?= BASE_URL ?>assets/audio/<?= rawurlencode('fanfare.mp3') ?>"></audio>
<audio id="pointsAudio" preload="auto" src="<?= BASE_URL ?>assets/audio/<?= rawurlencode('points.mp3') ?>"></audio>

<style>
.leaderboard-container {
    max-width: 860px;
    margin: 0 auto;
}
.leaderboard-title {
    font-size: 2.4rem;
    font-weight: 800;
    color: #ffffff;
    text-shadow: 0 3px 10px rgba(0,0,0,0.4);
    letter-spacing: 0.5px;
}
.leaderboard-subtitle {
    font-size: 1.1rem;
    color: rgba(255,255,255,0.9);
    font-weight: 600;
    text-shadow: 0 2px 4px rgba(0,0,0,0.3);
}
.leaderboard-card {
    background: rgba(255,255,255,0.96);
    border-radius: 20px;
    box-shadow: 0 12px 36px rgba(0,0,0,0.2);
    backdrop-filter: blur(12px);
    overflow: hidden;
}
.leaderboard-row {
    background: #ffffff;
    border-radius: 14px;
    border: 1px solid rgba(0,0,0,0.06);
    box-shadow: 0 3px 10px rgba(0,0,0,0.04);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    animation: slideInRow 0.45s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
    animation-delay: var(--row-delay, 0s);
    opacity: 0;
    transform: translateY(16px);
}
.leaderboard-row:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 18px rgba(0,0,0,0.08);
}
@keyframes slideInRow {
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
.rank-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 44px;
    height: 44px;
    border-radius: 50%;
    font-weight: 800;
    font-size: 1.15rem;
    padding: 0 6px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.12);
}
.rank-badge-gold {
    background: linear-gradient(135deg, #ffd700, #ffae00);
    color: #212529;
    border: 2px solid #ffffff;
    box-shadow: 0 0 14px rgba(255, 193, 7, 0.6);
}
.rank-badge-silver {
    background: linear-gradient(135deg, #f1f3f5, #adb5bd);
    color: #212529;
    border: 2px solid #ffffff;
    box-shadow: 0 2px 8px rgba(108, 117, 125, 0.3);
}
.rank-badge-bronze {
    background: linear-gradient(135deg, #f5cba7, #cd7f32);
    color: #ffffff;
    border: 2px solid #ffffff;
    box-shadow: 0 2px 8px rgba(205, 127, 50, 0.35);
}
.rank-badge-default {
    background: #e9ecef;
    color: #495057;
}
.leaderboard-avatar-wrapper {
    width: 50px;
    height: 50px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: #f8f9fa;
    border: 2px solid #e9ecef;
    box-shadow: 0 2px 6px rgba(0,0,0,0.06);
    flex-shrink: 0;
}
.leaderboard-avatar-wrapper svg {
    width: 44px;
    height: 44px;
}
.player-nickname {
    font-size: 1.25rem;
    font-weight: 700;
    color: #1e293b;
    max-width: 320px;
}
.streak-badge {
    font-size: 0.85rem;
    padding: 4px 8px;
    border-radius: 12px;
}
.streak-fire-anim {
    animation: flamePulse 1s infinite alternate;
}
@keyframes flamePulse {
    0% { transform: scale(1); filter: drop-shadow(0 0 2px #ff4500); }
    100% { transform: scale(1.2); filter: drop-shadow(0 0 6px #ff8c00); }
}
.gained-badge {
    font-size: 0.95rem;
    padding: 4px 10px;
    border-radius: 16px;
    opacity: 0;
    transform: scale(0.6);
    transition: opacity 0.4s ease, transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}
.gained-badge.revealed {
    opacity: 1;
    transform: scale(1);
}
.leaderboard-score {
    font-size: 1.5rem;
    font-weight: 800;
    color: #1368ce;
    transition: color 0.3s ease;
}
.score-unit {
    font-size: 0.95rem;
}
</style>

<nav class="navbar navbar-dark bg-dark px-4 py-2 d-flex justify-content-between align-items-center">
    <div class="d-flex align-items-center">
        <img src="<?= BASE_URL ?>assets/img/logo.png" alt="Logo" style="max-height: 32px; filter: brightness(100);" class="mr-3">
        <span class="badge badge-light font-weight-bold mr-2">PIN: <?= htmlspecialchars($room['pin']) ?></span>
        <span class="text-white font-weight-bold mr-2">LEADERBOARD</span>
        <span class="badge badge-primary font-weight-bold">Question <?= $questionNumber ?> / <?= $totalQuestions ?></span>
    </div>
</nav>

<div class="container my-4 leaderboard-container">

    <div class="text-center mb-4">
        <h1 class="leaderboard-title mb-1">
            <i class="fas fa-trophy text-warning mr-2"></i>Scoreboard
        </h1>
        <p class="leaderboard-subtitle mb-0">
            <?= htmlspecialchars($quizName) ?> &bull; Question <?= $questionNumber ?> of <?= $totalQuestions ?>
        </p>
    </div>

    <!-- Player Standings List (Bảng điểm hiện tại) -->
    <div class="card border-0 leaderboard-card p-3 p-md-4 mb-4">
        <?php if (empty($leaderboard)): ?>
            <div class="text-center py-5 text-muted font-weight-bold">
                <i class="fas fa-users fa-3x mb-3 text-secondary" style="opacity: 0.5;"></i>
                <h4 class="font-weight-bold">No scores recorded yet</h4>
                <p class="mb-0">Waiting for players to answer questions!</p>
            </div>
        <?php else: ?>
            <div class="leaderboard-list">
                <?php foreach ($leaderboard as $idx => $p):
                    $rank = $idx + 1;
                    $currScore = intval($p['score']);
                    $gained = intval($p['points_gained'] ?? 0);
                    $prevScore = max(0, $currScore - $gained);
                    $streak = intval($p['current_streak'] ?? 0);
                    $appearance = get_player_appearance($room, (int)$p['id_player']);

                    $rankIcon = '';
                    if ($rank === 1) {
                        $rankBadgeClass = 'rank-badge-gold';
                        $rankIcon = '<i class="fas fa-crown mr-1"></i>';
                    } elseif ($rank === 2) {
                        $rankBadgeClass = 'rank-badge-silver';
                        $rankIcon = '<i class="fas fa-medal mr-1"></i>';
                    } elseif ($rank === 3) {
                        $rankBadgeClass = 'rank-badge-bronze';
                        $rankIcon = '<i class="fas fa-award mr-1"></i>';
                    } else {
                        $rankBadgeClass = 'rank-badge-default';
                    }
                ?>
                    <div class="leaderboard-row d-flex justify-content-between align-items-center py-2 px-3 mb-2"
                         style="--row-delay: <?= $idx * 0.08 ?>s;">
                        <div class="d-flex align-items-center flex-grow-1 mr-2" style="min-width: 0;">
                            <!-- Rank Badge -->
                            <div class="rank-badge <?= $rankBadgeClass ?> mr-3 flex-shrink-0">
                                <?= $rankIcon ?><?= $rank ?>
                            </div>

                            <!-- Player Character Avatar -->
                            <div class="leaderboard-avatar-wrapper mr-3 flex-shrink-0">
                                <?= player_appearance_image($appearance, 'leaderboard-avatar') ?>
                            </div>

                            <!-- Player Nickname & Badges -->
                            <div class="d-flex align-items-center flex-wrap" style="min-width: 0;">
                                <span class="player-nickname font-weight-bold text-dark mr-2 text-truncate">
                                    <?= htmlspecialchars($p['nickname']) ?>
                                </span>

                                <!-- Streak Badge -->
                                <?php if ($streak >= 2): ?>
                                    <span class="badge badge-danger font-weight-bold mr-2 shadow-sm streak-badge"
                                          title="<?= $streak ?> consecutive correct answers!">
                                        <i class="fas fa-fire streak-fire-anim mr-1"></i><?= $streak ?>
                                    </span>
                                <?php endif; ?>

                                <!-- Points Gained Pill (+X) -->
                                <?php if ($gained > 0): ?>
                                    <span class="badge badge-success font-weight-bold gained-badge shadow-sm"
                                          data-gained="<?= $gained ?>">
                                        +<?= number_format($gained) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Right Side: Animated Score Counter & Correct count -->
                        <div class="text-right flex-shrink-0 ml-2">
                            <div class="d-flex align-items-baseline justify-content-end">
                                <span class="leaderboard-score score-counter font-weight-bold"
                                      data-start="<?= $prevScore ?>"
                                      data-target="<?= $currScore ?>"
                                      data-gained="<?= $gained ?>">
                                    <?= number_format($prevScore) ?>
                                </span>
                                <span class="score-unit text-muted font-weight-bold ml-1">pts</span>
                            </div>
                            <small class="text-muted d-block font-weight-bold" style="font-size: 0.85rem;">
                                <i class="fas fa-check text-success mr-1"></i><?= (int)$p['correct_count'] ?> correct
                            </small>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Bottom Action Button: Host manually clicks to advance -->
    <div class="text-center my-4">
        <a href="<?= BASE_URL ?>modules/host/play.php?action=next_question&qid=<?= $currentQId ?>" class="btn btn-kahoot btn-kahoot-green btn-lg shadow font-weight-bold px-5 py-3" style="font-size: 1.25rem; border-radius: 12px;" onclick="stopAllAudio()">
            <?= $hasNextQuestion ? 'Next Question &raquo;' : 'Final Results 🏆' ?>
        </a>
    </div>

</div>

<!-- Include jQuery for animations -->
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script>
const interimAudio = document.getElementById('interimAudio');
const pointsAudio = document.getElementById('pointsAudio');

function stopAllAudio() {
    [interimAudio, pointsAudio].forEach(function(a) {
        if (a) {
            try { a.pause(); a.currentTime = 0; } catch(e) {}
        }
    });
}
window.addEventListener('beforeunload', stopAllAudio);

$(document).ready(function() {
    // Play fanfare/sound on leaderboard entry
    if (interimAudio) {
        interimAudio.volume = 0.5;
        interimAudio.play().catch(function() {
            $(document).one('click keydown', function() {
                if (interimAudio) interimAudio.play().catch(function() {});
            });
        });
    }

    // Reveal gained points pills and roll up scores with ease-out cubic
    setTimeout(function() {
        $('.gained-badge').addClass('revealed');

        if (pointsAudio) {
            pointsAudio.volume = 0.45;
            pointsAudio.play().catch(function() {});
        }

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
});
</script>

</body>
</html>
