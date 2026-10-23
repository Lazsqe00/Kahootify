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
        set_flash_message('success', 'Đã xóa báo cáo ván đấu thành công!');
    } else {
        set_flash_message('danger', 'Không tìm thấy ván đấu hoặc bạn không có quyền xóa ván này!');
    }
}

redirect('modules/reports/list.php');
