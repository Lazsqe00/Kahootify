<?php

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/room_access.php';
require_once __DIR__ . '/../../includes/player_characters.php';

$rawInput = file_get_contents('php://input');
$jsonData = json_decode($rawInput, true);

$action = $_GET['action'] ?? ($_POST['action'] ?? ($jsonData['action'] ?? ''));
$roomId = intval($_GET['id_room'] ?? ($_POST['id_room'] ?? ($jsonData['id_room'] ?? 0)));

if ($roomId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid room ID']);
    exit;
}

if (isset($_GET['pid'])) {
    $playerId = (int)$_GET['pid'];
    $token = $_GET['token'] ?? '';
    if ($playerId <= 0 || !player_request_is_valid($playerId, $roomId, $token)) {
        echo json_encode(['success' => false, 'message' => 'Invalid player session']);
        exit;
    }
    $playerRoom = db_fetch_one('SELECT id_room, pin FROM room WHERE id_room = :r', [':r' => $roomId]);
    if ($playerRoom && player_is_kicked($playerRoom, $playerId)) {
        echo json_encode(['success' => false, 'kicked' => true]);
        exit;
    }
}

if ($action === 'update_settings') {
    $showVal = 0;
    if (isset($_POST['show_questions_on_players'])) {
        $showVal = intval($_POST['show_questions_on_players']) ? 1 : 0;
    } elseif (isset($_GET['show_questions_on_players'])) {
        $showVal = intval($_GET['show_questions_on_players']) ? 1 : 0;
    } elseif (isset($jsonData['show_questions_on_players'])) {
        $showVal = intval($jsonData['show_questions_on_players']) ? 1 : 0;
    }

    db_execute("UPDATE room SET show_questions_on_players = :val WHERE id_room = :r", [
        ':val' => $showVal,
        ':r'   => $roomId
    ]);

    if (isset($jsonData['question_limit']) || isset($_POST['question_limit'])) {
        $qLimit = isset($jsonData['question_limit']) ? intval($jsonData['question_limit']) : intval($_POST['question_limit'] ?? 0);
        $qMode = $jsonData['question_mode'] ?? ($_POST['question_mode'] ?? 'all');
        $saveLimit = ($qMode === 'random' && $qLimit > 0) ? $qLimit : null;
        db_execute("UPDATE room SET question_limit = :ql WHERE id_room = :r", [
            ':ql' => $saveLimit,
            ':r'  => $roomId
        ]);
    }

    echo json_encode([
        'success' => true,
        'show_questions_on_players' => $showVal,
        'message' => 'Settings updated successfully'
    ]);
    exit;
}

if ($action === 'get_room_status') {
    $room = db_fetch_one("SELECT id_room, pin, status, question_start_time, show_questions_on_players FROM room WHERE id_room = :r", [':r' => $roomId]);
    if ($room) {
        echo json_encode([
            'success' => true,
            'status'  => $room['status'],
            'is_locked' => room_is_locked($room),
            'start_time' => $room['question_start_time'],
            'show_questions_on_players' => intval($room['show_questions_on_players'] ?? 0)
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Room does not exist']);
    }
    exit;
}

if ($action === 'get_players') {
    $room = db_fetch_one('SELECT id_room, pin FROM room WHERE id_room = :r', [':r' => $roomId]);
    if (!$room) {
        echo json_encode(['success' => false, 'message' => 'Room does not exist']);
        exit;
    }
    $players = filter_room_players($roomId, db_fetch_all("SELECT id_player, nickname, score FROM players WHERE id_room = :r ORDER BY id_player ASC", [':r' => $roomId]));
    foreach ($players as &$lobbyPlayer) {
        $appearance = get_player_appearance($room, (int) $lobbyPlayer['id_player']);
        $lobbyPlayer['character_svg'] = player_appearance_image($appearance, 'lobby-character');
    }
    unset($lobbyPlayer);
    echo json_encode([
        'success' => true,
        'players' => $players,
        'is_locked' => room_is_locked($room),
        'count'   => count($players)
    ]);
    exit;
}

if ($action === 'get_question_status') {
    $qId = intval($_GET['id_question'] ?? 0);
    $room = db_fetch_one('SELECT id_room, pin FROM room WHERE id_room = :r', [':r' => $roomId]);
    if (!$room) {
        echo json_encode(['success' => false, 'message' => 'Room does not exist']);
        exit;
    }
    $counts = room_question_counts($room, $qId);

    echo json_encode([
        'success'       => true,
        'total_players' => $counts['total_players'],
        'voted_count'   => $counts['voted_count']
    ]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);
