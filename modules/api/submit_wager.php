<?php

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/room_access.php';
require_once __DIR__ . '/../../includes/modes/GameStateManager.php';

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

$playerId   = intval($data['id_player'] ?? 0);
$roomId     = intval($data['id_room'] ?? 0);
$questionId = intval($data['id_question'] ?? 0);
$tierId     = trim((string)($data['tier_id'] ?? ''));
$token      = trim((string)($data['token'] ?? ''));

// Validation: client MUST NOT send custom points / scores
if (isset($data['wager_amount']) || isset($data['points']) || isset($data['score'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Lỗi: Client chỉ được gửi tier_id, không được gửi số điểm tự chế.',
    ]);
    exit;
}

if ($playerId <= 0 || $roomId <= 0 || $questionId <= 0 || empty($tierId)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Thiếu thông tin cược bắt buộc.',
    ]);
    exit;
}

if (!player_request_is_valid($playerId, $roomId, $token)) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Phiên người chơi không hợp lệ.',
    ]);
    exit;
}

$room = db_fetch_one("SELECT id_room, pin FROM room WHERE id_room = :r", [':r' => $roomId]);
if (!$room) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Phòng không tồn tại.']);
    exit;
}

if (player_is_kicked($room, $playerId)) {
    echo json_encode(['success' => false, 'kicked' => true, 'message' => 'Bạn đã bị đuổi khỏi phòng.']);
    exit;
}

$result = GameStateManager::submitPlayerWager($roomId, $playerId, $questionId, $tierId);

if (!$result['success']) {
    http_response_code(400);
    echo json_encode($result);
    exit;
}

// Check if all active players have wagered; if so, automatically lock and advance!
$wagerStatus = GameStateManager::getWagerPhaseStatus($roomId, $questionId);
if (!empty($wagerStatus['is_all_wagered'])) {
    GameStateManager::lockWagerPhaseAndAdvance($roomId, $questionId);
    $result['all_wagered_advanced'] = true;
}

echo json_encode($result);
