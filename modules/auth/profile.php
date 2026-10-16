<?php

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/database.php';

$creatorId = intval($_SESSION['id_creator']);
$user = db_fetch_one("SELECT * FROM creators WHERE id_creator = :c", [':c' => $creatorId]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');

        if (!empty($name) && !empty($email)) {
            db_execute("UPDATE creators SET name = :n, email = :e WHERE id_creator = :c", [
                ':n' => $name,
                ':e' => $email,
                ':c' => $creatorId
            ]);
            $_SESSION['nameCreator'] = $name;
            $_SESSION['email'] = $email;
            set_flash_message('success', 'Profile updated successfully!');
        } else {
            set_flash_message('danger', 'Please do not leave fields empty!');
        }
        redirect('modules/auth/profile.php');
    }

    if ($action === 'change_password') {
        $oldPass = $_POST['old_password'] ?? '';
        $newPass = $_POST['new_password'] ?? '';

        $shaOld = hash('sha512', $oldPass);
        $isValid = ($user['password'] === $shaOld) || password_verify($oldPass, $user['password']) || ($user['password'] === $oldPass);

        if (!$isValid) {
            set_flash_message('danger', 'Current password is incorrect!');
        } elseif (strlen($newPass) < 6) {
            set_flash_message('danger', 'New password must be at least 6 characters long!');
        } else {
            $shaNew = hash('sha512', $newPass);
            db_execute("UPDATE creators SET password = :p WHERE id_creator = :c", [
                ':p' => $shaNew,
                ':c' => $creatorId
            ]);
            set_flash_message('success', 'Password changed successfully!');
        }
        redirect('modules/auth/profile.php');
    }
}

$pageTitle = "Account Profile | Kahoot!";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
?>

<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card border-0 shadow-sm" style="border-radius: 12px;">
                <div class="card-header bg-white py-3">
                    <h5 class="font-weight-bold mb-0 text-dark">
                        <i class="fas fa-user-circle mr-2 text-primary"></i>Account Profile
                    </h5>
                </div>
                <div class="card-body p-4">
                    <form action="" method="POST" class="mb-4">
                        <input type="hidden" name="action" value="update_profile">
                        <h6 class="font-weight-bold text-uppercase text-muted mb-3">1. General Information</h6>
                        <div class="form-group">
                            <label class="font-weight-bold">Username</label>
                            <input type="text" class="form-control" value="<?= htmlspecialchars($user['username']) ?>" disabled>
                            <small class="text-muted">Username cannot be changed.</small>
                        </div>
                        <div class="form-group">
                            <label class="font-weight-bold">Full Name</label>
                            <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($user['name']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="font-weight-bold">Email Address</label>
                            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" required>
                        </div>
                        <button type="submit" class="btn btn-kahoot btn-kahoot-purple">
                            <i class="fas fa-save mr-1"></i> Save Changes
                        </button>
                    </form>

                    <hr class="my-4">

                    <form action="" method="POST">
                        <input type="hidden" name="action" value="change_password">
                        <h6 class="font-weight-bold text-uppercase text-muted mb-3">2. Change Password</h6>
                        <div class="form-group">
                            <label class="font-weight-bold">Current Password</label>
                            <input type="password" name="old_password" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label class="font-weight-bold">New Password</label>
                            <input type="password" name="new_password" class="form-control" required>
                        </div>
                        <button type="submit" class="btn btn-warning font-weight-bold">
                            <i class="fas fa-key mr-1"></i> Update Password
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
