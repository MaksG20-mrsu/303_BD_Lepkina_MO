<?php
declare(strict_types=1);

require_once __DIR__ . '/data.php';

function escapeHtml(string $text): string
{
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

$connection = createConnection();
$masters = fetchMasters($connection);
$validIds = array_map('intval', array_column($masters, 'id'));

$selectedMasterId = null;
$errorMessage = null;

$rawMasterId = $_GET['master_id'] ?? '';
if (!is_string($rawMasterId)) {
    $errorMessage = 'Некорректный номер мастера.';
} elseif ($rawMasterId !== '') {
    $parsedId = filter_var($rawMasterId, FILTER_VALIDATE_INT);
    if ($parsedId !== false && in_array($parsedId, $validIds, true)) {
        $selectedMasterId = $parsedId;
    } else {
        $errorMessage = 'Мастера с таким номером нет.';
    }
}

$works = fetchPerformedWorks($connection, $selectedMasterId);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Оказанные услуги</title>
    <style>
        body { font-family: sans-serif; margin: 2rem; }
        table { border-collapse: collapse; margin-top: 1rem; }
        th, td { border: 1px solid #999; padding: 6px 12px; }
        th { background: #eee; }
        td.price { text-align: right; }
        .error { color: #b00020; }
    </style>
</head>
<body>
    <h1>Оказанные услуги</h1>

    <form method="get" action="index.php">
        <label for="master_id">Мастер:</label>
        <select name="master_id" id="master_id">
            <option value="">Все мастера</option>
            <?php foreach ($masters as $master): ?>
                <option value="<?= (int)$master['id'] ?>"
                    <?= (int)$master['id'] === $selectedMasterId ? 'selected' : '' ?>>
                    <?= (int)$master['id'] ?> - <?= escapeHtml($master['last_name'] . ' ' . $master['first_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button type="submit">Показать</button>
    </form>

    <?php if ($errorMessage !== null): ?>
        <p class="error"><?= escapeHtml($errorMessage) ?></p>
    <?php endif; ?>

    <?php if (count($works) === 0): ?>
        <p>Оказанных услуг не найдено.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>№ мастера</th>
                    <th>ФИО</th>
                    <th>Дата работы</th>
                    <th>Услуга</th>
                    <th>Стоимость</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($works as $work): ?>
                    <tr>
                        <td><?= (int)$work['master_id'] ?></td>
                        <td><?= escapeHtml($work['master_name']) ?></td>
                        <td><?= escapeHtml($work['work_date']) ?></td>
                        <td><?= escapeHtml($work['service_name']) ?></td>
                        <td class="price"><?= number_format((float)$work['price'], 2, '.', ' ') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</body>
</html>