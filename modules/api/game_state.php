<?php

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

$rawInput = file_get_contents('php://input');
$jsonData = json_decode($rawInput, true);

$action = $_GET['action'] ?? ($_POST['action'] ?? ($jsonData['action'] ?? ''));
$roomId = intval($_GET['id_room'] ?? ($_POST['id_room'] ?? ($jsonData['id_room'] ?? 0)));

if ($roomId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid room ID']);
    exit;
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

    echo json_encode([
        'success' => true,
        'show_questions_on_players' => $showVal,
        'message' => 'Settings updated successfully'
    ]);
    exit;
}

if ($action === 'get_room_status') {
    $room = db_fetch_one("SELECT status, question_start_time, show_questions_on_players FROM room WHERE id_room = :r", [':r' => $roomId]);
    if ($room) {
        echo json_encode([
            'success' => true,
            'status'  => $room['status'],
            'start_time' => $room['question_start_time'],
            'show_questions_on_players' => intval($room['show_questions_on_players'] ?? 0)
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Room does not exist']);
    }
    exit;
}

if ($action === 'get_players') {
    $players = db_fetch_all("SELECT id_player, nickname, score FROM players WHERE id_room = :r ORDER BY id_player ASC", [':r' => $roomId]);
    echo json_encode([
        'success' => true,
        'players' => $players,
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
