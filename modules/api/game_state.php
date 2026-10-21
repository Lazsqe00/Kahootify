<?php

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/room_access.php';

$action = $_GET['action'] ?? '';
$roomId = intval($_GET['id_room'] ?? 0);

if ($roomId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid room ID']);
    exit;
}

if ($action === 'get_room_status') {
    $room = db_fetch_one("SELECT id_room, pin, status, question_start_time FROM room WHERE id_room = :r", [':r' => $roomId]);
    if ($room) {
        echo json_encode([
            'success' => true,
            'status'  => $room['status'],
            'is_locked' => room_is_locked($room),
            'start_time' => $room['question_start_time']
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
    $players = db_fetch_all("SELECT id_player, nickname, score FROM players WHERE id_room = :r ORDER BY id_player ASC", [':r' => $roomId]);
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
    $totalPlayers = db_count('players', 'id_room = :r', [':r' => $roomId]);

    $votedCount = db_fetch_one(
        "SELECT COUNT(DISTINCT v.id_player) as total
         FROM votes v JOIN answers a ON v.id_answer = a.id_answer
         WHERE a.id_question = :qid AND v.id_player IN (SELECT id_player FROM players WHERE id_room = :r)",
        [':qid' => $qId, ':r' => $roomId]
    )['total'] ?? 0;

    echo json_encode([
        'success'       => true,
        'total_players' => $totalPlayers,
        'voted_count'   => intval($votedCount)
    ]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);
