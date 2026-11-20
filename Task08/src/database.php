<?php
declare(strict_types=1);

date_default_timezone_set('Europe/Moscow');

const DATABASE_FILE = __DIR__ . '/../data/service_station.db';

function createConnection(): PDO
{
    if (!file_exists(DATABASE_FILE)) {
        throw new RuntimeException('Файл базы данных не найден: ' . DATABASE_FILE);
    }

    $connection = new PDO('sqlite:' . DATABASE_FILE);
    $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $connection->exec('PRAGMA foreign_keys = ON');

    return $connection;
}

function escapeHtml(string $text): string
{
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}
