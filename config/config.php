<?php
/**
 * File cấu hình chung cho dự án BTN - Kahoot Clone
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Thiết lập múi giờ
date_default_timezone_set('Asia/Ho_Chi_Minh');

// Cấu hình Database
define('DB_HOST', '127.0.0.1');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'kahoot_btn');
define('DB_PORT_DEFAULT', 3306);
define('DB_PORT_BACKUP', 3307);

// Tự động nhận diện Base URL linh hoạt
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || ($_SERVER['SERVER_PORT'] ?? 0) == 443) ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$docRoot = str_replace('\\', '/', dirname(__DIR__));
$serverDocRoot = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? '');
$subPath = str_replace($serverDocRoot, '', $docRoot);
define('BASE_URL', rtrim($protocol . $host . $subPath, '/') . '/');

// Màu sắc & Biểu tượng chuẩn Kahoot
define('KAHOOT_COLOR_RED', '#e21b3c');
define('KAHOOT_COLOR_BLUE', '#1368ce');
define('KAHOOT_COLOR_YELLOW', '#d89e00');
define('KAHOOT_COLOR_GREEN', '#26890c');
define('KAHOOT_COLOR_PURPLE', '#46178F');

define('KAHOOT_ICON_RED', '🔺');
define('KAHOOT_ICON_BLUE', '🔷');
define('KAHOOT_ICON_YELLOW', '🟡');
define('KAHOOT_ICON_GREEN', '🟩');

/**
 * Trợ giúp chuyển hướng URL an toàn
 */
function redirect($path) {
    if (strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0) {
        header("Location: " . $path);
    } else {
        header("Location: " . BASE_URL . ltrim($path, '/'));
    }
    exit;
}

/**
 * Hiển thị thông báo Toast / Flash message
 */
function set_flash_message($type, $message) {
    $_SESSION['flash_message'] = [
        'type' => $type, // 'success', 'danger', 'warning', 'info'
        'message' => $message
    ];
}

function get_flash_message() {
    if (isset($_SESSION['flash_message'])) {
        $msg = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $msg;
    }
    return null;
}
