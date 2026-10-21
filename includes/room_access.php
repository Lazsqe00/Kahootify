<?php

// Database helpers are loaded by the calling page. Room lock state lives only
// in server-side files; no database column or status value is needed.

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

function read_room_lock($handle) {
    rewind($handle);
    $value = stream_get_contents($handle);
    if ($value === false) {
        throw new RuntimeException('Unable to read room lock state.');
    }
    return trim($value) === '1';
}

function write_room_lock($handle, $isLocked) {
    rewind($handle);
    if (fwrite($handle, $isLocked ? '1' : '0') !== 1 || !ftruncate($handle, 1) || !fflush($handle)) {
        throw new RuntimeException('Unable to save room lock state.');
    }
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
