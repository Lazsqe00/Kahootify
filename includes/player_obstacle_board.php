<?php
// Player Obstacle Puzzle Board
// Included by modules/player/play.php when room.game_type === 'obstacle' and status === 'board'

$quiz = db_fetch_one("SELECT * FROM quiz WHERE id_quiz = :q", [':q' => $room['id_quiz']]);
$totalPieces = intval($quiz['total_pieces'] ?? 4);
if (!in_array($totalPieces, [4, 6, 8, 9, 10, 12, 15, 16, 20, 25])) $totalPieces = 4;

$openedPieces = !empty($room['opened_pieces']) ? json_decode($room['opened_pieces'], true) : [];
if (!is_array($openedPieces)) $openedPieces = [];

$myUnlocked = !empty($player['unlocked_pieces']) ? json_decode($player['unlocked_pieces'], true) : [];
if (!is_array($myUnlocked)) $myUnlocked = [];

$allOpened = (count($openedPieces) >= $totalPieces);
if ($allOpened) {
    $myUnlocked = range(1, $totalPieces);
    $isDisqualified = !empty($player['is_disqualified_obstacle']) && !empty($room['all_pieces_final_revealed']);
} else {
    $isDisqualified = !empty($player['is_disqualified_obstacle']);
}
$secretImageUrl = !empty($quiz['obstacle_image']) ? BASE_URL . $quiz['obstacle_image'] : '';

// Grid layout
$cols = 2; $rows = 2;
if ($totalPieces === 6) { $cols = 3; $rows = 2; }
elseif ($totalPieces === 8) { $cols = 4; $rows = 2; }
elseif ($totalPieces === 9) { $cols = 3; $rows = 3; }
elseif ($totalPieces === 10) { $cols = 5; $rows = 2; }
elseif ($totalPieces === 12) { $cols = 4; $rows = 3; }
elseif ($totalPieces === 15) { $cols = 5; $rows = 3; }
elseif ($totalPieces === 16) { $cols = 4; $rows = 4; }
elseif ($totalPieces === 20) { $cols = 5; $rows = 4; }
elseif ($totalPieces === 25) { $cols = 5; $rows = 5; }

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
}
.grid-cols-2 { grid-template-columns: repeat(2, 1fr); }
.grid-cols-3 { grid-template-columns: repeat(3, 1fr); }
.grid-cols-4 { grid-template-columns: repeat(4, 1fr); }
.grid-cols-5 { grid-template-columns: repeat(5, 1fr); }

.grid-rows-2 { grid-template-rows: repeat(2, 1fr); }
.grid-rows-3 { grid-template-rows: repeat(3, 1fr); }
.grid-rows-4 { grid-template-rows: repeat(4, 1fr); }
.grid-rows-5 { grid-template-rows: repeat(5, 1fr); }

.obstacle-piece-tile {
    position: relative;
    border: 2px solid rgba(255, 255, 255, 0.25);
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    box-sizing: border-box;
    overflow: hidden;
}

.piece-locked {
    background: linear-gradient(135deg, #2b1055 0%, #150630 100%);
    opacity: 1;
}
.piece-locked::after {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0; bottom: 0;
    background: radial-gradient(circle at center, rgba(120, 60, 200, 0.4) 0%, rgba(20, 5, 40, 0.85) 100%);
    pointer-events: none;
}

.piece-unlocked {
    background: transparent !important;
    border-color: rgba(255, 255, 255, 0.1) !important;
}
.piece-unlocked .piece-label {
    display: none !important;
}

.piece-label {
    position: relative;
    z-index: 3;
    font-size: 2.2rem;
    font-weight: 900;
    color: #fff;
    text-shadow: 0 4px 12px rgba(0,0,0,0.8);
    display: flex;
    flex-direction: column;
    align-items: center;
    pointer-events: none;
}
.piece-badge {
    font-size: 0.75rem;
    padding: 3px 8px;
    border-radius: 12px;
    background: rgba(0,0,0,0.5);
    color: #f2994a;
    border: 1px solid rgba(242, 153, 74, 0.5);
    margin-top: 4px;
}

/* STICKY BOTTOM BUZZER */
.bottom-buzz-bar {
    position: fixed;
    bottom: 0; left: 0; right: 0;
    padding: 16px 20px;
    background: rgba(18, 10, 32, 0.95);
    backdrop-filter: blur(12px);
    border-top: 2px solid rgba(255, 255, 255, 0.15);
    display: flex;
    justify-content: center;
    z-index: 1000;
    box-shadow: 0 -8px 30px rgba(0,0,0,0.6);
}
.btn-buzz {
    background: linear-gradient(135deg, #e21b3c 0%, #b3122c 100%);
    color: #fff !important;
    font-family: 'Montserrat', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;
    font-size: 1.4rem;
    font-weight: 900;
    letter-spacing: 0.5px;
    border-radius: 50px;
    padding: 16px 48px;
    border: 3px solid #fff;
    box-shadow: 0 8px 24px rgba(226, 27, 60, 0.55);
    transition: all 0.25s ease;
    animation: buzzPulse 1.8s infinite;
    max-width: 600px;
    width: 100%;
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
    color: #bbb !important;
    cursor: not-allowed !important;
    animation: none !important;
    box-shadow: none !important;
    font-size: 1.15rem !important;
}
.vcnv-badge {
    font-family: 'Montserrat', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;
    font-weight: 800 !important;
    letter-spacing: 0.5px;
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
</style>

<div class="player-obstacle-wrapper">

    <!-- TOP BAR -->
    <div class="player-top-bar">
        <div class="d-flex align-items-center">
            <span class="badge badge-warning text-dark font-weight-bold px-3 py-1 mr-2 vcnv-badge" style="font-size: 0.9rem;">
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
    <?php if ($isDisqualified && !$allOpened): ?>
        <div class="alert alert-warning font-weight-bold text-center py-2 px-3 shadow-sm mb-3" style="border-radius: 14px; font-size: 0.9rem; background: #fff3cd; color: #856404; border: 1px solid #ffeeba;">
            <i class="fas fa-info-circle mr-1 text-warning"></i> Bạn đã đoán sai từ khóa nên bị tạm khóa quyền bấm chuông trong các mảnh này. Bạn vẫn được tham gia trả lời câu hỏi và cộng điểm bình thường. Chuông sẽ được mở lại cho bạn khi mở hết các mảnh ghép!
        </div>
    <?php elseif ($allOpened): ?>
        <div class="alert alert-success font-weight-bold text-center py-2 px-3 shadow-sm mb-3" style="border-radius: 14px; font-size: 0.95rem; background: #d4edda; color: #155724; border: 1px solid #c3e6cb;">
            <i class="fas fa-unlock-alt mr-1 text-success"></i> TẤT CẢ MẢNH GHÉP ĐÃ ĐƯỢC MỞ! Chuông đoán từ khóa đã được mở lại cho TẤT CẢ người chơi. Hãy nhanh tay bấm chuông giành quyền trả lời!
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
            <?php if ($secretImageUrl): ?>
                <img src="<?= htmlspecialchars($secretImageUrl) ?>" class="obstacle-secret-img" alt="Bức tranh bí mật">
            <?php endif; ?>

            <div class="obstacle-grid-overlay grid-cols-<?= $cols ?> grid-rows-<?= $rows ?>" id="playerPiecesGrid">
                <?php for ($p = 1; $p <= $totalPieces; $p++): 
                    $isPieceUnlocked = in_array($p, $myUnlocked);
                ?>
                    <div class="obstacle-piece-tile <?= $isPieceUnlocked ? 'piece-unlocked' : 'piece-locked' ?>" 
                         id="pieceTile_<?= $p ?>"
                         data-piece="<?= $p ?>">
                        <div class="piece-label">
                            <span>🧩 <?= $p ?></span>
                            <span class="piece-badge"><i class="fas fa-lock mr-1"></i> BÍ MẬT</span>
                        </div>
                    </div>
                <?php endfor; ?>
            </div>
        </div>

        <div class="mt-3 text-center text-white-50 small">
            <i class="fas fa-lightbulb text-warning mr-1"></i>
            Trả lời đúng các câu hỏi để mở từng mảnh ghép của riêng bạn, hoặc bấm chuông bên dưới để đoán từ khóa bức tranh!
        </div>
    </div>
</div>

<!-- NÚT BẤM CHUÔNG STICKY Ở ĐÁY MÀN HÌNH -->
<div class="bottom-buzz-bar">
    <?php if ($isDisqualified && !$allOpened): ?>
        <button type="button" class="btn btn-buzz btn-buzz-disabled" disabled id="btnBuzz">
            <i class="fas fa-lock mr-2"></i> TẠM KHÓA CHUÔNG (CHỜ MỞ HẾT MẢNH)
        </button>
    <?php elseif ($isDisqualified && $allOpened): ?>
        <button type="button" class="btn btn-buzz btn-buzz-disabled" disabled id="btnBuzz">
            <i class="fas fa-ban mr-2"></i> ĐÃ HẾT LƯỢT ĐOÁN
        </button>
    <?php else: ?>
        <button type="button" class="btn btn-buzz" id="btnBuzz" onclick="claimBuzzer()">
            <i class="fas fa-bell mr-2"></i> ĐOÁN BỨC TRANH
        </button>
    <?php endif; ?>
</div>

<!-- MODAL NHẬP TỪ KHÓA CHO NGƯỜI BẤM CHUÔNG THÀNH CÔNG -->
<div class="player-modal-backdrop" id="guessModal">
    <div class="player-modal-card">
        <h3 class="font-weight-bold text-warning mb-1">
            🔔 LƯỢT ĐOÁN CỦA BẠN!
        </h3>
        <p class="text-white-50 small mb-3">Nhập từ khóa bức tranh bí mật:</p>

        <!-- FORM NHẬP CÂU TRẢ LỜI (KHÔNG CÒN ĐẾM NGƯỢC THỜI GIAN) -->
        <form id="guessForm" onsubmit="submitGuess(event)">
            <div class="form-group mb-3">
                <input type="text" id="guessInput" class="form-control form-control-lg text-center font-weight-bold" 
                       placeholder="Nhập từ khóa đáp án..." autocomplete="off" required>
            </div>
            <button type="submit" class="btn btn-kahoot btn-kahoot-green btn-lg btn-block font-weight-bold shadow" id="btnSubmitGuess">
                <i class="fas fa-paper-plane mr-1"></i> XÁC NHẬN ĐÁP ÁN
            </button>
        </form>

        <!-- TRẠNG THÁI ĐÃ GỬI VÀ CHỜ HOST XÁC NHẬN -->
        <div id="guessWaitingHost" style="display: none;" class="py-3">
            <div class="spinner-border text-warning mb-3" style="width: 3.2rem; height: 3.2rem;" role="status"></div>
            <h4 class="font-weight-bold text-white mb-2">ĐÃ GỬI CÂU TRẢ LỜI!</h4>
            <div class="p-2 mb-2 rounded bg-dark border border-secondary text-warning font-weight-bold" id="submittedGuessDisplay" style="font-size: 1.3rem; word-break: break-word;">
                ...
            </div>
            <p class="text-white-50 small mb-0">Đang chờ Host xác nhận đáp án của bạn...</p>
        </div>
    </div>
</div>

<!-- MODAL THÔNG BÁO PHÒNG BỊ KHÓA KHI NGƯỜI KHÁC BẤM CHUÔNG -->
<div class="player-modal-backdrop" id="roomLockedModal">
    <div class="player-modal-card" style="border-color: #e21b3c;">
        <i class="fas fa-bell fa-4x text-danger mb-3" style="animation: buzzPulse 1s infinite;"></i>
        <h4 class="font-weight-bold text-white mb-2" id="lockedPlayerNotice">
            CÓ NGƯỜI CHƠI BẤM CHUÔNG!
        </h4>
        <p class="text-white-50 mb-4" id="lockedPlayerSubtext">
            Phòng chơi đang tạm khóa để người chơi trả lời và Host xác nhận. Vui lòng chờ...
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
let isAllOpened = <?= $allOpened ? 'true' : 'false' ?>;
let isMyTurnToGuess = false;
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
    $('#guessForm').show();
    $('#guessWaitingHost').hide();
    $('#guessInput').val('').focus();
    $('#btnSubmitGuess').prop('disabled', false).html('<i class="fas fa-paper-plane mr-1"></i> XÁC NHẬN ĐÁP ÁN');
    $('#guessModal').css('display', 'flex');
}

function submitGuess(e) {
    e.preventDefault();
    const guessText = $('#guessInput').val().trim();
    if (!guessText) return;

    $('#btnSubmitGuess').prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Đang gửi...');

    $.post('<?= BASE_URL ?>modules/api/obstacle_action.php?action=submit_guess&id_room=' + roomId, {
        id_player: playerId,
        token: playerToken,
        guess: guessText
    }, function(res) {
        if (res && res.success) {
            // Chuyển modal sang trạng thái chờ Host xác nhận
            $('#guessForm').hide();
            $('#submittedGuessDisplay').text('"' + guessText + '"');
            $('#guessWaitingHost').show();
        } else {
            $('#btnSubmitGuess').prop('disabled', false).html('<i class="fas fa-paper-plane mr-1"></i> XÁC NHẬN ĐÁP ÁN');
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

        // 1. Nếu trò chơi đã kết thúc
        if (data.status === "finished") {
            clearInterval(pollInterval);
            $('#guessModal').hide();
            $('#roomLockedModal').hide();
            if (data.all_disqualified) {
                alert('Tất cả người chơi đều đã trả lời sai từ khóa bức tranh! Trò chơi kết thúc, người chiến thắng được xếp hạng theo số điểm.');
            }
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
            $('#guessModal').hide();
            $('#roomLockedModal').hide();
            revealEntirePuzzle(data.winner_player_name, data.obstacle_answer);
            return;
        }

        // 4. Xử lý Buzzer
        if (data.buzzer_status === 'buzzing') {
            if (data.buzzer_player_id === playerId) {
                // Đây là lượt của chính mình
                if (!isMyTurnToGuess) {
                    openGuessModal();
                }
            } else {
                // Người chơi khác bấm chuông: Khóa màn hình
                $('#lockedPlayerNotice').text('🔔 ' + (data.buzzer_player_name || 'Người chơi') + ' ĐANG BẤM CHUÔNG!');
                $('#lockedPlayerSubtext').text('Người chơi đang nhập câu trả lời...');
                if (!$('#roomLockedModal').is(':visible')) {
                    playBuzzerSound();
                    $('#roomLockedModal').css('display', 'flex');
                }
            }
        } else if (data.buzzer_status === 'pending_review') {
            if (data.buzzer_player_id === playerId) {
                // Mình đã gửi đáp án, đang chờ Host duyệt
                if (!$('#guessModal').is(':visible')) {
                    $('#guessModal').css('display', 'flex');
                }
                $('#guessForm').hide();
                if (data.buzzer_guess) {
                    $('#submittedGuessDisplay').text('"' + data.buzzer_guess + '"');
                }
                $('#guessWaitingHost').show();
            } else {
                // Người chơi khác đã gửi đáp án, đang chờ Host xác nhận
                $('#lockedPlayerNotice').text('🔔 ' + (data.buzzer_player_name || 'Người chơi') + ' ĐÃ GỬI CÂU TRẢ LỜI!');
                $('#lockedPlayerSubtext').text('Đang chờ Host xác nhận đáp án...');
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
            if (isMyTurnToGuess) {
                isMyTurnToGuess = false;
                $('#guessModal').hide();
                $('#guessForm').show();
                $('#guessWaitingHost').hide();

                // Kiểm tra nếu mình vừa bị Host đánh dấu là SAI
                if (data.my_is_disqualified) {
                    isDisqualified = true;
                    if (data.all_pieces_opened) {
                        $('#btnBuzz').replaceWith('<button type="button" class="btn btn-buzz btn-buzz-disabled" disabled id="btnBuzz"><i class="fas fa-ban mr-2"></i> ĐÃ HẾT LƯỢT ĐOÁN</button>');
                        alert('Host đã xác nhận đáp án chưa chính xác! Bạn đã mất quyền đoán từ khóa bức tranh.');
                    } else {
                        $('#btnBuzz').replaceWith('<button type="button" class="btn btn-buzz btn-buzz-disabled" disabled id="btnBuzz"><i class="fas fa-lock mr-2"></i> TẠM KHÓA CHUÔNG (CHỜ MỞ HẾT MẢNH)</button>');
                        alert('Host đã xác nhận đáp án chưa chính xác! Bạn bị tạm khóa quyền bấm chuông. Bạn vẫn được tham gia trả lời các câu hỏi tiếp theo và chuông sẽ được mở lại khi mở hết các mảnh ghép.');
                    }
                }
            }
        }

        // 5. Đồng bộ khi mở hết mảnh ghép (all_pieces_opened)
        if (data.all_pieces_opened) {
            isAllOpened = true;
            $('.obstacle-piece-tile').removeClass('piece-locked').addClass('piece-unlocked');
            $('#unlockedCountBadge').text(data.total_pieces + ' / ' + data.total_pieces + ' Mảnh đã mở');
            // Nếu người chơi chưa bấm chuông sai ở vòng cuối này: Mở nút chuông!
            if (!data.my_is_disqualified) {
                isDisqualified = false;
                if ($('#btnBuzz').hasClass('btn-buzz-disabled')) {
                    $('#btnBuzz').replaceWith('<button type="button" class="btn btn-buzz" id="btnBuzz" onclick="claimBuzzer()"><i class="fas fa-bell mr-2"></i> ĐOÁN BỨC TRANH</button>');
                }
            }
        } else {
            // Đồng bộ các mảnh ghép đã mở của cá nhân người chơi
            if (data.my_unlocked_pieces && Array.isArray(data.my_unlocked_pieces)) {
                data.my_unlocked_pieces.forEach(function(pIdx) {
                    $('#pieceTile_' + pIdx).removeClass('piece-locked').addClass('piece-unlocked');
                });
                $('#unlockedCountBadge').text(data.my_unlocked_pieces.length + ' / ' + data.total_pieces + ' Mảnh đã mở');
            }
        }

        // 6. Đồng bộ trạng thái bị khóa chuông
        if (data.my_is_disqualified && !isDisqualified) {
            isDisqualified = true;
            if (data.all_pieces_opened) {
                $('#btnBuzz').replaceWith('<button type="button" class="btn btn-buzz btn-buzz-disabled" disabled id="btnBuzz"><i class="fas fa-ban mr-2"></i> ĐÃ HẾT LƯỢT ĐOÁN</button>');
            } else {
                $('#btnBuzz').replaceWith('<button type="button" class="btn btn-buzz btn-buzz-disabled" disabled id="btnBuzz"><i class="fas fa-lock mr-2"></i> TẠM KHÓA CHUÔNG (CHỜ MỞ HẾT MẢNH)</button>');
            }
        }
    });
}, 750);
</script>
</body>
</html>
