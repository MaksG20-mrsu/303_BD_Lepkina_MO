<?php
declare(strict_types=1);

const SLOT_STEP_MINUTES = 30;

const WEEKDAY_NAMES = [
    1 => 'Понедельник',
    2 => 'Вторник',
    3 => 'Среда',
    4 => 'Четверг',
    5 => 'Пятница',
    6 => 'Суббота',
    7 => 'Воскресенье',
];

function readIntParam(array $source, string $key): ?int
{
    $value = $source[$key] ?? null;
    if (!is_string($value)) {
        return null;
    }

    $parsed = filter_var($value, FILTER_VALIDATE_INT);

    return $parsed === false ? null : $parsed;
}

function isValidDate(string $value): bool
{
    $date = DateTimeImmutable::createFromFormat('Y-m-d', $value);

    return $date !== false && $date->format('Y-m-d') === $value;
}

function isValidTime(string $value): bool
{
    return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $value) === 1;
}

function fetchServices(PDO $connection): array
{
    $statement = $connection->prepare(
        'SELECT id, name, duration_min, price FROM services ORDER BY name'
    );
    $statement->execute();

    return $statement->fetchAll();
}

function findService(PDO $connection, int $serviceId): ?array
{
    $statement = $connection->prepare(
        'SELECT id, name, duration_min, price FROM services WHERE id = :id'
    );
    $statement->execute([':id' => $serviceId]);
    $service = $statement->fetch();

    return $service === false ? null : $service;
}

/**
 * Мастера, которые сейчас работают (не уволены).
 */
function fetchActiveMasters(PDO $connection): array
{
    $statement = $connection->prepare(
        "SELECT id, last_name, first_name
         FROM masters
         WHERE dismissal_date IS NULL OR dismissal_date >= date('now')
         ORDER BY last_name, first_name"
    );
    $statement->execute();

    return $statement->fetchAll();
}

/**
 * Работающие мастера, оказывающие данную услугу.
 */
function fetchMastersByService(PDO $connection, int $serviceId): array
{
    $statement = $connection->prepare(
        "SELECT m.id, m.last_name, m.first_name
         FROM masters AS m
         JOIN master_services AS ms ON ms.master_id = m.id
         WHERE ms.service_id = :service_id
           AND (m.dismissal_date IS NULL OR m.dismissal_date >= date('now'))
         ORDER BY m.last_name, m.first_name"
    );
    $statement->execute([':service_id' => $serviceId]);

    return $statement->fetchAll();
}

/**
 * Все мастера со списком услуг, которые они выполняют.
 */
function fetchMastersWithServices(PDO $connection): array
{
    $statement = $connection->prepare(
        "SELECT m.id, m.last_name, m.first_name, m.phone, m.hire_date,
                m.dismissal_date, m.percent,
                GROUP_CONCAT(s.name, ', ') AS service_names
         FROM masters AS m
         LEFT JOIN master_services AS ms ON ms.master_id = m.id
         LEFT JOIN services AS s ON s.id = ms.service_id
         GROUP BY m.id
         ORDER BY m.last_name, m.first_name"
    );
    $statement->execute();

    return $statement->fetchAll();
}

function fetchSchedules(PDO $connection): array
{
    $statement = $connection->prepare(
        "SELECT m.last_name || ' ' || m.first_name AS master_name,
                s.weekday, s.start_time, s.end_time
         FROM schedules AS s
         JOIN masters AS m ON m.id = s.master_id
         ORDER BY m.last_name, m.first_name, s.weekday"
    );
    $statement->execute();

    return $statement->fetchAll();
}

/**
 * Свободное время мастера на дату: по его графику, минус уже занятое.
 * Возвращает список вида ['09:00', '09:30', ...].
 */
function findAvailableSlots(PDO $connection, int $masterId, string $date, int $durationMinutes): array
{
    $weekday = (int)(new DateTimeImmutable($date))->format('N');

    $scheduleStatement = $connection->prepare(
        'SELECT start_time, end_time
         FROM schedules
         WHERE master_id = :master_id AND weekday = :weekday'
    );
    $scheduleStatement->execute([':master_id' => $masterId, ':weekday' => $weekday]);
    $schedule = $scheduleStatement->fetch();

    if ($schedule === false) {
        return [];
    }

    $busyStatement = $connection->prepare(
        "SELECT start_time, end_time
         FROM appointments
         WHERE master_id = :master_id
           AND status <> 'cancelled'
           AND substr(start_time, 1, 10) = :day"
    );
    $busyStatement->execute([':master_id' => $masterId, ':day' => $date]);
    $busyIntervals = $busyStatement->fetchAll();

    $workEnd = new DateTimeImmutable($date . ' ' . $schedule['end_time']);
    $now = new DateTimeImmutable('now');
    $current = new DateTimeImmutable($date . ' ' . $schedule['start_time']);
    $slots = [];

    while (true) {
        $slotEnd = $current->modify('+' . $durationMinutes . ' minutes');
        if ($slotEnd > $workEnd) {
            break;
        }

        $startText = $current->format('Y-m-d H:i');
        $endText = $slotEnd->format('Y-m-d H:i');

        $isFree = true;
        foreach ($busyIntervals as $busy) {
            if ($busy['start_time'] < $endText && $busy['end_time'] > $startText) {
                $isFree = false;
                break;
            }
        }

        if ($isFree && $current > $now) {
            $slots[] = $current->format('H:i');
        }

        $current = $current->modify('+' . SLOT_STEP_MINUTES . ' minutes');
    }

    return $slots;
}

/**
 * Сохраняет запись: находит или создаёт клиента и автомобиль, затем запись и выбранную услугу.
 */
function createAppointment(
    PDO $connection,
    int $masterId,
    int $serviceId,
    string $startTime,
    string $endTime,
    string $clientName,
    string $clientPhone,
    string $carPlate,
    string $carModel
): void {
    $connection->beginTransaction();

    try {
        $carStatement = $connection->prepare('SELECT id FROM cars WHERE plate = :plate');
        $carStatement->execute([':plate' => $carPlate]);
        $car = $carStatement->fetch();

        if ($car !== false) {
            $carId = (int)$car['id'];
        } else {
            $clientStatement = $connection->prepare('SELECT id FROM clients WHERE phone = :phone');
            $clientStatement->execute([':phone' => $clientPhone]);
            $client = $clientStatement->fetch();

            if ($client !== false) {
                $clientId = (int)$client['id'];
            } else {
                $insertClient = $connection->prepare(
                    'INSERT INTO clients (full_name, phone) VALUES (:full_name, :phone)'
                );
                $insertClient->execute([':full_name' => $clientName, ':phone' => $clientPhone]);
                $clientId = (int)$connection->lastInsertId();
            }

            $insertCar = $connection->prepare(
                'INSERT INTO cars (client_id, plate, model) VALUES (:client_id, :plate, :model)'
            );
            $insertCar->execute([
                ':client_id' => $clientId,
                ':plate' => $carPlate,
                ':model' => $carModel,
            ]);
            $carId = (int)$connection->lastInsertId();
        }

        $insertAppointment = $connection->prepare(
            'INSERT INTO appointments (master_id, car_id, start_time, end_time)
             VALUES (:master_id, :car_id, :start_time, :end_time)'
        );
        $insertAppointment->execute([
            ':master_id' => $masterId,
            ':car_id' => $carId,
            ':start_time' => $startTime,
            ':end_time' => $endTime,
        ]);
        $appointmentId = (int)$connection->lastInsertId();

        $insertLink = $connection->prepare(
            'INSERT INTO appointment_services (appointment_id, service_id)
             VALUES (:appointment_id, :service_id)'
        );
        $insertLink->execute([':appointment_id' => $appointmentId, ':service_id' => $serviceId]);

        $connection->commit();
    } catch (Throwable $exception) {
        $connection->rollBack();
        throw $exception;
    }
}
