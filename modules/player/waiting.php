<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/player_characters.php';

$playerId = intval($_GET['pid'] ?? ($_SESSION['id_player'] ?? 0));
$token    = trim($_GET['token'] ?? '');
$roomId   = intval($_SESSION['player_room_id'] ?? 0);
$nickname = $_SESSION['player_nickname'] ?? 'Player';

$player = null;
if ($playerId > 0) {
    $player = db_fetch_one("SELECT * FROM players WHERE id_player = :p", [':p' => $playerId]);
    if ($player) {
        $roomId   = intval($player['id_room']);
        $nickname = $player['nickname'];
    }
}

if ($roomId <= 0 || $playerId <= 0 || !$player) {
    redirect('modules/player/join.php');
}

$ownsPlayer = ((int) ($_SESSION['id_player'] ?? 0) === $playerId && (int) ($_SESSION['player_room_id'] ?? 0) === $roomId)
    || hash_equals(substr(md5($playerId . '_' . $roomId . '_kahoot_secret_2026'), 0, 16), $token);
if (!$ownsPlayer) redirect('modules/player/join.php');
$characterRoom = db_fetch_one('SELECT id_room, pin, status FROM room WHERE id_room = :r', [':r' => $roomId]);
if (!$characterRoom) redirect('modules/player/join.php');
if ($characterRoom['status'] !== 'waiting') redirect('modules/player/play.php?pid=' . $playerId . '&token=' . urlencode($token));
$_SESSION['character_csrf'] ??= bin2hex(random_bytes(32));
$characterError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? null;
    if (!is_string($csrf) || !hash_equals($_SESSION['character_csrf'], $csrf)) {
        http_response_code(403);
        $characterError = 'Please refresh the page and try again.';
    } else {
        try {
            $newCharacter = $_POST['character_id'] ?? null;
            $newAccessory = $_POST['accessory_id'] ?? '';
            if (!is_string($newCharacter) || !is_string($newAccessory)) throw new InvalidArgumentException();
            save_player_character($characterRoom, $playerId, $newCharacter, $newAccessory);
            redirect('modules/player/waiting.php?pid=' . $playerId . '&token=' . urlencode($token));
        } catch (InvalidArgumentException $exception) {
            $characterError = 'Please choose an available character and accessory.';
        } catch (Throwable $exception) {
            $characterError = 'Your changes could not be saved. Please try again.';
        }
    }
}
$pageTitle = "You're in! - " . $nickname;
$waitingAppearance = get_player_appearance($characterRoom, $playerId);
$selectedCharacter = $waitingAppearance['character_id'];
$selectedAccessory = $waitingAppearance['accessory_id'];
$pageStyles = ['assets/css/player-characters.css'];
$bodyClass = "backgroundColorChanges";
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex align-items-center justify-content-center text-center text-white" style="min-height: 100vh; padding: 20px;">
    <div class="player-waiting-content">
        <h1 class="display-3 font-weight-bold mb-3" style="text-shadow: 2px 2px 6px rgba(0,0,0,0.5);">
            You're in! 🎉
        </h1>
        <p class="lead font-weight-bold" style="text-shadow: 1px 1px 3px rgba(0,0,0,0.5);">
            See your name on the big screen?
        </p>

        <div class="waiting-profile bg-white text-dark py-3 px-5 rounded-pill shadow-lg d-inline-block my-4">
            <?= player_appearance_image($waitingAppearance, 'waiting-character') ?>
            <h2 class="font-weight-bold mb-0" style="color: var(--kahoot-purple);">
                <i class="fas fa-smile-wink text-warning mr-2"></i><?= htmlspecialchars($nickname) ?>
            </h2>
        </div>

        <details class="waiting-editor" <?= $characterError !== '' ? 'open' : '' ?>>
            <summary>Edit character</summary>
            <?php if ($characterError !== ''): ?><p role="alert" class="text-danger"><?= htmlspecialchars($characterError) ?></p><?php endif; ?>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['character_csrf']) ?>">
                <?php require __DIR__ . '/../../includes/character_picker.php'; ?>
                <button type="submit" class="btn btn-kahoot character-save">Done</button>
            </form>
        </details>
        <div class="mt-3">
            <div class="spinner-grow text-light" role="status"></div>
            <p class="mt-2 font-weight-bold" style="opacity: 0.9;">Waiting for the host to start the game...</p>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="<?= BASE_URL ?>assets/js/player-characters.js" defer></script>
<script>
const roomId = <?= $roomId ?>;
const playerId = <?= $playerId ?>;
const playerToken = '<?= htmlspecialchars($token) ?>';

sessionStorage.setItem('kahoot_pid', playerId);
sessionStorage.setItem('kahoot_token', playerToken);

setInterval(function() {
    $.getJSON('<?= BASE_URL ?>modules/api/game_state.php?action=get_room_status&id_room=' + roomId, function(res) {
        if (res && res.success) {

            if (res.status !== 'waiting') {
                window.location.href = '<?= BASE_URL ?>modules/player/play.php?pid=' + playerId + '&token=' + playerToken;
            }
        }
    });
}, 1000);
</script>
</body>
</html>
