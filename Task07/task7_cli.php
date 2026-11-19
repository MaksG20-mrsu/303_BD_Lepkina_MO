<?php
declare(strict_types=1);

require_once __DIR__ . '/data.php';

mb_internal_encoding('UTF-8');

/**
 * Дополняет строку пробелами до нужной ширины (с учётом кириллицы).
 */
function padText(string $text, int $width, bool $alignRight = false): string
{
    $padding = str_repeat(' ', max(0, $width - mb_strlen($text)));

    return $alignRight ? $padding . $text : $text . $padding;
}

function buildBorder(array $widths, string $left, string $middle, string $right): string
{
    $parts = [];
    foreach ($widths as $width) {
        $parts[] = str_repeat('─', $width + 2);
    }

    return $left . implode($middle, $parts) . $right;
}

function buildRow(array $cells, array $widths, array $rightAligned): string
{
    $parts = [];
    foreach ($cells as $index => $cell) {
        $parts[] = ' ' . padText($cell, $widths[$index], in_array($index, $rightAligned, true)) . ' ';
    }

    return '│' . implode('│', $parts) . '│';
}

function printTable(array $headers, array $rows, array $rightAligned = []): void
{
    $widths = array_map('mb_strlen', $headers);
    foreach ($rows as $row) {
        foreach ($row as $index => $cell) {
            $widths[$index] = max($widths[$index], mb_strlen($cell));
        }
    }

    echo buildBorder($widths, '┌', '┬', '┐'), PHP_EOL;
    echo buildRow($headers, $widths, []), PHP_EOL;
    echo buildBorder($widths, '├', '┼', '┤'), PHP_EOL;
    foreach ($rows as $row) {
        echo buildRow($row, $widths, $rightAligned), PHP_EOL;
    }
    echo buildBorder($widths, '└', '┴', '┘'), PHP_EOL;
}

/**
 * Запрашивает номер мастера, пока не будет введено корректное значение.
 * Пустой ввод означает «все мастера» (возвращает null).
 */
function askMasterId(array $validIds): ?int
{
    while (true) {
        echo 'Введите номер мастера (Enter - все мастера): ';
        $line = fgets(STDIN);

        if ($line === false) {
            return null;
        }

        $input = trim($line);

        if ($input === '') {
            return null;
        }

        if (ctype_digit($input) && in_array((int)$input, $validIds, true)) {
            return (int)$input;
        }

        echo 'Ошибка: мастера с таким номером нет. Повторите ввод.', PHP_EOL;
    }
}

try {
    $connection = createConnection();
    $masters = fetchMasters($connection);

    if (count($masters) === 0) {
        echo 'В базе нет мастеров.', PHP_EOL;
        exit(0);
    }

    echo 'Мастера:', PHP_EOL;
    foreach ($masters as $master) {
        echo '  ', $master['id'], ' - ', $master['last_name'], ' ', $master['first_name'], PHP_EOL;
    }
    echo PHP_EOL;

    $validIds = array_map('intval', array_column($masters, 'id'));
    $selectedMasterId = askMasterId($validIds);

    $works = fetchPerformedWorks($connection, $selectedMasterId);

    echo PHP_EOL;
    if (count($works) === 0) {
        echo 'Оказанных услуг не найдено.', PHP_EOL;
        exit(0);
    }

    $rows = [];
    foreach ($works as $work) {
        $rows[] = [
            (string)$work['master_id'],
            $work['master_name'],
            $work['work_date'],
            $work['service_name'],
            number_format((float)$work['price'], 2, '.', ''),
        ];
    }

    printTable(
        ['№ мастера', 'ФИО', 'Дата работы', 'Услуга', 'Стоимость'],
        $rows,
        [4]
    );
} catch (Throwable $exception) {
    fwrite(STDERR, 'Ошибка: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}