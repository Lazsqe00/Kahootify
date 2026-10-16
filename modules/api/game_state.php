<?php
/**
 * API đồng bộ trạng thái phòng chơi (Real-time Polling)
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

$action = $_GET['action'] ?? '';
$roomId = intval($_GET['id_room'] ?? 0);

if ($roomId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid room ID']);
    exit;
}

// 1. Lấy trạng thái phòng chơi
if ($action === 'get_room_status') {
    $room = db_fetch_one("SELECT status, question_start_time FROM room WHERE id_room = :r", [':r' => $roomId]);
    if ($room) {
        echo json_encode([
            'success' => true,
            'status'  => $room['status'],
            'start_time' => $room['question_start_time']
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Room does not exist']);
    }
    exit;
}

// 2. Lấy danh sách người chơi trong phòng chờ (Lobby)
if ($action === 'get_players') {
    $players = db_fetch_all("SELECT id_player, nickname, score FROM players WHERE id_room = :r ORDER BY id_player ASC", [':r' => $roomId]);
    echo json_encode([
        'success' => true,
        'players' => $players,
        'count'   => count($players)
    ]);
    exit;
}

// 3. Lấy tiến độ nộp bài của câu hỏi hiện tại
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
