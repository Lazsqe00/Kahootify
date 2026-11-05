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

        if (empty($name) || empty($email)) {
            set_flash_message('danger', 'Please do not leave fields empty!');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/^[^@\s]+@[^@\s]+\.[^@\s]+$/', $email)) {
            set_flash_message('danger', 'Invalid email address format (e.g. name@example.com)!');
        } else {
            $existingEmail = db_fetch_one("SELECT id_creator FROM creators WHERE email = :e AND id_creator != :c LIMIT 1", [
                ':e' => $email,
                ':c' => $creatorId
            ]);
            if ($existingEmail) {
                set_flash_message('danger', 'Email is already in use by another account!');
            } else {
                db_execute("UPDATE creators SET name = :n, email = :e WHERE id_creator = :c", [
                    ':n' => $name,
                    ':e' => $email,
                    ':c' => $creatorId
                ]);
                $_SESSION['nameCreator'] = $name;
                $_SESSION['email'] = $email;
                set_flash_message('success', 'Profile updated successfully!');
            }
        }
        redirect('modules/auth/profile.php');
    }

    if ($action === 'change_password') {
        $oldPass = $_POST['old_password'] ?? '';
        $newPass = $_POST['new_password'] ?? '';
        $confirmPass = $_POST['confirm_password'] ?? '';

        $shaOld = hash('sha512', $oldPass);
        $isValid = ($user['password'] === $shaOld) || password_verify($oldPass, $user['password']) || ($user['password'] === $oldPass);

        if (!$isValid) {
            set_flash_message('danger', 'Current password is incorrect!');
        } elseif (strlen($newPass) < 6) {
            set_flash_message('danger', 'New password must be at least 6 characters long!');
        } elseif ($newPass !== $confirmPass) {
            set_flash_message('danger', 'New password and confirm password do not match!');
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
                    <form action="" method="POST" id="updateProfileForm" class="mb-4" novalidate>
                        <input type="hidden" name="action" value="update_profile">
                        <h6 class="font-weight-bold text-uppercase text-muted mb-3">1. General Information</h6>
                        <div class="form-group">
                            <label class="font-weight-bold">Username</label>
                            <input type="text" class="form-control" value="<?= htmlspecialchars($user['username']) ?>" disabled>
                            <small class="text-muted">Username cannot be changed.</small>
                        </div>
                        <div class="form-group">
                            <label class="font-weight-bold">Full Name</label>
                            <input type="text" name="name" id="profileName" class="form-control" value="<?= htmlspecialchars($user['name']) ?>" required>
                            <div class="invalid-feedback font-weight-bold" id="profileNameError"></div>
                        </div>
                        <div class="form-group">
                            <label class="font-weight-bold">Email Address</label>
                            <input type="email" name="email" id="profileEmail" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" placeholder="name@example.com" required autocomplete="email">
                            <div class="invalid-feedback font-weight-bold" id="profileEmailError"></div>
                        </div>
                        <button type="submit" class="btn btn-kahoot btn-kahoot-purple">
                            <i class="fas fa-save mr-1"></i> Save Changes
                        </button>
                    </form>

                    <hr class="my-4">

                    <form action="" method="POST" id="changePasswordForm">
                        <input type="hidden" name="action" value="change_password">
                        <h6 class="font-weight-bold text-uppercase text-muted mb-3">2. Change Password</h6>
                        <div class="form-group">
                            <label class="font-weight-bold">Current Password</label>
                            <div class="password-wrapper position-relative">
                                <input type="password" name="old_password" id="oldPassword" class="form-control" placeholder="Current password" required autocomplete="current-password">
                                <button type="button" class="toggle-password btn-toggle-eye" tabindex="-1" title="Show password" aria-label="Show password">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="font-weight-bold">New Password</label>
                            <div class="password-wrapper position-relative">
                                <input type="password" name="new_password" id="newPassword" class="form-control" placeholder="New password" required autocomplete="new-password">
                                <button type="button" class="toggle-password btn-toggle-eye" tabindex="-1" title="Show password" aria-label="Show password">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="invalid-feedback font-weight-bold" id="newPasswordError"></div>
                            <small class="form-text text-muted">Must be at least 6 characters</small>
                        </div>
                        <div class="form-group">
                            <label class="font-weight-bold">Confirm New Password</label>
                            <div class="password-wrapper position-relative">
                                <input type="password" name="confirm_password" id="confirmNewPassword" class="form-control" placeholder="Confirm new password" required autocomplete="new-password">
                                <button type="button" class="toggle-password btn-toggle-eye" tabindex="-1" title="Show password" aria-label="Show password">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="invalid-feedback font-weight-bold" id="confirmNewPasswordError"></div>
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    function clearError(input, errorElem) {
        if (input) input.classList.remove('is-invalid');
        if (errorElem) {
            errorElem.textContent = '';
            errorElem.style.display = 'none';
        }
    }

    function setError(input, errorElem, message) {
        if (input) input.classList.add('is-invalid');
        if (errorElem) {
            errorElem.innerHTML = '<i class="fas fa-exclamation-circle mr-1"></i> ' + message;
            errorElem.style.display = 'block';
        }
    }

    function isValidEmail(email) {
        var re = /^[a-zA-Z0-9.!#$%&'*+/=?^_`{|}~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)+$/;
        return re.test(email);
    }

    // 1. Profile Information Validation (Email & Name)
    var updateProfileForm = document.getElementById('updateProfileForm');
    if (updateProfileForm) {
        var profileName = document.getElementById('profileName');
        var profileEmail = document.getElementById('profileEmail');
        var profileNameError = document.getElementById('profileNameError');
        var profileEmailError = document.getElementById('profileEmailError');

        if (profileName) {
            profileName.addEventListener('input', function() {
                if (profileName.value.trim()) {
                    clearError(profileName, profileNameError);
                }
            });
        }

        if (profileEmail) {
            profileEmail.addEventListener('input', function() {
                if (profileEmail.classList.contains('is-invalid') && isValidEmail(profileEmail.value.trim())) {
                    clearError(profileEmail, profileEmailError);
                }
            });

            profileEmail.addEventListener('blur', function() {
                var emailVal = profileEmail.value.trim();
                if (!emailVal) return;
                if (!isValidEmail(emailVal)) {
                    setError(profileEmail, profileEmailError, 'Invalid email address format (e.g. name@example.com)!');
                } else {
                    clearError(profileEmail, profileEmailError);
                }
            });
        }

        updateProfileForm.addEventListener('submit', function(e) {
            var nameVal = profileName ? profileName.value.trim() : '';
            var emailVal = profileEmail ? profileEmail.value.trim() : '';
            var hasErr = false;

            clearError(profileName, profileNameError);
            clearError(profileEmail, profileEmailError);

            if (!nameVal) {
                setError(profileName, profileNameError, 'Please enter your full name!');
                hasErr = true;
            }

            if (!emailVal) {
                setError(profileEmail, profileEmailError, 'Please enter your email address!');
                hasErr = true;
            } else if (!isValidEmail(emailVal)) {
                setError(profileEmail, profileEmailError, 'Invalid email address format (e.g. name@example.com)!');
                hasErr = true;
            }

            if (hasErr) {
                e.preventDefault();
                var firstInv = updateProfileForm.querySelector('.is-invalid');
                if (firstInv) firstInv.focus();
                return false;
            }
        });
    }

    // 2. Change Password Validation
    var form = document.getElementById('changePasswordForm');
    if (!form) return;

    var oldPass = document.getElementById('oldPassword');
    var newPass = document.getElementById('newPassword');
    var confirmPass = document.getElementById('confirmNewPassword');
    var newPassError = document.getElementById('newPasswordError');
    var confirmPassError = document.getElementById('confirmNewPasswordError');

    if (newPass) {
        newPass.addEventListener('input', function() {
            clearError(newPass, newPassError);
            if (confirmPass && confirmPass.value && confirmPass.value === newPass.value) {
                clearError(confirmPass, confirmPassError);
            }
        });
    }

    if (confirmPass) {
        confirmPass.addEventListener('input', function() {
            clearError(confirmPass, confirmPassError);
        });
    }

    form.addEventListener('submit', function(e) {
        var hasError = false;
        clearError(oldPass);
        clearError(newPass, newPassError);
        clearError(confirmPass, confirmPassError);

        var oldVal = oldPass ? oldPass.value.trim() : '';
        var newVal = newPass ? newPass.value : '';
        var confirmVal = confirmPass ? confirmPass.value : '';

        if (!oldVal) {
            if (oldPass) oldPass.classList.add('is-invalid');
            hasError = true;
        }

        if (!newVal) {
            setError(newPass, newPassError, 'Please enter a new password!');
            hasError = true;
        } else if (newVal.length < 6) {
            setError(newPass, newPassError, 'New password must be at least 6 characters long!');
            hasError = true;
        }

        if (!confirmVal) {
            setError(confirmPass, confirmPassError, 'Please confirm your new password!');
            hasError = true;
        } else if (newVal !== confirmVal) {
            setError(confirmPass, confirmPassError, 'New password and confirm password do not match!');
            hasError = true;
        }

        if (hasError) {
            e.preventDefault();
            var firstInvalid = form.querySelector('.is-invalid');
            if (firstInvalid) firstInvalid.focus();
            return false;
        }
    });
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
