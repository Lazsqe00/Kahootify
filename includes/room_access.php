<?php

// Room locks and removed players are stored on the server without DB changes.

function with_room_access_lock($room, $callback, $exclusive = true) {
    $key = hash('sha256', $room['id_room'] . ':' . $room['pin']);
    $handle = fopen(__DIR__ . '/../storage/room-locks/' . $key . '.lock', 'c+');
    if ($handle === false) {
        throw new RuntimeException('Unable to open room lock storage.');
    }
    try {
        if (!flock($handle, $exclusive ? LOCK_EX : LOCK_SH)) {
            throw new RuntimeException('Unable to access room lock storage.');
        }
        return $callback($handle);
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

function read_room_access_state($handle) {
    rewind($handle);
    $value = stream_get_contents($handle);
    if ($value === false) {
        throw new RuntimeException('Unable to read room lock state.');
    }
    $value = trim($value);
    // Keep files created by the original room-lock feature compatible.
    if (in_array($value, ['', '0', '1'], true)) {
        return ['locked' => $value === '1', 'kicked' => []];
    }
    $state = json_decode($value, true);
    if (!is_array($state) || !isset($state['locked'], $state['kicked']) || !is_bool($state['locked']) || !is_array($state['kicked'])) {
        throw new RuntimeException('Invalid room access state.');
    }
    return $state;
}

function write_room_access_state($handle, $state) {
    $value = json_encode($state, JSON_THROW_ON_ERROR);
    rewind($handle);
    if (fwrite($handle, $value) !== strlen($value) || !ftruncate($handle, strlen($value)) || !fflush($handle)) {
        throw new RuntimeException('Unable to save room lock state.');
    }
}

function read_room_lock($handle) {
    return read_room_access_state($handle)['locked'];
}

function write_room_lock($handle, $isLocked) {
    $state = read_room_access_state($handle);
    $state['locked'] = (bool)$isLocked;
    write_room_access_state($handle, $state);
}

function get_room_access_state($room) {
    return with_room_access_lock($room, 'read_room_access_state', false);
}

function player_is_kicked($room, $playerId) {
    return !empty(get_room_access_state($room)['kicked'][$playerId]);
}

function filter_room_players($roomId, $players, $state = null) {
    if ($state === null) {
        $room = db_fetch_one('SELECT id_room, pin FROM room WHERE id_room = :r', [':r' => $roomId]);
        if (!$room) return [];
        $state = get_room_access_state($room);
    }
    return array_values(array_filter($players, function ($player) use ($state) {
        return empty($state['kicked'][$player['id_player']]);
    }));
}

function room_question_counts($room, $questionId, $state = null) {
    $players = filter_room_players($room['id_room'], db_fetch_all(
        'SELECT id_player FROM players WHERE id_room = :r', [':r' => $room['id_room']]
    ), $state);
    $answered = db_fetch_all(
        'SELECT DISTINCT v.id_player FROM votes v JOIN answers a ON a.id_answer = v.id_answer JOIN players p ON p.id_player = v.id_player WHERE p.id_room = :r AND a.id_question = :q',
        [':r' => $room['id_room'], ':q' => $questionId]
    );
    return [
        'total_players' => count($players),
        'voted_count' => count(array_intersect(array_column($players, 'id_player'), array_column($answered, 'id_player')))
    ];
}

function kick_room_player($room, $creatorId, $playerId) {
    return with_room_access_lock($room, function ($handle) use ($room, $creatorId, $playerId) {
        $currentRoom = db_fetch_one("SELECT id_room FROM room WHERE id_room = :r AND id_creator = :c AND status != 'finished'", [':r' => $room['id_room'], ':c' => $creatorId]);
        $player = db_fetch_one('SELECT id_player FROM players WHERE id_room = :r AND id_player = :p', [':r' => $room['id_room'], ':p' => $playerId]);
        if (!$currentRoom || !$player) return false;
        $state = read_room_access_state($handle);
        $state['kicked'][$playerId] = true;
        write_room_access_state($handle, $state);
        return true;
    });
}

function player_request_is_valid($playerId, $roomId, $token) {
    if ((int)($_SESSION['id_player'] ?? 0) === (int)$playerId && (int)($_SESSION['player_room_id'] ?? 0) === (int)$roomId) return true;
    $expected = substr(md5($playerId . '_' . $roomId . '_kahoot_secret_2026'), 0, 16);
    return is_string($token) && hash_equals($expected, $token);
}

function redirect_removed_player($playerId) {
    if ((int)($_SESSION['id_player'] ?? 0) === (int)$playerId) {
        unset($_SESSION['id_player'], $_SESSION['player_room_id'], $_SESSION['player_pin'], $_SESSION['player_nickname']);
    }
    redirect('modules/player/join.php?removed=1');
}

function room_is_locked($room) {
    return with_room_access_lock($room, function ($handle) {
        return read_room_lock($handle);
    }, false);
}

function set_room_locked($room, $isLocked) {
    return with_room_access_lock($room, function ($handle) use ($room, $isLocked) {
        $currentRoom = db_fetch_one(
            "SELECT id_room FROM room WHERE id_room = :r AND pin = :pin AND id_creator = :c AND status = 'waiting'",
            [':r' => $room['id_room'], ':pin' => $room['pin'], ':c' => $room['id_creator']]
        );
        if (!$currentRoom) {
            return false;
        }
        write_room_lock($handle, $isLocked);
        return true;
    });
}

function room_join_error($room, $isLocked = null) {
    if (!$room || $room['status'] === 'finished') {
        return 'Invalid Game PIN or room has closed!';
    }
    if ($isLocked ?? room_is_locked($room)) {
        return 'This game is locked. Please wait for the host to unlock it.';
    }
    return '';
}

function join_room_player($roomId, $pin, $nickname) {
    $room = db_fetch_one('SELECT * FROM room WHERE id_room = :r AND pin = :pin', [':r' => $roomId, ':pin' => $pin]);
    if (!$room) {
        return ['success' => false, 'message' => room_join_error($room)];
    }

    // Hold the same file lock used by the host until admission has committed.
    return with_room_access_lock($room, function ($handle) use ($roomId, $pin, $nickname) {
        $pdo = getDB();
        $pdo->beginTransaction();
        try {
            $currentRoom = db_fetch_one(
                'SELECT * FROM room WHERE id_room = :r AND pin = :pin FOR UPDATE',
                [':r' => $roomId, ':pin' => $pin]
            );
            $error = room_join_error($currentRoom, read_room_lock($handle));
            if ($error !== '') {
                $pdo->rollBack();
                return ['success' => false, 'message' => $error];
            }

            $existing = db_fetch_one(
                'SELECT id_player FROM players WHERE id_room = :r AND nickname = :nick LIMIT 1',
                [':r' => $roomId, ':nick' => $nickname]
            );
            if ($existing) {
                $nickname .= '_' . mt_rand(10, 99);
            }
            $playerId = db_insert(
                'INSERT INTO players (id_room, nickname, score, answers_correct) VALUES (:r, :nick, 0, 0)',
                [':r' => $roomId, ':nick' => $nickname]
            );
            $pdo->commit();
            return ['success' => true, 'id_player' => $playerId, 'nickname' => $nickname];
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $error;
        }
    });
}
