<?php

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/database.php';

$roomId = intval($_SESSION['id_room'] ?? 0);
$room = db_fetch_one("SELECT r.*, q.name as quiz_name FROM room r JOIN quiz q ON r.id_quiz = q.id_quiz WHERE r.id_room = :r", [':r' => $roomId]);

if (!$room) {
    redirect('modules/auth/dashboard.php');
}

$status = $room['status'];

if ($status === 'waiting') {
    redirect('modules/host/lobby.php');
}

if ($status === 'finished') {
    redirect('modules/host/leaderboard.php?final=true');
}

$allQuestions = db_fetch_all("SELECT * FROM questions WHERE id_quiz = :q ORDER BY id_question ASC", [':q' => $room['id_quiz']]);
$totalQuestions = count($allQuestions);

if (isset($_GET['action']) && $_GET['action'] === 'show_result') {
    if (is_numeric($status)) {
        db_execute("UPDATE room SET status = :s WHERE id_room = :r", [
            ':s' => 'res_' . $status,
            ':r' => $roomId
        ]);
    }
    redirect('modules/host/play.php');
}

if (isset($_GET['action']) && $_GET['action'] === 'next_question') {
    $currentQId = intval(str_replace('res_', '', $status));
    $nextQ = null;
    $foundCurrent = false;

    foreach ($allQuestions as $idx => $q) {
        if ($foundCurrent) {
            $nextQ = $q;
            $_SESSION['question_index'] = $idx;
            break;
        }
        if ($q['id_question'] == $currentQId) {
            $foundCurrent = true;
        }
    }

    if ($nextQ) {
        db_execute("UPDATE room SET status = :s, question_start_time = UNIX_TIMESTAMP() WHERE id_room = :r", [
            ':s' => (string)$nextQ['id_question'],
            ':r' => $roomId
        ]);
        redirect('modules/host/play.php');
    } else {

        db_execute("UPDATE room SET status = 'finished' WHERE id_room = :r", [':r' => $roomId]);
        redirect('modules/host/leaderboard.php?final=true');
    }
}

$isResultPhase = (strpos($status, 'res_') === 0);
$qId = $isResultPhase ? intval(str_replace('res_', '', $status)) : intval($status);

$currentQuestion = db_fetch_one("SELECT * FROM questions WHERE id_question = :qid", [':qid' => $qId]);
$answers = db_fetch_all("SELECT * FROM answers WHERE id_question = :qid ORDER BY id_answer ASC", [':qid' => $qId]);

$totalPlayers = db_count('players', 'id_room = :r', [':r' => $roomId]);
$votedCount = db_fetch_one(
    "SELECT COUNT(DISTINCT v.id_player) as total
     FROM votes v JOIN answers a ON v.id_answer = a.id_answer
     WHERE a.id_question = :qid AND v.id_player IN (SELECT id_player FROM players WHERE id_room = :r)",
    [':qid' => $qId, ':r' => $roomId]
)['total'] ?? 0;

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

$questionNumber = 1;
foreach ($allQuestions as $idx => $q) {
    if ($q['id_question'] == $qId) {
        $questionNumber = $idx + 1;
        break;
    }
}

$questionAudioFile = null;
if (!$isResultPhase) {
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
}

$colors = [KAHOOT_COLOR_RED, KAHOOT_COLOR_BLUE, KAHOOT_COLOR_YELLOW, KAHOOT_COLOR_GREEN];
$icons  = [KAHOOT_ICON_RED, KAHOOT_ICON_BLUE, KAHOOT_ICON_YELLOW, KAHOOT_ICON_GREEN];

$pageTitle = "Question " . $questionNumber . "/" . $totalQuestions . " - Kahoot! Host";
$bodyClass = "backgroundColorChanges";
require_once __DIR__ . '/../../includes/header.php';
?>

<?php if (!$isResultPhase && $questionAudioFile): ?>

<audio id="questionAudio" preload="auto">
    <source src="<?= BASE_URL ?>assets/audio/<?= rawurlencode($questionAudioFile) ?>" type="audio/mpeg">
</audio>
<?php endif; ?>

<nav class="navbar navbar-dark bg-dark px-4 py-2 d-flex justify-content-between align-items-center">
    <div class="d-flex align-items-center">
        <img src="<?= BASE_URL ?>assets/img/logo.png" alt="Logo" style="max-height: 32px; filter: brightness(100);" class="mr-3">
        <span class="badge badge-light font-weight-bold mr-2">PIN: <?= htmlspecialchars($room['pin']) ?></span>
        <span class="text-white font-weight-bold">Question <?= $questionNumber ?> / <?= $totalQuestions ?></span>
    </div>
    <div class="d-flex align-items-center">
        <?php if (!$isResultPhase && $questionAudioFile): ?>

            <button type="button" id="audioToggleBtn" class="btn btn-sm btn-light font-weight-bold shadow-sm rounded-pill mr-2 px-3" onclick="toggleQuestionAudio()" title="Toggle countdown music">
                <i class="fas fa-volume-up text-success mr-1" id="audioIcon"></i>
                <span id="audioLabel">Music: ON</span>
            </button>
        <?php endif; ?>

        <?php if ($isResultPhase): ?>
            <a href="<?= BASE_URL ?>modules/host/leaderboard.php?qid=<?= $qId ?>" class="btn btn-warning font-weight-bold mr-2">
                <i class="fas fa-trophy mr-1"></i> Leaderboard &raquo;
            </a>
            <a href="play.php?action=next_question" class="btn btn-primary font-weight-bold">
                <?= $questionNumber >= $totalQuestions ? 'Final Results 🏆' : 'Next Question &raquo;' ?>
            </a>
        <?php else: ?>
            <a href="play.php?action=show_result" onclick="stopQuestionAudio()" class="btn btn-outline-light font-weight-bold">
                Skip to Results &raquo;
            </a>
        <?php endif; ?>
    </div>
</nav>

<div class="container my-3" style="max-width: 1050px;">

    <div class="question-card-banner">
        <h2 class="font-weight-bold text-dark mb-0">
            <?= htmlspecialchars($currentQuestion['text']) ?>
        </h2>
    </div>

    <?php if ($isResultPhase): ?>
        <div class="card border-0 shadow-sm mx-auto my-3" style="max-width: 600px; background: rgba(255,255,255,0.95); border-radius: 14px;">
            <div class="card-body py-2 px-4 text-center">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="font-weight-bold text-dark" style="font-size: 1.05rem;">
                        <i class="fas fa-hourglass-half text-warning mr-2"></i>Auto-transition to Leaderboard in: <span id="autoLeaderboardCountdown" class="badge badge-warning text-dark font-weight-bold ml-1 px-2 py-1" style="font-size: 1.1rem;">5</span>s
                    </span>
                    <a href="<?= BASE_URL ?>modules/host/leaderboard.php?qid=<?= $qId ?>" class="btn btn-sm btn-primary font-weight-bold">
                        View now &raquo;
                    </a>
                </div>
                <div class="progress" style="height: 6px; border-radius: 4px; background: #e9ecef;">
                    <div id="autoProgress" class="progress-bar bg-warning progress-bar-striped progress-bar-animated" role="progressbar" style="width: 100%; transition: width 1s linear;"></div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center my-3 px-3">

        <div>
            <?php if (!$isResultPhase): ?>
                <div id="timerBox" class="timer-circle <?= $remainingSeconds <= 5 ? 'warning' : '' ?>">
                    <span id="timerSeconds"><?= $remainingSeconds ?></span>
                </div>
            <?php else: ?>
                <div class="timer-circle" style="background: #28a745; color: #fff; border-color: #1e7e34;">
                    <i class="fas fa-check"></i>
                </div>
            <?php endif; ?>
        </div>

        <div class="text-center">
            <?php if (!$isResultPhase): ?>
                <div class="bg-white rounded-pill px-4 py-2 shadow-sm font-weight-bold text-dark" style="font-size: 1.2rem;">
                    <i class="fas fa-paper-plane text-primary mr-1"></i>
                    <span id="voteCount"><?= $votedCount ?></span> / <?= $totalPlayers ?> Answers
                </div>
            <?php else: ?>
                <span class="badge badge-light px-4 py-2 font-weight-bold" style="font-size: 1.1rem;">
                    Time is up!
                </span>
            <?php endif; ?>
        </div>
    </div>

    <div class="row">
        <?php

        $voteStats = [];
        if ($isResultPhase && !empty($answers)) {
            $ansIds = array_column($answers, 'id_answer');
            $inClause = implode(',', array_map('intval', $ansIds));
            $statsRows = db_fetch_all(
                "SELECT v.id_answer, COUNT(v.id_vote) as cnt
                 FROM votes v
                 JOIN players p ON v.id_player = p.id_player
                 WHERE p.id_room = :r AND v.id_answer IN ($inClause)
                 GROUP BY v.id_answer",
                [':r' => $roomId]
            );
            foreach ($statsRows as $sr) {
                $voteStats[$sr['id_answer']] = intval($sr['cnt']);
            }
        }
        ?>

        <?php foreach ($answers as $idx => $a):
            $cIdx = $idx % 4;
            $isCorrect = ($a['is_correct'] == 1);
            $opacity = ($isResultPhase && !$isCorrect) ? 'opacity: 0.35;' : 'opacity: 1;';
            $ansCount = $voteStats[$a['id_answer']] ?? 0;
        ?>
            <div class="col-md-6 mb-3">
                <div class="answer-box shadow-sm d-flex justify-content-between align-items-center"
                     style="background-color: <?= $colors[$cIdx] ?>; <?= $opacity ?> cursor: default;">
                    <div class="d-flex align-items-center">
                        <span class="symbol mr-3" style="font-size: 2rem;"><?= $icons[$cIdx] ?></span>
                        <span style="font-size: 1.3rem;"><?= htmlspecialchars($a['text']) ?></span>
                    </div>

                    <?php if ($isResultPhase): ?>
                        <div class="text-right">
                            <span class="badge badge-dark px-3 py-2 font-weight-bold" style="font-size: 1rem;">
                                <?= $ansCount ?> answers
                            </span>
                            <?php if ($isCorrect): ?>
                                <span class="badge badge-light text-success font-weight-bold ml-1 px-2 py-1" style="font-size: 1.1rem;">
                                    ✓ CORRECT
                                </span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<?php if ($isResultPhase): ?>
<script>

let autoSec = 5;
const autoTimer = setInterval(function() {
    autoSec--;
    if (autoSec <= 0) {
        clearInterval(autoTimer);
        window.location.href = '<?= BASE_URL ?>modules/host/leaderboard.php?qid=<?= $qId ?>';
    } else {
        $('#autoLeaderboardCountdown').text(autoSec);
        $('#autoProgress').css('width', (autoSec / 5 * 100) + '%');
    }
}, 1000);
</script>
<?php else: ?>
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

const timerInterval = setInterval(function() {
    remaining--;
    if (remaining <= 0) {
        clearInterval(timerInterval);
        stopQuestionAudio();
        $('#timerSeconds').text('0');

        window.location.href = 'play.php?action=show_result';
    } else {
        $('#timerSeconds').text(remaining);
        if (remaining <= 5) {
            $('#timerBox').addClass('warning');
        }
    }
}, 1000);

const pollInterval = setInterval(function() {
    $.getJSON('<?= BASE_URL ?>modules/api/game_state.php?action=get_question_status&id_room=' + roomId + '&id_question=' + qId, function(data) {
        if (data && data.success) {
            $('#voteCount').text(data.voted_count);

            if (data.total_players > 0 && data.voted_count >= data.total_players) {
                clearInterval(pollInterval);
                clearInterval(timerInterval);
                stopQuestionAudio();
                window.location.href = 'play.php?action=show_result';
            }
        }
    });
}, 1000);
</script>
<?php endif; ?>
</body>
</html>
