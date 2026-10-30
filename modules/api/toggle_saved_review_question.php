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
    saved_review_response(405, ['success' => false, 'message' => 'Yêu cầu phương thức POST để lưu câu hỏi.']);
}

// Bắt buộc phải đăng nhập thì mới có thể lưu câu hỏi vào Dashboard
$creatorId = (int)($_SESSION['id_creator'] ?? 0);
if ($creatorId <= 0) {
    saved_review_response(401, [
        'success' => false,
        'message' => 'Bạn cần đăng nhập để lưu câu hỏi vào Dashboard.'
    ]);
}

$data = json_decode(file_get_contents('php://input'), true);
$playerId = (int) ($data['id_player'] ?? 0);
$roomId = (int) ($data['id_room'] ?? 0);
$questionId = (int) ($data['id_question'] ?? 0);
$shouldSave = filter_var($data['saved'] ?? null, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
$token = $data['token'] ?? '';

if ($questionId <= 0 || $shouldSave === null) {
    saved_review_response(400, ['success' => false, 'message' => 'Thông tin câu hỏi hoặc trạng thái lưu không hợp lệ.']);
}

// Kiểm tra phiên người chơi nếu gọi từ trang kết quả game
if ($playerId > 0 && $roomId > 0) {
    if (!player_request_is_valid($playerId, $roomId, $token)) {
        saved_review_response(403, ['success' => false, 'message' => 'Phiên người chơi không hợp lệ.']);
    }
}

// Kiểm tra câu hỏi có tồn tại không
$question = db_fetch_one("SELECT id_question FROM questions WHERE id_question = :qid LIMIT 1", [':qid' => $questionId]);
if (!$question) {
    saved_review_response(404, ['success' => false, 'message' => 'Câu hỏi không tồn tại.']);
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

// Đếm tổng số câu hỏi đã lưu hiện tại của tài khoản
$totalSaved = db_count('saved_review_questions', 'id_creator = :c', [':c' => $creatorId]);

saved_review_response(200, [
    'success' => true,
    'saved' => $shouldSave,
    'total_saved' => $totalSaved,
    'message' => $shouldSave ? 'Đã lưu câu hỏi vào Dashboard.' : 'Đã bỏ lưu câu hỏi.'
]);
