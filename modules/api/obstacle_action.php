<?php

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/room_access.php';

$rawInput = file_get_contents('php://input');
$jsonData = json_decode($rawInput, true);

$action = $_GET['action'] ?? ($_POST['action'] ?? ($jsonData['action'] ?? ''));
$roomId = intval($_GET['id_room'] ?? ($_POST['id_room'] ?? ($jsonData['id_room'] ?? 0)));

if ($roomId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Mã phòng không hợp lệ']);
    exit;
}

$room = db_fetch_one("SELECT r.*, q.obstacle_image, q.obstacle_answer, q.total_pieces 
                      FROM room r 
                      JOIN quiz q ON r.id_quiz = q.id_quiz 
                      WHERE r.id_room = :r", [':r' => $roomId]);

if (!$room) {
    echo json_encode(['success' => false, 'message' => 'Phòng chơi không tồn tại']);
    exit;
}

function normalizeKeyword($str) {
    $str = mb_strtolower(trim($str ?? ''), 'UTF-8');
    $str = preg_replace('/\s+/', ' ', $str);
    $str = preg_replace('/[.,\/#!$%\^&\*;:{}=\-_`~()?]/', '', $str);
    return trim($str);
}

function removeVietnameseAccents($str) {
    $accents = [
        'a' => '/[àáạảãâầấậẩẫăằắặẳẵ]/u',
        'e' => '/[èéẹẻẽêềếệểễ]/u',
        'i' => '/[ìíịỉĩ]/u',
        'o' => '/[òóọỏõôồốộổỗơờớợởỡ]/u',
        'u' => '/[ùúụủũưừứựửữ]/u',
        'y' => '/[ỳýỵỷỹ]/u',
        'd' => '/[đ]/u',
    ];
    $str = mb_strtolower($str ?? '', 'UTF-8');
    foreach ($accents as $nonAccent => $pattern) {
        $str = preg_replace($pattern, $nonAccent, $str);
    }
    return $str;
}

function matchKeyword($guess, $correctAnswer) {
    $normGuess = normalizeKeyword($guess);
    $normAns   = normalizeKeyword($correctAnswer);
    if ($normGuess === '' || $normAns === '') return false;
    if ($normGuess === $normAns) return true;
    if (removeVietnameseAccents($normGuess) === removeVietnameseAccents($normAns)) return true;
    return false;
}

// 1. HOST CHỌN MẢNH GHÉP ĐỂ MỞ CÂU HỎI
if ($action === 'select_piece') {
    $creatorId = intval($_SESSION['id_creator'] ?? 0);
    if ($creatorId !== intval($room['id_creator'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Bạn không phải Host của phòng này']);
        exit;
    }

    $pieceIndex = intval($_POST['piece_index'] ?? ($jsonData['piece_index'] ?? 0));
    if ($pieceIndex <= 0) {
        echo json_encode(['success' => false, 'message' => 'Mảnh ghép không hợp lệ']);
        exit;
    }

    // Tìm câu hỏi tương ứng với piece_index
    $question = db_fetch_one("SELECT * FROM questions WHERE id_quiz = :qz AND piece_index = :pi ORDER BY id_question ASC LIMIT 1", [
        ':qz' => $room['id_quiz'],
        ':pi' => $pieceIndex
    ]);

    // Fallback nếu không có câu hỏi gắn piece_index: lấy câu hỏi thứ K theo sort_order
    if (!$question) {
        $allQ = db_fetch_all("SELECT * FROM questions WHERE id_quiz = :qz ORDER BY sort_order ASC, id_question ASC", [':qz' => $room['id_quiz']]);
        if (isset($allQ[$pieceIndex - 1])) {
            $question = $allQ[$pieceIndex - 1];
        } else {
            $question = $allQ[0] ?? null;
        }
    }

    if (!$question) {
        echo json_encode(['success' => false, 'message' => 'Không tìm thấy câu hỏi cho mảnh ghép này']);
        exit;
    }

    // Cập nhật opened_pieces
    $openedPieces = !empty($room['opened_pieces']) ? json_decode($room['opened_pieces'], true) : [];
    if (!is_array($openedPieces)) $openedPieces = [];
    if (!in_array($pieceIndex, $openedPieces)) {
        $openedPieces[] = $pieceIndex;
        sort($openedPieces);
    }

    $qId = intval($question['id_question']);
    db_execute(
        "UPDATE room 
         SET status = :s, 
             question_start_time = UNIX_TIMESTAMP(), 
             opened_pieces = :op, 
             buzzer_status = 'idle', 
             buzzer_player_id = NULL, 
             buzzer_time = NULL 
         WHERE id_room = :r",
        [
            ':s'  => (string)$qId,
            ':op' => json_encode($openedPieces),
            ':r'  => $roomId
        ]
    );

    echo json_encode([
        'success'      => true,
        'qid'          => $qId,
        'piece_index'  => $pieceIndex,
        'opened_pieces'=> $openedPieces
    ]);
    exit;
}

// 2. HOST QUAY VỀ BẢNG GHÉP (SAU KHI CÂU HỎI KẾT THÚC)
if ($action === 'return_to_board') {
    $creatorId = intval($_SESSION['id_creator'] ?? 0);
    if ($creatorId !== intval($room['id_creator'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Bạn không phải Host']);
        exit;
    }

    db_execute(
        "UPDATE room 
         SET status = 'board', 
             buzzer_status = 'idle', 
             buzzer_player_id = NULL, 
             buzzer_time = NULL 
         WHERE id_room = :r",
        [':r' => $roomId]
    );

    echo json_encode(['success' => true]);
    exit;
}

// 3. NGƯỜI CHƠI BẤM CHUÔNG ĐOÁN TỪ KHÓA
if ($action === 'buzz') {
    $playerId = intval($_POST['id_player'] ?? ($jsonData['id_player'] ?? 0));
    $token    = trim($_POST['token'] ?? ($jsonData['token'] ?? ''));

    if ($playerId <= 0 || !player_request_is_valid($playerId, $roomId, $token)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Phiên người chơi không hợp lệ']);
        exit;
    }

    $player = db_fetch_one("SELECT * FROM players WHERE id_player = :p AND id_room = :r", [':p' => $playerId, ':r' => $roomId]);
    if (!$player) {
        echo json_encode(['success' => false, 'message' => 'Người chơi không tồn tại trong phòng']);
        exit;
    }

    if (!empty($player['is_disqualified_obstacle'])) {
        echo json_encode(['success' => false, 'message' => 'Bạn đã bị loại khỏi quyền đoán bức tranh do đoán sai trước đó!']);
        exit;
    }

    // Atomic claim chuông
    if ($room['buzzer_status'] === 'buzzing') {
        if (intval($room['buzzer_player_id']) === $playerId) {
            echo json_encode(['success' => true, 'your_turn' => true, 'time_limit' => 20]);
            exit;
        }
        echo json_encode(['success' => false, 'message' => 'Người chơi khác đã nhanh tay bấm chuông trước!']);
        exit;
    }

    if ($room['buzzer_status'] === 'finished') {
        echo json_encode(['success' => false, 'message' => 'Trò chơi đã có người chiến thắng chung cuộc!']);
        exit;
    }

    $affected = db_execute(
        "UPDATE room 
         SET buzzer_status = 'buzzing', 
             buzzer_player_id = :p, 
             buzzer_time = UNIX_TIMESTAMP() 
         WHERE id_room = :r AND buzzer_status = 'idle'",
        [
            ':p' => $playerId,
            ':r' => $roomId
        ]
    );

    if ($affected > 0) {
        echo json_encode([
            'success'     => true,
            'your_turn'   => true,
            'player_name' => $player['nickname'],
            'time_limit'  => 20
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Rất tiếc! Người chơi khác đã bấm chuông trước một tích tắc.']);
    }
    exit;
}

// 4. NGƯỜI BẤM CHUÔNG GỬI TỪ KHÓA DỰ ĐOÁN
if ($action === 'submit_guess') {
    $playerId = intval($_POST['id_player'] ?? ($jsonData['id_player'] ?? 0));
    $token    = trim($_POST['token'] ?? ($jsonData['token'] ?? ''));
    $guess    = trim($_POST['guess'] ?? ($jsonData['guess'] ?? ''));

    if ($playerId <= 0 || !player_request_is_valid($playerId, $roomId, $token)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Phiên người chơi không hợp lệ']);
        exit;
    }

    if ($room['buzzer_status'] !== 'buzzing' || intval($room['buzzer_player_id']) !== $playerId) {
        echo json_encode(['success' => false, 'message' => 'Không phải lượt trả lời của bạn hoặc thời gian đã hết!']);
        exit;
    }

    $player = db_fetch_one("SELECT * FROM players WHERE id_player = :p AND id_room = :r", [':p' => $playerId, ':r' => $roomId]);
    $correctAnswer = $room['obstacle_answer'] ?? '';

    $isCorrect = matchKeyword($guess, $correctAnswer);

    if ($isCorrect) {
        // ĐOÁN ĐÚNG: Chiến thắng chung cuộc!
        db_execute(
            "UPDATE room 
             SET buzzer_status = 'finished', 
                 winner_player_id = :p, 
                 status = 'finished' 
             WHERE id_room = :r",
            [':p' => $playerId, ':r' => $roomId]
        );

        // Cộng 5000 điểm vinh danh cho người thắng cuộc
        db_execute(
            "UPDATE players 
             SET score = score + 5000 
             WHERE id_player = :p",
            [':p' => $playerId]
        );

        echo json_encode([
            'success'     => true,
            'correct'     => true,
            'winner_name' => $player['nickname'] ?? 'Player',
            'answer'      => $correctAnswer,
            'message'     => 'CHÚC MỪNG! Bạn đã đoán chính xác từ khóa bức tranh bí mật!'
        ]);
    } else {
        // ĐOÁN SAI: Loại khỏi quyền đoán, kiểm tra xem còn người chơi nào không
        db_execute(
            "UPDATE players 
             SET is_disqualified_obstacle = 1 
             WHERE id_player = :p",
            [':p' => $playerId]
        );

        $activeRemaining = db_count('players', 'id_room = :r AND is_kicked = 0 AND is_disqualified_obstacle = 0', [':r' => $roomId]);
        $totalInRoom = db_count('players', 'id_room = :r AND is_kicked = 0', [':r' => $roomId]);

        if ($totalInRoom > 0 && $activeRemaining === 0) {
            // TẤT CẢ NGƯỜI CHƠI ĐỀU ĐÃ ĐOÁN SAI KHI BẤM CHUÔNG -> TRÒ CHƠI KẾT THÚC!
            // Tính điểm người chiến thắng là người có điểm cao nhất
            $topPlayer = db_fetch_one(
                "SELECT id_player, nickname, score 
                 FROM players 
                 WHERE id_room = :r AND is_kicked = 0 
                 ORDER BY score DESC, playeradd ASC, id_player ASC LIMIT 1",
                [':r' => $roomId]
            );
            $topPid = $topPlayer ? intval($topPlayer['id_player']) : null;

            db_execute(
                "UPDATE room 
                 SET status = 'finished', 
                     buzzer_status = 'finished', 
                     winner_player_id = :wp, 
                     buzzer_player_id = NULL, 
                     buzzer_time = NULL 
                 WHERE id_room = :r",
                [
                    ':wp' => $topPid,
                    ':r'  => $roomId
                ]
            );

            echo json_encode([
                'success'          => true,
                'correct'          => false,
                'all_disqualified' => true,
                'winner_name'      => $topPlayer['nickname'] ?? 'Player',
                'message'          => 'Đáp án chưa chính xác! Tất cả người chơi đều đã đoán sai từ khóa. Trò chơi kết thúc! Người chiến thắng theo điểm số là: ' . ($topPlayer['nickname'] ?? 'Player') . '.'
            ]);
            exit;
        }

        db_execute(
            "UPDATE room 
             SET buzzer_status = 'idle', 
                 buzzer_player_id = NULL, 
                 buzzer_time = NULL 
             WHERE id_room = :r",
            [':r' => $roomId]
        );

        echo json_encode([
            'success'          => true,
            'correct'          => false,
            'all_disqualified' => false,
            'message'          => 'Đáp án chưa chính xác! Bạn đã bị hủy tư cách thi đấu ở tất cả các vòng tiếp theo.'
        ]);
    }
    exit;
}

// 5. HẾT 20S MÀ NGƯỜI BẤM CHUÔNG KHÔNG TRẢ LỜI
if ($action === 'buzzer_timeout') {
    if ($room['buzzer_status'] === 'buzzing') {
        $buzzerPid = intval($room['buzzer_player_id']);
        if ($buzzerPid > 0) {
            db_execute("UPDATE players SET is_disqualified_obstacle = 1 WHERE id_player = :p", [':p' => $buzzerPid]);
        }

        $activeRemaining = db_count('players', 'id_room = :r AND is_kicked = 0 AND is_disqualified_obstacle = 0', [':r' => $roomId]);
        $totalInRoom = db_count('players', 'id_room = :r AND is_kicked = 0', [':r' => $roomId]);

        if ($totalInRoom > 0 && $activeRemaining === 0) {
            $topPlayer = db_fetch_one(
                "SELECT id_player, nickname, score 
                 FROM players 
                 WHERE id_room = :r AND is_kicked = 0 
                 ORDER BY score DESC, playeradd ASC, id_player ASC LIMIT 1",
                [':r' => $roomId]
            );
            $topPid = $topPlayer ? intval($topPlayer['id_player']) : null;

            db_execute(
                "UPDATE room 
                 SET status = 'finished', 
                     buzzer_status = 'finished', 
                     winner_player_id = :wp, 
                     buzzer_player_id = NULL, 
                     buzzer_time = NULL 
                 WHERE id_room = :r",
                [
                    ':wp' => $topPid,
                    ':r'  => $roomId
                ]
            );
            echo json_encode(['success' => true, 'all_disqualified' => true]);
            exit;
        }

        db_execute(
            "UPDATE room 
             SET buzzer_status = 'idle', 
                 buzzer_player_id = NULL, 
                 buzzer_time = NULL 
             WHERE id_room = :r",
            [':r' => $roomId]
        );
    }
    echo json_encode(['success' => true]);
    exit;
}

// 6. LẤY TRẠNG THÁI REALTIME CỦA PHÒNG CHƯỚNG NGẠI VẬT
if ($action === 'get_obstacle_state') {
    $openedPieces = !empty($room['opened_pieces']) ? json_decode($room['opened_pieces'], true) : [];
    if (!is_array($openedPieces)) $openedPieces = [];

    $buzzerPlayerName = '';
    if (!empty($room['buzzer_player_id'])) {
        $bp = db_fetch_one("SELECT nickname FROM players WHERE id_player = :p", [':p' => $room['buzzer_player_id']]);
        $buzzerPlayerName = $bp['nickname'] ?? '';
    }

    $winnerPlayerName = '';
    if (!empty($room['winner_player_id'])) {
        $wp = db_fetch_one("SELECT nickname FROM players WHERE id_player = :p", [':p' => $room['winner_player_id']]);
        $winnerPlayerName = $wp['nickname'] ?? '';
    }

    $buzzerRemaining = 0;
    if ($room['buzzer_status'] === 'buzzing' && !empty($room['buzzer_time'])) {
        $elapsed = time() - intval($room['buzzer_time']);
        $buzzerRemaining = max(0, 20 - $elapsed);
    }

    $myUnlockedPieces = [];
    $isDisqualified = false;
    $playerId = intval($_GET['pid'] ?? ($_POST['id_player'] ?? ($jsonData['id_player'] ?? 0)));
    if ($playerId > 0) {
        $p = db_fetch_one("SELECT unlocked_pieces, is_disqualified_obstacle FROM players WHERE id_player = :p", [':p' => $playerId]);
        if ($p) {
            $myUnlockedPieces = !empty($p['unlocked_pieces']) ? json_decode($p['unlocked_pieces'], true) : [];
            if (!is_array($myUnlockedPieces)) $myUnlockedPieces = [];
            $isDisqualified = !empty($p['is_disqualified_obstacle']);
        }
    }

    $isRevealed = ($room['buzzer_status'] === 'finished' || $room['status'] === 'finished' || !empty($room['winner_player_id']));

    $activeRemaining = db_count('players', 'id_room = :r AND is_kicked = 0 AND is_disqualified_obstacle = 0', [':r' => $roomId]);
    $totalInRoom = db_count('players', 'id_room = :r AND is_kicked = 0', [':r' => $roomId]);
    $allDisqualified = ($totalInRoom > 0 && $activeRemaining === 0);

    echo json_encode([
        'success'             => true,
        'status'              => $room['status'],
        'total_pieces'        => intval($room['total_pieces'] ?? 4),
        'opened_pieces'       => $openedPieces,
        'buzzer_status'       => $room['buzzer_status'],
        'buzzer_player_id'    => $room['buzzer_player_id'] ? intval($room['buzzer_player_id']) : null,
        'buzzer_player_name'  => $buzzerPlayerName,
        'buzzer_remaining'    => $buzzerRemaining,
        'winner_player_id'    => $room['winner_player_id'] ? intval($room['winner_player_id']) : null,
        'winner_player_name'  => $winnerPlayerName,
        'obstacle_answer'     => $isRevealed ? $room['obstacle_answer'] : null,
        'obstacle_image'      => !empty($room['obstacle_image']) ? BASE_URL . $room['obstacle_image'] : null,
        'is_revealed'         => $isRevealed,
        'all_disqualified'    => $allDisqualified,
        'my_unlocked_pieces'  => $myUnlockedPieces,
        'my_is_disqualified'  => $isDisqualified
    ]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Action không hợp lệ']);
