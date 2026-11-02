<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
    || (isset($_POST['ajax']) && $_POST['ajax'] === '1')
    || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

function login_respond($success, $message, $redirectUrl = 'index.php', $username = '') {
    global $isAjax;
    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success'  => $success,
            'message'  => $message,
            'redirect' => $redirectUrl
        ]);
        exit;
    }

    if ($success) {
        set_flash_message('success', $message);
    } else {
        $_SESSION['login_error'] = $message;
        if (!empty($username)) {
            $_SESSION['login_username'] = $username;
        }
    }
    redirect($redirectUrl);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        login_respond(false, 'Please enter both username and password!', 'index.php', $username);
    }

    try {
        $user = db_fetch_one("SELECT * FROM creators WHERE username = :u OR email = :e LIMIT 1", [
            ':u' => $username,
            ':e' => $username
        ]);

        if (!$user) {
            login_respond(false, 'Account does not exist in the system!', 'index.php', $username);
        }

        $sha512 = hash('sha512', $password);
        $isValid = ($user['password'] === $sha512)
                   || password_verify($password, $user['password'])
                   || ($user['password'] === $password);

        if (!$isValid) {
            login_respond(false, 'Incorrect password!', 'index.php', $username);
        }

        $_SESSION['id_creator'] = $user['id_creator'];
        $_SESSION['nameCreator'] = $user['name'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['avatar'] = !empty($user['avatar']) ? $user['avatar'] : 'default.png';

        login_respond(true, 'Welcome ' . htmlspecialchars($user['name']) . '! You have logged in successfully.', 'modules/quiz/list.php');

    } catch (Exception $e) {
        login_respond(false, 'System error: ' . $e->getMessage(), 'index.php', $username);
    }
} else {
    redirect('index.php');
}
