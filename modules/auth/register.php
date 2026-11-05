<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
    || (isset($_POST['ajax']) && $_POST['ajax'] === '1')
    || (isset($_GET['ajax']) && $_GET['ajax'] === '1')
    || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

function register_respond($success, $message, $redirectUrl = 'index.php', $errors = [], $formData = []) {
    global $isAjax;
    if ($isAjax) {
        if ($success) {
            set_flash_message('success', $message);
        }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success'  => $success,
            'message'  => $message,
            'redirect' => $redirectUrl,
            'errors'   => $errors
        ]);
        exit;
    }

    if ($success) {
        set_flash_message('success', $message);
        redirect($redirectUrl);
    } else {
        $_SESSION['signup_error'] = $message;
        $_SESSION['signup_errors'] = $errors;
        $_SESSION['signup_data'] = [
            'name'     => $formData['name'] ?? '',
            'username' => $formData['username'] ?? '',
            'email'    => $formData['email'] ?? '',
            'role'     => $formData['role'] ?? 1,
        ];
        redirect($redirectUrl);
    }
}

// Real-time field validation / availability check endpoint
$action = $_REQUEST['action'] ?? '';
if ($action === 'check_availability') {
    header('Content-Type: application/json; charset=utf-8');
    $field = trim($_REQUEST['field'] ?? '');
    $value = trim($_REQUEST['value'] ?? '');

    if ($field === 'username') {
        if ($value === '') {
            echo json_encode(['valid' => false, 'message' => 'Username is required!']);
            exit;
        }
        $exists = db_fetch_one("SELECT id_creator FROM creators WHERE username = :u LIMIT 1", [':u' => $value]);
        if ($exists) {
            echo json_encode(['valid' => false, 'message' => 'Username is already in use!']);
            exit;
        }
        echo json_encode(['valid' => true, 'message' => 'Username is available!']);
        exit;
    }

    if ($field === 'email') {
        if ($value === '') {
            echo json_encode(['valid' => false, 'message' => 'Email address is required!']);
            exit;
        }
        if (!filter_var($value, FILTER_VALIDATE_EMAIL) || !preg_match('/^[^@\s]+@[^@\s]+\.[^@\s]+$/', $value)) {
            echo json_encode(['valid' => false, 'message' => 'Invalid email address format (e.g. name@example.com)!']);
            exit;
        }
        $exists = db_fetch_one("SELECT id_creator FROM creators WHERE email = :e LIMIT 1", [':e' => $value]);
        if ($exists) {
            echo json_encode(['valid' => false, 'message' => 'Email is already in use!']);
            exit;
        }
        echo json_encode(['valid' => true, 'message' => 'Email is available!']);
        exit;
    }

    echo json_encode(['valid' => true]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirmPassword = trim($_POST['confirm_password'] ?? '');
    $role     = intval($_POST['role'] ?? 1);

    $errors = [];

    if ($name === '') {
        $errors['name'] = 'Please enter your full name!';
    }

    if ($username === '') {
        $errors['username'] = 'Please enter a username!';
    }

    if ($email === '') {
        $errors['email'] = 'Please enter your email address!';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/^[^@\s]+@[^@\s]+\.[^@\s]+$/', $email)) {
        $errors['email'] = 'Invalid email address format (e.g. name@example.com)!';
    }

    if ($password === '') {
        $errors['password'] = 'Please enter a password!';
    } elseif (strlen($password) < 6) {
        $errors['password'] = 'Password must be at least 6 characters long!';
    }

    if ($confirmPassword === '') {
        $errors['confirm_password'] = 'Please confirm your password!';
    } elseif ($confirmPassword !== $password) {
        $errors['confirm_password'] = 'Passwords do not match!';
    }

    if (!empty($errors)) {
        $firstMsg = reset($errors);
        register_respond(false, $firstMsg, 'index.php', $errors, $_POST);
    }

    try {
        $existingUser = db_fetch_one("SELECT id_creator FROM creators WHERE username = :u LIMIT 1", [
            ':u' => $username
        ]);
        $existingEmail = db_fetch_one("SELECT id_creator FROM creators WHERE email = :e LIMIT 1", [
            ':e' => $email
        ]);

        if ($existingUser || $existingEmail) {
            if ($existingUser && $existingEmail) {
                $errors['username'] = 'Username is already in use!';
                $errors['email'] = 'Email is already in use!';
                register_respond(false, 'Username and Email are already in use!', 'index.php', $errors, $_POST);
            } elseif ($existingUser) {
                $errors['username'] = 'Username is already in use!';
                register_respond(false, 'Username is already in use!', 'index.php', $errors, $_POST);
            } else {
                $errors['email'] = 'Email is already in use!';
                register_respond(false, 'Email is already in use!', 'index.php', $errors, $_POST);
            }
        }

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

        $_SESSION['id_creator'] = $newId;
        $_SESSION['nameCreator'] = $name;
        $_SESSION['username'] = $username;
        $_SESSION['email'] = $email;
        $_SESSION['role'] = $role;
        $_SESSION['avatar'] = 'default.png';

        register_respond(true, 'Account created successfully! Start creating your quizzes now.', BASE_URL . 'modules/quiz/list.php');

    } catch (Exception $e) {
        register_respond(false, 'Registration error: ' . $e->getMessage(), 'index.php', ['general' => $e->getMessage()], $_POST);
    }
} else {
    redirect('index.php');
}
