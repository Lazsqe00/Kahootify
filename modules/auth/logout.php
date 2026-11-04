<?php

require_once __DIR__ . '/../../config/config.php';

unset($_SESSION['id_creator']);
unset($_SESSION['nameCreator']);
unset($_SESSION['username']);
unset($_SESSION['email']);
unset($_SESSION['role']);
unset($_SESSION['avatar']);
unset($_SESSION['flash_message']);

redirect('index.php');
