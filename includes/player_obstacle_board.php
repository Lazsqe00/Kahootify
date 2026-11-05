<?php
// Player Obstacle Puzzle Board
// Included by modules/player/play.php when room.game_type === 'obstacle' and status === 'board'

require_once __DIR__ . '/TeamManager.php';

$quiz = db_fetch_one("SELECT * FROM quiz WHERE id_quiz = :q", [':q' => $room['id_quiz']]);
$totalPieces = intval($quiz['total_pieces'] ?? 4);
if (!in_array($totalPieces, [4, 6, 8, 9, 10, 12, 15, 16, 20, 25])) $totalPieces = 4;

$openedPieces = !empty($room['opened_pieces']) ? json_decode($room['opened_pieces'], true) : [];
if (!is_array($openedPieces)) $openedPieces = [];

$myUnlocked = !empty($player['unlocked_pieces']) ? json_decode($player['unlocked_pieces'], true) : [];
if (!is_array($myUnlocked)) $myUnlocked = [];

$allOpened = (count($openedPieces) >= $totalPieces);
$gameConfig = !empty($room['game_config']) ? json_decode($room['game_config'], true) : [];
if (!is_array($gameConfig)) $gameConfig = [];

if ($allOpened) {
    if (empty($gameConfig['final_round_reset'])) {
        $gameConfig['final_round_reset'] = true;
        db_execute("UPDATE room SET game_config = :gc WHERE id_room = :r", [
            ':gc' => json_encode($gameConfig),
            ':r'  => $room['id_room']
        ]);
        db_execute("UPDATE players SET is_disqualified_obstacle = 0 WHERE id_room = :r", [
            ':r'  => $room['id_room']
        ]);
        $player['is_disqualified_obstacle'] = 0;
    }
    $myUnlocked = range(1, $totalPieces);
}

$isDisqualified = !empty($player['is_disqualified_obstacle']);
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

$pageTitle = "Obstacle Puzzle - " . htmlspecialchars($nickname);
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
                <i class="fas fa-puzzle-piece mr-1"></i> OBSTACLE PUZZLE
            </span>
            <span class="font-weight-bold d-none d-sm-inline"><?= htmlspecialchars($nickname) ?></span>
            <?php if (!empty($room['is_team_mode']) && !empty($player['team_id'])): 
                $myTeam = TeamManager::getTeamInfo((int)$player['team_id']);
            ?>
                <span class="badge ml-2 font-weight-bold shadow-sm" style="background: <?= $myTeam['color'] ?>; color: <?= $myTeam['text_color'] ?>; border-radius: 8px; padding: 4px 9px; font-size: 0.8rem;">
                    <i class="<?= $myTeam['icon'] ?> mr-1"></i><?= htmlspecialchars($myTeam['short_name']) ?>
                </span>
            <?php endif; ?>
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
            <i class="fas fa-info-circle mr-1 text-warning"></i> You guessed the keyword incorrectly and your buzzer is temporarily locked for these pieces. You can still answer questions and earn points normally. Buzzer will be unlocked once all pieces are revealed!
        </div>
    <?php elseif ($isDisqualified && $allOpened): ?>
        <div class="alert alert-danger font-weight-bold text-center py-2 px-3 shadow-sm mb-3" style="border-radius: 14px; font-size: 0.95rem; background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb;">
            <i class="fas fa-ban mr-1 text-danger"></i> You have used your guess attempt in this final round. Please wait for the final results!
        </div>
    <?php elseif ($allOpened): ?>
        <div class="alert alert-success font-weight-bold text-center py-2 px-3 shadow-sm mb-3" style="border-radius: 14px; font-size: 0.95rem; background: #d4edda; color: #155724; border: 1px solid #c3e6cb;">
            <i class="fas fa-unlock-alt mr-1 text-success"></i> ALL PIECES REVEALED! The secret picture is fully revealed. Keyword guessing buzzer has been re-enabled for ALL players. Buzz in quickly to claim your chance!
        </div>
    <?php endif; ?>

    <div class="puzzle-main-card">
        <div class="d-flex justify-content-between align-items-center mb-3 text-white">
            <h5 class="font-weight-bold mb-0">
                <i class="fas fa-image text-warning mr-2"></i>Secret Picture
            </h5>
            <span class="badge badge-success px-3 py-1 font-weight-bold" id="unlockedCountBadge">
                <?= count($myUnlocked) ?> / <?= $totalPieces ?> Pieces Unlocked
            </span>
        </div>

        <div class="obstacle-puzzle-container">
            <?php if ($secretImageUrl): ?>
                <img src="<?= htmlspecialchars($secretImageUrl) ?>" class="obstacle-secret-img" alt="Secret picture">
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
                            <span class="piece-badge"><i class="fas fa-lock mr-1"></i> SECRET</span>
                        </div>
                    </div>
                <?php endfor; ?>
            </div>
        </div>

        <div class="mt-3 text-center text-white-50 small">
            <i class="fas fa-lightbulb text-warning mr-1"></i>
            Answer questions correctly to reveal your own pieces, or ring the buzzer below to guess the secret keyword!
        </div>
    </div>
</div>

<!-- STICKY BUZZER BUTTON AT BOTTOM -->
<div class="bottom-buzz-bar">
    <?php if (empty($openedPieces)): ?>
        <button type="button" class="btn btn-buzz btn-buzz-disabled" disabled id="btnBuzz" title="At least one piece must be revealed before guessing">
            <i class="fas fa-lock mr-2"></i> GUESS LOCKED (NO PIECES REVEALED YET)
        </button>
    <?php elseif ($isDisqualified && !$allOpened): ?>
        <button type="button" class="btn btn-buzz btn-buzz-disabled" disabled id="btnBuzz">
            <i class="fas fa-lock mr-2"></i> BUZZER LOCKED (WAIT FOR ALL PIECES)
        </button>
    <?php elseif ($isDisqualified && $allOpened): ?>
        <button type="button" class="btn btn-buzz btn-buzz-disabled" disabled id="btnBuzz">
            <i class="fas fa-ban mr-2"></i> OUT OF GUESSES
        </button>
    <?php else: ?>
        <button type="button" class="btn btn-buzz" id="btnBuzz" onclick="claimBuzzer()">
            <i class="fas fa-bell mr-2"></i> GUESS SECRET PICTURE
        </button>
    <?php endif; ?>
</div>

<!-- MODAL KEYWORD INPUT FOR BUZZER WINNER -->
<div class="player-modal-backdrop" id="guessModal">
    <div class="player-modal-card">
        <h3 class="font-weight-bold text-warning mb-1">
            🔔 YOUR TURN TO GUESS!
        </h3>
        <p class="text-white-50 small mb-3">Enter the secret picture keyword:</p>

        <!-- KEYWORD INPUT FORM -->
        <form id="guessForm" onsubmit="submitGuess(event)">
            <div class="form-group mb-3">
                <input type="text" id="guessInput" class="form-control form-control-lg text-center font-weight-bold" 
                       placeholder="Enter answer keyword..." autocomplete="off" required>
            </div>
            <button type="submit" class="btn btn-kahoot btn-kahoot-green btn-lg btn-block font-weight-bold shadow mb-2" id="btnSubmitGuess">
                <i class="fas fa-paper-plane mr-1"></i> CONFIRM ANSWER
            </button>
            <button type="button" class="btn btn-sm btn-link text-white-50 font-weight-bold" onclick="cancelGuess()" style="text-decoration: underline;">
                <i class="fas fa-times-circle mr-1"></i> Skip answering (Forfeit guess)
            </button>
        </form>

        <!-- SUBMITTED AND WAITING FOR HOST VERIFICATION -->
        <div id="guessWaitingHost" style="display: none;" class="py-3">
            <div class="spinner-border text-warning mb-3" style="width: 3.2rem; height: 3.2rem;" role="status"></div>
            <h4 class="font-weight-bold text-white mb-2">ANSWER SUBMITTED!</h4>
            <div class="p-2 mb-2 rounded bg-dark border border-secondary text-warning font-weight-bold" id="submittedGuessDisplay" style="font-size: 1.3rem; word-break: break-word;">
                ...
            </div>
            <p class="text-white-50 small mb-0">Waiting for Host to verify your answer...</p>
        </div>
    </div>
</div>

<!-- MODAL ROOM LOCKED WHEN ANOTHER PLAYER BUZZES -->
<div class="player-modal-backdrop" id="roomLockedModal">
    <div class="player-modal-card" style="border-color: #e21b3c;">
        <i class="fas fa-bell fa-4x text-danger mb-3" style="animation: buzzPulse 1s infinite;"></i>
        <h4 class="font-weight-bold text-white mb-2" id="lockedPlayerNotice">
            A PLAYER HAS BUZZED IN!
        </h4>
        <p class="text-white-50 mb-4" id="lockedPlayerSubtext">
            The game is paused for the player to answer and Host to verify. Please wait...
        </p>
        <div class="spinner-border text-danger" role="status"></div>
    </div>
</div>

<!-- FINAL VICTORY MODAL WHEN KEYWORD GUESSED -->
<div class="player-modal-backdrop" id="victoryModal">
    <div class="player-modal-card" style="border-color: #f2994a; max-width: 640px;">
        <h2 class="display-4 font-weight-bold text-warning mb-2">🏆 GAME OVER! 🏆</h2>
        <h4 class="font-weight-bold text-white mb-3" id="victoryWinnerDisplay">
            Congratulations to the Champion!
        </h4>
        <div class="alert alert-warning py-2 mb-3 font-weight-bold text-dark" style="font-size: 1.2rem;" id="victoryAnswerDisplay">
            KEYWORD: ...
        </div>
        <p class="text-muted small">The secret picture has been 100% revealed!</p>
        <div class="mt-4">
            <a href="<?= BASE_URL ?>modules/player/play.php?pid=<?= $playerId ?>&token=<?= urlencode($token) ?>" class="btn btn-warning btn-lg font-weight-bold px-4 rounded-pill">
                <i class="fas fa-trophy mr-1"></i> View Final Podium &raquo;
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
    if ($('#btnBuzz').hasClass('btn-buzz-disabled') || $('#btnBuzz').prop('disabled')) {
        alert('Cannot guess yet! At least one puzzle piece must be revealed first.');
        return;
    }

    $.post('<?= BASE_URL ?>modules/api/obstacle_action.php?action=buzz&id_room=' + roomId, {
        id_player: playerId,
        token: playerToken
    }, function(res) {
        if (res && res.success && res.your_turn) {
            openGuessModal();
        } else {
            alert(res.message || 'Cannot buzz in at this time');
        }
    }, 'json').fail(function() {
        alert('Server connection error');
    });
}

function openGuessModal() {
    isMyTurnToGuess = true;
    $('#guessForm').show();
    $('#guessWaitingHost').hide();
    $('#guessInput').val('').focus();
    $('#btnSubmitGuess').prop('disabled', false).html('<i class="fas fa-paper-plane mr-1"></i> CONFIRM ANSWER');
    $('#guessModal').css('display', 'flex');
}

function cancelGuess() {
    $.post('<?= BASE_URL ?>modules/api/obstacle_action.php?action=cancel_or_timeout_guess&id_room=' + roomId, {
        id_player: playerId,
        token: playerToken
    }, function() {
        alert('You did not provide an answer and lost buzzer rights until all pieces are revealed!');
        window.location.reload();
    }, 'json').fail(function() {
        window.location.reload();
    });
}

function submitGuess(e) {
    e.preventDefault();
    const guessText = $('#guessInput').val().trim();
    if (!guessText) return;

    $('#btnSubmitGuess').prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Sending...');

    $.post('<?= BASE_URL ?>modules/api/obstacle_action.php?action=submit_guess&id_room=' + roomId, {
        id_player: playerId,
        token: playerToken,
        guess: guessText
    }, function(res) {
        if (res && res.success) {
            // Switch modal to waiting for Host verification
            $('#guessForm').hide();
            $('#submittedGuessDisplay').text('"' + guessText + '"');
            $('#guessWaitingHost').show();
        } else {
            $('#btnSubmitGuess').prop('disabled', false).html('<i class="fas fa-paper-plane mr-1"></i> CONFIRM ANSWER');
            alert(res.message || 'Failed to submit answer');
        }
    }, 'json').fail(function() {
        alert('Server connection error');
        $('#btnSubmitGuess').prop('disabled', false).html('<i class="fas fa-paper-plane mr-1"></i> CONFIRM ANSWER');
    });
}

function revealEntirePuzzle(winnerName, answer) {
    // Reveal 100% of pieces: remove overlay
    $('.obstacle-piece-tile').removeClass('piece-locked').addClass('piece-unlocked');
    $('#victoryWinnerDisplay').text('🏆 ' + (winnerName || 'Player') + ' successfully guessed the keyword!');
    $('#victoryAnswerDisplay').text('CORRECT KEYWORD: ' + (answer || ''));
    $('#victoryModal').css('display', 'flex');
    try {
        document.getElementById('fanfareAudio').play();
    } catch(e) {}
}

// Polling realtime
const pollInterval = setInterval(function() {
    $.getJSON('<?= BASE_URL ?>modules/api/obstacle_action.php?action=get_obstacle_state&id_room=' + roomId + '&pid=' + playerId, function(data) {
        if (!data || !data.success) return;

        // 1. If game ended
        if (data.status === "finished") {
            clearInterval(pollInterval);
            $('#guessModal').hide();
            $('#roomLockedModal').hide();
            if (data.all_disqualified) {
                alert('All players guessed the secret keyword incorrectly! Game ended, winners ranked by score.');
            }
            window.location.href = '<?= BASE_URL ?>modules/player/play.php?pid=' + playerId + '&token=' + encodeURIComponent(playerToken);
            return;
        }

        // 2. If Host switched to question (numeric status), redirect to question screen
        if (data.status && data.status !== 'board' && data.status !== 'waiting') {
            clearInterval(pollInterval);
            window.location.href = '<?= BASE_URL ?>modules/player/play.php?pid=' + playerId + '&token=' + encodeURIComponent(playerToken);
            return;
        }

        // 3. If there is a winner
        if (data.is_revealed && data.winner_player_name && !winnerDeclared) {
            winnerDeclared = true;
            clearInterval(pollInterval);
            $('#guessModal').hide();
            $('#roomLockedModal').hide();
            const winnerLabel = data.winner_player_name + (data.winner_team_name ? ' (' + data.winner_team_name + ')' : '');
            revealEntirePuzzle(winnerLabel, data.obstacle_answer);
            return;
        }

        // 4. Handle Buzzer
        const otherBuzzerLabel = (data.buzzer_player_name || 'Player') + (data.buzzer_team_name ? ' (' + data.buzzer_team_name + ')' : '');
        if (data.buzzer_status === 'buzzing') {
            if (data.buzzer_player_id === playerId) {
                // This is current player's turn
                if (!isMyTurnToGuess) {
                    openGuessModal();
                }
            } else {
                // Another player buzzed in: lock screen
                $('#lockedPlayerNotice').text('🔔 ' + otherBuzzerLabel + ' IS BUZZING IN!');
                $('#lockedPlayerSubtext').text('Player is entering their answer...');
                if (!$('#roomLockedModal').is(':visible')) {
                    playBuzzerSound();
                    $('#roomLockedModal').css('display', 'flex');
                }
            }
        } else if (data.buzzer_status === 'pending_review') {
            if (data.buzzer_player_id === playerId) {
                // Submitted answer, waiting for Host review
                if (!$('#guessModal').is(':visible')) {
                    $('#guessModal').css('display', 'flex');
                }
                $('#guessForm').hide();
                if (data.buzzer_guess) {
                    $('#submittedGuessDisplay').text('"' + data.buzzer_guess + '"');
                }
                $('#guessWaitingHost').show();
            } else {
                // Another player submitted answer, waiting for Host review
                $('#lockedPlayerNotice').text('🔔 ' + otherBuzzerLabel + ' HAS SUBMITTED AN ANSWER!');
                $('#lockedPlayerSubtext').text('Waiting for Host to verify answer...');
                if (!$('#roomLockedModal').is(':visible')) {
                    playBuzzerSound();
                    $('#roomLockedModal').css('display', 'flex');
                }
            }
        } else {
            // Idle: Close room lock
            if ($('#roomLockedModal').is(':visible')) {
                $('#roomLockedModal').hide();
            }
            if (isMyTurnToGuess) {
                isMyTurnToGuess = false;
                $('#guessModal').hide();
                $('#guessForm').show();
                $('#guessWaitingHost').hide();

                // Check if Host marked answer as incorrect
                if (data.my_is_disqualified) {
                    isDisqualified = true;
                    if (data.all_pieces_opened) {
                        $('#btnBuzz').replaceWith('<button type="button" class="btn btn-buzz btn-buzz-disabled" disabled id="btnBuzz"><i class="fas fa-ban mr-2"></i> OUT OF GUESSES</button>');
                        alert('Host confirmed your answer is incorrect! You have forfeited keyword guessing rights.');
                    } else {
                        $('#btnBuzz').replaceWith('<button type="button" class="btn btn-buzz btn-buzz-disabled" disabled id="btnBuzz"><i class="fas fa-lock mr-2"></i> BUZZER LOCKED (WAIT FOR ALL PIECES)</button>');
                        alert('Host confirmed your answer is incorrect! Your buzzer is temporarily locked. You can still answer upcoming questions and buzzer will unlock when all pieces are opened.');
                    }
                }
            }
        }

        // 5. Sync when all pieces opened (all_pieces_opened)
        if (data.all_pieces_opened) {
            isAllOpened = true;
            if (data.obstacle_image) {
                if (!$('.obstacle-secret-img').length) {
                    $('.obstacle-puzzle-container').prepend('<img src="' + data.obstacle_image + '" class="obstacle-secret-img" alt="Secret picture">');
                } else if (!$('.obstacle-secret-img').attr('src')) {
                    $('.obstacle-secret-img').attr('src', data.obstacle_image);
                }
            }
            $('.obstacle-piece-tile').removeClass('piece-locked').addClass('piece-unlocked');
            $('#unlockedCountBadge').text(data.total_pieces + ' / ' + data.total_pieces + ' Pieces Unlocked');
            // If player has not guessed incorrectly in final round: open buzzer!
            if (!data.my_is_disqualified) {
                isDisqualified = false;
                if ($('#btnBuzz').hasClass('btn-buzz-disabled')) {
                    $('#btnBuzz').replaceWith('<button type="button" class="btn btn-buzz" id="btnBuzz" onclick="claimBuzzer()"><i class="fas fa-bell mr-2"></i> GUESS SECRET PICTURE</button>');
                }
            } else {
                isDisqualified = true;
                if (!$('#btnBuzz').hasClass('btn-buzz-disabled')) {
                    $('#btnBuzz').replaceWith('<button type="button" class="btn btn-buzz btn-buzz-disabled" disabled id="btnBuzz"><i class="fas fa-ban mr-2"></i> OUT OF GUESSES</button>');
                }
            }
        } else {
            // Sync personal unlocked pieces
            if (data.my_unlocked_pieces && Array.isArray(data.my_unlocked_pieces)) {
                data.my_unlocked_pieces.forEach(function(pIdx) {
                    $('#pieceTile_' + pIdx).removeClass('piece-locked').addClass('piece-unlocked');
                });
                $('#unlockedCountBadge').text(data.my_unlocked_pieces.length + ' / ' + data.total_pieces + ' Pieces Unlocked');
            }

            const hasAnyPieceRevealed = Array.isArray(data.opened_pieces) && data.opened_pieces.length > 0;
            if (!hasAnyPieceRevealed) {
                if (!$('#btnBuzz').hasClass('btn-buzz-disabled')) {
                    $('#btnBuzz').replaceWith('<button type="button" class="btn btn-buzz btn-buzz-disabled" disabled id="btnBuzz" title="At least one piece must be revealed before guessing"><i class="fas fa-lock mr-2"></i> GUESS LOCKED (NO PIECES REVEALED YET)</button>');
                }
            } else if (data.my_is_disqualified && !isDisqualified) {
                isDisqualified = true;
                $('#btnBuzz').replaceWith('<button type="button" class="btn btn-buzz btn-buzz-disabled" disabled id="btnBuzz"><i class="fas fa-lock mr-2"></i> BUZZER LOCKED (WAIT FOR ALL PIECES)</button>');
            } else if (!data.my_is_disqualified && isDisqualified) {
                isDisqualified = false;
                $('#btnBuzz').replaceWith('<button type="button" class="btn btn-buzz" id="btnBuzz" onclick="claimBuzzer()"><i class="fas fa-bell mr-2"></i> GUESS SECRET PICTURE</button>');
            } else if (!data.my_is_disqualified && !isDisqualified && $('#btnBuzz').hasClass('btn-buzz-disabled')) {
                $('#btnBuzz').replaceWith('<button type="button" class="btn btn-buzz" id="btnBuzz" onclick="claimBuzzer()"><i class="fas fa-bell mr-2"></i> GUESS SECRET PICTURE</button>');
            }
        }
    });
}, 750);
</script>
</body>
</html>
