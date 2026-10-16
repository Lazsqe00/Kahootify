<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pin = trim($_POST['pin'] ?? '');

    if (!empty($pin)) {
        $room = db_fetch_one("SELECT * FROM room WHERE pin = :pin AND status != 'finished' LIMIT 1", [':pin' => $pin]);

        if ($room) {
            $_SESSION['player_room_id'] = $room['id_room'];
            $_SESSION['player_pin'] = $room['pin'];
            redirect('modules/player/nickname.php?room_id=' . $room['id_room'] . '&pin=' . urlencode($room['pin']));
        } else {
            $error = 'Invalid Game PIN or room has closed!';
        }
    } else {
        $error = 'Please enter a Game PIN!';
    }
}

$pageTitle = "Kahoot! | Enter Game PIN";
$bodyClass = "backgroundColorChanges";
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex align-items-center justify-content-center" style="min-height: 100vh; padding: 20px;">
    <div class="pin-card-box">
        <a href="<?= BASE_URL ?>">
            <img src="<?= BASE_URL ?>assets/img/logo.png" alt="Logo Kahoot" class="img-fluid mb-4" style="max-height: 50px;">
        </a>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger font-weight-bold small py-2">
                <i class="fas fa-exclamation-triangle mr-1"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form action="" method="POST">
            <div class="form-group mb-3">
                <input type="text" name="pin" class="form-control pin-input text-center" maxlength="6" pattern="[0-9]*" inputmode="numeric" placeholder="Game PIN" required autofocus autocomplete="off">
            </div>
            <button type="submit" class="btn btn-kahoot btn-block text-white font-weight-bold py-3" style="background-color: #333333; font-size: 1.15rem;">
                Enter
            </button>
        </form>

        <div class="mt-4 pt-3 border-top">
            <small class="text-muted">
                Are you a Teacher / Host? <a href="<?= BASE_URL ?>" class="font-weight-bold text-dark">Log in here</a>
            </small>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
</body>
</html>
