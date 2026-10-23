<?php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/room_access.php';

if (!function_exists('getDB')) {
    function getDB() {
        static $pdo = null;
        if ($pdo === null) {
            $ports = [DB_PORT_DEFAULT, DB_PORT_BACKUP];
            $lastException = null;

            foreach ($ports as $port) {
                try {
                    $dsn = "mysql:host=" . DB_HOST . ";port=" . $port . ";dbname=" . DB_NAME . ";charset=utf8mb4";
                    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => true
                    ]);
                    break;
                } catch (PDOException $e) {
                    $lastException = $e;
                }
            }

            if ($pdo === null) {
                die("<div style='font-family: Arial; padding: 20px; background: #fff3cd; color: #856404; border: 1px solid #ffeeba; border-radius: 6px; max-width: 600px; margin: 50px auto;'>
                    <h3 style='margin-top:0;'>⚠️ Unable to connect to the Database!</h3>
                    <p><b>Error:</b> " . htmlspecialchars($lastException->getMessage()) . "</p>
                    <p><b>Troubleshooting:</b></p>
                    <ol>
                        <li>Open XAMPP Control Panel and verify the <b>MySQL</b> service is running.</li>
                        <li>Go to <a href='http://localhost/phpmyadmin' target='_blank'>phpMyAdmin</a> and import file: <code>BTN/database/kahoot_btn.sql</code></li>
                        <li>Check the MySQL port settings in <code>BTN/config/config.php</code>.</li>
                    </ol>
                </div>");
            }
        }
        return $pdo;
    }
}

function db_fetch_one($sql, $params = []) {
    $stmt = getDB()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetch();
}

function db_fetch_all($sql, $params = []) {
    $stmt = getDB()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function db_execute($sql, $params = []) {
    $stmt = getDB()->prepare($sql);
    return $stmt->execute($params);
}

function db_insert($sql, $params = []) {
    $pdo = getDB();
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $pdo->lastInsertId();
}

function db_count($table, $where = "1", $params = []) {
    $sql = "SELECT COUNT(*) as total FROM `{$table}` WHERE {$where}";
    $row = db_fetch_one($sql, $params);
    return intval($row['total'] ?? 0);
}

function get_room_leaderboard($id_room) {
    $sql = "SELECT p.*,
            (SELECT COUNT(*) FROM votes v JOIN answers a ON v.id_answer = a.id_answer WHERE v.id_player = p.id_player AND a.is_correct = 1) as correct_count
            FROM players p
            WHERE p.id_room = :id_room
            ORDER BY p.score DESC, p.playeradd ASC";
    return filter_room_players($id_room, db_fetch_all($sql, [':id_room' => $id_room]));
}
