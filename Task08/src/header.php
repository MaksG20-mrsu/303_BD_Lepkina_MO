<?php
/** @var string $pageTitle */
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= escapeHtml($pageTitle) ?></title>
    <style>
        body { font-family: sans-serif; margin: 2rem; max-width: 900px; }
        nav a { margin-right: 1rem; }
        form p { margin: 0.6rem 0; }
        label { display: inline-block; min-width: 150px; }
        label.inline { min-width: 0; margin-right: 1rem; }
        fieldset { margin: 1rem 0; }
        table { border-collapse: collapse; margin-top: 1rem; }
        th, td { border: 1px solid #999; padding: 6px 12px; }
        th { background: #eee; }
        .error { color: #b00020; }
        .success { color: #1b7f2a; font-weight: bold; }
    </style>
</head>
<body>
    <nav>
        <a href="index.php">Главная</a>
        <a href="master_form.php">Мастера</a>
        <a href="schedule_form.php">График</a>
        <a href="appointment_form.php">Запись</a>
    </nav>
    <h1><?= escapeHtml($pageTitle) ?></h1>
