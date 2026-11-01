<?php
// Player Obstacle Puzzle Board
// Included by modules/player/play.php when room.game_type === 'obstacle' and status === 'board'

$quiz = db_fetch_one("SELECT * FROM quiz WHERE id_quiz = :q", [':q' => $room['id_quiz']]);
$totalPieces = intval($quiz['total_pieces'] ?? 4);
if (!in_array($totalPieces, [4, 6, 8, 9])) $totalPieces = 4;

$openedPieces = !empty($room['opened_pieces']) ? json_decode($room['opened_pieces'], true) : [];
if (!is_array($openedPieces)) $openedPieces = [];

$myUnlocked = !empty($player['unlocked_pieces']) ? json_decode($player['unlocked_pieces'], true) : [];
if (!is_array($myUnlocked)) $myUnlocked = [];

$isDisqualified = !empty($player['is_disqualified_obstacle']);
$secretImageUrl = !empty($quiz['obstacle_image']) ? BASE_URL . $quiz['obstacle_image'] : '';

// Grid layout
$cols = 2; $rows = 2;
if ($totalPieces === 6) { $cols = 3; $rows = 2; }
elseif ($totalPieces === 8) { $cols = 4; $rows = 2; }
elseif ($totalPieces === 9) { $cols = 3; $rows = 3; }

$pageTitle = "Vượt Chướng Ngại Vật - " . htmlspecialchars($nickname);
$bodyClass = "backgroundColorChanges";
require_once __DIR__ . '/header.php';
?>

<style>
.player-obstacle-wrapper {
    max-width: 820px;
    margin: 20px auto;
    padding: 0 16px 80px;
}
.player-top-bar {
    background: rgba(0, 0, 0, 0.4);
    backdrop-filter: blur(10px);
    border-radius: 50px;
    padding: 10px 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    color: #fff;
    margin-bottom: 20px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.25);
}
.puzzle-main-card {
    background: #180e2b;
    border-radius: 24px;
    padding: 24px;
    box-shadow: 0 16px 48px rgba(0,0,0,0.45);
    border: 2px solid rgba(255,255,255,0.12);
}
.obstacle-puzzle-container {
    position: relative;
    max-width: 720px;
    width: 100%;
    aspect-ratio: 16 / 10;
    margin: 0 auto;
    border-radius: 18px;
    overflow: hidden;
    box-shadow: 0 12px 36px rgba(0,0,0,0.6);
    background: #000;
}
.obstacle-secret-img {
    position: absolute;
    top: 0; left: 0;
    width: 100%; height: 100%;
    object-fit: cover;
    z-index: 1;
}
.obstacle-grid-overlay {
    position: absolute;
    top: 0; left: 0;
    width: 100%; height: 100%;
    display: grid;
    z-index: 2;
    gap: 4px;
    background: rgba(0,0,0,0.5);
}
.cols-2 { grid-template-columns: repeat(2, 1fr); }
.cols-3 { grid-template-columns: repeat(3, 1fr); }
.cols-4 { grid-template-columns: repeat(4, 1fr); }

.rows-2 { grid-template-rows: repeat(2, 1fr); }
.rows-3 { grid-template-rows: repeat(3, 1fr); }

.obstacle-piece-tile {
    position: relative;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #46178f 0%, #22084d 100%);
    color: #fff;
    border: 1px solid rgba(255, 255, 255, 0.2);
    transition: opacity 0.8s cubic-bezier(0.4, 0, 0.2, 1), transform 0.5s ease;
    user-select: none;
    text-align: center;
    padding: 10px;
}
.obstacle-piece-tile.piece-unlocked {
    opacity: 0 !important;
    pointer-events: none !important;
}
.obstacle-piece-tile.piece-locked {
    opacity: 1;
}
.p-tile-num {
    font-size: 2rem;
    font-weight: 900;
    text-shadow: 0 2px 8px rgba(0,0,0,0.6);
}
.p-tile-state {
    font-size: 0.75rem;
    font-weight: 700;
    margin-top: 4px;
    opacity: 0.85;
}
.bottom-buzz-bar {
    position: fixed;
    bottom: 0; left: 0; right: 0;
    background: rgba(24, 14, 43, 0.95);
    backdrop-filter: blur(12px);
    padding: 14px 20px;
    display: flex;
    justify-content: center;
    z-index: 999;
    box-shadow: 0 -8px 24px rgba(0,0,0,0.4);
    border-top: 1px solid rgba(255,255,255,0.1);
}
.btn-buzz {
    background: linear-gradient(135deg, #e21b3c 0%, #ff4757 100%);
    color: #fff !important;
    font-size: 1.35rem;
    font-weight: 800;
    padding: 14px 42px;
    border-radius: 50px;
    border: none;
    box-shadow: 0 8px 24px rgba(226, 27, 60, 0.5);
    transition: all 0.25s ease;
    letter-spacing: 0.5px;
    animation: buzzPulse 1.8s infinite;
}
.btn-buzz:hover:not(:disabled) {
    transform: translateY(-2px) scale(1.03);
    box-shadow: 0 12px 30px rgba(226, 27, 60, 0.75);
}
@keyframes buzzPulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.03); box-shadow: 0 0 25px rgba(226, 27, 60, 0.75); }
    100% { transform: scale(1); }
}
.btn-buzz-disabled {
    background: #4a4a58 !important;
    color: #999 !important;
    cursor: not-allowed !important;
    animation: none !important;
    box-shadow: none !important;
}

/* MODALS */
.player-modal-backdrop {
    display: none;
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0, 0, 0, 0.85);
    backdrop-filter: blur(8px);
    z-index: 99999;
    align-items: center;
    justify-content: center;
    padding: 20px;
}
.player-modal-card {
    background: #1e1136;
    border-radius: 24px;
    border: 3px solid var(--kahoot-purple-light);
    max-width: 540px;
    width: 100%;
    padding: 30px;
    color: #fff;
    text-align: center;
    box-shadow: 0 24px 60px rgba(0,0,0,0.7);
    animation: scaleModal 0.35s ease;
}
@keyframes scaleModal {
    from { opacity: 0; transform: scale(0.9); }
    to { opacity: 1; transform: scale(1); }
}
.countdown-ring {
    font-size: 2.8rem;
    font-weight: 900;
    color: #e21b3c;
    margin: 12px 0;
    text-shadow: 0 2px 10px rgba(226, 27, 60, 0.5);
}
</style>

<div class="player-obstacle-wrapper">

    <!-- TOP BAR -->
    <div class="player-top-bar">
        <div class="d-flex align-items-center">
            <span class="badge badge-warning text-dark font-weight-bold px-3 py-1 mr-2" style="font-size: 0.9rem;">
                <i class="fas fa-puzzle-piece mr-1"></i> VƯỢT CHƯỚNG NGẠI VẬT
            </span>
            <span class="font-weight-bold d-none d-sm-inline"><?= htmlspecialchars($nickname) ?></span>
        </div>
        <div class="d-flex align-items-center">
            <span class="badge badge-light text-dark font-weight-bold px-3 py-1" style="font-size: 1rem;">
                <i class="fas fa-star text-warning mr-1"></i> <?= number_format(intval($player['score'] ?? 0)) ?> pts
            </span>
        </div>
    </div>

    <!-- PUZZLE CONTAINER -->
    <?php if ($isDisqualified): ?>
        <div class="alert alert-danger font-weight-bold text-center py-3 px-4 shadow mb-4" style="border-radius: 16px; background: rgba(226, 27, 60, 0.95); border: 2px solid #fff; color: #fff;">
            <i class="fas fa-ban fa-2x d-block mb-2 text-white"></i>
            <h4 class="font-weight-bold mb-1">BẠN ĐÃ BỊ HỦY TƯ CÁCH THI ĐẤU!</h4>
            <p class="mb-0 small" style="opacity: 0.95;">
                Do bạn đã bấm chuông và đoán sai từ khóa bức tranh bí mật, bạn đã bị hủy tư cách chơi ở tất cả các vòng tiếp theo. Hãy theo dõi các người chơi còn lại thi đấu.
            </p>
        </div>
    <?php endif; ?>

    <div class="puzzle-main-card">
        <div class="d-flex justify-content-between align-items-center mb-3 text-white">
            <h5 class="font-weight-bold mb-0">
                <i class="fas fa-image text-warning mr-2"></i>Bức Tranh Bí Mật
            </h5>
            <span class="badge badge-success px-3 py-1 font-weight-bold" id="unlockedCountBadge">
                <?= count($myUnlocked) ?> / <?= $totalPieces ?> Mảnh đã mở
            </span>
        </div>

        <div class="obstacle-puzzle-container">
            <!-- Ảnh bí mật lớn nằm ở tầng nền dưới -->
            <?php if ($secretImageUrl): ?>
                <img src="<?= htmlspecialchars($secretImageUrl) ?>" class="obstacle-secret-img" alt="Secret Puzzle" id="secretImg">
            <?php endif; ?>

            <!-- Lưới các mảnh ghép che bên trên -->
            <div class="obstacle-grid-overlay cols-<?= $cols ?> rows-<?= $rows ?>" id="puzzleGrid">
                <?php for ($p = 1; $p <= $totalPieces; $p++): 
                    $isUnlocked = in_array($p, $myUnlocked);
                ?>
                    <div class="obstacle-piece-tile <?= $isUnlocked ? 'piece-unlocked' : 'piece-locked' ?>" 
                         id="pieceTile_<?= $p ?>">
                        <div class="p-tile-num">🧩 <?= $p ?></div>
                        <div class="p-tile-state">
                            <?= in_array($p, $openedPieces) ? 'Đã mở câu hỏi' : 'Đang che kín' ?>
                        </div>
                    </div>
                <?php endfor; ?>
            </div>
        </div>
    </div>
</div>

<!-- NÚT BẤM CHUÔNG STICKY Ở ĐÁY MÀN HÌNH -->
<div class="bottom-buzz-bar">
    <?php if ($isDisqualified): ?>
        <button type="button" class="btn btn-buzz btn-buzz-disabled" disabled>
            <i class="fas fa-ban mr-2"></i> BẠN ĐÃ BỊ HỦY TƯ CÁCH THI ĐẤU
        </button>
    <?php else: ?>
        <button type="button" class="btn btn-buzz" id="btnBuzz" onclick="claimBuzzer()">
            <i class="fas fa-bell mr-2"></i> 🔔 ĐOÁN BỨC TRANH
        </button>
    <?php endif; ?>
</div>

<!-- MODAL NHẬP TỪ KHÓA CHO NGƯỜI BẤM CHUÔNG THÀNH CÔNG -->
<div class="player-modal-backdrop" id="guessModal">
    <div class="player-modal-card">
        <h3 class="font-weight-bold text-warning mb-1">
            🔔 LƯỢT ĐOÁN CỦA BẠN!
        </h3>
        <p class="text-muted small">Nhập từ khóa bức tranh bí mật trước khi hết giờ:</p>
        
        <div class="countdown-ring" id="guessCountdown">20</div>

        <form id="guessForm" onsubmit="submitGuess(event)">
            <div class="form-group">
                <input type="text" id="guessInput" class="form-control form-control-lg text-center font-weight-bold" 
                       placeholder="Nhập từ khóa đáp án..." autocomplete="off" required>
            </div>
            <button type="submit" class="btn btn-kahoot btn-kahoot-green btn-lg btn-block font-weight-bold" id="btnSubmitGuess">
                <i class="fas fa-paper-plane mr-1"></i> XÁC NHẬN ĐÁP ÁN
            </button>
        </form>
    </div>
</div>

<!-- MODAL THÔNG BÁO PHÒNG BỊ KHÓA KHI NGƯỜI KHÁC BẤM CHUÔNG -->
<div class="player-modal-backdrop" id="roomLockedModal">
    <div class="player-modal-card" style="border-color: #e21b3c;">
        <i class="fas fa-bell fa-4x text-danger mb-3" style="animation: buzzPulse 1s infinite;"></i>
        <h4 class="font-weight-bold text-white mb-2" id="lockedPlayerNotice">
            CÓ NGƯỜI CHƠI BẤM CHUÔNG!
        </h4>
        <p class="text-white-50 mb-4">
            Phòng chơi đang tạm khóa trong 20 giây để người chơi đó gửi từ khóa đáp án. Vui lòng chờ...
        </p>
        <div class="spinner-border text-danger" role="status"></div>
    </div>
</div>

<!-- MODAL CHIẾN THẮNG CHUNG CUỘC KHI CÓ NGƯỜI ĐOÁN ĐÚNG -->
<div class="player-modal-backdrop" id="victoryModal">
    <div class="player-modal-card" style="border-color: #f2994a; max-width: 640px;">
        <h2 class="display-4 font-weight-bold text-warning mb-2">🏆 KẾT THÚC! 🏆</h2>
        <h4 class="font-weight-bold text-white mb-3" id="victoryWinnerDisplay">
            Chúc mừng Quán quân!
        </h4>
        <div class="alert alert-warning py-2 mb-3 font-weight-bold text-dark" style="font-size: 1.2rem;" id="victoryAnswerDisplay">
            TỪ KHÓA: ...
        </div>
        <p class="text-muted small">Bức tranh bí mật đã được mở hoàn toàn 100%!</p>
        <div class="mt-4">
            <a href="<?= BASE_URL ?>modules/player/play.php?pid=<?= $playerId ?>&token=<?= urlencode($token) ?>" class="btn btn-warning btn-lg font-weight-bold px-4 rounded-pill">
                <i class="fas fa-trophy mr-1"></i> Xem Bảng Vinh Danh &raquo;
            </a>
        </div>
    </div>
</div>

<audio id="fanfareAudio" preload="auto" src="<?= BASE_URL ?>assets/audio/fanfare.mp3"></audio>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script>
const roomId = <?= $roomId ?>;
const playerId = <?= $playerId ?>;
const playerToken = '<?= htmlspecialchars($token) ?>';
let isDisqualified = <?= $isDisqualified ? 'true' : 'false' ?>;
let isMyTurnToGuess = false;
let guessTimer = null;
let guessSecondsLeft = 20;
let winnerDeclared = false;

function playBuzzerSound() {
    try {
        const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        const osc = audioCtx.createOscillator();
        const gain = audioCtx.createGain();
        osc.type = 'sawtooth';
        osc.frequency.setValueAtTime(520, audioCtx.currentTime);
        osc.frequency.exponentialRampToValueAtTime(780, audioCtx.currentTime + 0.25);
        gain.gain.setValueAtTime(0.5, audioCtx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + 0.35);
        osc.connect(gain);
        gain.connect(audioCtx.destination);
        osc.start();
        osc.stop(audioCtx.currentTime + 0.35);
    } catch(e) {}
}

function claimBuzzer() {
    if (isDisqualified) return;

    $.post('<?= BASE_URL ?>modules/api/obstacle_action.php?action=buzz&id_room=' + roomId, {
        id_player: playerId,
        token: playerToken
    }, function(res) {
        if (res && res.success && res.your_turn) {
            openGuessModal();
        } else {
            alert(res.message || 'Không thể bấm chuông lúc này');
        }
    }, 'json').fail(function() {
        alert('Lỗi kết nối máy chủ');
    });
}

function openGuessModal() {
    isMyTurnToGuess = true;
    $('#guessModal').css('display', 'flex');
    $('#guessInput').val('').focus();
    guessSecondsLeft = 20;
    $('#guessCountdown').text(guessSecondsLeft);

    clearInterval(guessTimer);
    guessTimer = setInterval(function() {
        guessSecondsLeft--;
        $('#guessCountdown').text(guessSecondsLeft);
        if (guessSecondsLeft <= 0) {
            clearInterval(guessTimer);
            $('#guessModal').hide();
            isMyTurnToGuess = false;
            // Báo timeout
            $.post('<?= BASE_URL ?>modules/api/obstacle_action.php?action=buzzer_timeout&id_room=' + roomId);
            isDisqualified = true;
            $('#btnBuzz').replaceWith('<button type="button" class="btn btn-buzz btn-buzz-disabled" disabled><i class="fas fa-ban mr-2"></i> BẠN ĐÃ BỊ HỦY TƯ CÁCH THI ĐẤU</button>');
            alert('Hết 20 giây! Bạn đã bị hủy tư cách thi đấu ở các vòng sau.');
        }
    }, 1000);
}

function submitGuess(e) {
    e.preventDefault();
    const guessText = $('#guessInput').val().trim();
    if (!guessText) return;

    clearInterval(guessTimer);
    $('#btnSubmitGuess').prop('disabled', true).text('Đang kiểm tra...');

    $.post('<?= BASE_URL ?>modules/api/obstacle_action.php?action=submit_guess&id_room=' + roomId, {
        id_player: playerId,
        token: playerToken,
        guess: guessText
    }, function(res) {
        $('#guessModal').hide();
        $('#btnSubmitGuess').prop('disabled', false).html('<i class="fas fa-paper-plane mr-1"></i> XÁC NHẬN ĐÁP ÁN');
        isMyTurnToGuess = false;

        if (res && res.success) {
            if (res.correct) {
                revealEntirePuzzle(res.winner_name, res.answer);
            } else {
                isDisqualified = true;
                $('#btnBuzz').replaceWith('<button type="button" class="btn btn-buzz btn-buzz-disabled" disabled><i class="fas fa-ban mr-2"></i> BẠN ĐÃ BỊ HỦY TƯ CÁCH THI ĐẤU</button>');
                alert(res.message || 'Đáp án chưa chính xác! Bạn đã bị hủy tư cách chơi các vòng sau.');
                if (res.all_disqualified) {
                    window.location.href = '<?= BASE_URL ?>modules/player/play.php?pid=' + playerId + '&token=' + encodeURIComponent(playerToken);
                }
            }
        } else {
            alert(res.message || 'Lỗi gửi đáp án');
        }
    }, 'json').fail(function() {
        alert('Lỗi kết nối máy chủ');
        $('#btnSubmitGuess').prop('disabled', false).html('<i class="fas fa-paper-plane mr-1"></i> XÁC NHẬN ĐÁP ÁN');
    });
}

function revealEntirePuzzle(winnerName, answer) {
    // Mở toàn bộ 100% các mảnh ghép: gỡ bỏ che phủ
    $('.obstacle-piece-tile').removeClass('piece-locked').addClass('piece-unlocked');
    $('#victoryWinnerDisplay').text('🏆 ' + (winnerName || 'Người chơi') + ' đã xuất sắc đoán đúng từ khóa!');
    $('#victoryAnswerDisplay').text('TỪ KHÓA CHÍNH XÁC: ' + (answer || ''));
    $('#victoryModal').css('display', 'flex');
    try {
        document.getElementById('fanfareAudio').play();
    } catch(e) {}
}

// Polling realtime
const pollInterval = setInterval(function() {
    $.getJSON('<?= BASE_URL ?>modules/api/obstacle_action.php?action=get_obstacle_state&id_room=' + roomId + '&pid=' + playerId, function(data) {
        if (!data || !data.success) return;

        // 1. Nếu trò chơi đã kết thúc hoặc tất cả người chơi bị loại
        if (data.status === 'finished' || data.all_disqualified) {
            clearInterval(pollInterval);
            window.location.href = '<?= BASE_URL ?>modules/player/play.php?pid=' + playerId + '&token=' + encodeURIComponent(playerToken);
            return;
        }

        // 2. Nếu Host đã chuyển sang câu hỏi (numeric status), chuyển ngay sang màn hình câu hỏi
        if (data.status && data.status !== 'board' && data.status !== 'waiting') {
            clearInterval(pollInterval);
            window.location.href = '<?= BASE_URL ?>modules/player/play.php?pid=' + playerId + '&token=' + encodeURIComponent(playerToken);
            return;
        }

        // 3. Nếu có người chiến thắng chung cuộc
        if (data.is_revealed && data.winner_player_name && !winnerDeclared) {
            winnerDeclared = true;
            clearInterval(pollInterval);
            revealEntirePuzzle(data.winner_player_name, data.obstacle_answer);
            return;
        }

        // 3. Xử lý Buzzer
        if (data.buzzer_status === 'buzzing') {
            if (data.buzzer_player_id === playerId) {
                // Đây là lượt của chính mình
                if (!isMyTurnToGuess) {
                    openGuessModal();
                }
            } else {
                // Người chơi khác bấm chuông: Khóa màn hình
                $('#lockedPlayerNotice').text('🔔 ' + (data.buzzer_player_name || 'Người chơi') + ' ĐANG BẤM CHUÔNG!');
                if (!$('#roomLockedModal').is(':visible')) {
                    playBuzzerSound();
                    $('#roomLockedModal').css('display', 'flex');
                }
            }
        } else {
            // Idle: Đóng khóa phòng
            if ($('#roomLockedModal').is(':visible')) {
                $('#roomLockedModal').hide();
            }
        }

        // 4. Đồng bộ các mảnh ghép đã mở của cá nhân người chơi
        if (data.my_unlocked_pieces && Array.isArray(data.my_unlocked_pieces)) {
            data.my_unlocked_pieces.forEach(function(pIdx) {
                $('#pieceTile_' + pIdx).removeClass('piece-locked').addClass('piece-unlocked');
            });
            $('#unlockedCountBadge').text(data.my_unlocked_pieces.length + ' / ' + data.total_pieces + ' Mảnh đã mở');
        }

        // 5. Đồng bộ trạng thái bị loại
        if (data.my_is_disqualified && !isDisqualified) {
            isDisqualified = true;
            $('#btnBuzz').replaceWith('<button type="button" class="btn btn-buzz btn-buzz-disabled" disabled><i class="fas fa-ban mr-2"></i> BẠN ĐÃ BỊ HỦY TƯ CÁCH THI ĐẤU</button>');
            window.location.reload();
        }
    });
}, 750);
</script>
</body>
</html>
