<?php
// Host Obstacle Puzzle Board
// Included by modules/host/play.php when room.game_type === 'obstacle' and status === 'board'

require_once __DIR__ . '/TeamManager.php';

$quiz = db_fetch_one("SELECT * FROM quiz WHERE id_quiz = :q", [':q' => $room['id_quiz']]);
$totalPieces = intval($quiz['total_pieces'] ?? 4);
if (!in_array($totalPieces, [4, 6, 8, 9, 10, 12, 15, 16, 20, 25])) $totalPieces = 4;

$rawObsImg = $quiz['obstacle_image'] ?? ($room['obstacle_image'] ?? '');
$secretImageUrl = '';
if (!empty($rawObsImg)) {
    if (preg_match('#^https?://#i', $rawObsImg)) {
        $secretImageUrl = $rawObsImg;
    } else {
        $secretImageUrl = BASE_URL . ltrim($rawObsImg, '/');
    }
}

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

$pageTitle = "Obstacle Puzzle Board - Host";
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

<nav class="navbar navbar-dark bg-dark px-4 py-3 d-flex justify-content-between align-items-center shadow-sm" style="min-height: 64px;">
    <div class="d-flex align-items-center">
        <img src="<?= BASE_URL ?>assets/img/logo.png" alt="Logo" style="max-height: 34px; filter: brightness(100);" class="mr-3">
        <span class="badge badge-light font-weight-bold font-monospace px-3 py-2" style="font-size: 0.95rem; border-radius: 8px; letter-spacing: 0.5px;">PIN: <?= htmlspecialchars($room['pin']) ?></span>
    </div>
    <div class="d-flex align-items-center">
        <a href="<?= BASE_URL ?>modules/host/leaderboard.php?final=true" onclick="return confirm('Are you sure you want to end the game and view final podium?');" class="btn btn-outline-danger btn-sm font-weight-bold rounded-pill px-3 py-2">
            <i class="fas fa-flag-checkered mr-1"></i> End Game &raquo;
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
                    <h5 class="font-weight-bold mb-0" id="buzzerTitle">🔔 A PLAYER BUZZED IN!</h5>
                    <span id="buzzerDesc" style="opacity: 0.95;">Player is answering the secret picture keyword...</span>
                </div>
            </div>
            <div class="d-flex align-items-center">
                <span class="badge badge-light font-weight-bold py-2 px-3 mr-2" style="font-size: 1rem; color: #b3122c;" id="buzzerTimerBadge">
                    Answering...
                </span>
                <button type="button" class="btn btn-sm btn-outline-light font-weight-bold rounded-pill px-3" onclick="hostCancelBuzz()" title="Cancel buzzer if player does not answer">
                    <i class="fas fa-times mr-1"></i> Cancel Buzzer
                </button>
            </div>
        </div>
    </div>

    <?php if (!empty($room['is_team_mode'])): 
        $hostTeamScores = TeamManager::getTeamLeaderboard($roomId);
    ?>
        <div class="card border-0 shadow-sm p-3 mb-3" style="border-radius: 16px; background: rgba(30, 17, 54, 0.95); border: 1px solid rgba(255,255,255,0.15);">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="font-weight-bold text-white small"><i class="fas fa-users text-primary mr-1"></i> TEAM SCORES</span>
                <span class="badge badge-primary font-weight-bold"><?= count($hostTeamScores) ?> Teams</span>
            </div>
            <div class="d-flex flex-wrap" id="hostTeamScoresContainer" style="gap: 10px;">
                <?php foreach ($hostTeamScores as $ht): ?>
                    <div class="flex-grow-1 p-2 rounded text-center shadow-sm" style="background: rgba(255,255,255,0.08); border-top: 4px solid <?= $ht['color'] ?>; min-width: 120px;">
                        <div class="small font-weight-bold text-white">
                            <i class="<?= $ht['icon'] ?> mr-1" style="color: <?= $ht['color'] ?>;"></i><?= htmlspecialchars($ht['short_name']) ?>
                        </div>
                        <div class="font-weight-bold" style="font-size: 1.2rem; color: #ffca28;" id="team_score_<?= $ht['id'] ?>">
                            <?= number_format($ht['total_score']) ?>
                        </div>
                        <small class="text-white-50"><?= $ht['member_count'] ?> players</small>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- PUZZLE BOARD CARD -->
    <div class="obstacle-board-card">
        <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-3 border-secondary">
            <div>
                <h4 class="font-weight-bold text-white mb-0">
                    <i class="fas fa-th mr-2 text-warning"></i>Obstacle Puzzle Board
                </h4>
            </div>
            <div class="text-right">
                <span class="badge badge-primary px-3 py-2 font-weight-bold" style="font-size: 0.95rem;">
                    <i class="fas fa-users mr-1"></i> <span id="playerCountDisplay"><?= $totalPlayers ?></span> Players
                </span>
            </div>
        </div>

        <!-- PUZZLE BOARD OR UNLOCKED IMAGE -->
        <?php if ($allOpened): ?>
            <!-- ALL PIECES OPENED: HOST REVEALS SECRET IMAGE -->
            <div class="text-center py-2" id="unlockedImageSection">
                <div class="alert alert-success font-weight-bold py-2 mb-3 shadow" style="font-size: 1.15rem; border-radius: 12px; background: rgba(40, 167, 69, 0.25); border: 1px solid #28a745; color: #2ecc71;">
                    <i class="fas fa-unlock-alt mr-2"></i> ALL PIECES REVEALED - SECRET PICTURE UNLOCKED!
                </div>
                <?php if (!empty($secretImageUrl)): ?>
                    <img src="<?= htmlspecialchars($secretImageUrl) ?>" class="img-fluid rounded shadow border border-warning mb-2" style="max-height: 420px; object-fit: contain; width: 100%; background: #000;" alt="Secret picture">
                <?php endif; ?>
                <div class="mt-3 p-3 rounded text-center" style="background: rgba(255, 255, 255, 0.08); border: 1px dashed rgba(255, 255, 255, 0.3);">
                    <h5 class="text-warning font-weight-bold mb-0">
                        <i class="fas fa-bell mr-2"></i> Keyword buzzer is now open for ALL players!
                    </h5>
                </div>
            </div>
        <?php else: ?>
            <!-- GRID TILES: UNOPENED PIECES -->
            <div class="obstacle-grid cols-<?= $cols ?> rows-<?= $rows ?>" id="hostTilesGrid">
                <?php for ($p = 1; $p <= $totalPieces; $p++): 
                    $isOpened = in_array($p, $openedPieces);
                ?>
                    <div class="obstacle-tile <?= $isOpened ? 'disabled' : '' ?>" 
                         id="tile_<?= $p ?>" 
                         <?= !$isOpened ? 'onclick="activatePiece(' . $p . ')"' : '' ?>
                         title="<?= $isOpened ? 'Question already opened for this piece' : 'Click to open question for piece ' . $p ?>">
                        <div class="tile-num">🧩 <?= $p ?></div>
                        <span class="tile-badge <?= $isOpened ? 'badge-opened' : 'badge-unopened' ?>">
                            <?= $isOpened ? '<i class="fas fa-check-circle mr-1"></i> QUESTION OPENED' : '<i class="fas fa-lock-open mr-1"></i> UNOPENED' ?>
                        </span>
                    </div>
                <?php endfor; ?>
            </div>
        <?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mt-3 pt-3 border-top border-secondary">
            <span class="text-muted small">
                Click a numbered tile to open its question for players
            </span>
            <span class="text-warning font-weight-bold small">
                Opened: <?= count($openedPieces) ?> / <?= $totalPieces ?> pieces
            </span>
        </div>
    </div>
</div>

<!-- HOST REVIEW MODAL -->
<div class="winner-reveal-modal" id="hostReviewModal" style="z-index: 99998;">
    <div class="winner-modal-content" style="max-width: 600px; border-color: #f59f00;">
        <div class="mb-2">
            <span class="badge badge-warning text-dark font-weight-bold px-3 py-1 shadow-sm" style="font-size: 0.95rem;">
                <i class="fas fa-gavel mr-1"></i> CONFIRM ANSWER
            </span>
        </div>
        <h4 class="font-weight-bold text-white mb-3">
            Player <span class="text-warning" id="reviewPlayerName">...</span> submitted answer:
        </h4>
        <div class="p-3 mb-3 rounded" style="background: rgba(255, 255, 255, 0.08); border: 2px dashed #f59f00;">
            <div class="small text-white-50 text-uppercase font-weight-bold mb-1">Answer:</div>
            <div class="font-weight-bold text-white" id="reviewPlayerGuess" style="font-size: 2rem; word-break: break-word; line-height: 1.2;">
                ...
            </div>
        </div>
        <p class="text-white font-weight-bold mb-3" style="font-size: 1.05rem;">
            Do you confirm this answer is CORRECT or INCORRECT?
        </p>
        <div class="row">
            <div class="col-6">
                <button type="button" class="btn btn-success btn-lg btn-block font-weight-bold py-3 shadow" id="btnHostConfirmTrue" onclick="hostSubmitReview(true)" style="border-radius: 12px; font-size: 1.15rem;">
                    <i class="fas fa-check-circle mr-1"></i> CORRECT (True)
                </button>
            </div>
            <div class="col-6">
                <button type="button" class="btn btn-danger btn-lg btn-block font-weight-bold py-3 shadow" id="btnHostConfirmFalse" onclick="hostSubmitReview(false)" style="border-radius: 12px; font-size: 1.15rem;">
                    <i class="fas fa-times-circle mr-1"></i> INCORRECT (False)
                </button>
            </div>
        </div>
    </div>
</div>

<!-- WINNER REVEAL MODAL -->
<div class="winner-reveal-modal" id="winnerModal">
    <div class="winner-modal-content">
        <h2 class="display-4 font-weight-bold text-warning mb-2">
            🏆 CHAMPION! 🏆
        </h2>
        <h4 class="font-weight-bold text-white mb-3" id="winnerNickDisplay">
            The player successfully guessed the keyword!
        </h4>
        <div class="alert alert-warning py-2 mb-3 font-weight-bold text-dark" style="font-size: 1.25rem;" id="winnerAnswerDisplay">
            KEYWORD: ...
        </div>
        <div>
            <img src="<?= htmlspecialchars($secretImageUrl) ?>" id="winnerSecretImg" class="revealed-img-box" alt="Secret picture has been fully revealed!" style="<?= empty($secretImageUrl) ? 'display: none;' : '' ?>">
        </div>
        <p class="text-muted small">The picture is 100% revealed on all players' screens!</p>
        <div class="mt-4">
            <a href="<?= BASE_URL ?>modules/host/leaderboard.php?final=true" class="btn btn-warning btn-lg font-weight-bold px-5 rounded-pill shadow">
                <i class="fas fa-trophy mr-2"></i> View Final Podium &raquo;
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

// Web Audio API Synthesizer for buzzer alarm sound
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
        alert('A player has buzzed in to guess the keyword! Please wait.');
        return;
    }

    if (!confirm('Activate question for Piece #' + pieceIndex + '?')) {
        return;
    }

    $.post('<?= BASE_URL ?>modules/api/obstacle_action.php?action=select_piece&id_room=' + roomId, {
        piece_index: pieceIndex
    }, function(res) {
        if (res && res.success) {
            window.location.href = 'play.php';
        } else {
            alert(res.message || 'Cannot open question for this piece');
        }
    }, 'json').fail(function() {
        alert('Server connection error');
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
                $('#winnerNickDisplay').text('Congratulations to Champion: ' + (res.winner_name || '') + '!');
                $('#winnerAnswerDisplay').text('CORRECT KEYWORD: ' + (res.answer || ''));
                const secretImg = res.obstacle_image || '<?= htmlspecialchars($secretImageUrl, ENT_QUOTES, 'UTF-8') ?>';
                if (secretImg) {
                    $('#winnerSecretImg').attr('src', secretImg).show();
                }
                $('#winnerModal').css('display', 'flex');
                try {
                    document.getElementById('fanfareAudio').play();
                } catch(e) {}
            } else if (res.all_disqualified) {
                winnerDeclared = true;
                clearInterval(pollInterval);
                alert('All players guessed the secret keyword incorrectly! Game ended, winners ranked by score.');
                window.location.href = 'leaderboard.php?final=true';
            } else {
                $('#buzzerAlert').slideUp(200);
            }
        } else {
            alert(res.message || 'Error submitting confirmation');
        }
    }, 'json').fail(function() {
        $('#btnHostConfirmTrue, #btnHostConfirmFalse').prop('disabled', false);
        alert('Server connection error');
    });
}

function hostCancelBuzz() {
    if (!confirm('Are you sure you want to cancel this buzzer turn so the room can continue?')) return;
    $.post('<?= BASE_URL ?>modules/api/obstacle_action.php?action=host_cancel_buzz&id_room=' + roomId, function() {
        $('#buzzerAlert').slideUp(200);
        $('#hostReviewModal').hide();
    });
}

// Polling: Buzzer and winner status
const pollInterval = setInterval(function() {
    $.getJSON('<?= BASE_URL ?>modules/api/obstacle_action.php?action=get_obstacle_state&id_room=' + roomId, function(data) {
        if (!data || !data.success) return;

        // If all pieces opened and UI not showing revealed image: reload
        if (data.all_pieces_opened && !$('#unlockedImageSection').length) {
            window.location.reload();
            return;
        }

        // Update team scores in realtime if Team Mode is enabled
        if (data.live_teams && data.live_teams.length) {
            data.live_teams.forEach(function(t) {
                const el = document.getElementById('team_score_' + t.id);
                if (el) el.textContent = Number(t.total_score).toLocaleString();
            });
        }

        // 1. Check winner or all disqualified
        if (data.status === 'finished' || (data.is_revealed && !winnerDeclared)) {
            winnerDeclared = true;
            clearInterval(pollInterval);
            $('#hostReviewModal').hide();
            $('#buzzerAlert').hide();
            if (data.all_disqualified) {
                alert('All players guessed the secret keyword incorrectly! Game ended, winners ranked by score.');
                window.location.href = 'leaderboard.php?final=true';
                return;
            }
            if (data.winner_player_name) {
                const winnerLabel = data.winner_player_name + (data.winner_team_name ? ' (' + data.winner_team_name + ')' : '');
                $('#winnerNickDisplay').text('Congratulations to Champion: ' + winnerLabel + '!');
                $('#winnerAnswerDisplay').text('CORRECT KEYWORD: ' + (data.obstacle_answer || ''));
                const secretImg = data.obstacle_image || '<?= htmlspecialchars($secretImageUrl, ENT_QUOTES, 'UTF-8') ?>';
                if (secretImg) {
                    $('#winnerSecretImg').attr('src', secretImg).show();
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

        // 2. Check Buzzer
        const buzzerLabel = (data.buzzer_player_name || 'Player') + (data.buzzer_team_name ? ' (' + data.buzzer_team_name + ')' : '');
        if (data.buzzer_status === 'buzzing') {
            if (!isBuzzingActive) {
                isBuzzingActive = true;
                playBuzzerAlarmSound();
            }
            $('#buzzerTitle').text('🔔 ' + buzzerLabel + ' IS BUZZING IN TO GUESS KEYWORD!');
            $('#buzzerDesc').text('Waiting for player to enter answer...');
            $('#buzzerTimerBadge').html('<i class="fas fa-edit mr-1"></i> Typing...');
            $('#buzzerAlert').slideDown(200);
            $('#hostReviewModal').hide();
        } else if (data.buzzer_status === 'pending_review') {
            if (!isBuzzingActive) {
                isBuzzingActive = true;
                playBuzzerAlarmSound();
            }
            $('#buzzerTitle').text('🔔 ' + buzzerLabel + ' HAS SUBMITTED AN ANSWER!');
            $('#buzzerDesc').text('Please review and confirm if the answer is CORRECT or INCORRECT.');
            $('#buzzerTimerBadge').html('<i class="fas fa-hourglass-half mr-1"></i> Pending Review');
            $('#buzzerAlert').slideDown(200);

            $('#reviewPlayerName').text(buzzerLabel);
            $('#reviewPlayerGuess').text(data.buzzer_guess || '...');
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
