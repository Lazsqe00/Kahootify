<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/player_characters.php';

$roomId = intval($_GET['room_id'] ?? ($_POST['room_id'] ?? ($_SESSION['player_room_id'] ?? 0)));
$pin = trim($_GET['pin'] ?? ($_POST['pin'] ?? ($_SESSION['player_pin'] ?? '')));

if ($roomId <= 0 && !empty($pin)) {
    $room = db_fetch_one("SELECT * FROM room WHERE pin = :pin AND status != 'finished' LIMIT 1", [':pin' => $pin]);
    if ($room) {
        $roomId = intval($room['id_room']);
    }
}

if ($roomId <= 0) {
    redirect('modules/player/join.php');
}

$error = '';
$room = db_fetch_one("SELECT * FROM room WHERE id_room = :r AND status != 'finished'", [':r' => $roomId]);
if (!$room) redirect('modules/player/join.php');
$characters = player_character_catalog();
$selectedCharacter = is_string($_POST['character_id'] ?? null) ? $_POST['character_id'] : default_player_character();
$selectedAccessory = is_string($_POST['accessory_id'] ?? null) ? $_POST['accessory_id'] : '';
$nickname = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nickname = trim($_POST['nickname'] ?? '');

    if (!isset($characters[$selectedCharacter])) {
        $error = 'Please choose an available character.';
        $selectedCharacter = default_player_character();
    } elseif ($selectedAccessory !== '' && !isset(player_accessory_catalog()[$selectedAccessory])) {
        $error = 'Please choose an available accessory.';
        $selectedAccessory = '';
    } elseif (!empty($nickname)) {

        $existing = db_fetch_one("SELECT id_player FROM players WHERE id_room = :r AND nickname = :nick LIMIT 1", [
            ':r'    => $roomId,
            ':nick' => $nickname
        ]);

        if ($existing) {
            $nickname .= '_' . mt_rand(10, 99);
        }

        $pdo = getDB();
        try {
            $pdo->beginTransaction();
            $playerId = db_insert(
                "INSERT INTO players (id_room, nickname, score, answers_correct, character_id, accessory_id) VALUES (:r, :nick, 0, 0, :char, :acc)",
                [
                    ':r'    => $roomId,
                    ':nick' => $nickname,
                    ':char' => $selectedCharacter,
                    ':acc'  => $selectedAccessory
                ]
            );
            $pdo->commit();

            $playerToken = substr(md5($playerId . '_' . $roomId . '_kahoot_secret_2026'), 0, 16);

            $_SESSION['id_player'] = $playerId;
            $_SESSION['player_nickname'] = $nickname;
            $_SESSION['player_room_id'] = $roomId;

            redirect('modules/player/waiting.php?pid=' . $playerId . '&token=' . $playerToken);
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = 'We could not save your character. Please try joining again.';
        }
    } else {
        $error = 'Please enter your nickname!';
    }
}

$pageTitle = "Kahoot! | Enter Nickname";
$bodyClass = "backgroundColorChanges";
$pageStyles = ['assets/css/player-characters.css'];
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex align-items-center justify-content-center" style="min-height: 100vh; padding: 20px;">
    <div class="pin-card-box join-card">
        <p id="joinStepLabel" class="join-step-label" hidden></p>
        <h3 id="joinHeading" class="font-weight-bold text-dark mb-3">Player Nickname</h3>
        <p id="joinDescription" class="text-muted small">Pick a nickname and customize your character.</p>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger font-weight-bold small py-2">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form action="" method="POST" id="playerJoinForm" data-has-error="<?= $error !== '' ? 'true' : 'false' ?>">
            <input type="hidden" name="room_id" value="<?= $roomId ?>">
            <input type="hidden" name="pin" value="<?= htmlspecialchars($pin) ?>">
            <div class="form-group mb-3" id="nicknameStep">
                <input type="text" name="nickname" class="form-control pin-input text-center" maxlength="20" placeholder="Nickname" value="<?= htmlspecialchars($nickname) ?>" required autofocus autocomplete="off">
            </div>
            <div id="appearanceStep">
                <?php require __DIR__ . '/../../includes/character_picker.php'; ?>
            </div>
            <div class="join-actions">
                <button type="button" class="join-back" id="joinBack" hidden>Back</button>
                <button type="button" class="btn btn-kahoot join-primary" id="joinContinue" hidden>Continue</button>
                <button type="submit" class="btn btn-kahoot join-primary" id="joinSubmit">OK, go!</button>
            </div>
        </form>
    </div>
</div>

<script src="<?= BASE_URL ?>assets/js/player-characters.js" defer></script>
</body>
</html>
