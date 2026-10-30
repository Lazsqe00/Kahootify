<?php

require_once __DIR__ . '/../config/database.php';

function player_character_catalog(): array {
    static $characters = null;
    if ($characters === null) {
        $characters = [];
        foreach (glob(__DIR__ . '/../assets/avatars/avatar_*.png') ?: [] as $file) {
            if (preg_match('/^avatar_(\d+)\.png$/', basename($file), $match)) {
                $characters[$match[1]] = 'assets/avatars/' . basename($file);
            }
        }
        ksort($characters, SORT_NUMERIC);
    }
    return $characters;
}

function default_player_character(): string {
    return (string) array_key_first(player_character_catalog());
}

function player_character_features(string $characterId): array {
    static $features = null;
    $features ??= require __DIR__ . '/../config/character_appearance.php';
    return $features[$characterId] ?? ['Character', 35, 65, 55, 6, 8];
}

function player_accessory_catalog(): array {
    static $accessories = null;
    if ($accessories === null) {
        $accessories = [];
        foreach (glob(__DIR__ . '/../assets/items/item_*.png') ?: [] as $file) {
            if (preg_match('/^item_(\d+)_([a-z0-9-]+)\.png$/', basename($file), $match)) {
                $accessories[$match[1]] = [
                    'path' => 'assets/items/' . basename($file),
                    'name' => ucwords(str_replace('-', ' ', $match[2])),
                ];
            }
        }
        ksort($accessories, SORT_NUMERIC);
    }
    return $accessories;
}

function player_character_file(array $room, int $playerId): string {
    // Deprecated: Character appearances are saved directly in MySQL (players table).
    return '';
}

function save_player_character(array|int $room, int $playerId, string $characterId, string $accessoryId = ''): void {
    if ($playerId <= 0 || !isset(player_character_catalog()[$characterId])) {
        throw new InvalidArgumentException('Invalid player character.');
    }
    if ($accessoryId !== '' && !isset(player_accessory_catalog()[$accessoryId])) {
        throw new InvalidArgumentException('Invalid player accessory.');
    }
    $roomId = is_array($room) ? (int)($room['id_room'] ?? 0) : (int)$room;
    $sql = 'UPDATE players SET character_id = :char, accessory_id = :acc WHERE id_player = :pid';
    $params = [
        ':char' => $characterId,
        ':acc'  => $accessoryId,
        ':pid'  => $playerId,
    ];
    if ($roomId > 0) {
        $sql .= ' AND id_room = :r';
        $params[':r'] = $roomId;
    }
    db_execute($sql, $params);
}

function get_player_character(array|int $room, int $playerId): string {
    return get_player_appearance($room, $playerId)['character_id'];
}

function get_player_appearance(array|int $room, int $playerId): array {
    $fallback = ['character_id' => default_player_character(), 'accessory_id' => ''];
    if ($playerId <= 0) return $fallback;
    $roomId = is_array($room) ? (int)($room['id_room'] ?? 0) : (int)$room;
    $sql = 'SELECT character_id, accessory_id FROM players WHERE id_player = :pid';
    $params = [':pid' => $playerId];
    if ($roomId > 0) {
        $sql .= ' AND id_room = :r';
        $params[':r'] = $roomId;
    }
    $sql .= ' LIMIT 1';
    $player = db_fetch_one($sql, $params);
    if (!$player) return $fallback;
    $id = $player['character_id'] ?? null;
    if (!is_string($id) || !isset(player_character_catalog()[$id])) {
        $id = default_player_character();
    }
    $accessory = $player['accessory_id'] ?? '';
    if (!is_string($accessory) || !isset(player_accessory_catalog()[$accessory])) {
        $accessory = '';
    }
    return ['character_id' => $id, 'accessory_id' => $accessory];
}

function player_appearance_image(array $appearance, string $class = ''): string {
    return player_character_image($appearance['character_id'], $class, $appearance['accessory_id'] ?? '');
}

function player_character_image(string $characterId, string $class = '', string $accessoryId = ''): string {
    $catalog = player_character_catalog();
    $path = $catalog[$characterId] ?? $catalog[default_player_character()] ?? null;
    if ($path === null) return '';
    [$name, $left, $right, $y, $rx, $ry] = player_character_features($characterId);
    $accessory = player_accessory_catalog()[$accessoryId] ?? null;
    $label = $name . ($accessory ? ' with ' . $accessory['name'] : '');
    $escape = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" width="96" height="96" class="player-avatar ' . $escape($class) . '" role="img" aria-label="' . $escape($label) . '">';
    $svg .= '<image href="' . $escape(BASE_URL . $path) . '" width="100" height="100"/>';
    if ($rx > 0) {
        $svg .= '<g class="avatar-eyes">';
        foreach ([$left, $right] as $x) {
            $svg .= '<ellipse cx="' . $x . '" cy="' . $y . '" rx="' . $rx . '" ry="' . $ry . '" fill="#fff"/>';
            $svg .= '<ellipse cx="' . $x . '" cy="' . ($y + .6) . '" rx="' . round($rx * .48, 2) . '" ry="' . round($ry * .58, 2) . '" fill="#232337"/>';
            $svg .= '<circle cx="' . ($x - 1) . '" cy="' . ($y - 1.5) . '" r="1.1" fill="#fff"/>';
        }
        $svg .= '</g>';
    }
    // The accessory artwork uses a shared eye-line at (50, 55), with 32 units between eyes.
    $scale = round(($right - $left) / 32, 3);
    $tx = round(($right + $left) / 2 - 50 * $scale, 3);
    $ty = round($y - 55 * $scale, 3);
    $svg .= '<g class="avatar-accessory" transform="translate(' . $tx . ' ' . $ty . ') scale(' . $scale . ')">';
    if ($accessory) $svg .= '<image href="' . $escape(BASE_URL . $accessory['path']) . '" width="100" height="100"/>';
    return $svg . '</g></svg>';
}
