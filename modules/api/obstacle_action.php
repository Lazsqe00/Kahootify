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
    echo json_encode(['success' => false, 'message' => 'Invalid room PIN']);
    exit;
}

$room = db_fetch_one("SELECT r.*, q.obstacle_image, q.obstacle_answer, q.total_pieces 
                      FROM room r 
                      JOIN quiz q ON r.id_quiz = q.id_quiz 
                      WHERE r.id_room = :r", [':r' => $roomId]);

if (!$room) {
    echo json_encode(['success' => false, 'message' => 'Game room does not exist']);
    exit;
}

// 1. HOST SELECTS PUZZLE PIECE TO OPEN QUESTION
if ($action === 'select_piece') {
    $creatorId = intval($_SESSION['id_creator'] ?? 0);
    if ($creatorId !== intval($room['id_creator'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'You are not the host of this room']);
        exit;
    }

    $pieceIndex = intval($_POST['piece_index'] ?? ($jsonData['piece_index'] ?? 0));
    if ($pieceIndex <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid puzzle piece']);
        exit;
    }

    // Find question corresponding to piece_index
    $question = db_fetch_one("SELECT * FROM questions WHERE id_quiz = :qz AND piece_index = :pi ORDER BY id_question ASC LIMIT 1", [
        ':qz' => $room['id_quiz'],
        ':pi' => $pieceIndex
    ]);

    // Fallback if no question tied to piece_index
    if (!$question) {
        $allQ = db_fetch_all("SELECT * FROM questions WHERE id_quiz = :qz ORDER BY sort_order ASC, id_question ASC", [':qz' => $room['id_quiz']]);
        if (isset($allQ[$pieceIndex - 1])) {
            $question = $allQ[$pieceIndex - 1];
        } else {
            $question = $allQ[0] ?? null;
        }
    }

    if (!$question) {
        echo json_encode(['success' => false, 'message' => 'No question found for this puzzle piece']);
        exit;
    }

    // Update opened_pieces
    $openedPieces = !empty($room['opened_pieces']) ? json_decode($room['opened_pieces'], true) : [];
    if (!is_array($openedPieces)) $openedPieces = [];
    if (!in_array($pieceIndex, $openedPieces)) {
        $openedPieces[] = $pieceIndex;
        sort($openedPieces);
    }

    $qId = intval($question['id_question']);

    // Ensure this question has piece_index set to $pieceIndex
    db_execute("UPDATE questions SET piece_index = :pi WHERE id_question = :qid", [
        ':pi'  => $pieceIndex,
        ':qid' => $qId
    ]);

    // Save current_piece_index to room game_config
    $gameConfig = !empty($room['game_config']) ? json_decode($room['game_config'], true) : [];
    if (!is_array($gameConfig)) $gameConfig = [];
    $gameConfig['current_piece_index'] = $pieceIndex;

    db_execute(
        "UPDATE room 
         SET status = :s, 
             question_start_time = UNIX_TIMESTAMP(), 
             opened_pieces = :op, 
             game_config = :gc,
             buzzer_status = 'idle', 
             buzzer_player_id = NULL, 
             buzzer_guess = NULL, 
             buzzer_time = NULL 
         WHERE id_room = :r",
        [
            ':s'  => (string)$qId,
            ':op' => json_encode($openedPieces),
            ':gc' => json_encode($gameConfig),
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

// 2. HOST RETURNS TO BOARD (AFTER QUESTION FINISHES)
if ($action === 'return_to_board') {
    $creatorId = intval($_SESSION['id_creator'] ?? 0);
    if ($creatorId !== intval($room['id_creator'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'You are not the host']);
        exit;
    }

    $openedPieces = !empty($room['opened_pieces']) ? json_decode($room['opened_pieces'], true) : [];
    if (!is_array($openedPieces)) $openedPieces = [];
    $totalPieces = intval($room['total_pieces'] ?? 4);

    $gameConfig = !empty($room['game_config']) ? json_decode($room['game_config'], true) : [];
    if (!is_array($gameConfig)) $gameConfig = [];

    // If all pieces opened, reset buzzer rights for ALL players!
    if (count($openedPieces) >= $totalPieces) {
        $gameConfig['final_round_reset'] = true;
        db_execute("UPDATE players SET is_disqualified_obstacle = 0 WHERE id_room = :r", [':r' => $roomId]);
    }

    db_execute(
        "UPDATE room 
         SET status = 'board', 
             buzzer_status = 'idle', 
             buzzer_player_id = NULL, 
             buzzer_guess = NULL, 
             buzzer_time = NULL,
             game_config = :gc
         WHERE id_room = :r",
        [
            ':gc' => json_encode($gameConfig),
            ':r'  => $roomId
        ]
    );

    echo json_encode(['success' => true]);
    exit;
}

// 3. PLAYER BUZZES IN TO GUESS KEYWORD
if ($action === 'buzz') {
    $playerId = intval($_POST['id_player'] ?? ($jsonData['id_player'] ?? 0));
    $token    = trim($_POST['token'] ?? ($jsonData['token'] ?? ''));

    if ($playerId <= 0 || !player_request_is_valid($playerId, $roomId, $token)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Invalid player session']);
        exit;
    }

    $player = db_fetch_one("SELECT * FROM players WHERE id_player = :p AND id_room = :r", [':p' => $playerId, ':r' => $roomId]);
    if (!$player) {
        echo json_encode(['success' => false, 'message' => 'Player does not exist in room']);
        exit;
    }

    $openedPieces = !empty($room['opened_pieces']) ? json_decode($room['opened_pieces'], true) : [];
    if (!is_array($openedPieces)) $openedPieces = [];
    $totalPieces = intval($room['total_pieces'] ?? 4);
    $allPiecesOpened = (count($openedPieces) >= $totalPieces);

    // Disallow guessing when no puzzle pieces have been revealed yet
    if (empty($openedPieces)) {
        echo json_encode([
            'success' => false,
            'message' => 'No puzzle piece has been revealed yet! You must wait until at least one piece is opened to guess.'
        ]);
        exit;
    }

    if (!empty($player['is_disqualified_obstacle'])) {
        if (!$allPiecesOpened) {
            echo json_encode(['success' => false, 'message' => 'Your buzzer is temporarily locked during piece reveals. Buzzer will unlock once all pieces are opened!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'You have used up all your keyword guesses!']);
        }
        exit;
    }

    // Atomic buzzer claim
    if ($room['buzzer_status'] === 'buzzing' || $room['buzzer_status'] === 'pending_review') {
        if (intval($room['buzzer_player_id']) === $playerId) {
            echo json_encode(['success' => true, 'your_turn' => true]);
            exit;
        }
        echo json_encode(['success' => false, 'message' => 'Another player buzzed in first!']);
        exit;
    }

    if ($room['buzzer_status'] === 'finished') {
        echo json_encode(['success' => false, 'message' => 'The game already has a champion!']);
        exit;
    }

    $gameConfig = !empty($room['game_config']) ? json_decode($room['game_config'], true) : [];
    if (!is_array($gameConfig)) $gameConfig = [];

    // Freeze question timer during buzzer
    if (is_numeric($room['status']) && intval($room['status']) > 0) {
        $qId = intval($room['status']);
        $qRow = db_fetch_one("SELECT time_limit FROM questions WHERE id_question = :q", [':q' => $qId]);
        $tLimit = intval($qRow['time_limit'] ?? 20);
        $elapsed = time() - intval($room['question_start_time'] ?? time());
        $rem = max(1, $tLimit - $elapsed);
        $gameConfig['paused_remaining_seconds'] = $rem;
        $gameConfig['paused_time_limit'] = $tLimit;
    }

    $affected = db_execute(
        "UPDATE room 
         SET buzzer_status = 'buzzing', 
             buzzer_player_id = :p, 
             buzzer_guess = NULL, 
             buzzer_time = UNIX_TIMESTAMP(),
             game_config = :gc
         WHERE id_room = :r AND buzzer_status = 'idle'",
        [
            ':p'  => $playerId,
            ':gc' => json_encode($gameConfig),
            ':r'  => $roomId
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
        echo json_encode(['success' => false, 'message' => 'Oops! Another player buzzed in just a moment before you.']);
    }
    exit;
}

// 4. BUZZER PLAYER SUBMITS KEYWORD FOR HOST REVIEW
if ($action === 'submit_guess') {
    $playerId = intval($_POST['id_player'] ?? ($jsonData['id_player'] ?? 0));
    $token    = trim($_POST['token'] ?? ($jsonData['token'] ?? ''));
    $guess    = trim($_POST['guess'] ?? ($jsonData['guess'] ?? ''));

    if ($playerId <= 0 || !player_request_is_valid($playerId, $roomId, $token)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Invalid player session']);
        exit;
    }

    if ($room['buzzer_status'] !== 'buzzing' || intval($room['buzzer_player_id']) !== $playerId) {
        echo json_encode(['success' => false, 'message' => 'It is not your turn to answer!']);
        exit;
    }

    // Forfeited buzzer rights if no answer provided
    if ($guess === '') {
        db_execute("UPDATE players SET is_disqualified_obstacle = 1 WHERE id_player = :p", [':p' => $playerId]);

        $gameConfig = !empty($room['game_config']) ? json_decode($room['game_config'], true) : [];
        if (!is_array($gameConfig)) $gameConfig = [];
        $updateSql = "UPDATE room SET buzzer_status = 'idle', buzzer_player_id = NULL, buzzer_guess = NULL, buzzer_time = NULL";
        $updateParams = [':r' => $roomId];
        if (!empty($gameConfig['paused_remaining_seconds'])) {
            $rem = intval($gameConfig['paused_remaining_seconds']);
            $tLim = intval($gameConfig['paused_time_limit'] ?? 20);
            $newStartTime = time() - ($tLim - $rem);
            unset($gameConfig['paused_remaining_seconds'], $gameConfig['paused_time_limit']);
            $updateSql .= ", question_start_time = :st, game_config = :gc";
            $updateParams[':st'] = $newStartTime;
            $updateParams[':gc'] = json_encode($gameConfig);
        }
        $updateSql .= " WHERE id_room = :r";
        db_execute($updateSql, $updateParams);

        echo json_encode([
            'success'      => false,
            'disqualified' => true,
            'message'      => 'You did not provide an answer and forfeited buzzer rights!'
        ]);
        exit;
    }

    $player = db_fetch_one("SELECT * FROM players WHERE id_player = :p AND id_room = :r", [':p' => $playerId, ':r' => $roomId]);

    // Save player answer and set to pending_review
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
        'message'        => 'Answer submitted to Host! Please wait for confirmation.'
    ]);
    exit;
}

// 4b. BUZZER CANCEL OR TIMEOUT
if ($action === 'cancel_or_timeout_guess') {
    $playerId = intval($_POST['id_player'] ?? ($jsonData['id_player'] ?? 0));
    $token    = trim($_POST['token'] ?? ($jsonData['token'] ?? ''));

    if ($playerId <= 0 || !player_request_is_valid($playerId, $roomId, $token)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Invalid player session']);
        exit;
    }

    if ($room['buzzer_status'] === 'buzzing' && intval($room['buzzer_player_id']) === $playerId) {
        db_execute("UPDATE players SET is_disqualified_obstacle = 1 WHERE id_player = :p", [':p' => $playerId]);

        $gameConfig = !empty($room['game_config']) ? json_decode($room['game_config'], true) : [];
        if (!is_array($gameConfig)) $gameConfig = [];
        $updateSql = "UPDATE room SET buzzer_status = 'idle', buzzer_player_id = NULL, buzzer_guess = NULL, buzzer_time = NULL";
        $updateParams = [':r' => $roomId];
        if (!empty($gameConfig['paused_remaining_seconds'])) {
            $rem = intval($gameConfig['paused_remaining_seconds']);
            $tLim = intval($gameConfig['paused_time_limit'] ?? 20);
            $newStartTime = time() - ($tLim - $rem);
            unset($gameConfig['paused_remaining_seconds'], $gameConfig['paused_time_limit']);
            $updateSql .= ", question_start_time = :st, game_config = :gc";
            $updateParams[':st'] = $newStartTime;
            $updateParams[':gc'] = json_encode($gameConfig);
        }
        $updateSql .= " WHERE id_room = :r";
        db_execute($updateSql, $updateParams);

        echo json_encode([
            'success'      => true,
            'disqualified' => true,
            'message'      => 'You did not provide an answer and forfeited buzzer rights!'
        ]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Invalid buzzer turn']);
    exit;
}

// 5. HOST CONFIRMS TRUE OR FALSE
if ($action === 'host_confirm_guess') {
    $creatorId = intval($_SESSION['id_creator'] ?? 0);
    $creatorCheck = db_fetch_one("SELECT id_creator FROM room WHERE id_room = :r AND id_creator = :c", [':r' => $roomId, ':c' => $creatorId]);
    if (!$creatorCheck) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Only the host can confirm answers']);
        exit;
    }

    if ($room['buzzer_status'] !== 'pending_review' || empty($room['buzzer_player_id'])) {
        echo json_encode(['success' => false, 'message' => 'No answer currently pending confirmation']);
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
        // TRUE: Player wins and game finishes!
        db_execute(
            "UPDATE room 
             SET buzzer_status = 'finished', 
                 winner_player_id = :p, 
                 status = 'finished' 
             WHERE id_room = :r",
            [':p' => $playerId, ':r' => $roomId]
        );

        // Award 5000 points to winner
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
            'obstacle_image'    => (!empty($room['obstacle_image']) ? (preg_match('#^https?://#i', $room['obstacle_image']) ? $room['obstacle_image'] : BASE_URL . ltrim($room['obstacle_image'], '/')) : null),
            'message'           => 'Confirmed CORRECT! Player has won the game.'
        ]);
        exit;
    } else {
        // FALSE: Player temporarily loses buzzer rights
        db_execute(
            "UPDATE players 
             SET is_disqualified_obstacle = 1 
             WHERE id_player = :p",
            [':p' => $playerId]
        );

        $gameConfig = !empty($room['game_config']) ? json_decode($room['game_config'], true) : [];
        if (!is_array($gameConfig)) $gameConfig = [];

        $updateSql = "UPDATE room SET buzzer_status = 'idle', buzzer_player_id = NULL, buzzer_guess = NULL, buzzer_time = NULL";
        $updateParams = [':r' => $roomId];
        if (!empty($gameConfig['paused_remaining_seconds'])) {
            $rem = intval($gameConfig['paused_remaining_seconds']);
            $tLim = intval($gameConfig['paused_time_limit'] ?? 20);
            $newStartTime = time() - ($tLim - $rem);
            unset($gameConfig['paused_remaining_seconds'], $gameConfig['paused_time_limit']);
            $updateSql .= ", question_start_time = :st, game_config = :gc";
            $updateParams[':st'] = $newStartTime;
            $updateParams[':gc'] = json_encode($gameConfig);
        }
        $updateSql .= " WHERE id_room = :r";
        db_execute($updateSql, $updateParams);

        $gameConfig = !empty($room['game_config']) ? json_decode($room['game_config'], true) : [];
        if (!is_array($gameConfig)) $gameConfig = [];
        $isFinalRound = !empty($gameConfig['final_round_reset']);

        // Check end condition when all pieces revealed
        // End if all players guessed incorrectly in final round
        $activeRemaining = db_count('players', 'id_room = :r AND is_kicked = 0 AND is_disqualified_obstacle = 0', [':r' => $roomId]);
        $totalInRoom = db_count('players', 'id_room = :r AND is_kicked = 0', [':r' => $roomId]);
        $allDisqualifiedAtEnd = ($isFinalRound && $allPiecesOpened && $totalInRoom > 0 && $activeRemaining === 0);

        if ($allDisqualifiedAtEnd) {
            // Find top scoring player
            $topPlayer = db_fetch_one(
                "SELECT id_player, nickname, score FROM players 
                 WHERE id_room = :r AND is_kicked = 0 
                 ORDER BY score DESC, playeradd ASC, id_player ASC LIMIT 1",
                [':r' => $roomId]
            );
            $topPid = $topPlayer ? intval($topPlayer['id_player']) : null;

            // Finish game
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
                'message'           => 'All players guessed the secret keyword incorrectly! Game ended, winners ranked by score.'
            ]);
            exit;
        }

        echo json_encode([
            'success'           => true,
            'correct'           => false,
            'all_pieces_opened' => $allPiecesOpened,
            'all_disqualified'  => false,
            'message'           => $isFinalRound ? 'Confirmed INCORRECT! Other players still have a chance to buzz in.' : 'Confirmed INCORRECT! Player buzzer locked until all pieces are opened.'
        ]);
        exit;
    }
}

// 6. HOST CANCELS CURRENT BUZZER (IF NEEDED)
if ($action === 'host_cancel_buzz') {
    $creatorId = intval($_SESSION['id_creator'] ?? 0);
    $creatorCheck = db_fetch_one("SELECT id_creator FROM room WHERE id_room = :r AND id_creator = :c", [':r' => $roomId, ':c' => $creatorId]);
    if ($creatorCheck) {
        $buzzerPid = intval($room['buzzer_player_id'] ?? 0);
        if ($buzzerPid > 0) {
            db_execute("UPDATE players SET is_disqualified_obstacle = 1 WHERE id_player = :p", [':p' => $buzzerPid]);
        }

        $gameConfig = !empty($room['game_config']) ? json_decode($room['game_config'], true) : [];
        if (!is_array($gameConfig)) $gameConfig = [];

        $updateSql = "UPDATE room SET buzzer_status = 'idle', buzzer_player_id = NULL, buzzer_guess = NULL, buzzer_time = NULL";
        $updateParams = [':r' => $roomId];
        if (!empty($gameConfig['paused_remaining_seconds'])) {
            $rem = intval($gameConfig['paused_remaining_seconds']);
            $tLim = intval($gameConfig['paused_time_limit'] ?? 20);
            $newStartTime = time() - ($tLim - $rem);
            unset($gameConfig['paused_remaining_seconds'], $gameConfig['paused_time_limit']);
            $updateSql .= ", question_start_time = :st, game_config = :gc";
            $updateParams[':st'] = $newStartTime;
            $updateParams[':gc'] = json_encode($gameConfig);
        }
        $updateSql .= " WHERE id_room = :r";
        db_execute($updateSql, $updateParams);
        echo json_encode(['success' => true]);
        exit;
    }
}

// 7. GET REALTIME OBSTACLE ROOM STATE
if ($action === 'get_obstacle_state') {
    $openedPieces = !empty($room['opened_pieces']) ? json_decode($room['opened_pieces'], true) : [];
    if (!is_array($openedPieces)) $openedPieces = [];
    $totalPieces = intval($room['total_pieces'] ?? 4);
    $allPiecesOpened = (count($openedPieces) >= $totalPieces);

    $gameConfig = !empty($room['game_config']) ? json_decode($room['game_config'], true) : [];
    if (!is_array($gameConfig)) $gameConfig = [];

    // If all pieces opened and in board state,
    // reset buzzer for all players in final round!
    if ($allPiecesOpened && empty($gameConfig['final_round_reset']) && ($room['status'] === 'board' || !is_numeric($room['status']))) {
        $gameConfig['final_round_reset'] = true;
        db_execute("UPDATE room SET game_config = :gc WHERE id_room = :r", [
            ':gc' => json_encode($gameConfig),
            ':r'  => $roomId
        ]);
        db_execute("UPDATE players SET is_disqualified_obstacle = 0 WHERE id_room = :r", [
            ':r'  => $roomId
        ]);
        $room['game_config'] = json_encode($gameConfig);
    }

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

    $isFinalRound = !empty($gameConfig['final_round_reset']);
    $activeRemaining = db_count('players', 'id_room = :r AND is_kicked = 0 AND is_disqualified_obstacle = 0', [':r' => $roomId]);
    $totalInRoom = db_count('players', 'id_room = :r AND is_kicked = 0', [':r' => $roomId]);
    $allDisqualified = ($isFinalRound && $allPiecesOpened && $totalInRoom > 0 && $activeRemaining === 0);

    $isRevealed = ($room['buzzer_status'] === 'finished' || $room['status'] === 'finished' || !empty($room['winner_player_id']));

    // Secret image returned if winner exists OR all pieces opened
    $obstacleImage = (!empty($room['obstacle_image']) && ($isRevealed || $allPiecesOpened)) 
        ? (preg_match('#^https?://#i', $room['obstacle_image']) ? $room['obstacle_image'] : BASE_URL . ltrim($room['obstacle_image'], '/')) 
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

echo json_encode(['success' => false, 'message' => 'Invalid action']);
