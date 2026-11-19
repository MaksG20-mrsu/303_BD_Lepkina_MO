<?php
declare(strict_types=1);

const DATABASE_FILE = __DIR__ . '/service_station.db';

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

/**
 * Все мастера из базы (номер, фамилия, имя), по алфавиту.
 */
function fetchMasters(PDO $connection): array
{
    $statement = $connection->prepare(
        'SELECT id, last_name, first_name
         FROM masters
         ORDER BY last_name, first_name'
    );
    $statement->execute();

    return $statement->fetchAll();
}

/**
 * Оказанные услуги. Если передан номер мастера, то только его.
 * Сортировка: фамилия мастера, затем дата выполнения работы.
 */
function fetchPerformedWorks(PDO $connection, ?int $masterId): array
{
    $sql = "SELECT m.id AS master_id,
                   m.last_name || ' ' || m.first_name AS master_name,
                   w.work_date,
                   s.name AS service_name,
                   w.price
            FROM works AS w
            JOIN masters AS m ON m.id = w.master_id
            JOIN services AS s ON s.id = w.service_id";

    if ($masterId !== null) {
        $sql .= ' WHERE m.id = :master_id';
    }

    $sql .= ' ORDER BY m.last_name, w.work_date, w.id';

    $statement = $connection->prepare($sql);

    if ($masterId !== null) {
        $statement->bindValue(':master_id', $masterId, PDO::PARAM_INT);
    }

    $statement->execute();

    return $statement->fetchAll();
}