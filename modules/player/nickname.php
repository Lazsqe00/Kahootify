<?php
/**
 * THÀNH VIÊN 4: Đặt Nickname cho người chơi
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nickname = trim($_POST['nickname'] ?? '');

    if (!empty($nickname)) {
        // Kiểm tra nickname trùng trong phòng
        $existing = db_fetch_one("SELECT id_player FROM players WHERE id_room = :r AND nickname = :nick LIMIT 1", [
            ':r'    => $roomId,
            ':nick' => $nickname
        ]);

        if ($existing) {
            $nickname .= '_' . mt_rand(10, 99);
        }

        $playerId = db_insert(
            "INSERT INTO players (id_room, nickname, score, answers_correct) VALUES (:r, :nick, 0, 0)",
            [
                ':r'    => $roomId,
                ':nick' => $nickname
            ]
        );

        $playerToken = substr(md5($playerId . '_' . $roomId . '_kahoot_secret_2026'), 0, 16);

        $_SESSION['id_player'] = $playerId;
        $_SESSION['player_nickname'] = $nickname;
        $_SESSION['player_room_id'] = $roomId;

        redirect('modules/player/waiting.php?pid=' . $playerId . '&token=' . $playerToken);
    } else {
        $error = 'Please enter your nickname!';
    }
}

$pageTitle = "Kahoot! | Enter Nickname";
$bodyClass = "backgroundColorChanges";
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex align-items-center justify-content-center" style="min-height: 100vh; padding: 20px;">
    <div class="pin-card-box">
        <h3 class="font-weight-bold text-dark mb-3">Player Nickname</h3>
        <p class="text-muted small">Pick a cool nickname to appear on the host screen!</p>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger font-weight-bold small py-2">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form action="" method="POST">
            <input type="hidden" name="room_id" value="<?= $roomId ?>">
            <input type="hidden" name="pin" value="<?= htmlspecialchars($pin) ?>">
            <div class="form-group mb-3">
                <input type="text" name="nickname" class="form-control pin-input text-center" maxlength="20" placeholder="Nickname" required autofocus autocomplete="off">
            </div>
            <button type="submit" class="btn btn-kahoot btn-block text-white font-weight-bold py-3" style="background-color: #28a745; font-size: 1.15rem;">
                OK, Go!
            </button>
        </form>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
</body>
</html>
