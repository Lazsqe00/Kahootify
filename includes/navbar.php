<?php
/**
 * Thanh điều hướng Navbar chuẩn Kahoot
 */
$isLoggedIn = !empty($_SESSION['id_creator']);
$creatorName = $_SESSION['nameCreator'] ?? 'Creator';
$creatorAvatar = $_SESSION['avatar'] ?? 'default.png';
?>
<nav class="navbar navbar-expand-md navbar-dark navbar-kahoot">
    <div class="container-fluid">
        <!-- Logo Kahoot -->
        <a class="navbar-brand d-flex align-items-center" href="<?= BASE_URL ?>">
            <img src="<?= BASE_URL ?>assets/img/logo.png" alt="Kahoot! Logo" class="mr-2">
        </a>

        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#kahootNavbar">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="kahootNavbar">
            <ul class="navbar-nav ml-auto align-items-center">
                <!-- Nút Nhập mã PIN (Dành cho người chơi) -->
                <li class="nav-item mr-2">
                    <a href="<?= BASE_URL ?>modules/player/join.php" class="nav-link font-weight-bold text-white px-3 py-1 rounded" style="background: rgba(255,255,255,0.15);">
                        <i class="fas fa-gamepad mr-1"></i> Enter PIN
                    </a>
                </li>

                <?php if ($isLoggedIn): ?>
                    <!-- Menu dành cho Creator đã đăng nhập -->
                    <li class="nav-item mr-2">
                        <a href="<?= BASE_URL ?>modules/quiz/list.php" class="nav-link text-light font-weight-bold">
                            <i class="fas fa-th-list mr-1"></i> My Kahoots
                        </a>
                    </li>
                    <li class="nav-item mr-3">
                        <a href="<?= BASE_URL ?>modules/quiz/create.php" class="btn btn-sm btn-success font-weight-bold">
                            <i class="fas fa-plus-circle mr-1"></i> Create New Quiz
                        </a>
                    </li>

                    <!-- Dropdown Profile người dùng -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center text-white" href="#" id="userDropdown" role="button" data-toggle="dropdown">
                            <img src="<?= BASE_URL ?>assets/img/imatges_perfil/default.png" alt="Avatar" class="rounded-circle mr-2 border border-light" style="width: 32px; height: 32px; object-fit: cover;">
                            <span class="font-weight-bold"><?= htmlspecialchars($creatorName) ?></span>
                        </a>
                        <div class="dropdown-menu dropdown-menu-right shadow" aria-labelledby="userDropdown">
                            <a class="dropdown-item" href="<?= BASE_URL ?>modules/auth/dashboard.php">
                                <i class="fas fa-tachometer-alt mr-2 text-primary"></i> Dashboard
                            </a>
                            <a class="dropdown-item" href="<?= BASE_URL ?>modules/auth/profile.php">
                                <i class="fas fa-user-cog mr-2 text-info"></i> Profile Settings
                            </a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item text-danger font-weight-bold" href="<?= BASE_URL ?>modules/auth/logout.php">
                                <i class="fas fa-sign-out-alt mr-2"></i> Log Out
                            </a>
                        </div>
                    </li>
                <?php else: ?>
                    <!-- Menu khi chưa đăng nhập -->
                    <li class="nav-item mr-2">
                        <button type="button" class="btn btn-success nav-pill-btn font-weight-bold" data-toggle="modal" data-target="#signUpModal">
                            Sign Up! - It's free
                        </button>
                    </li>
                    <li class="nav-item">
                        <button type="button" class="btn btn-outline-light nav-pill-btn font-weight-bold" data-toggle="modal" data-target="#loginModal">
                            Log In
                        </button>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<!-- Vùng thông báo Flash message nếu có -->
<?php $flash = get_flash_message(); if ($flash): ?>
    <div class="container mt-3">
        <div class="alert alert-<?= htmlspecialchars($flash['type']) ?> alert-dismissible fade show alert-auto-dismiss shadow-sm" role="alert">
            <?= htmlspecialchars($flash['message']) ?>
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
    </div>
<?php endif; ?>
