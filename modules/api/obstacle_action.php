<?php

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/room_access.php';
require_once __DIR__ . '/../../includes/TeamManager.php';

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
             buzzer_guess = NULL, 
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

    $openedPieces = !empty($room['opened_pieces']) ? json_decode($room['opened_pieces'], true) : [];
    if (!is_array($openedPieces)) $openedPieces = [];
    $totalPieces = intval($room['total_pieces'] ?? 4);

    // Nếu đã mở hết tất cả các mảnh ghép, reset lại quyền bấm chuông cho TẤT CẢ người chơi!
    if (count($openedPieces) >= $totalPieces) {
        db_execute("UPDATE players SET is_disqualified_obstacle = 0 WHERE id_room = :r", [':r' => $roomId]);
    }

    db_execute(
        "UPDATE room 
         SET status = 'board', 
             buzzer_status = 'idle', 
             buzzer_player_id = NULL, 
             buzzer_guess = NULL, 
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

    $openedPieces = !empty($room['opened_pieces']) ? json_decode($room['opened_pieces'], true) : [];
    if (!is_array($openedPieces)) $openedPieces = [];
    $totalPieces = intval($room['total_pieces'] ?? 4);
    $allPiecesOpened = (count($openedPieces) >= $totalPieces);

    if (!empty($player['is_disqualified_obstacle'])) {
        if (!$allPiecesOpened) {
            echo json_encode(['success' => false, 'message' => 'Bạn đã tạm mất quyền bấm chuông trong lúc mở các mảnh ghép. Chuông sẽ được mở lại khi mở hết các mảnh ghép!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Bạn đã hết quyền bấm chuông đoán từ khóa!']);
        }
        exit;
    }

    // Atomic claim chuông
    if ($room['buzzer_status'] === 'buzzing' || $room['buzzer_status'] === 'pending_review') {
        if (intval($room['buzzer_player_id']) === $playerId) {
            echo json_encode(['success' => true, 'your_turn' => true]);
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
             buzzer_guess = NULL, 
             buzzer_time = UNIX_TIMESTAMP() 
         WHERE id_room = :r AND buzzer_status = 'idle'",
        [
            ':p' => $playerId,
            ':r' => $roomId
        ]
    );

    if ($affected > 0) {
        $tInfo = !empty($player['team_id']) ? TeamManager::getTeamInfo(intval($player['team_id'])) : null;
        echo json_encode([
            'success'     => true,
            'your_turn'   => true,
            'player_name' => $player['nickname'],
            'team_id'     => $player['team_id'] ? intval($player['team_id']) : null,
            'team_name'   => $player['team_name'] ?? null,
            'team_color'  => $tInfo['color'] ?? null,
            'team_icon'   => $tInfo['icon'] ?? null
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Rất tiếc! Người chơi khác đã bấm chuông trước một tích tắc.']);
    }
    exit;
}

// 4. NGƯỜI BẤM CHUÔNG GỬI TỪ KHÓA DỰ ĐOÁN ĐỂ HOST XÁC NHẬN
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
        echo json_encode(['success' => false, 'message' => 'Không phải lượt trả lời của bạn!']);
        exit;
    }

    if ($guess === '') {
        echo json_encode(['success' => false, 'message' => 'Vui lòng nhập câu trả lời!']);
        exit;
    }

    $player = db_fetch_one("SELECT * FROM players WHERE id_player = :p AND id_room = :r", [':p' => $playerId, ':r' => $roomId]);

    // Lưu câu trả lời của người chơi và chuyển sang pending_review để Host xác nhận True / False
    db_execute(
        "UPDATE room 
         SET buzzer_status = 'pending_review', 
             buzzer_guess = :guess 
         WHERE id_room = :r AND buzzer_player_id = :p",
        [
            ':guess' => $guess,
            ':r'     => $roomId,
            ':p'     => $playerId
        ]
    );

    echo json_encode([
        'success'        => true,
        'pending_review' => true,
        'guess'          => $guess,
        'message'        => 'Câu trả lời đã được gửi tới Host! Vui lòng chờ Host xác nhận.'
    ]);
    exit;
}

// 5. HOST XÁC NHẬN CÂU TRẢ LỜI CỦA NGƯỜI CHƠI LÀ TRUE HOẶC FALSE
if ($action === 'host_confirm_guess') {
    $creatorId = intval($_SESSION['id_creator'] ?? 0);
    $creatorCheck = db_fetch_one("SELECT id_creator FROM room WHERE id_room = :r AND id_creator = :c", [':r' => $roomId, ':c' => $creatorId]);
    if (!$creatorCheck) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Chỉ Host mới có quyền xác nhận câu trả lời']);
        exit;
    }

    if ($room['buzzer_status'] !== 'pending_review' || empty($room['buzzer_player_id'])) {
        echo json_encode(['success' => false, 'message' => 'Hiện không có câu trả lời nào đang chờ xác nhận']);
        exit;
    }

    $decision = $_POST['decision'] ?? ($jsonData['decision'] ?? null);
    $isCorrect = ($decision === true || $decision === 'true' || $decision === 1 || $decision === '1');
    $playerId = intval($room['buzzer_player_id']);
    $player = db_fetch_one("SELECT * FROM players WHERE id_player = :p AND id_room = :r", [':p' => $playerId, ':r' => $roomId]);

    $openedPieces = !empty($room['opened_pieces']) ? json_decode($room['opened_pieces'], true) : [];
    if (!is_array($openedPieces)) $openedPieces = [];
    $totalPieces = intval($room['total_pieces'] ?? 4);
    $allPiecesOpened = (count($openedPieces) >= $totalPieces);

    if ($isCorrect) {
        // ĐÚNG (TRUE): Người chơi giành chiến thắng và trò chơi kết thúc!
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

        $wTeam = !empty($player['team_id']) ? TeamManager::getTeamInfo(intval($player['team_id'])) : null;
        echo json_encode([
            'success'           => true,
            'correct'           => true,
            'winner_id'         => $playerId,
            'winner_name'       => $player['nickname'] ?? 'Player',
            'winner_team_id'    => $player['team_id'] ? intval($player['team_id']) : null,
            'winner_team_name'  => $player['team_name'] ?? null,
            'winner_team_color' => $wTeam['color'] ?? null,
            'answer'            => $room['obstacle_answer'],
            'message'           => 'Xác nhận ĐÚNG! Người chơi đã giành chiến thắng chung cuộc.'
        ]);
        exit;
    } else {
        // SAI (FALSE): Người chơi tạm mất quyền bấm chuông
        db_execute(
            "UPDATE players 
             SET is_disqualified_obstacle = 1 
             WHERE id_player = :p",
            [':p' => $playerId]
        );

        db_execute(
            "UPDATE room 
             SET buzzer_status = 'idle', 
                 buzzer_player_id = NULL, 
                 buzzer_guess = NULL, 
                 buzzer_time = NULL 
             WHERE id_room = :r",
            [':r' => $roomId]
        );

        // KIỂM TRA ĐIỀU KIỆN KẾT THÚC KHI ĐÃ HIỂN THỊ CẢ BỨC TRANH:
        // Nếu đã mở hết tất cả các mảnh ghép mà TẤT CẢ người chơi đều đã trả lời sai bức tranh:
        $activeRemaining = db_count('players', 'id_room = :r AND is_kicked = 0 AND is_disqualified_obstacle = 0', [':r' => $roomId]);
        $totalInRoom = db_count('players', 'id_room = :r AND is_kicked = 0', [':r' => $roomId]);
        $allDisqualifiedAtEnd = ($allPiecesOpened && $totalInRoom > 0 && $activeRemaining === 0);

        if ($allDisqualifiedAtEnd) {
            // Tìm người có số điểm cao nhất để vinh danh quán quân theo điểm số
            $topPlayer = db_fetch_one(
                "SELECT id_player, nickname, score FROM players 
                 WHERE id_room = :r AND is_kicked = 0 
                 ORDER BY score DESC, playeradd ASC, id_player ASC LIMIT 1",
                [':r' => $roomId]
            );
            $topPid = $topPlayer ? intval($topPlayer['id_player']) : null;

            // Kết thúc trò chơi
            db_execute(
                "UPDATE room 
                 SET status = 'finished', 
                     buzzer_status = 'finished', 
                     winner_player_id = :wp, 
                     buzzer_player_id = NULL, 
                     buzzer_guess = NULL, 
                     buzzer_time = NULL 
                 WHERE id_room = :r",
                [':wp' => $topPid, ':r' => $roomId]
            );

            echo json_encode([
                'success'           => true,
                'correct'           => false,
                'all_pieces_opened' => true,
                'all_disqualified'  => true,
                'game_finished'     => true,
                'winner_name'       => $topPlayer['nickname'] ?? '',
                'winner_score'      => $topPlayer['score'] ?? 0,
                'message'           => 'Tất cả người chơi đều đã trả lời sai từ khóa bức tranh! Trò chơi kết thúc, người chiến thắng được xếp hạng theo số điểm.'
            ]);
            exit;
        }

        echo json_encode([
            'success'           => true,
            'correct'           => false,
            'all_pieces_opened' => $allPiecesOpened,
            'all_disqualified'  => false,
            'message'           => 'Xác nhận SAI! Người chơi đã bị tạm khóa quyền bấm chuông.'
        ]);
        exit;
    }
}

// 6. HOST HỦY LƯỢT CHUÔNG HIỆN TẠI (NẾU CẦN)
if ($action === 'host_cancel_buzz') {
    $creatorId = intval($_SESSION['id_creator'] ?? 0);
    $creatorCheck = db_fetch_one("SELECT id_creator FROM room WHERE id_room = :r AND id_creator = :c", [':r' => $roomId, ':c' => $creatorId]);
    if ($creatorCheck) {
        db_execute(
            "UPDATE room 
             SET buzzer_status = 'idle', 
                 buzzer_player_id = NULL, 
                 buzzer_guess = NULL, 
                 buzzer_time = NULL 
             WHERE id_room = :r",
            [':r' => $roomId]
        );
        echo json_encode(['success' => true]);
        exit;
    }
}

// 7. LẤY TRẠNG THÁI REALTIME CỦA PHÒNG CHƯỚNG NGẠI VẬT
if ($action === 'get_obstacle_state') {
    $openedPieces = !empty($room['opened_pieces']) ? json_decode($room['opened_pieces'], true) : [];
    if (!is_array($openedPieces)) $openedPieces = [];
    $totalPieces = intval($room['total_pieces'] ?? 4);
    $allPiecesOpened = (count($openedPieces) >= $totalPieces);

    $buzzerPlayerName = '';
    $buzzerTeamId = null;
    $buzzerTeamName = '';
    $buzzerTeamColor = '';
    $buzzerTeamIcon = '';
    if (!empty($room['buzzer_player_id'])) {
        $bp = db_fetch_one("SELECT nickname, team_id, team_name FROM players WHERE id_player = :p", [':p' => $room['buzzer_player_id']]);
        $buzzerPlayerName = $bp['nickname'] ?? '';
        $buzzerTeamId = !empty($bp['team_id']) ? intval($bp['team_id']) : null;
        $buzzerTeamName = $bp['team_name'] ?? '';
        if ($buzzerTeamId) {
            $tInfo = TeamManager::getTeamInfo($buzzerTeamId);
            $buzzerTeamColor = $tInfo['color'] ?? '';
            $buzzerTeamIcon = $tInfo['icon'] ?? '';
        }
    }

    $winnerPlayerName = '';
    $winnerTeamId = null;
    $winnerTeamName = '';
    $winnerTeamColor = '';
    $winnerTeamIcon = '';
    if (!empty($room['winner_player_id'])) {
        $wp = db_fetch_one("SELECT nickname, team_id, team_name FROM players WHERE id_player = :p", [':p' => $room['winner_player_id']]);
        $winnerPlayerName = $wp['nickname'] ?? '';
        $winnerTeamId = !empty($wp['team_id']) ? intval($wp['team_id']) : null;
        $winnerTeamName = $wp['team_name'] ?? '';
        if ($winnerTeamId) {
            $wtInfo = TeamManager::getTeamInfo($winnerTeamId);
            $winnerTeamColor = $wtInfo['color'] ?? '';
            $winnerTeamIcon = $wtInfo['icon'] ?? '';
        }
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

    $activeRemaining = db_count('players', 'id_room = :r AND is_kicked = 0 AND is_disqualified_obstacle = 0', [':r' => $roomId]);
    $totalInRoom = db_count('players', 'id_room = :r AND is_kicked = 0', [':r' => $roomId]);
    $allDisqualified = ($allPiecesOpened && $totalInRoom > 0 && $activeRemaining === 0);

    if ($allDisqualified && $room['status'] !== 'finished') {
        $topPlayer = db_fetch_one(
            "SELECT id_player, nickname, score FROM players 
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
                 buzzer_guess = NULL, 
                 buzzer_time = NULL 
             WHERE id_room = :r",
            [':wp' => $topPid, ':r' => $roomId]
        );
        $room['status'] = 'finished';
        $room['buzzer_status'] = 'finished';
        $room['winner_player_id'] = $topPid;
    }

    $isRevealed = ($room['buzzer_status'] === 'finished' || $room['status'] === 'finished' || !empty($room['winner_player_id']));

    // Ảnh bí mật được trả về nếu đã có người chiến thắng HOẶC toàn bộ mảnh ghép đã mở hết!
    $obstacleImage = (!empty($room['obstacle_image']) && ($isRevealed || $allPiecesOpened)) 
        ? BASE_URL . $room['obstacle_image'] 
        : null;

    $liveTeams = !empty($room['is_team_mode']) ? TeamManager::getTeamLeaderboard($roomId) : [];

    echo json_encode([
        'success'             => true,
        'status'              => $room['status'],
        'is_team_mode'        => intval($room['is_team_mode'] ?? 0),
        'total_pieces'        => $totalPieces,
        'opened_pieces'       => $openedPieces,
        'all_pieces_opened'   => $allPiecesOpened,
        'buzzer_status'       => $room['buzzer_status'],
        'buzzer_player_id'    => $room['buzzer_player_id'] ? intval($room['buzzer_player_id']) : null,
        'buzzer_player_name'  => $buzzerPlayerName,
        'buzzer_team_id'      => $buzzerTeamId,
        'buzzer_team_name'    => $buzzerTeamName,
        'buzzer_team_color'   => $buzzerTeamColor,
        'buzzer_team_icon'    => $buzzerTeamIcon,
        'buzzer_guess'        => $room['buzzer_guess'] ?? '',
        'winner_player_id'    => $room['winner_player_id'] ? intval($room['winner_player_id']) : null,
        'winner_player_name'  => $winnerPlayerName,
        'winner_team_id'      => $winnerTeamId,
        'winner_team_name'    => $winnerTeamName,
        'winner_team_color'   => $winnerTeamColor,
        'winner_team_icon'    => $winnerTeamIcon,
        'obstacle_answer'     => ($isRevealed || !empty($_SESSION['id_creator'])) ? $room['obstacle_answer'] : null,
        'obstacle_image'      => $obstacleImage,
        'is_revealed'         => $isRevealed,
        'all_disqualified'    => $allDisqualified,
        'my_unlocked_pieces'  => $allPiecesOpened ? range(1, $totalPieces) : $myUnlockedPieces,
        'my_is_disqualified'  => $isDisqualified,
        'live_teams'          => $liveTeams
    ]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Action không hợp lệ']);
