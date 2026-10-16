<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

$playerId = intval($_GET['pid'] ?? ($_SESSION['id_player'] ?? 0));
$token    = trim($_GET['token'] ?? '');
$roomId   = intval($_SESSION['player_room_id'] ?? 0);
$nickname = $_SESSION['player_nickname'] ?? 'Player';

if ($playerId > 0) {
    $player = db_fetch_one("SELECT * FROM players WHERE id_player = :p", [':p' => $playerId]);
    if ($player) {
        $roomId   = intval($player['id_room']);
        $nickname = $player['nickname'];
    }
}

if ($roomId <= 0 || $playerId <= 0) {
    redirect('modules/player/join.php');
}

$pageTitle = "You're in! - " . htmlspecialchars($nickname);
$bodyClass = "backgroundColorChanges";
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex align-items-center justify-content-center text-center text-white" style="min-height: 100vh; padding: 20px;">
    <div>
        <h1 class="display-3 font-weight-bold mb-3" style="text-shadow: 2px 2px 6px rgba(0,0,0,0.5);">
            You're in! 🎉
        </h1>
        <p class="lead font-weight-bold" style="text-shadow: 1px 1px 3px rgba(0,0,0,0.5);">
            See your name on the big screen?
        </p>

        <div class="bg-white text-dark py-3 px-5 rounded-pill shadow-lg d-inline-block my-4">
            <h2 class="font-weight-bold mb-0" style="color: var(--kahoot-purple);">
                <i class="fas fa-smile-wink text-warning mr-2"></i><?= htmlspecialchars($nickname) ?>
            </h2>
        </div>

        <div class="mt-3">
            <div class="spinner-grow text-light" role="status"></div>
            <p class="mt-2 font-weight-bold" style="opacity: 0.9;">Waiting for the host to start the game...</p>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script>
const roomId = <?= $roomId ?>;
const playerId = <?= $playerId ?>;
const playerToken = '<?= htmlspecialchars($token) ?>';

sessionStorage.setItem('kahoot_pid', playerId);
sessionStorage.setItem('kahoot_token', playerToken);

setInterval(function() {
    $.getJSON('<?= BASE_URL ?>modules/api/game_state.php?action=get_room_status&id_room=' + roomId, function(res) {
        if (res && res.success) {

            if (res.status !== 'waiting' && res.status !== 'finished') {
                window.location.href = '<?= BASE_URL ?>modules/player/play.php?pid=' + playerId + '&token=' + playerToken;
            }
        }
    });
}, 1000);
</script>
</body>
</html>
