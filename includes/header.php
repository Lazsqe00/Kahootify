<?php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

$pageTitle = $pageTitle ?? 'Kahoot! | Make Learning Awesome!';
$bodyClass = $bodyClass ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title><?= htmlspecialchars($pageTitle) ?></title>

    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/kahoot-theme.css?v=<?= file_exists(__DIR__ . '/../assets/css/kahoot-theme.css') ? filemtime(__DIR__ . '/../assets/css/kahoot-theme.css') : '1' ?>">
</head>
<body class="<?= htmlspecialchars($bodyClass) ?>">
