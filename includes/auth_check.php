<?php
/**
 * Middleware kiểm tra phiên đăng nhập của Giáo viên / Host
 */
require_once __DIR__ . '/../config/config.php';

if (empty($_SESSION['id_creator'])) {
    set_flash_message('danger', 'Please log in to access this page!');
    redirect('index.php');
}
