<?php

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/room_access.php';

$roomId = intval($_SESSION['id_room'] ?? 0);
$creatorId = intval($_SESSION['id_creator']);
$room = db_fetch_one("SELECT r.*, q.name as quiz_name FROM room r JOIN quiz q ON r.id_quiz = q.id_quiz WHERE r.id_room = :r AND r.id_creator = :c", [':r' => $roomId, ':c' => $creatorId]);

if (!$room || $room['status'] === 'finished') {
    set_flash_message('danger', 'Game room does not exist or has already finished!');
    redirect('modules/auth/dashboard.php');
}

if ($room['status'] !== 'waiting') {
    redirect('modules/host/play.php');
}

if (empty($_SESSION['room_lock_token'])) {
    $_SESSION['room_lock_token'] = bin2hex(random_bytes(32));
}
$roomLockError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_room_lock'])) {
    $token = $_POST['room_lock_token'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['room_lock_token'], $token)) {
        http_response_code(403);
        $roomLockError = 'Your request could not be verified. Refresh the page and try again.';
    } elseif (($_POST['lock_room_id'] ?? '') !== (string)$roomId || !in_array($_POST['set_room_lock'], ['0', '1'], true)) {
        http_response_code(400);
        $roomLockError = 'Invalid room lock setting.';
    } else {
        set_room_locked($room, $_POST['set_room_lock'] === '1');
        redirect('modules/host/lobby.php');
    }
}
$isLocked = room_is_locked($room);

if (isset($_GET['start_game'])) {
    $firstQ = db_fetch_one("SELECT id_question FROM questions WHERE id_quiz = :q ORDER BY id_question ASC LIMIT 1", [':q' => $room['id_quiz']]);
    if ($firstQ) {
        db_execute("UPDATE room SET status = :s, question_start_time = UNIX_TIMESTAMP() WHERE id_room = :r", [
            ':s' => (string)$firstQ['id_question'],
            ':r' => $roomId
        ]);
        $_SESSION['question_index'] = 0;
        redirect('modules/host/play.php');
    }
}

if (isset($_GET['cancel_game'])) {
    db_execute("UPDATE room SET status = 'finished' WHERE id_room = :r", [':r' => $roomId]);
    unset($_SESSION['id_room'], $_SESSION['pin'], $_SESSION['id_quiz']);
    set_flash_message('info', 'Game room has been cancelled!');
    redirect('modules/auth/dashboard.php');
}

$players = filter_room_players($roomId, db_fetch_all("SELECT * FROM players WHERE id_room = :r ORDER BY id_player ASC", [':r' => $roomId]));

$pageTitle = "Lobby - Game PIN: " . htmlspecialchars($room['pin']);
$bodyClass = "backgroundColorChanges";
require_once __DIR__ . '/../../includes/header.php';
?>

<audio id="lobbyAudio" loop preload="auto">
    <source src="<?= BASE_URL ?>assets/audio/lobby.mp3" type="audio/mpeg">
    <source src="<?= BASE_URL ?>assets/audio/lobby.wav" type="audio/wav">
    <source src="<?= BASE_URL ?>assets/audio/lobby.ogg" type="audio/ogg">
</audio>

<nav class="navbar navbar-dark bg-dark px-4 py-2 d-flex justify-content-between align-items-center">
    <div class="d-flex align-items-center">
        <img src="<?= BASE_URL ?>assets/img/logo.png" alt="Logo" style="max-height: 32px; filter: brightness(100);" class="mr-3">
        <span class="text-white font-weight-bold" style="font-size: 1.1rem;"><?= htmlspecialchars($room['quiz_name']) ?></span>
    </div>
    <div class="d-flex align-items-center" style="gap: 12px;">

        <button type="button" id="audioToggleBtn" class="btn btn-sm btn-light font-weight-bold shadow-sm rounded-pill px-3" onclick="toggleAudio()" title="Toggle lobby music">
            <i class="fas fa-volume-up text-success mr-1" id="audioIcon"></i>
            <span id="audioLabel">Music: ON</span>
        </button>

        <a href="lobby.php?cancel_game=true" class="btn btn-sm btn-outline-danger font-weight-bold" onclick="return confirm('Are you sure you want to cancel this game room?');">
            <i class="fas fa-times mr-1"></i> Cancel Game
        </a>
    </div>
</nav>

<style>

.lobby-pin-card {
    background: #ffffff;
    border-radius: 20px;
    padding: 25px 40px;
    max-width: 540px;
    width: 90%;
    margin: 20px auto;
    box-shadow: 0 15px 35px rgba(0,0,0,0.3);
    text-align: center;
    box-sizing: border-box;
}
.lobby-pin-number {
    font-family: 'Montserrat', sans-serif;
    font-weight: 900;
    font-size: clamp(3rem, 7.5vw, 4.8rem);
    letter-spacing: 10px;
    margin-right: -10px;
    line-height: 1.1;
    color: #212529;
    user-select: all;
    display: inline-block;
    word-break: keep-all;
    white-space: nowrap;
}
</style>

<div class="container-fluid text-center text-white py-4">

    <div class="lobby-pin-card mx-auto text-dark">
        <p class="text-muted font-weight-bold text-uppercase mb-2" style="letter-spacing: 3px; font-size: 0.95rem;">GAME PIN</p>
        <div class="lobby-pin-number">
            <?= htmlspecialchars($room['pin']) ?>
        </div>
        <form action="<?= BASE_URL ?>modules/host/lobby.php" method="POST" class="mt-3">
            <input type="hidden" name="room_lock_token" value="<?= htmlspecialchars($_SESSION['room_lock_token'], ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="lock_room_id" value="<?= $roomId ?>">
            <button type="submit" name="set_room_lock" value="<?= $isLocked ? '0' : '1' ?>" id="roomLockButton" class="btn <?= $isLocked ? 'btn-warning' : 'btn-outline-dark' ?> font-weight-bold" aria-describedby="roomLockState">
                <i id="roomLockIcon" class="fas <?= $isLocked ? 'fa-lock' : 'fa-lock-open' ?> mr-1" aria-hidden="true"></i>
                <span id="roomLockLabel"><?= $isLocked ? 'Unlock room' : 'Lock room' ?></span>
            </button>
        </form>
        <p id="roomLockState" class="small font-weight-bold mt-2 mb-0 <?= $isLocked ? 'text-danger' : 'text-muted' ?>" role="status" aria-live="polite"><?= $isLocked ? 'Room locked. New players cannot join.' : 'Room open. Players can join using the Game PIN or a join link.' ?></p>
        <?php if ($roomLockError !== ''): ?>
            <div class="alert alert-danger small mt-3 mb-0" role="alert"><?= htmlspecialchars($roomLockError) ?></div>
        <?php endif; ?>
    </div>

    <div class="d-flex justify-content-center align-items-center my-4" style="gap: 20px;">
        <div class="bg-dark px-4 py-2 rounded-pill shadow">
            <i class="fas fa-users text-success mr-2"></i>
            <span class="font-weight-bold" style="font-size: 1.2rem;">
                <span id="playerCount"><?= count($players) ?></span> players
            </span>
        </div>

        <a href="lobby.php?start_game=true" id="btnStartGame" class="btn btn-kahoot btn-kahoot-green btn-lg px-5 py-3 shadow" style="<?= count($players) === 0 ? 'pointer-events: none; opacity: 0.6;' : '' ?>">
            <i class="fas fa-play mr-2"></i> START GAME
        </a>
    </div>

    <div class="container mt-4" style="max-width: 900px;">
        <h5 class="font-weight-bold mb-3" style="text-shadow: 1px 1px 3px rgba(0,0,0,0.5);">
            Waiting for players to join:
        </h5>
        <?php require __DIR__ . '/../../includes/host_players.php'; ?>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script>

const audio = document.getElementById('lobbyAudio');

function tryPlayAudio() {
    if (!audio) return;
    audio.play().then(function() {
        updateAudioUI(true);
    }).catch(function() {

        updateAudioUI(false);

        $(document).one('click keydown', function() {
            audio.play().then(function() {
                updateAudioUI(true);
            }).catch(function() {});
        });
    });
}

function toggleAudio() {
    if (!audio) return;
    if (audio.paused) {
        audio.play().then(function() {
            updateAudioUI(true);
        }).catch(function(e) {
            console.log('Không thể phát nhạc:', e);
        });
    } else {
        audio.pause();
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
        btn.className = 'btn btn-sm btn-light font-weight-bold shadow-sm rounded-pill px-3';
    } else {
        icon.className = 'fas fa-volume-mute text-danger mr-1';
        label.textContent = 'Music: OFF';
        btn.className = 'btn btn-sm btn-outline-light font-weight-bold shadow-sm rounded-pill px-3';
    }
}

$(document).ready(function() {
    tryPlayAudio();
});

document.addEventListener('roomplayerschange', function (event) {
    const data = event.detail;
    updateRoomLockUI(data.is_locked);
    $('#playerCount').text(data.players.length);
    $('#btnStartGame').css(data.players.length > 0
        ? {'pointer-events': 'auto', 'opacity': '1'}
        : {'pointer-events': 'none', 'opacity': '0.6'});
});

function updateRoomLockUI(isLocked) {
    $('#roomLockButton').val(isLocked ? '0' : '1')
        .toggleClass('btn-warning', isLocked).toggleClass('btn-outline-dark', !isLocked);
    $('#roomLockIcon').toggleClass('fa-lock', isLocked).toggleClass('fa-lock-open', !isLocked);
    $('#roomLockLabel').text(isLocked ? 'Unlock room' : 'Lock room');
    $('#roomLockState').text(isLocked
        ? 'Room locked. New players cannot join.'
        : 'Room open. Players can join using the Game PIN or a join link.')
        .toggleClass('text-danger', isLocked).toggleClass('text-muted', !isLocked);
}

</script>
</body>
</html>
