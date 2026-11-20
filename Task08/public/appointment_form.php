<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/database.php';
require_once __DIR__ . '/../src/queries.php';

$connection = createConnection();
$services = fetchServices($connection);

$input = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;

$selectedService = null;
$masters = [];
$selectedMaster = null;
$selectedDate = '';
$slots = [];

$serviceId = readIntParam($input, 'service_id');
if ($serviceId !== null) {
    $selectedService = findService($connection, $serviceId);
}

if ($selectedService !== null) {
    $masters = fetchMastersByService($connection, (int)$selectedService['id']);

    $masterId = readIntParam($input, 'master_id');
    foreach ($masters as $master) {
        if ($masterId !== null && (int)$master['id'] === $masterId) {
            $selectedMaster = $master;
        }
    }
}

$dateInput = is_string($input['date'] ?? null) ? $input['date'] : '';
if (isValidDate($dateInput) && $dateInput >= date('Y-m-d')) {
    $selectedDate = $dateInput;
}

if ($selectedService !== null && $selectedMaster !== null && $selectedDate !== '') {
    $slots = findAvailableSlots(
        $connection,
        (int)$selectedMaster['id'],
        $selectedDate,
        (int)$selectedService['duration_min']
    );
}

$errors = [];
$clientData = ['name' => '', 'phone' => '', 'plate' => '', 'model' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $clientData['name'] = trim((string)($_POST['client_name'] ?? ''));
    $clientData['phone'] = trim((string)($_POST['client_phone'] ?? ''));
    $clientData['plate'] = trim((string)($_POST['car_plate'] ?? ''));
    $clientData['model'] = trim((string)($_POST['car_model'] ?? ''));
    $slotTime = is_string($_POST['slot'] ?? null) ? $_POST['slot'] : '';

    if ($selectedService === null || $selectedMaster === null || $selectedDate === '') {
        $errors[] = 'Сначала выберите услугу, мастера и дату.';
    } elseif (!in_array($slotTime, $slots, true)) {
        $errors[] = 'Выбранное время недоступно. Выберите время из списка.';
    }
    if ($clientData['name'] === '') {
        $errors[] = 'Укажите ФИО клиента.';
    }
    if ($clientData['phone'] === '') {
        $errors[] = 'Укажите телефон клиента.';
    }
    if ($clientData['plate'] === '') {
        $errors[] = 'Укажите госномер автомобиля.';
    }
    if ($clientData['model'] === '') {
        $errors[] = 'Укажите марку и модель автомобиля.';
    }

    if (count($errors) === 0) {
        $startTime = $selectedDate . ' ' . $slotTime;
        $endTime = (new DateTimeImmutable($startTime))
            ->modify('+' . (int)$selectedService['duration_min'] . ' minutes')
            ->format('Y-m-d H:i');

        try {
            createAppointment(
                $connection,
                (int)$selectedMaster['id'],
                (int)$selectedService['id'],
                $startTime,
                $endTime,
                $clientData['name'],
                $clientData['phone'],
                $clientData['plate'],
                $clientData['model']
            );

            header('Location: appointment_form.php?saved=1');
            exit;
        } catch (PDOException $exception) {
            if (str_contains($exception->getMessage(), 'Мастер занят')) {
                $errors[] = 'Это время уже занято. Выберите другое.';
            } else {
                $errors[] = 'Не удалось сохранить запись: ' . $exception->getMessage();
            }
        }
    }
}

$successMessage = isset($_GET['saved']) ? 'Запись сохранена.' : null;

$pageTitle = 'Предварительная запись к мастеру';
require __DIR__ . '/../src/header.php';
?>
    <?php if ($successMessage !== null): ?>
        <p class="success"><?= escapeHtml($successMessage) ?></p>
    <?php endif; ?>

    <?php if (count($errors) > 0): ?>
        <ul class="error">
            <?php foreach ($errors as $error): ?>
                <li><?= escapeHtml($error) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <form method="get" action="appointment_form.php">
        <p><label for="service_id">1. Услуга</label>
            <select id="service_id" name="service_id" onchange="this.form.submit()" required>
                <option value="">Выберите услугу</option>
                <?php foreach ($services as $service): ?>
                    <option value="<?= (int)$service['id'] ?>"
                        <?= $selectedService !== null && (int)$service['id'] === (int)$selectedService['id'] ? 'selected' : '' ?>>
                        <?= escapeHtml($service['name']) ?> (<?= (int)$service['duration_min'] ?> мин, <?= number_format((float)$service['price'], 2, '.', ' ') ?>)
                    </option>
                <?php endforeach; ?>
            </select></p>

        <p><label for="master_id">2. Мастер</label>
            <select id="master_id" name="master_id" <?= $selectedService === null ? 'disabled' : '' ?>>
                <option value="">Выберите мастера</option>
                <?php foreach ($masters as $master): ?>
                    <option value="<?= (int)$master['id'] ?>"
                        <?= $selectedMaster !== null && (int)$master['id'] === (int)$selectedMaster['id'] ? 'selected' : '' ?>>
                        <?= escapeHtml($master['last_name'] . ' ' . $master['first_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select></p>

        <p><label for="date">3. Дата</label>
            <input type="date" id="date" name="date" min="<?= date('Y-m-d') ?>" value="<?= escapeHtml($selectedDate) ?>"></p>

        <button type="submit">Показать свободное время</button>
    </form>

    <?php if ($selectedService !== null && count($masters) === 0): ?>
        <p class="error">Эту услугу сейчас не оказывает ни один мастер.</p>
    <?php endif; ?>

    <?php if ($selectedService !== null && $selectedMaster !== null && $selectedDate !== ''): ?>
        <?php if (count($slots) === 0): ?>
            <p class="error">На эту дату свободного времени нет: мастер не работает в этот день или всё занято.</p>
        <?php else: ?>
            <h2>4. Время и данные клиента</h2>
            <form method="post" action="appointment_form.php">
                <input type="hidden" name="service_id" value="<?= (int)$selectedService['id'] ?>">
                <input type="hidden" name="master_id" value="<?= (int)$selectedMaster['id'] ?>">
                <input type="hidden" name="date" value="<?= escapeHtml($selectedDate) ?>">

                <p><label for="slot">Свободное время</label>
                    <select id="slot" name="slot" required>
                        <?php foreach ($slots as $slot): ?>
                            <option value="<?= escapeHtml($slot) ?>"><?= escapeHtml($slot) ?></option>
                        <?php endforeach; ?>
                    </select></p>
                <p><label for="client_name">ФИО клиента</label>
                    <input type="text" id="client_name" name="client_name" value="<?= escapeHtml($clientData['name']) ?>" required></p>
                <p><label for="client_phone">Телефон</label>
                    <input type="text" id="client_phone" name="client_phone" value="<?= escapeHtml($clientData['phone']) ?>" required></p>
                <p><label for="car_plate">Госномер</label>
                    <input type="text" id="car_plate" name="car_plate" value="<?= escapeHtml($clientData['plate']) ?>" required></p>
                <p><label for="car_model">Марка и модель</label>
                    <input type="text" id="car_model" name="car_model" value="<?= escapeHtml($clientData['model']) ?>" required></p>

                <button type="submit">Записаться</button>
            </form>
        <?php endif; ?>
    <?php endif; ?>
<?php require __DIR__ . '/../src/footer.php'; ?>
