<?php

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/database.php';

$creatorId = intval($_SESSION['id_creator']);
$roomId    = intval($_GET['id_room'] ?? $_GET['id'] ?? 0);

if ($roomId > 0) {
    $room = db_fetch_one("SELECT id_room FROM room WHERE id_room = :r AND id_creator = :c", [
        ':r' => $roomId,
        ':c' => $creatorId
    ]);

    if ($room) {
        db_execute("DELETE FROM room WHERE id_room = :r", [':r' => $roomId]);
        set_flash_message('success', 'Game session report deleted successfully!');
    } else {
        set_flash_message('danger', 'Report not found or you do not have permission to delete it!');
    }
}

redirect('modules/reports/list.php');
