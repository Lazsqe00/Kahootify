<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/database.php';

function kick_response($status, $message, $success = false) {
    http_response_code($status);
    echo json_encode(['success' => $success, 'message' => $message]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    kick_response(405, 'Use POST to remove a player.');
}
$creatorId = (int)($_SESSION['id_creator'] ?? 0);
if (!$creatorId) kick_response(401, 'Please log in as the host.');
$token = $_POST['csrf_token'] ?? '';
if (!is_string($token) || empty($_SESSION['kick_player_token']) || !hash_equals($_SESSION['kick_player_token'], $token)) {
    kick_response(403, 'Your request could not be verified. Refresh the page and try again.');
}
$roomId = filter_var($_POST['id_room'] ?? null, FILTER_VALIDATE_INT);
$playerId = filter_var($_POST['id_player'] ?? null, FILTER_VALIDATE_INT);
if ($roomId <= 0 || $playerId <= 0) kick_response(400, 'Invalid room or player.');
$room = db_fetch_one('SELECT * FROM room WHERE id_room = :r AND id_creator = :c', [':r' => $roomId, ':c' => $creatorId]);
if (!$room) kick_response(403, 'Only the host of this room can remove players.');
if ($room['status'] === 'finished') kick_response(409, 'This game has already finished.');
if (!kick_room_player($room, $creatorId, $playerId)) kick_response(409, 'The player or game is no longer available.');
kick_response(200, 'Player removed.', true);
