<?php

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/room_access.php';

function saved_review_response(int $status, array $payload): void {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    saved_review_response(405, ['success' => false, 'message' => 'POST method required to save question.']);
}

// Must be logged in to save question to Dashboard
$creatorId = (int)($_SESSION['id_creator'] ?? 0);
if ($creatorId <= 0) {
    saved_review_response(401, [
        'success' => false,
        'message' => 'You need to be logged in to save questions to Dashboard.'
    ]);
}

$data = json_decode(file_get_contents('php://input'), true);
$playerId = (int) ($data['id_player'] ?? 0);
$roomId = (int) ($data['id_room'] ?? 0);
$questionId = (int) ($data['id_question'] ?? 0);
$shouldSave = filter_var($data['saved'] ?? null, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
$token = $data['token'] ?? '';

if ($questionId <= 0 || $shouldSave === null) {
    saved_review_response(400, ['success' => false, 'message' => 'Invalid question info or save state.']);
}

// Verify player session if called from game results
if ($playerId > 0 && $roomId > 0) {
    if (!player_request_is_valid($playerId, $roomId, $token)) {
        saved_review_response(403, ['success' => false, 'message' => 'Invalid player session.']);
    }
}

// Check if question exists
$question = db_fetch_one("SELECT id_question FROM questions WHERE id_question = :qid LIMIT 1", [':qid' => $questionId]);
if (!$question) {
    saved_review_response(404, ['success' => false, 'message' => 'Question does not exist.']);
}

if ($shouldSave) {
    db_execute(
        'INSERT INTO saved_review_questions (id_creator, id_player, id_question) 
         VALUES (:cid, :pid, :qid)
         ON DUPLICATE KEY UPDATE id_player = COALESCE(:pid, id_player), saved_at = CURRENT_TIMESTAMP',
        [
            ':cid' => $creatorId,
            ':pid' => ($playerId > 0 ? $playerId : null),
            ':qid' => $questionId
        ]
    );
} else {
    db_execute(
        'DELETE FROM saved_review_questions WHERE id_creator = :cid AND id_question = :qid',
        [':cid' => $creatorId, ':qid' => $questionId]
    );
}

// Count total saved questions for account
$totalSaved = db_count('saved_review_questions', 'id_creator = :c', [':c' => $creatorId]);

saved_review_response(200, [
    'success' => true,
    'saved' => $shouldSave,
    'total_saved' => $totalSaved,
    'message' => $shouldSave ? 'Question saved to Dashboard.' : 'Question removed from saved.'
]);
