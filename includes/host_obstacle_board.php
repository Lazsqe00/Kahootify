<?php
// Host Obstacle Puzzle Board
// Included by modules/host/play.php when room.game_type === 'obstacle' and status === 'board'

$quiz = db_fetch_one("SELECT * FROM quiz WHERE id_quiz = :q", [':q' => $room['id_quiz']]);
$totalPieces = intval($quiz['total_pieces'] ?? 4);
if (!in_array($totalPieces, [4, 6, 8, 9, 10, 12, 15, 16, 20, 25])) $totalPieces = 4;

$openedPieces = !empty($room['opened_pieces']) ? json_decode($room['opened_pieces'], true) : [];
if (!is_array($openedPieces)) $openedPieces = [];
$allOpened = (count($openedPieces) >= $totalPieces);

$gameConfig = !empty($room['game_config']) ? json_decode($room['game_config'], true) : [];
if (!is_array($gameConfig)) $gameConfig = [];
if ($allOpened && empty($gameConfig['final_round_reset'])) {
    $gameConfig['final_round_reset'] = true;
    db_execute("UPDATE room SET game_config = :gc WHERE id_room = :r", [
        ':gc' => json_encode($gameConfig),
        ':r'  => $room['id_room']
    ]);
    db_execute("UPDATE players SET is_disqualified_obstacle = 0 WHERE id_room = :r", [
        ':r'  => $room['id_room']
    ]);
}

$counts = room_question_counts($room, 0);
$totalPlayers = $counts['total_players'];

// Grid cols & rows
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

$pageTitle = "Bảng Vượt Chướng Ngại Vật - Host";
$bodyClass = "backgroundColorChanges";
require_once __DIR__ . '/header.php';
?>

<style>
.obstacle-host-wrapper {
    max-width: 960px;
    margin: 20px auto;
    padding: 0 15px;
}
.obstacle-board-card {
    background: #1e1136;
    border-radius: 20px;
    padding: 28px;
    box-shadow: 0 16px 40px rgba(0,0,0,0.4);
    border: 2px solid rgba(255,255,255,0.1);
    position: relative;
    overflow: hidden;
}
.obstacle-grid {
    display: grid;
    gap: 16px;
    margin: 24px 0;
    min-height: 420px;
}
.cols-2 { grid-template-columns: repeat(2, 1fr); }
.cols-3 { grid-template-columns: repeat(3, 1fr); }
.cols-4 { grid-template-columns: repeat(4, 1fr); }
.cols-5 { grid-template-columns: repeat(5, 1fr); }

.rows-2 { grid-template-rows: repeat(2, 1fr); }
.rows-3 { grid-template-rows: repeat(3, 1fr); }
.rows-4 { grid-template-rows: repeat(4, 1fr); }
.rows-5 { grid-template-rows: repeat(5, 1fr); }

.obstacle-tile {
    position: relative;
    border-radius: 16px;
    background: linear-gradient(135deg, #46178f 0%, #250954 100%);
    border: 3px solid #794bc4;
    color: #fff;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 24px 15px;
    cursor: pointer;
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 8px 20px rgba(0,0,0,0.25);
    user-select: none;
    text-align: center;
}
.obstacle-tile:hover:not(.disabled) {
    transform: translateY(-4px) scale(1.02);
    border-color: #f2994a;
    box-shadow: 0 12px 28px rgba(242, 153, 74, 0.3);
    background: linear-gradient(135deg, #5b1eb8 0%, #340c75 100%);
}
.obstacle-tile.disabled {
    background: #2a2a38 !important;
    border-color: #444 !important;
    color: #777 !important;
    cursor: not-allowed;
    opacity: 0.65;
    box-shadow: none;
    transform: none;
}
.tile-num {
    font-size: 2.4rem;
    font-weight: 900;
    margin-bottom: 6px;
    text-shadow: 0 2px 8px rgba(0,0,0,0.5);
}
.tile-badge {
    font-size: 0.85rem;
    font-weight: 700;
    padding: 4px 12px;
    border-radius: 20px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.badge-unopened {
    background: rgba(242, 153, 74, 0.25);
    color: #f2994a;
    border: 1px solid #f2994a;
}
.badge-opened {
    background: rgba(255, 255, 255, 0.1);
    color: #aaa;
    border: 1px solid #555;
}
.buzzer-alert-banner {
    display: none;
    background: linear-gradient(135deg, #e21b3c 0%, #b3122c 100%);
    color: #fff;
    border-radius: 14px;
    padding: 16px 24px;
    margin-bottom: 20px;
    animation: pulseBuzzer 0.8s infinite alternate;
    box-shadow: 0 8px 24px rgba(226, 27, 60, 0.5);
}
@keyframes pulseBuzzer {
    from { transform: scale(1); box-shadow: 0 0 15px rgba(226, 27, 60, 0.5); }
    to { transform: scale(1.015); box-shadow: 0 0 30px rgba(226, 27, 60, 0.85); }
}
.winner-reveal-modal {
    display: none;
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0, 0, 0, 0.88);
    z-index: 99999;
    align-items: center;
    justify-content: center;
    padding: 20px;
    backdrop-filter: blur(8px);
}
.winner-modal-content {
    background: #1e1136;
    border: 3px solid #f2994a;
    border-radius: 20px;
    max-width: 680px;
    width: 100%;
    padding: 32px;
    text-align: center;
    color: #fff;
    box-shadow: 0 20px 60px rgba(0,0,0,0.6);
    animation: zoomInModal 0.4s ease;
}
@keyframes zoomInModal {
    from { opacity: 0; transform: scale(0.85); }
    to { opacity: 1; transform: scale(1); }
}
.revealed-img-box {
    max-height: 320px;
    width: 100%;
    object-fit: cover;
    border-radius: 14px;
    border: 3px solid #fff;
    margin: 18px 0;
    box-shadow: 0 10px 30px rgba(0,0,0,0.5);
}
.vcnv-badge {
    font-family: 'Montserrat', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;
    font-weight: 800 !important;
    letter-spacing: 0.5px;
}
</style>

<nav class="navbar navbar-dark bg-dark px-4 py-2 d-flex justify-content-between align-items-center">
    <div class="d-flex align-items-center">
        <img src="<?= BASE_URL ?>assets/img/logo.png" alt="Logo" style="max-height: 32px; filter: brightness(100);" class="mr-3">
        <span class="badge badge-light font-weight-bold mr-2">PIN: <?= htmlspecialchars($room['pin']) ?></span>
        <span class="badge badge-warning text-dark font-weight-bold mr-3 vcnv-badge">
            <i class="fas fa-puzzle-piece mr-1"></i> VƯỢT CHƯỚNG NGẠI VẬT
        </span>
        <span class="text-white font-weight-bold d-none d-md-inline"><?= htmlspecialchars($quiz['name']) ?></span>
    </div>
    <div class="d-flex align-items-center">
        <a href="<?= BASE_URL ?>modules/host/leaderboard.php?final=true" onclick="return confirm('Bạn có chắc muốn kết thúc trò chơi và xem bảng vinh danh chung cuộc?');" class="btn btn-outline-danger btn-sm font-weight-bold rounded-pill px-3">
            <i class="fas fa-flag-checkered mr-1"></i> Kết Thúc Trò Chơi &raquo;
        </a>
    </div>
</nav>

<div class="obstacle-host-wrapper">

    <!-- BUZZER ALERT BANNER -->
    <div class="buzzer-alert-banner" id="buzzerAlert">
        <div class="d-flex align-items-center justify-content-between flex-wrap">
            <div class="d-flex align-items-center mb-2 mb-sm-0">
                <i class="fas fa-bell fa-2x mr-3 text-warning"></i>
                <div>
                    <h5 class="font-weight-bold mb-0" id="buzzerTitle">🔔 CÓ NGƯỜI BẤM CHUÔNG!</h5>
                    <span id="buzzerDesc" style="opacity: 0.95;">Người chơi đang trả lời từ khóa bức tranh bí mật...</span>
                </div>
            </div>
            <div class="d-flex align-items-center">
                <span class="badge badge-light font-weight-bold py-2 px-3 mr-2" style="font-size: 1rem; color: #b3122c;" id="buzzerTimerBadge">
                    Đang trả lời...
                </span>
                <button type="button" class="btn btn-sm btn-outline-light font-weight-bold rounded-pill px-3" onclick="hostCancelBuzz()" title="Hủy lượt chuông nếu người chơi không trả lời">
                    <i class="fas fa-times mr-1"></i> Hủy chuông
                </button>
            </div>
        </div>
    </div>

    <!-- PUZZLE BOARD CARD -->
    <div class="obstacle-board-card">
        <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-3 border-secondary">
            <div>
                <h4 class="font-weight-bold text-white mb-0">
                    <i class="fas fa-th mr-2 text-warning"></i>Bảng Mảnh Ghép Chướng Ngại Vật
                </h4>
            </div>
            <div class="text-right">
                <span class="badge badge-primary px-3 py-2 font-weight-bold" style="font-size: 0.95rem;">
                    <i class="fas fa-users mr-1"></i> <span id="playerCountDisplay"><?= $totalPlayers ?></span> Người chơi
                </span>
            </div>
        </div>

        <!-- BẢNG MẢNH GHÉP HOẶC HÌNH ẢNH MỞ KHÓA -->
        <?php if ($allOpened): ?>
            <!-- TOÀN BỘ MẢNH GHÉP ĐÃ MỞ: HOST MỞ KHÓA HÌNH LUÔN -->
            <div class="text-center py-2" id="unlockedImageSection">
                <div class="alert alert-success font-weight-bold py-2 mb-3 shadow" style="font-size: 1.15rem; border-radius: 12px; background: rgba(40, 167, 69, 0.25); border: 1px solid #28a745; color: #2ecc71;">
                    <i class="fas fa-unlock-alt mr-2"></i> ĐÃ MỞ HẾT TẤT CẢ CÁC MẢNH GHÉP - BỨC TRANH BÍ MẬT ĐÃ MỞ KHÓA!
                </div>
                <?php if (!empty($quiz['obstacle_image'])): ?>
                    <img src="<?= BASE_URL . htmlspecialchars($quiz['obstacle_image']) ?>" class="img-fluid rounded shadow border border-warning mb-2" style="max-height: 420px; object-fit: contain; width: 100%; background: #000;" alt="Bức tranh bí mật">
                <?php endif; ?>
                <div class="mt-3 p-3 rounded text-center" style="background: rgba(255, 255, 255, 0.08); border: 1px dashed rgba(255, 255, 255, 0.3);">
                    <h5 class="text-warning font-weight-bold mb-0">
                        <i class="fas fa-bell mr-2"></i> Chuông đoán từ khóa đã được mở cho TẤT CẢ người chơi!
                    </h5>
                </div>
            </div>
        <?php else: ?>
            <!-- LƯỚI Ô SỐ: CHƯA MỞ HẾT CÁC MẢNH -->
            <div class="obstacle-grid cols-<?= $cols ?> rows-<?= $rows ?>" id="hostTilesGrid">
                <?php for ($p = 1; $p <= $totalPieces; $p++): 
                    $isOpened = in_array($p, $openedPieces);
                ?>
                    <div class="obstacle-tile <?= $isOpened ? 'disabled' : '' ?>" 
                         id="tile_<?= $p ?>" 
                         <?= !$isOpened ? 'onclick="activatePiece(' . $p . ')"' : '' ?>
                         title="<?= $isOpened ? 'Mảnh này đã mở câu hỏi' : 'Nhấn để mở câu hỏi mảnh ' . $p ?>">
                        <div class="tile-num">🧩 <?= $p ?></div>
                        <span class="tile-badge <?= $isOpened ? 'badge-opened' : 'badge-unopened' ?>">
                            <?= $isOpened ? '<i class="fas fa-check-circle mr-1"></i> ĐÃ MỞ CÂU HỎI' : '<i class="fas fa-lock-open mr-1"></i> CHƯA MỞ' ?>
                        </span>
                    </div>
                <?php endfor; ?>
            </div>
        <?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mt-3 pt-3 border-top border-secondary">
            <span class="text-muted small">
                Nhấn vào một ô số để mở câu hỏi cho người chơi
            </span>
            <span class="text-warning font-weight-bold small">
                Đã mở: <?= count($openedPieces) ?> / <?= $totalPieces ?> mảnh
            </span>
        </div>
    </div>
</div>

<!-- MODAL XÁC NHẬN CÂU TRẢ LỜI CHO HOST -->
<div class="winner-reveal-modal" id="hostReviewModal" style="z-index: 99998;">
    <div class="winner-modal-content" style="max-width: 600px; border-color: #f59f00;">
        <div class="mb-2">
            <span class="badge badge-warning text-dark font-weight-bold px-3 py-1 shadow-sm" style="font-size: 0.95rem;">
                <i class="fas fa-gavel mr-1"></i> XÁC NHẬN CÂU TRẢ LỜI
            </span>
        </div>
        <h4 class="font-weight-bold text-white mb-3">
            Người chơi <span class="text-warning" id="reviewPlayerName">...</span> đã đưa ra đáp án:
        </h4>
        <div class="p-3 mb-3 rounded" style="background: rgba(255, 255, 255, 0.08); border: 2px dashed #f59f00;">
            <div class="small text-white-50 text-uppercase font-weight-bold mb-1">Câu trả lời:</div>
            <div class="font-weight-bold text-white" id="reviewPlayerGuess" style="font-size: 2rem; word-break: break-word; line-height: 1.2;">
                ...
            </div>
        </div>
        <div class="p-2 mb-3 rounded bg-dark border border-secondary text-left d-flex align-items-center justify-content-between px-3">
            <span class="text-white-50 small font-weight-bold">
                <i class="fas fa-key mr-1 text-warning"></i> Đáp án gốc bức tranh:
            </span>
            <span class="badge badge-light text-dark font-weight-bold px-2 py-1" style="font-size: 0.95rem;" id="reviewTargetAnswer">
                <?= htmlspecialchars($quiz['obstacle_answer'] ?? '') ?>
            </span>
        </div>
        <p class="text-white font-weight-bold mb-3" style="font-size: 1.05rem;">
            Bạn xác nhận câu trả lời này là ĐÚNG hay SAI?
        </p>
        <div class="row">
            <div class="col-6">
                <button type="button" class="btn btn-success btn-lg btn-block font-weight-bold py-3 shadow" id="btnHostConfirmTrue" onclick="hostSubmitReview(true)" style="border-radius: 12px; font-size: 1.15rem;">
                    <i class="fas fa-check-circle mr-1"></i> ĐÚNG (True)
                </button>
            </div>
            <div class="col-6">
                <button type="button" class="btn btn-danger btn-lg btn-block font-weight-bold py-3 shadow" id="btnHostConfirmFalse" onclick="hostSubmitReview(false)" style="border-radius: 12px; font-size: 1.15rem;">
                    <i class="fas fa-times-circle mr-1"></i> SAI (False)
                </button>
            </div>
        </div>
    </div>
</div>

<!-- WINNER REVEAL MODAL KHI CÓ NGƯỜI ĐOÁN ĐÚNG HOÀN TOÀN -->
<div class="winner-reveal-modal" id="winnerModal">
    <div class="winner-modal-content">
        <h2 class="display-4 font-weight-bold text-warning mb-2">
            🏆 QUÁN QUÂN! 🏆
        </h2>
        <h4 class="font-weight-bold text-white mb-3" id="winnerNickDisplay">
            Người chơi đã xuất sắc đoán đúng từ khóa!
        </h4>
        <div class="alert alert-warning py-2 mb-3 font-weight-bold text-dark" style="font-size: 1.25rem;" id="winnerAnswerDisplay">
            TỪ KHÓA: ...
        </div>
        <div>
            <img src="" id="winnerSecretImg" class="revealed-img-box" alt="Bức tranh bí mật đã được mở hoàn toàn!">
        </div>
        <p class="text-muted small">Bức tranh đã được mở hoàn toàn 100% trên toàn bộ màn hình người chơi!</p>
        <div class="mt-4">
            <a href="<?= BASE_URL ?>modules/host/leaderboard.php?final=true" class="btn btn-warning btn-lg font-weight-bold px-5 rounded-pill shadow">
                <i class="fas fa-trophy mr-2"></i> Xem Bảng Vinh Danh Chung Cuộc &raquo;
            </a>
        </div>
    </div>
</div>

<audio id="fanfareAudio" preload="auto" src="<?= BASE_URL ?>assets/audio/fanfare.mp3"></audio>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script>
const roomId = <?= $roomId ?>;
let isBuzzingActive = false;
let winnerDeclared = false;

// Web Audio API Synthesizer cho âm thanh chuông báo động buzzer
function playBuzzerAlarmSound() {
    try {
        const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        const osc = audioCtx.createOscillator();
        const gain = audioCtx.createGain();
        osc.type = 'sawtooth';
        osc.frequency.setValueAtTime(440, audioCtx.currentTime);
        osc.frequency.exponentialRampToValueAtTime(880, audioCtx.currentTime + 0.3);
        gain.gain.setValueAtTime(0.5, audioCtx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + 0.4);
        osc.connect(gain);
        gain.connect(audioCtx.destination);
        osc.start();
        osc.stop(audioCtx.currentTime + 0.4);
    } catch(e) {}
}

function activatePiece(pieceIndex) {
    if (isBuzzingActive) {
        alert('Phòng đang có người chơi bấm chuông đoán từ khóa! Vui lòng chờ.');
        return;
    }

    if (!confirm('Kích hoạt câu hỏi cho Mảnh ghép số ' + pieceIndex + '?')) {
        return;
    }

    $.post('<?= BASE_URL ?>modules/api/obstacle_action.php?action=select_piece&id_room=' + roomId, {
        piece_index: pieceIndex
    }, function(res) {
        if (res && res.success) {
            window.location.href = 'play.php';
        } else {
            alert(res.message || 'Không thể mở câu hỏi cho mảnh này');
        }
    }, 'json').fail(function() {
        alert('Lỗi kết nối máy chủ');
    });
}

function hostSubmitReview(isCorrect) {
    if ($('#btnHostConfirmTrue').prop('disabled')) return;
    $('#btnHostConfirmTrue, #btnHostConfirmFalse').prop('disabled', true);

    $.post('<?= BASE_URL ?>modules/api/obstacle_action.php?action=host_confirm_guess&id_room=' + roomId, {
        decision: isCorrect ? 'true' : 'false'
    }, function(res) {
        $('#btnHostConfirmTrue, #btnHostConfirmFalse').prop('disabled', false);
        if (res && res.success) {
            $('#hostReviewModal').hide();
            if (res.correct) {
                winnerDeclared = true;
                clearInterval(pollInterval);
                $('#winnerNickDisplay').text('Chúc mừng Quán quân: ' + (res.winner_name || '') + '!');
                $('#winnerAnswerDisplay').text('TỪ KHÓA CHÍNH XÁC: ' + (res.answer || ''));
                if (res.obstacle_image) {
                    $('#winnerSecretImg').attr('src', res.obstacle_image);
                }
                $('#winnerModal').css('display', 'flex');
                try {
                    document.getElementById('fanfareAudio').play();
                } catch(e) {}
            } else if (res.all_disqualified) {
                winnerDeclared = true;
                clearInterval(pollInterval);
                alert('Tất cả người chơi đều đã trả lời sai từ khóa bức tranh! Trò chơi kết thúc, người chiến thắng được xếp hạng theo số điểm.');
                window.location.href = 'leaderboard.php?final=true';
            } else {
                $('#buzzerAlert').slideUp(200);
            }
        } else {
            alert(res.message || 'Lỗi khi gửi xác nhận');
        }
    }, 'json').fail(function() {
        $('#btnHostConfirmTrue, #btnHostConfirmFalse').prop('disabled', false);
        alert('Lỗi kết nối máy chủ');
    });
}

function hostCancelBuzz() {
    if (!confirm('Bạn có chắc muốn hủy lượt bấm chuông này để phòng tiếp tục?')) return;
    $.post('<?= BASE_URL ?>modules/api/obstacle_action.php?action=host_cancel_buzz&id_room=' + roomId, function() {
        $('#buzzerAlert').slideUp(200);
        $('#hostReviewModal').hide();
    });
}

// Polling kiểm tra realtime: Chuông bấm và Người thắng cuộc
const pollInterval = setInterval(function() {
    $.getJSON('<?= BASE_URL ?>modules/api/obstacle_action.php?action=get_obstacle_state&id_room=' + roomId, function(data) {
        if (!data || !data.success) return;

        // Nếu tất cả mảnh ghép đã mở mà giao diện chưa chuyển sang ảnh mở khóa: reload
        if (data.all_pieces_opened && !$('#unlockedImageSection').length) {
            window.location.reload();
            return;
        }

        // 1. Kiểm tra Người chiến thắng chung cuộc hoặc Tất cả trả lời sai
        if (data.status === 'finished' || (data.is_revealed && !winnerDeclared)) {
            winnerDeclared = true;
            clearInterval(pollInterval);
            $('#hostReviewModal').hide();
            $('#buzzerAlert').hide();
            if (data.all_disqualified) {
                alert('Tất cả người chơi đều đã trả lời sai từ khóa bức tranh! Trò chơi kết thúc, người chiến thắng được xếp hạng theo số điểm.');
                window.location.href = 'leaderboard.php?final=true';
                return;
            }
            if (data.winner_player_name) {
                $('#winnerNickDisplay').text('Chúc mừng Quán quân: ' + data.winner_player_name + '!');
                $('#winnerAnswerDisplay').text('TỪ KHÓA CHÍNH XÁC: ' + (data.obstacle_answer || ''));
                if (data.obstacle_image) {
                    $('#winnerSecretImg').attr('src', data.obstacle_image);
                }
                $('#winnerModal').css('display', 'flex');
                try {
                    document.getElementById('fanfareAudio').play();
                } catch(e) {}
            } else {
                window.location.href = 'leaderboard.php?final=true';
            }
            return;
        }

        // 2. Kiểm tra Buzzer
        if (data.buzzer_status === 'buzzing') {
            if (!isBuzzingActive) {
                isBuzzingActive = true;
                playBuzzerAlarmSound();
            }
            $('#buzzerTitle').text('🔔 ' + (data.buzzer_player_name || 'Người chơi') + ' ĐANG BẤM CHUÔNG ĐOÁN TỪ KHÓA!');
            $('#buzzerDesc').text('Đang chờ người chơi nhập câu trả lời...');
            $('#buzzerTimerBadge').html('<i class="fas fa-edit mr-1"></i> Đang nhập...');
            $('#buzzerAlert').slideDown(200);
            $('#hostReviewModal').hide();
        } else if (data.buzzer_status === 'pending_review') {
            if (!isBuzzingActive) {
                isBuzzingActive = true;
                playBuzzerAlarmSound();
            }
            $('#buzzerTitle').text('🔔 ' + (data.buzzer_player_name || 'Người chơi') + ' ĐÃ GỬI CÂU TRẢ LỜI!');
            $('#buzzerDesc').text('Vui lòng xem và xác nhận câu trả lời là ĐÚNG hay SAI.');
            $('#buzzerTimerBadge').html('<i class="fas fa-hourglass-half mr-1"></i> Chờ duyệt');
            $('#buzzerAlert').slideDown(200);

            $('#reviewPlayerName').text(data.buzzer_player_name || 'Người chơi');
            $('#reviewPlayerGuess').text(data.buzzer_guess || '...');
            if (data.obstacle_answer) {
                $('#reviewTargetAnswer').text(data.obstacle_answer);
            }
            if (!$('#hostReviewModal').is(':visible') && !winnerDeclared) {
                $('#hostReviewModal').css('display', 'flex');
            }
        } else {
            if (isBuzzingActive) {
                isBuzzingActive = false;
                $('#buzzerAlert').slideUp(200);
            }
            $('#hostReviewModal').hide();
        }
    });
}, 750);
</script>
</body>
</html>
