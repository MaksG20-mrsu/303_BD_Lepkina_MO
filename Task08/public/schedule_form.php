<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/database.php';
require_once __DIR__ . '/../src/queries.php';

$connection = createConnection();
$masters = fetchActiveMasters($connection);
$masterIds = array_map('intval', array_column($masters, 'id'));

$errors = [];
$formData = [
    'master_id' => null,
    'weekdays' => [],
    'start_time' => '09:00',
    'end_time' => '18:00',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $masterId = readIntParam($_POST, 'master_id');
    if ($masterId === null || !in_array($masterId, $masterIds, true)) {
        $errors[] = 'Выберите мастера из списка.';
    } else {
        $formData['master_id'] = $masterId;
    }

    $weekdays = [];
    $postedWeekdays = $_POST['weekdays'] ?? [];
    if (is_array($postedWeekdays)) {
        foreach ($postedWeekdays as $postedDay) {
            $day = filter_var($postedDay, FILTER_VALIDATE_INT);
            if ($day !== false && $day >= 1 && $day <= 7) {
                $weekdays[] = $day;
            }
        }
    }
    $formData['weekdays'] = array_values(array_unique($weekdays));
    if (count($formData['weekdays']) === 0) {
        $errors[] = 'Отметьте хотя бы один день недели.';
    }

    $formData['start_time'] = is_string($_POST['start_time'] ?? null) ? $_POST['start_time'] : '';
    $formData['end_time'] = is_string($_POST['end_time'] ?? null) ? $_POST['end_time'] : '';
    if (!isValidTime($formData['start_time']) || !isValidTime($formData['end_time'])) {
        $errors[] = 'Укажите время начала и окончания работы.';
    } elseif ($formData['end_time'] <= $formData['start_time']) {
        $errors[] = 'Время окончания должно быть позже времени начала.';
    }

    if (count($errors) === 0) {
        try {
            $connection->beginTransaction();

            $saveSchedule = $connection->prepare(
                'INSERT INTO schedules (master_id, weekday, start_time, end_time)
                 VALUES (:master_id, :weekday, :start_time, :end_time)
                 ON CONFLICT (master_id, weekday)
                 DO UPDATE SET start_time = excluded.start_time, end_time = excluded.end_time'
            );
            foreach ($formData['weekdays'] as $weekday) {
                $saveSchedule->execute([
                    ':master_id' => $formData['master_id'],
                    ':weekday' => $weekday,
                    ':start_time' => $formData['start_time'],
                    ':end_time' => $formData['end_time'],
                ]);
            }

            $connection->commit();

            header('Location: schedule_form.php?saved=1');
            exit;
        } catch (PDOException $exception) {
            $connection->rollBack();
            $errors[] = 'Не удалось сохранить график: ' . $exception->getMessage();
        }
    }
}

$successMessage = isset($_GET['saved']) ? 'График сохранён.' : null;
$schedules = fetchSchedules($connection);

$pageTitle = 'График работы мастера';
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

    <form method="post" action="schedule_form.php">
        <p><label for="master_id">Мастер</label>
            <select id="master_id" name="master_id" required>
                <option value="">Выберите мастера</option>
                <?php foreach ($masters as $master): ?>
                    <option value="<?= (int)$master['id'] ?>"
                        <?= (int)$master['id'] === $formData['master_id'] ? 'selected' : '' ?>>
                        <?= escapeHtml($master['last_name'] . ' ' . $master['first_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select></p>

        <fieldset>
            <legend>Рабочие дни</legend>
            <?php foreach (WEEKDAY_NAMES as $number => $name): ?>
                <label class="inline">
                    <input type="checkbox" name="weekdays[]" value="<?= $number ?>"
                        <?= in_array($number, $formData['weekdays'], true) ? 'checked' : '' ?>>
                    <?= escapeHtml($name) ?>
                </label>
            <?php endforeach; ?>
        </fieldset>

        <p><label for="start_time">Начало работы</label>
            <input type="time" id="start_time" name="start_time" value="<?= escapeHtml($formData['start_time']) ?>" required></p>
        <p><label for="end_time">Конец работы</label>
            <input type="time" id="end_time" name="end_time" value="<?= escapeHtml($formData['end_time']) ?>" required></p>

        <button type="submit">Сохранить</button>
    </form>

    <h2>Текущие графики</h2>
    <table>
        <thead>
            <tr><th>Мастер</th><th>День недели</th><th>С</th><th>До</th></tr>
        </thead>
        <tbody>
            <?php foreach ($schedules as $schedule): ?>
                <tr>
                    <td><?= escapeHtml($schedule['master_name']) ?></td>
                    <td><?= escapeHtml(WEEKDAY_NAMES[(int)$schedule['weekday']]) ?></td>
                    <td><?= escapeHtml($schedule['start_time']) ?></td>
                    <td><?= escapeHtml($schedule['end_time']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php require __DIR__ . '/../src/footer.php'; ?>
