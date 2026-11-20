<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/database.php';
require_once __DIR__ . '/../src/queries.php';

$connection = createConnection();
$services = fetchServices($connection);
$serviceIds = array_map('intval', array_column($services, 'id'));

$errors = [];
$formData = [
    'last_name' => '',
    'first_name' => '',
    'phone' => '',
    'hire_date' => date('Y-m-d'),
    'percent' => '30',
    'service_ids' => [],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData['last_name'] = trim((string)($_POST['last_name'] ?? ''));
    $formData['first_name'] = trim((string)($_POST['first_name'] ?? ''));
    $formData['phone'] = trim((string)($_POST['phone'] ?? ''));
    $formData['hire_date'] = trim((string)($_POST['hire_date'] ?? ''));
    $formData['percent'] = trim((string)($_POST['percent'] ?? ''));

    $selectedServiceIds = [];
    $postedServiceIds = $_POST['service_ids'] ?? [];
    if (is_array($postedServiceIds)) {
        foreach ($postedServiceIds as $postedId) {
            $parsedId = filter_var($postedId, FILTER_VALIDATE_INT);
            if ($parsedId !== false && in_array($parsedId, $serviceIds, true)) {
                $selectedServiceIds[] = $parsedId;
            }
        }
    }
    $formData['service_ids'] = array_values(array_unique($selectedServiceIds));

    if ($formData['last_name'] === '') {
        $errors[] = 'Укажите фамилию.';
    }
    if ($formData['first_name'] === '') {
        $errors[] = 'Укажите имя.';
    }
    if (!isValidDate($formData['hire_date'])) {
        $errors[] = 'Укажите корректную дату приёма на работу.';
    }
    if (!is_numeric($formData['percent']) || (float)$formData['percent'] < 0 || (float)$formData['percent'] > 100) {
        $errors[] = 'Процент от выручки должен быть числом от 0 до 100.';
    }
    if (count($formData['service_ids']) === 0) {
        $errors[] = 'Отметьте хотя бы одну услугу, которую выполняет мастер.';
    }

    if (count($errors) === 0) {
        try {
            $connection->beginTransaction();

            $insertMaster = $connection->prepare(
                'INSERT INTO masters (last_name, first_name, phone, hire_date, percent)
                 VALUES (:last_name, :first_name, :phone, :hire_date, :percent)'
            );
            $insertMaster->execute([
                ':last_name' => $formData['last_name'],
                ':first_name' => $formData['first_name'],
                ':phone' => $formData['phone'] === '' ? null : $formData['phone'],
                ':hire_date' => $formData['hire_date'],
                ':percent' => (float)$formData['percent'],
            ]);
            $masterId = (int)$connection->lastInsertId();

            $insertLink = $connection->prepare(
                'INSERT INTO master_services (master_id, service_id) VALUES (:master_id, :service_id)'
            );
            foreach ($formData['service_ids'] as $serviceId) {
                $insertLink->execute([':master_id' => $masterId, ':service_id' => $serviceId]);
            }

            $connection->commit();

            header('Location: master_form.php?saved=1');
            exit;
        } catch (PDOException $exception) {
            $connection->rollBack();
            $errors[] = 'Не удалось сохранить мастера: ' . $exception->getMessage();
        }
    }
}

$successMessage = isset($_GET['saved']) ? 'Мастер сохранён.' : null;
$masters = fetchMastersWithServices($connection);

$pageTitle = 'Новый мастер';
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

    <form method="post" action="master_form.php">
        <p><label for="last_name">Фамилия</label>
            <input type="text" id="last_name" name="last_name" value="<?= escapeHtml($formData['last_name']) ?>" required></p>
        <p><label for="first_name">Имя</label>
            <input type="text" id="first_name" name="first_name" value="<?= escapeHtml($formData['first_name']) ?>" required></p>
        <p><label for="phone">Телефон</label>
            <input type="text" id="phone" name="phone" value="<?= escapeHtml($formData['phone']) ?>"></p>
        <p><label for="hire_date">Дата приёма</label>
            <input type="date" id="hire_date" name="hire_date" value="<?= escapeHtml($formData['hire_date']) ?>" required></p>
        <p><label for="percent">Процент от выручки</label>
            <input type="number" id="percent" name="percent" min="0" max="100" step="0.1" value="<?= escapeHtml($formData['percent']) ?>" required></p>

        <fieldset>
            <legend>Услуги, которые выполняет мастер</legend>
            <?php foreach ($services as $service): ?>
                <label class="inline">
                    <input type="checkbox" name="service_ids[]" value="<?= (int)$service['id'] ?>"
                        <?= in_array((int)$service['id'], $formData['service_ids'], true) ? 'checked' : '' ?>>
                    <?= escapeHtml($service['name']) ?>
                </label>
            <?php endforeach; ?>
        </fieldset>

        <button type="submit">Сохранить</button>
    </form>

    <h2>Мастера в базе</h2>
    <table>
        <thead>
            <tr><th>№</th><th>ФИО</th><th>Телефон</th><th>Принят</th><th>Уволен</th><th>Процент</th><th>Услуги</th></tr>
        </thead>
        <tbody>
            <?php foreach ($masters as $master): ?>
                <tr>
                    <td><?= (int)$master['id'] ?></td>
                    <td><?= escapeHtml($master['last_name'] . ' ' . $master['first_name']) ?></td>
                    <td><?= escapeHtml((string)$master['phone']) ?></td>
                    <td><?= escapeHtml($master['hire_date']) ?></td>
                    <td><?= escapeHtml((string)$master['dismissal_date']) ?></td>
                    <td><?= escapeHtml((string)$master['percent']) ?></td>
                    <td><?= escapeHtml((string)$master['service_names']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php require __DIR__ . '/../src/footer.php'; ?>
