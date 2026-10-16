<?php
/**
 * THÀNH VIÊN 1: Xử lý Đăng ký tài khoản mới
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $role     = intval($_POST['role'] ?? 1);

    if (empty($name) || empty($username) || empty($email) || empty($password)) {
        set_flash_message('danger', 'Please fill in all fields!');
        redirect('index.php');
    }

    if (strlen($password) < 6) {
        set_flash_message('danger', 'Password must be at least 6 characters long!');
        redirect('index.php');
    }

    try {
        // Kiểm tra xem username hoặc email đã tồn tại chưa
        $existing = db_fetch_one("SELECT id_creator FROM creators WHERE username = :u OR email = :e LIMIT 1", [
            ':u' => $username,
            ':e' => $email
        ]);

        if ($existing) {
            set_flash_message('warning', 'Username or Email is already in use!');
            redirect('index.php');
        }

        // Hash mật khẩu chuẩn SHA512 (đồng bộ với DB Kahoot)
        $passwordHash = hash('sha512', $password);

        $newId = db_insert(
            "INSERT INTO creators (role, username, name, email, password, confirmation_email) 
             VALUES (:role, :u, :name, :e, :pass, '1')",
            [
                ':role' => $role,
                ':u'    => $username,
                ':name' => $name,
                ':e'    => $email,
                ':pass' => $passwordHash
            ]
        );

        // Tự động đăng nhập
        $_SESSION['id_creator'] = $newId;
        $_SESSION['nameCreator'] = $name;
        $_SESSION['username'] = $username;
        $_SESSION['email'] = $email;
        $_SESSION['role'] = $role;
        $_SESSION['avatar'] = 'default.png';

        set_flash_message('success', 'Account created successfully! Start creating your quizzes now.');
        redirect('modules/auth/dashboard.php');

    } catch (Exception $e) {
        set_flash_message('danger', 'Registration error: ' . $e->getMessage());
        redirect('index.php');
    }
} else {
    redirect('index.php');
}
