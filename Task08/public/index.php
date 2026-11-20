<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/database.php';

$pageTitle = 'Станция технического обслуживания';
$links = [
    'master_form.php' => 'Добавить мастера',
    'schedule_form.php' => 'График работы мастеров',
    'appointment_form.php' => 'Предварительная запись к мастеру',
];

require __DIR__ . '/../src/header.php';
?>
    <ul>
        <?php foreach ($links as $url => $title): ?>
            <li><a href="<?= escapeHtml($url) ?>"><?= escapeHtml($title) ?></a></li>
        <?php endforeach; ?>
    </ul>
<?php require __DIR__ . '/../src/footer.php'; ?>
