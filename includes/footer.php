<?php

?>

<div class="modal fade" id="loginModal" tabindex="-1" role="dialog" aria-labelledby="loginModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header text-white" style="background-color: var(--kahoot-purple-dark);">
                <h5 class="modal-title font-weight-bold" id="loginModalTitle">
                    <i class="fas fa-lock mr-2"></i>Log In to Kahoot!
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <form id="loginForm" action="<?= BASE_URL ?>modules/auth/login.php" method="POST">
                <div class="modal-body p-4">
                    <div id="loginErrorAlert" class="alert alert-danger py-2 px-3 small font-weight-bold <?= !empty($_SESSION['login_error']) ? '' : 'd-none' ?>" style="border-radius: 8px;" role="alert">
                        <i class="fas fa-exclamation-circle mr-1"></i>
                        <span id="loginErrorMessage"><?= htmlspecialchars($_SESSION['login_error'] ?? '') ?></span>
                    </div>

                    <div class="form-group">
                        <label class="font-weight-bold">Username or Email</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fas fa-user"></i></span>
                            </div>
                            <input type="text" name="username" id="loginUsername" class="form-control" value="<?= htmlspecialchars($_SESSION['login_username'] ?? '') ?>" required autofocus>
                        </div>
                        <small class="form-text text-muted">Demo accounts: <b>admin</b> or <b>teacher</b></small>
                    </div>

                    <div class="form-group">
                        <label class="font-weight-bold">Password</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fas fa-key"></i></span>
                            </div>
                            <div class="password-wrapper position-relative flex-grow-1">
                                <input type="password" name="password" id="loginPassword" class="form-control" placeholder="Password" required autocomplete="current-password">
                                <button type="button" class="toggle-password btn-toggle-eye" tabindex="-1" title="Show password" aria-label="Show password">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        <small class="form-text text-muted">Default password: <b>123456</b></small>
                    </div>

                    <button type="submit" id="loginSubmitBtn" class="btn btn-kahoot btn-kahoot-purple btn-block mt-3">
                        <i class="fas fa-sign-in-alt mr-2"></i> Log In Now
                    </button>

                    <div class="text-center mt-3">
                        <span class="text-muted">Don't have an account?</span>
                        <a href="#" data-dismiss="modal" data-toggle="modal" data-target="#signUpModal" class="font-weight-bold" style="color: var(--kahoot-purple);">
                            Sign up for free!
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="signUpModal" tabindex="-1" role="dialog" aria-labelledby="signUpModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title font-weight-bold" id="signUpModalTitle">
                    <i class="fas fa-user-plus mr-2"></i>Create a Free Kahoot! Account
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <form id="signUpForm" action="<?= BASE_URL ?>modules/auth/register.php" method="POST" novalidate>
                <div class="modal-body p-4">
                    <div id="signUpErrorAlert" class="alert alert-danger py-2 px-3 small font-weight-bold <?= !empty($_SESSION['signup_error']) ? '' : 'd-none' ?>" style="border-radius: 8px;" role="alert">
                        <i class="fas fa-exclamation-circle mr-1"></i>
                        <span id="signUpErrorMessage"><?= htmlspecialchars($_SESSION['signup_error'] ?? '') ?></span>
                    </div>

                    <div class="form-group">
                        <label class="font-weight-bold">Full Name</label>
                        <input type="text" name="name" id="signUpName" class="form-control <?= !empty($_SESSION['signup_errors']['name']) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars($_SESSION['signup_data']['name'] ?? '') ?>" required>
                        <div class="invalid-feedback font-weight-bold" id="signUpNameError"><?= !empty($_SESSION['signup_errors']['name']) ? '<i class="fas fa-exclamation-circle mr-1"></i> ' . htmlspecialchars($_SESSION['signup_errors']['name']) : '' ?></div>
                    </div>

                    <div class="form-group">
                        <label class="font-weight-bold">Username</label>
                        <input type="text" name="username" id="signUpUsername" class="form-control <?= !empty($_SESSION['signup_errors']['username']) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars($_SESSION['signup_data']['username'] ?? '') ?>" required autocomplete="username">
                        <div class="invalid-feedback font-weight-bold" id="signUpUsernameError"><?= !empty($_SESSION['signup_errors']['username']) ? '<i class="fas fa-exclamation-circle mr-1"></i> ' . htmlspecialchars($_SESSION['signup_errors']['username']) : '' ?></div>
                    </div>

                    <div class="form-group">
                        <label class="font-weight-bold">Email Address</label>
                        <input type="email" name="email" id="signUpEmail" class="form-control <?= !empty($_SESSION['signup_errors']['email']) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars($_SESSION['signup_data']['email'] ?? '') ?>" required autocomplete="email">
                        <div class="invalid-feedback font-weight-bold" id="signUpEmailError"><?= !empty($_SESSION['signup_errors']['email']) ? '<i class="fas fa-exclamation-circle mr-1"></i> ' . htmlspecialchars($_SESSION['signup_errors']['email']) : '' ?></div>
                    </div>

                    <div class="form-group">
                        <label class="font-weight-bold">Password</label>
                        <div class="password-wrapper position-relative">
                            <input type="password" name="password" id="signUpPassword" class="form-control <?= !empty($_SESSION['signup_errors']['password']) ? 'is-invalid' : '' ?>" placeholder="Password" required autocomplete="new-password">
                            <button type="button" class="toggle-password btn-toggle-eye" tabindex="-1" title="Show password" aria-label="Show password">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div class="invalid-feedback font-weight-bold <?= !empty($_SESSION['signup_errors']['password']) ? 'd-block' : '' ?>" id="signUpPasswordError"><?= !empty($_SESSION['signup_errors']['password']) ? '<i class="fas fa-exclamation-circle mr-1"></i> ' . htmlspecialchars($_SESSION['signup_errors']['password']) : '' ?></div>
                        <small class="form-text text-muted">Must be at least 6 characters</small>
                    </div>

                    <div class="form-group">
                        <label class="font-weight-bold">Confirm Password</label>
                        <div class="password-wrapper position-relative">
                            <input type="password" name="confirm_password" id="signUpConfirmPassword" class="form-control <?= !empty($_SESSION['signup_errors']['confirm_password']) ? 'is-invalid' : '' ?>" placeholder="Confirm password" required autocomplete="new-password">
                            <button type="button" class="toggle-password btn-toggle-eye" tabindex="-1" title="Show password" aria-label="Show password">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div class="invalid-feedback font-weight-bold <?= !empty($_SESSION['signup_errors']['confirm_password']) ? 'd-block' : '' ?>" id="signUpConfirmPasswordError"><?= !empty($_SESSION['signup_errors']['confirm_password']) ? '<i class="fas fa-exclamation-circle mr-1"></i> ' . htmlspecialchars($_SESSION['signup_errors']['confirm_password']) : '' ?></div>
                    </div>

                    <div class="form-group">
                        <label class="font-weight-bold">Role</label>
                        <select name="role" id="signUpRole" class="form-control">
                            <option value="1" <?= (($_SESSION['signup_data']['role'] ?? 1) == 1) ? 'selected' : '' ?>>Teacher / Host</option>
                            <option value="2" <?= (($_SESSION['signup_data']['role'] ?? 1) == 2) ? 'selected' : '' ?>>Student</option>
                        </select>
                    </div>

                    <button type="submit" id="signUpSubmitBtn" class="btn btn-kahoot btn-kahoot-green btn-block mt-3">
                        <i class="fas fa-check-circle mr-2"></i> Complete Registration
                    </button>

                    <div class="text-center mt-3">
                        <span class="text-muted">Already have an account?</span>
                        <a href="#" data-dismiss="modal" data-toggle="modal" data-target="#loginModal" class="font-weight-bold text-success">
                            Log in now
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
<script src="<?= BASE_URL ?>assets/js/app.js"></script>
<script>
$(document).ready(function() {
    <?php if (!empty($_SESSION['login_error'])): ?>
        $('#loginModal').modal('show');
        $('#loginPassword').val('').focus();
        <?php unset($_SESSION['login_error'], $_SESSION['login_username']); ?>
    <?php endif; ?>

    <?php if (!empty($_SESSION['signup_error'])): ?>
        $('#signUpModal').modal('show');
        <?php unset($_SESSION['signup_error'], $_SESSION['signup_errors'], $_SESSION['signup_data']); ?>
    <?php endif; ?>

    $('#loginForm').on('submit', function(e) {
        e.preventDefault();
        var $form = $(this);
        var $btn = $('#loginSubmitBtn');
        var $errAlert = $('#loginErrorAlert');
        var $errMsg = $('#loginErrorMessage');
        var $pwd = $('#loginPassword');
        var originalBtnHtml = $btn.html();

        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-2"></i> Logging in...');
        $errAlert.addClass('d-none');

        $.ajax({
            url: $form.attr('action'),
            method: 'POST',
            data: $form.serialize() + '&ajax=1',
            dataType: 'json',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).done(function(res) {
            if (res && res.success) {
                window.location.href = res.redirect || '<?= BASE_URL ?>modules/quiz/list.php';
            } else {
                var message = (res && res.message) ? res.message : 'Incorrect password or username!';
                $errMsg.text(message);
                $errAlert.removeClass('d-none');
                $pwd.val('').focus();
                $btn.prop('disabled', false).html(originalBtnHtml);
            }
        }).fail(function(xhr) {
            var msg = 'An error occurred during login. Please try again.';
            if (xhr.responseJSON && xhr.responseJSON.message) {
                msg = xhr.responseJSON.message;
            }
            $errMsg.text(msg);
            $errAlert.removeClass('d-none');
            $pwd.val('').focus();
            $btn.prop('disabled', false).html(originalBtnHtml);
        });
    });

    // Helper to validate email format
    function isValidEmail(email) {
        var re = /^[a-zA-Z0-9.!#$%&'*+/=?^_`{|}~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)+$/;
        return re.test(email);
    }

    function setFieldError($input, $errorElem, message) {
        $input.addClass('is-invalid');
        $errorElem.html('<i class="fas fa-exclamation-circle mr-1"></i> ' + message).show();
    }

    function clearFieldError($input, $errorElem) {
        $input.removeClass('is-invalid');
        $errorElem.text('').hide();
    }

    // Real-time / Blur checking for Username
    $('#signUpUsername').on('input', function() {
        if ($(this).hasClass('is-invalid')) {
            clearFieldError($(this), $('#signUpUsernameError'));
        }
    }).on('blur', function() {
        var $input = $(this);
        var $err = $('#signUpUsernameError');
        var val = $.trim($input.val());
        if (!val) return;

        $.ajax({
            url: '<?= BASE_URL ?>modules/auth/register.php',
            method: 'GET',
            data: { action: 'check_availability', field: 'username', value: val, ajax: 1 },
            dataType: 'json'
        }).done(function(res) {
            if (res && res.valid === false) {
                setFieldError($input, $err, res.message || 'Username is already in use!');
            } else {
                clearFieldError($input, $err);
            }
        });
    });

    // Real-time / Blur checking for Email
    $('#signUpEmail').on('input', function() {
        var $input = $(this);
        var $err = $('#signUpEmailError');
        var val = $.trim($input.val());
        if ($input.hasClass('is-invalid') && isValidEmail(val)) {
            clearFieldError($input, $err);
        }
    }).on('blur', function() {
        var $input = $(this);
        var $err = $('#signUpEmailError');
        var val = $.trim($input.val());
        if (!val) return;

        if (!isValidEmail(val)) {
            setFieldError($input, $err, 'Invalid email address format (e.g. name@example.com)!');
            return;
        }

        $.ajax({
            url: '<?= BASE_URL ?>modules/auth/register.php',
            method: 'GET',
            data: { action: 'check_availability', field: 'email', value: val, ajax: 1 },
            dataType: 'json'
        }).done(function(res) {
            if (res && res.valid === false) {
                setFieldError($input, $err, res.message || 'Email is already in use!');
            } else {
                clearFieldError($input, $err);
            }
        });
    });

    $('#signUpPassword').on('input', function() {
        if ($(this).hasClass('is-invalid')) {
            clearFieldError($(this), $('#signUpPasswordError'));
        }
        var confirmVal = $('#signUpConfirmPassword').val();
        if (confirmVal && $('#signUpConfirmPassword').hasClass('is-invalid') && confirmVal === $(this).val()) {
            clearFieldError($('#signUpConfirmPassword'), $('#signUpConfirmPasswordError'));
        }
    });

    $('#signUpConfirmPassword').on('input', function() {
        if ($(this).hasClass('is-invalid')) {
            clearFieldError($(this), $('#signUpConfirmPasswordError'));
        }
    });

    $('#signUpName').on('input', function() {
        if ($(this).hasClass('is-invalid')) {
            clearFieldError($(this), $('#signUpNameError'));
        }
    });

    // Sign Up Form Submit via AJAX
    $('#signUpForm').on('submit', function(e) {
        e.preventDefault();
        var $form = $(this);
        var $btn = $('#signUpSubmitBtn');
        var $errAlert = $('#signUpErrorAlert');
        var $errMsg = $('#signUpErrorMessage');
        var originalBtnHtml = $btn.html();

        var $name = $('#signUpName');
        var $username = $('#signUpUsername');
        var $email = $('#signUpEmail');
        var $password = $('#signUpPassword');
        var $confirmPassword = $('#signUpConfirmPassword');

        var nameVal = $.trim($name.val());
        var usernameVal = $.trim($username.val());
        var emailVal = $.trim($email.val());
        var passwordVal = $password.val();
        var confirmPasswordVal = $confirmPassword.val();

        var hasError = false;

        clearFieldError($name, $('#signUpNameError'));
        clearFieldError($username, $('#signUpUsernameError'));
        clearFieldError($email, $('#signUpEmailError'));
        clearFieldError($password, $('#signUpPasswordError'));
        clearFieldError($confirmPassword, $('#signUpConfirmPasswordError'));
        $errAlert.addClass('d-none');

        if (!nameVal) {
            setFieldError($name, $('#signUpNameError'), 'Please enter your full name!');
            hasError = true;
        }

        if (!usernameVal) {
            setFieldError($username, $('#signUpUsernameError'), 'Please enter a username!');
            hasError = true;
        }

        if (!emailVal) {
            setFieldError($email, $('#signUpEmailError'), 'Please enter your email address!');
            hasError = true;
        } else if (!isValidEmail(emailVal)) {
            setFieldError($email, $('#signUpEmailError'), 'Invalid email address format (e.g. name@example.com)!');
            hasError = true;
        }

        if (!passwordVal) {
            setFieldError($password, $('#signUpPasswordError'), 'Please enter a password!');
            hasError = true;
        } else if (passwordVal.length < 6) {
            setFieldError($password, $('#signUpPasswordError'), 'Password must be at least 6 characters long!');
            hasError = true;
        }

        if (!confirmPasswordVal) {
            setFieldError($confirmPassword, $('#signUpConfirmPasswordError'), 'Please confirm your password!');
            hasError = true;
        } else if (confirmPasswordVal !== passwordVal) {
            setFieldError($confirmPassword, $('#signUpConfirmPasswordError'), 'Passwords do not match!');
            hasError = true;
        }

        if (hasError) {
            $errMsg.text('Please check the registration details below.');
            $errAlert.removeClass('d-none');
            $form.find('.is-invalid').first().focus();
            return;
        }

        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-2"></i> Creating account...');

        $.ajax({
            url: $form.attr('action'),
            method: 'POST',
            data: $form.serialize() + '&ajax=1',
            dataType: 'json',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).done(function(res) {
            if (res && res.success) {
                window.location.href = res.redirect || '<?= BASE_URL ?>modules/quiz/list.php';
            } else {
                var message = (res && res.message) ? res.message : 'Registration failed. Please check your inputs.';
                $errMsg.text(message);
                $errAlert.removeClass('d-none');

                if (res && res.errors) {
                    if (res.errors.name) setFieldError($name, $('#signUpNameError'), res.errors.name);
                    if (res.errors.username) setFieldError($username, $('#signUpUsernameError'), res.errors.username);
                    if (res.errors.email) setFieldError($email, $('#signUpEmailError'), res.errors.email);
                    if (res.errors.password) setFieldError($password, $('#signUpPasswordError'), res.errors.password);
                    if (res.errors.confirm_password) setFieldError($confirmPassword, $('#signUpConfirmPasswordError'), res.errors.confirm_password);
                }

                $form.find('.is-invalid').first().focus();
                $btn.prop('disabled', false).html(originalBtnHtml);
            }
        }).fail(function(xhr) {
            var msg = 'An error occurred during registration. Please try again.';
            if (xhr.responseJSON && xhr.responseJSON.message) {
                msg = xhr.responseJSON.message;
            }
            $errMsg.text(msg);
            $errAlert.removeClass('d-none');

            if (xhr.responseJSON && xhr.responseJSON.errors) {
                var errs = xhr.responseJSON.errors;
                if (errs.name) setFieldError($name, $('#signUpNameError'), errs.name);
                if (errs.username) setFieldError($username, $('#signUpUsernameError'), errs.username);
                if (errs.email) setFieldError($email, $('#signUpEmailError'), errs.email);
                if (errs.password) setFieldError($password, $('#signUpPasswordError'), errs.password);
                if (errs.confirm_password) setFieldError($confirmPassword, $('#signUpConfirmPasswordError'), errs.confirm_password);
            }

            $form.find('.is-invalid').first().focus();
            $btn.prop('disabled', false).html(originalBtnHtml);
        });
    });

    $('#signUpModal').on('hidden.bs.modal', function() {
        $('#signUpErrorAlert').addClass('d-none');
        clearFieldError($('#signUpName'), $('#signUpNameError'));
        clearFieldError($('#signUpUsername'), $('#signUpUsernameError'));
        clearFieldError($('#signUpEmail'), $('#signUpEmailError'));
        clearFieldError($('#signUpPassword'), $('#signUpPasswordError'));
        clearFieldError($('#signUpConfirmPassword'), $('#signUpConfirmPasswordError'));
        $('#signUpModal input[type="text"]').filter('#signUpPassword, #signUpConfirmPassword').attr('type', 'password');
        $('#signUpModal .toggle-password i').removeClass('fa-eye-slash').addClass('fa-eye');
    });

    $('#loginModal').on('hidden.bs.modal', function() {
        if ($('#loginPassword').attr('type') === 'text') {
            $('#loginPassword').attr('type', 'password');
            $('#loginModal .toggle-password i').removeClass('fa-eye-slash').addClass('fa-eye');
        }
    });

    // Global Password Visibility Toggle
    $(document).on('click', '.toggle-password', function(e) {
        e.preventDefault();
        var targetSelector = $(this).data('target');
        var $input = targetSelector ? $(targetSelector) : $(this).closest('.password-wrapper, .input-group').find('input');
        if ($input.length === 0) return;

        var $icon = $(this).find('i');
        if ($input.attr('type') === 'password') {
            $input.attr('type', 'text');
            $icon.removeClass('fa-eye').addClass('fa-eye-slash');
            $(this).attr('title', 'Hide password').attr('aria-label', 'Hide password');
        } else {
            $input.attr('type', 'password');
            $icon.removeClass('fa-eye-slash').addClass('fa-eye');
            $(this).attr('title', 'Show password').attr('aria-label', 'Show password');
        }
    });
});
</script>
</body>
</html>