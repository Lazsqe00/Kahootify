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
                            <input type="password" name="password" id="loginPassword" class="form-control" required autocomplete="current-password">
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
            <form action="<?= BASE_URL ?>modules/auth/register.php" method="POST">
                <div class="modal-body p-4">
                    <div class="form-group">
                        <label class="font-weight-bold">Full Name</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label class="font-weight-bold">Username</label>
                        <input type="text" name="username" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label class="font-weight-bold">Email Address</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label class="font-weight-bold">Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label class="font-weight-bold">Role</label>
                        <select name="role" class="form-control">
                            <option value="1" selected>Teacher / Host</option>
                            <option value="2">Student</option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-kahoot btn-kahoot-green btn-block mt-3">
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
});
</script>
</body>
</html>
