<?php

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/database.php';

$roomId = intval($_SESSION['id_room'] ?? 0);
$room = db_fetch_one("SELECT r.*, q.name as quiz_name FROM room r JOIN quiz q ON r.id_quiz = q.id_quiz WHERE r.id_room = :r", [':r' => $roomId]);

if (!$room || $room['status'] === 'finished') {
    set_flash_message('danger', 'Game room does not exist or has already finished!');
    redirect('modules/auth/dashboard.php');
}

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

$players = db_fetch_all("SELECT * FROM players WHERE id_room = :r ORDER BY id_player ASC", [':r' => $roomId]);
$joinPageUrl = BASE_URL . 'modules/player/join.php';
$joinUrl = $joinPageUrl . '?pin=' . rawurlencode($room['pin']);
$joinHost = strtolower(parse_url(BASE_URL, PHP_URL_HOST) ?? '');
$isLoopbackHost = in_array($joinHost, ['localhost', '127.0.0.1', '[::1]', '::1'], true) || substr($joinHost, -10) === '.localhost';

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
    max-width: 900px;
    width: 90%;
    margin: 20px auto;
    box-shadow: 0 15px 35px rgba(0,0,0,0.3);
    text-align: center;
    box-sizing: border-box;
}
.lobby-join-options {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 32px;
}
.lobby-pin-details { flex: 1; min-width: 0; }
.lobby-join-address { overflow-wrap: anywhere; }
.lobby-qr-panel { border-left: 2px solid #eee; padding-left: 32px; }
.lobby-qr-button {
    display: block;
    padding: 0;
    margin: 0 auto;
    border: 0;
    border-radius: 8px;
    background: #fff;
    cursor: zoom-in;
    width: 208px;
    max-width: 100%;
}
.lobby-qr-button:focus-visible { outline: 3px solid #1368ce; outline-offset: 4px; }
.lobby-qr-code svg { display: block; width: 100%; height: auto; }
.lobby-qr-dialog {
    width: min(90vw, 560px);
    max-height: 90vh;
    overflow: auto;
    border: 0;
    border-radius: 20px;
    padding: 24px;
    text-align: center;
    color: #212529;
}
.lobby-qr-dialog::backdrop { background: rgba(0, 0, 0, 0.75); }
.lobby-qr-dialog .lobby-qr-code { max-width: 440px; margin: auto; }
@media (max-width: 900px) {
    .lobby-pin-card { padding: 24px 16px; }
    .lobby-join-options { flex-direction: column; gap: 20px; }
    .lobby-qr-panel { border-left: 0; border-top: 2px solid #eee; padding: 20px 0 0; width: 100%; }
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
@media (max-width: 600px) {
    .lobby-pin-number {
        font-size: clamp(2rem, 11vw, 3rem);
        letter-spacing: 6px;
        margin-right: -6px;
    }
}
</style>

<div class="container-fluid text-center text-white py-4">

    <div class="lobby-pin-card mx-auto text-dark">
        <div class="lobby-join-options" id="lobbyJoin" data-join-url="<?= htmlspecialchars($joinUrl, ENT_QUOTES, 'UTF-8') ?>">
            <div class="lobby-pin-details">
                <p class="font-weight-bold mb-2">Join at</p>
                <a class="lobby-join-address" href="<?= htmlspecialchars($joinPageUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener"><?= htmlspecialchars(preg_replace('#^https?://#', '', $joinPageUrl)) ?></a>
                <p class="text-muted font-weight-bold text-uppercase mt-3 mb-2" style="letter-spacing: 3px; font-size: 0.95rem;">GAME PIN</p>
                <div class="lobby-pin-number"><?= htmlspecialchars($room['pin']) ?></div>
                <div class="mt-3">
                    <button type="button" id="copyJoinLink" class="btn btn-outline-dark font-weight-bold" hidden>
                        <i class="fas fa-link mr-1" aria-hidden="true"></i> Copy join link
                    </button>
                    <p id="joinLinkStatus" class="small text-muted mt-2 mb-0" role="status" aria-live="polite"></p>
                    <input type="text" id="joinLinkFallback" class="form-control mt-2" aria-label="Join link: select and copy" value="<?= htmlspecialchars($joinUrl, ENT_QUOTES, 'UTF-8') ?>" readonly hidden>
                </div>
            </div>
            <div class="lobby-qr-panel">
                <p class="font-weight-bold mb-1">Or scan to join</p>
                <button type="button" id="enlargeJoinQr" class="lobby-qr-button" aria-label="Enlarge the QR code to join this game" aria-haspopup="dialog" aria-controls="joinQrDialog" disabled>
                    <span id="joinQrCode" class="lobby-qr-code"><span class="small text-muted">Loading QR code...</span></span>
                </button>
                <p id="joinQrHint" class="small text-muted mb-0">Scan with your phone camera</p>
                <noscript><p class="small text-danger">Enable JavaScript to display the QR code, or join using the PIN.</p></noscript>
            </div>
        </div>
        <?php if ($isLoopbackHost): ?>
            <div class="alert alert-warning small text-left mt-3 mb-0" role="note">
                <strong>Joining from a phone?</strong> Connect to the same Wi-Fi and open this lobby using your computer's LAN IP instead of localhost (for example, http://192.168.1.10/Kahootify/). The QR code will then use that address.
            </div>
        <?php endif; ?>
    </div>

    <dialog id="joinQrDialog" class="lobby-qr-dialog" aria-labelledby="joinQrTitle">
        <form method="dialog" class="text-right"><button type="submit" class="btn btn-outline-dark btn-sm" aria-label="Close enlarged QR code">Close <span aria-hidden="true">&times;</span></button></form>
        <h2 id="joinQrTitle" class="h4 font-weight-bold mt-2">Scan to join</h2>
        <div id="joinQrCodeLarge" class="lobby-qr-code"></div>
        <p class="font-weight-bold mt-2 mb-0">Game PIN: <?= htmlspecialchars($room['pin']) ?></p>
    </dialog>

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
        <div id="playersContainer" class="d-flex flex-wrap justify-content-center" style="gap: 12px;">
            <?php if (empty($players)): ?>
                <div id="waitingMsg" class="py-4">
                    <div class="spinner-border text-light mb-3" role="status"></div>
                    <p class="font-weight-bold" style="font-size: 1.2rem; text-shadow: 1px 1px 2px rgba(0,0,0,0.5);">
                        Waiting for players to enter PIN or scan the QR code...
                    </p>
                </div>
            <?php else: ?>
                <?php foreach ($players as $p): ?>
                    <div class="badge badge-light px-3 py-2 text-dark font-weight-bold shadow-sm" style="font-size: 1.1rem; border-radius: 8px;">
                        <i class="fas fa-user-circle text-primary mr-1"></i> <?= htmlspecialchars($p['nickname']) ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="<?= BASE_URL ?>assets/js/qrcodegen.js"></script>
<script src="<?= BASE_URL ?>assets/js/lobby-join.js"></script>
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

const roomId = <?= $roomId ?>;
let previousCount = <?= count($players) ?>;

function checkLobbyPlayers() {
    $.getJSON('<?= BASE_URL ?>modules/api/game_state.php?action=get_players&id_room=' + roomId, function(res) {
        if (res && res.success) {
            const players = res.players;
            $('#playerCount').text(players.length);

            if (players.length > 0) {
                $('#btnStartGame').css({'pointer-events': 'auto', 'opacity': '1'});
                $('#waitingMsg').remove();
            } else {
                $('#btnStartGame').css({'pointer-events': 'none', 'opacity': '0.6'});
            }

            if (players.length !== previousCount) {
                previousCount = players.length;
                let html = '';
                players.forEach(function(p) {
                    html += `<div class="badge badge-light px-3 py-2 text-dark font-weight-bold shadow-sm" style="font-size: 1.1rem; border-radius: 8px;">
                                <i class="fas fa-user-circle text-primary mr-1"></i> ${escapeHtml(p.nickname)}
                             </div>`;
                });
                $('#playersContainer').html(html);
            }
        }
    });
}

function escapeHtml(text) {
    var div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

setInterval(checkLobbyPlayers, 1500);
</script>
</body>
</html>
