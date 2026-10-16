<?php
/**
 * THÀNH VIÊN 3: Màn hình phòng chờ Lobby chiếu mã PIN
 */
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/database.php';

$roomId = intval($_SESSION['id_room'] ?? 0);
$room = db_fetch_one("SELECT r.*, q.name as quiz_name FROM room r JOIN quiz q ON r.id_quiz = q.id_quiz WHERE r.id_room = :r", [':r' => $roomId]);

if (!$room || $room['status'] === 'finished') {
    set_flash_message('danger', 'Game room does not exist or has already finished!');
    redirect('modules/auth/dashboard.php');
}

// Xử lý Bắt đầu Game
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

// Xử lý Hủy phòng
if (isset($_GET['cancel_game'])) {
    db_execute("UPDATE room SET status = 'finished' WHERE id_room = :r", [':r' => $roomId]);
    unset($_SESSION['id_room'], $_SESSION['pin'], $_SESSION['id_quiz']);
    set_flash_message('info', 'Game room has been cancelled!');
    redirect('modules/auth/dashboard.php');
}

// Lấy danh sách người chơi hiện tại
$players = db_fetch_all("SELECT * FROM players WHERE id_room = :r ORDER BY id_player ASC", [':r' => $roomId]);

$pageTitle = "Lobby - Game PIN: " . htmlspecialchars($room['pin']);
$bodyClass = "backgroundColorChanges";
require_once __DIR__ . '/../../includes/header.php';
?>

<!-- Audio nhạc nền phòng chờ -->
<audio id="lobbyAudio" loop preload="auto">
    <source src="<?= BASE_URL ?>assets/audio/lobby.mp3" type="audio/mpeg">
    <source src="<?= BASE_URL ?>assets/audio/lobby.wav" type="audio/wav">
    <source src="<?= BASE_URL ?>assets/audio/lobby.ogg" type="audio/ogg">
</audio>

<!-- Header Host điều khiển -->
<nav class="navbar navbar-dark bg-dark px-4 py-2 d-flex justify-content-between align-items-center">
    <div class="d-flex align-items-center">
        <img src="<?= BASE_URL ?>assets/img/logo.png" alt="Logo" style="max-height: 32px; filter: brightness(100);" class="mr-3">
        <span class="text-white font-weight-bold" style="font-size: 1.1rem;"><?= htmlspecialchars($room['quiz_name']) ?></span>
    </div>
    <div class="d-flex align-items-center" style="gap: 12px;">
        <!-- Nút Bật / Tắt Nhạc nền -->
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
/* Hộp hiển thị mã PIN phòng chờ rộng rãi, không bị tràn số */
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
    margin-right: -10px; /* Bù trừ khoảng trắng của ký tự cuối cùng để căn giữa hoàn hảo */
    line-height: 1.1;
    color: #212529;
    user-select: all;
    display: inline-block;
    word-break: keep-all;
    white-space: nowrap;
}
</style>

<div class="container-fluid text-center text-white py-4">
    <!-- Hộp hiển thị mã Game PIN chuẩn đẹp, không bị tràn số -->
    <div class="lobby-pin-card mx-auto text-dark">
        <p class="text-muted font-weight-bold text-uppercase mb-2" style="letter-spacing: 3px; font-size: 0.95rem;">GAME PIN</p>
        <div class="lobby-pin-number">
            <?= htmlspecialchars($room['pin']) ?>
        </div>
    </div>

    <!-- Thanh trạng thái và nút Bắt đầu -->
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

    <!-- Danh sách người chơi đã tham gia phòng -->
    <div class="container mt-4" style="max-width: 900px;">
        <h5 class="font-weight-bold mb-3" style="text-shadow: 1px 1px 3px rgba(0,0,0,0.5);">
            Waiting for players to join:
        </h5>
        <div id="playersContainer" class="d-flex flex-wrap justify-content-center" style="gap: 12px;">
            <?php if (empty($players)): ?>
                <div id="waitingMsg" class="py-4">
                    <div class="spinner-border text-light mb-3" role="status"></div>
                    <p class="font-weight-bold" style="font-size: 1.2rem; text-shadow: 1px 1px 2px rgba(0,0,0,0.5);">
                        Waiting for players to enter PIN...
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

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script>
// ==================== QUẢN LÝ NHẠC PHÒNG CHỜ ====================
const audio = document.getElementById('lobbyAudio');

function tryPlayAudio() {
    if (!audio) return;
    audio.play().then(function() {
        updateAudioUI(true);
    }).catch(function() {
        // Trình duyệt chặn autoplay khi chưa có tương tác người dùng
        updateAudioUI(false);
        // Tự động phát ngay khi người dùng click bất kỳ đâu trên trang
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

// Khởi chạy nhạc khi trang sẵn sàng
$(document).ready(function() {
    tryPlayAudio();
});

// ==================== POLLING NGƯỜI CHƠI ====================
// Polling trạng thái phòng & cập nhật người chơi mỗi 1.5 giây
const roomId = <?= $roomId ?>;
let previousCount = <?= count($players) ?>;

function checkLobbyPlayers() {
    $.getJSON('<?= BASE_URL ?>modules/api/game_state.php?action=get_players&id_room=' + roomId, function(res) {
        if (res && res.success) {
            const players = res.players;
            $('#playerCount').text(players.length);

            // Bật/tắt nút bắt đầu nếu có ít nhất 1 người
            if (players.length > 0) {
                $('#btnStartGame').css({'pointer-events': 'auto', 'opacity': '1'});
                $('#waitingMsg').remove();
            } else {
                $('#btnStartGame').css({'pointer-events': 'none', 'opacity': '0.6'});
            }

            // Nếu số lượng người chơi thay đổi, cập nhật danh sách
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
