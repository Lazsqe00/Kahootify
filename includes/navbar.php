<?php

require_once __DIR__ . '/../config/config.php';

$isLoggedIn = !empty($_SESSION['id_creator']);
$creatorName = $_SESSION['nameCreator'] ?? 'Creator';
$creatorAvatar = $_SESSION['avatar'] ?? 'default.png';
?>
<style>
/* Clean, human-designed navbar buttons */
.navbar-nav .btn-nav-pin {
    background: #ffffff;
    color: #381072 !important;
    border: 1px solid #ffffff;
    border-radius: 6px;
    padding: 6px 14px;
    font-size: 0.88rem;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.12);
    transition: background 0.15s ease, transform 0.15s ease;
    text-decoration: none !important;
}
.navbar-nav .btn-nav-pin:hover {
    background: #f4f0fa;
    color: #280a5c !important;
    transform: translateY(-1px);
}

.navbar-nav .btn-nav-subtle {
    background: rgba(255, 255, 255, 0.12);
    color: #ffffff !important;
    border: 1px solid rgba(255, 255, 255, 0.28);
    border-radius: 6px;
    padding: 6px 14px;
    font-size: 0.88rem;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: background 0.15s ease, border-color 0.15s ease;
    text-decoration: none !important;
}
.navbar-nav .btn-nav-subtle:hover {
    background: rgba(255, 255, 255, 0.22);
    border-color: rgba(255, 255, 255, 0.45);
    color: #ffffff !important;
}

.navbar-nav .btn-nav-user {
    background: rgba(255, 255, 255, 0.1);
    color: #ffffff !important;
    border: 1px solid rgba(255, 255, 255, 0.22);
    border-radius: 20px;
    padding: 4px 12px 4px 5px;
    font-size: 0.88rem;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    transition: background 0.15s ease;
    text-decoration: none !important;
}
.navbar-nav .btn-nav-user:hover {
    background: rgba(255, 255, 255, 0.2);
    color: #ffffff !important;
}

/* Dropdown menu styling */
.navbar-kahoot .dropdown-menu {
    border-radius: 8px;
    border: 1px solid #e2e8f0;
    padding: 6px;
    min-width: 200px;
    margin-top: 8px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.12);
}
.navbar-kahoot .dropdown-item {
    border-radius: 6px;
    padding: 8px 12px;
    font-size: 0.88rem;
    font-weight: 600;
    color: #334155;
    display: flex;
    align-items: center;
    transition: background 0.15s ease;
}
.navbar-kahoot .dropdown-item:hover {
    background: #f1f5f9;
    color: #0f172a;
}
.navbar-kahoot .dropdown-item i {
    width: 20px;
    font-size: 0.9rem;
    margin-right: 8px;
    color: #64748b;
    text-align: center;
}
.navbar-kahoot .dropdown-item.text-danger {
    color: #dc2626 !important;
}
.navbar-kahoot .dropdown-item.text-danger i {
    color: #dc2626 !important;
}
.navbar-kahoot .dropdown-item.text-danger:hover {
    background: #fee2e2;
}
</style>

<nav class="navbar navbar-expand-md navbar-dark navbar-kahoot">
    <div class="container-fluid">

        <a class="navbar-brand d-flex align-items-center" href="<?= BASE_URL ?>">
            <img src="<?= BASE_URL ?>assets/img/logo.png" alt="Kahoot! Logo" class="mr-2">
        </a>

        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#kahootNavbar">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="kahootNavbar">
            <ul class="navbar-nav ml-auto align-items-center" style="gap: 8px;">

                <li class="nav-item">
                    <a href="<?= BASE_URL ?>modules/player/join.php" class="btn-nav-pin">
                        <i class="fas fa-gamepad"></i>
                        <span>Enter PIN</span>
                    </a>
                </li>

                <?php if ($isLoggedIn): ?>

                    <li class="nav-item">
                        <a href="<?= BASE_URL ?>modules/quiz/list.php" class="btn-nav-subtle">
                            <i class="fas fa-layer-group"></i>
                            <span>My Kahoots</span>
                        </a>
                    </li>

                    <li class="nav-item dropdown">
                        <a class="btn-nav-user dropdown-toggle" href="#" id="userDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <img src="<?= BASE_URL ?>assets/img/imatges_perfil/default.png" alt="Avatar" class="rounded-circle mr-2 border border-light" style="width: 26px; height: 26px; object-fit: cover;">
                            <span><?= htmlspecialchars($creatorName) ?></span>
                        </a>
                        <div class="dropdown-menu dropdown-menu-right" aria-labelledby="userDropdown">
                            <a class="dropdown-item" href="<?= BASE_URL ?>modules/quiz/list.php">
                                <i class="fas fa-layer-group"></i>
                                <span>My Kahoots</span>
                            </a>
                            <a class="dropdown-item" href="<?= BASE_URL ?>modules/quiz/saved_questions.php">
                                <i class="fas fa-bookmark"></i>
                                <span>Saved Questions</span>
                            </a>
                            <a class="dropdown-item" href="<?= BASE_URL ?>modules/reports/list.php">
                                <i class="fas fa-chart-bar"></i>
                                <span>Reports</span>
                            </a>
                            <a class="dropdown-item" href="<?= BASE_URL ?>modules/auth/profile.php">
                                <i class="fas fa-user"></i>
                                <span>Profile</span>
                            </a>
                            <div class="dropdown-divider my-1"></div>
                            <a class="dropdown-item text-danger" href="<?= BASE_URL ?>modules/auth/logout.php">
                                <i class="fas fa-sign-out-alt"></i>
                                <span>Log Out</span>
                            </a>
                        </div>
                    </li>
                <?php else: ?>

                    <li class="nav-item">
                        <button type="button" class="btn-nav-subtle" data-toggle="modal" data-target="#signUpModal">
                            <i class="fas fa-user-plus"></i>
                            <span>Sign Up</span>
                        </button>
                    </li>
                    <li class="nav-item">
                        <button type="button" class="btn-nav-subtle" data-toggle="modal" data-target="#loginModal">
                            <i class="fas fa-sign-in-alt"></i>
                            <span>Log In</span>
                        </button>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<?php $flash = get_flash_message(); if ($flash): ?>
    <div class="container mt-3">
        <div class="alert alert-<?= htmlspecialchars($flash['type']) ?> alert-dismissible fade show alert-auto-dismiss shadow-sm" role="alert">
            <?= htmlspecialchars($flash['message']) ?>
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
    </div>
<?php endif; ?>
