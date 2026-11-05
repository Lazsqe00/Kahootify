<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/room_access.php';

header('Content-Type: application/json; charset=utf-8');

$playerId = intval($_POST['pid'] ?? ($_GET['pid'] ?? 0));
$roomId   = intval($_POST['id_room'] ?? ($_GET['id_room'] ?? 0));
$token    = trim($_POST['token'] ?? ($_GET['token'] ?? ''));

if ($playerId <= 0 || $roomId <= 0 || empty($token)) {
    $rawInput = file_get_contents('php://input');
    if (!empty($rawInput)) {
        $jsonData = json_decode($rawInput, true);
        if (is_array($jsonData)) {
            $playerId = intval($jsonData['pid'] ?? 0);
            $roomId   = intval($jsonData['id_room'] ?? 0);
            $token    = trim($jsonData['token'] ?? '');
        }
    }
}

if ($playerId > 0 && $roomId > 0 && player_request_is_valid($playerId, $roomId, $token)) {
    remove_player_from_room($playerId, $roomId);
    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid leave request']);
