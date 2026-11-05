<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/room_access.php';

$playerId = intval($_GET['pid'] ?? ($_SESSION['id_player'] ?? 0));
$token    = trim($_GET['token'] ?? '');
$roomId   = intval($_GET['room_id'] ?? ($_SESSION['player_room_id'] ?? 0));
$target   = $_GET['target'] ?? 'index'; // 'index', 'nickname', or 'join'

if ($playerId > 0) {
    if ($roomId <= 0) {
        $pRow = db_fetch_one("SELECT id_room FROM players WHERE id_player = :p", [':p' => $playerId]);
        if ($pRow) {
            $roomId = intval($pRow['id_room']);
        }
    }
    remove_player_from_room($playerId, $roomId);
}

$pin = $_SESSION['player_pin'] ?? '';
if (empty($pin) && $roomId > 0) {
    $rRow = db_fetch_one("SELECT pin FROM room WHERE id_room = :r", [':r' => $roomId]);
    if ($rRow) {
        $pin = $rRow['pin'];
    }
}

// Clear player session
unset(
    $_SESSION['id_player'],
    $_SESSION['player_nickname'],
    $_SESSION['player_team_id'],
    $_SESSION['player_team_name']
);

if ($target === 'nickname' && $roomId > 0 && !empty($pin)) {
    redirect('modules/player/nickname.php?room_id=' . $roomId . '&pin=' . urlencode($pin));
} elseif ($target === 'join') {
    unset($_SESSION['player_room_id'], $_SESSION['player_pin']);
    redirect('modules/player/join.php');
} else {
    unset($_SESSION['player_room_id'], $_SESSION['player_pin']);
    redirect('index.php');
}
