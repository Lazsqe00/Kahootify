<?php

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/database.php';

$creatorId = intval($_SESSION['id_creator']);
$roomId = intval($_GET['id_room'] ?? $_GET['id'] ?? 0);

if ($roomId <= 0) {
    set_flash_message('danger', 'Mã phòng không hợp lệ!');
    redirect('modules/reports/list.php');
}

// Kiểm tra quyền sở hữu phòng
$room = db_fetch_one("
    SELECT r.*, q.name as quiz_name
    FROM room r
    JOIN quiz q ON r.id_quiz = q.id_quiz
    WHERE r.id_room = :r AND r.id_creator = :c
", [':r' => $roomId, ':c' => $creatorId]);

if (!$room) {
    set_flash_message('danger', 'Không tìm thấy ván đấu hoặc bạn không có quyền tải báo cáo này!');
    redirect('modules/reports/list.php');
}

// Lấy tổng số câu hỏi đã chơi trong ván (nếu bốc ngẫu nhiên N câu thì lấy N)
$selectedQIds = !empty($room['question_order']) ? json_decode($room['question_order'], true) : null;
if (is_array($selectedQIds) && !empty($selectedQIds)) {
    $totalQuestions = count($selectedQIds);
} else {
    $totalQuestions = db_count('questions', 'id_quiz = :q', [':q' => $room['id_quiz']]);
}

// Lấy danh sách người chơi xếp hạng
$sql = "SELECT p.*,
        (SELECT COUNT(*) FROM votes v JOIN answers a ON v.id_answer = a.id_answer WHERE v.id_player = p.id_player AND a.is_correct = 1) as correct_count
        FROM players p
        WHERE p.id_room = :id_room
        ORDER BY p.score DESC, p.playeradd ASC";
$players = filter_room_players($roomId, db_fetch_all($sql, [':id_room' => $roomId]));

// Tên file theo định dạng: Kahoot_Report_PIN_[MãPIN]_[Ngày].csv
$dateStr = date('Y-m-d', strtotime($room['roomadd'] ?? 'now'));
$filename = "Kahoot_Report_PIN_" . preg_replace('/[^A-Za-z0-9_-]/', '', $room['pin']) . "_" . $dateStr . ".csv";

// Thiết lập Headers cho trình duyệt
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0, no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Ghi mã BOM UTF-8 để Microsoft Excel trên Windows mở không bị lỗi font tiếng Việt
echo "\xEF\xBB\xBF";

$output = fopen('php://output', 'w');

// Tiêu đề các cột theo yêu cầu: Hạng, Tên người chơi, Tổng điểm, Số câu đúng, Tổng số câu, Tỷ lệ đúng (%)
fputcsv($output, ['Hạng', 'Tên người chơi', 'Tổng điểm', 'Số câu đúng', 'Tổng số câu', 'Tỷ lệ đúng (%)']);

// Xuất thông tin từng học sinh
foreach ($players as $idx => $p) {
    $rank = $idx + 1;
    $correct = intval($p['correct_count'] ?? $p['answers_correct'] ?? 0);
    $accuracy = $totalQuestions > 0 ? round(($correct / $totalQuestions) * 100, 1) : 0;

    fputcsv($output, [
        $rank,
        $p['nickname'],
        intval($p['score']),
        $correct,
        $totalQuestions,
        $accuracy . '%'
    ]);
}

fclose($output);
exit;
