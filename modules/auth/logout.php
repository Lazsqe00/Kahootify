<?php
/**
 * THÀNH VIÊN 1: Đăng xuất
 */
require_once __DIR__ . '/../../config/config.php';

unset($_SESSION['id_creator']);
unset($_SESSION['nameCreator']);
unset($_SESSION['username']);
unset($_SESSION['email']);
unset($_SESSION['role']);
unset($_SESSION['avatar']);

set_flash_message('success', 'You have been safely logged out!');
redirect('index.php');
