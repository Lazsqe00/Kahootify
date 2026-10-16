<?php
/**
 * THÀNH VIÊN 1: Xử lý Đăng nhập
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        set_flash_message('danger', 'Please enter both username and password!');
        redirect('index.php');
    }

    try {
        // Tìm tài khoản theo username hoặc email
        $user = db_fetch_one("SELECT * FROM creators WHERE username = :u OR email = :e LIMIT 1", [
            ':u' => $username,
            ':e' => $username
        ]);

        if (!$user) {
            set_flash_message('danger', 'Account does not exist in the system!');
            redirect('index.php');
        }

        // Kiểm tra mật khẩu (hỗ trợ cả hash SHA512, password_verify và plain)
        $sha512 = hash('sha512', $password);
        $isValid = ($user['password'] === $sha512) 
                   || password_verify($password, $user['password'])
                   || ($user['password'] === $password);

        if (!$isValid) {
            set_flash_message('danger', 'Incorrect password!');
            redirect('index.php');
        }

        // Lưu thông tin vào Session
        $_SESSION['id_creator'] = $user['id_creator'];
        $_SESSION['nameCreator'] = $user['name'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['avatar'] = !empty($user['avatar']) ? $user['avatar'] : 'default.png';

        set_flash_message('success', 'Welcome ' . htmlspecialchars($user['name']) . '! You have logged in successfully.');
        redirect('modules/auth/dashboard.php');

    } catch (Exception $e) {
        set_flash_message('danger', 'System error: ' . $e->getMessage());
        redirect('index.php');
    }
} else {
    redirect('index.php');
}
