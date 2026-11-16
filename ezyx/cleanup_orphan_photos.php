<?php
/**
 * Удаляет осиротевшие фото из uploads/.
 * Осиротевшее = файл есть на диске, но нет ссылок в employees.photo.
 * Удаляет только файлы старше N часов (защита от гонок при загрузке).
 *
 * Запуск:
 *   - через cron: php /path/to/cleanup_orphan_photos.php
 *   - через URL:  https://ezyx.ru/cleanup_orphan_photos.php?token=XXX
 */

require_once __DIR__.'/config.php';

$isCli = (php_sapi_name() === 'cli');

if (!$isCli) {
    // Веб-запуск: нужен токен
    $token = $_GET['token'] ?? '';
    if (!CLEANUP_TOKEN || !hash_equals(CLEANUP_TOKEN, $token)) {
        http_response_code(403);
        die('Forbidden');
    }
    header('Content-Type: text/plain; charset=utf-8');
}

$uploadsDir = __DIR__.'/uploads';
$minAgeSec  = 24 * 3600; // удаляем файлы старше 24 часов
$now        = time();

if (!is_dir($uploadsDir)) {
    echo "uploads/ не найден\n";
    exit;
}

// 1. Собираем все используемые пути
$used = [];
$st = $pdo->query("SELECT DISTINCT photo FROM employees WHERE photo IS NOT NULL AND photo<>''");
foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $p) {
    $used[ltrim($p, '/')] = true;
}
echo "Используется в БД: ".count($used)." фото\n";

// 2. Сканируем uploads/
$deleted = 0;
$kept    = 0;
$skipped = 0;

foreach (scandir($uploadsDir) as $file) {
    if ($file === '.' || $file === '..' || $file === '.htaccess') continue;

    $full = $uploadsDir.'/'.$file;
    if (!is_file($full)) continue;

    $rel = 'uploads/'.$file;

    // Используется — не трогаем
    if (isset($used[$rel])) { $kept++; continue; }

    // Слишком свежий — не трогаем (защита от гонок)
    if (($now - filemtime($full)) < $minAgeSec) { $skipped++; continue; }

    // Удаляем
    if (@unlink($full)) {
        $deleted++;
        echo "Удалён: $rel\n";
    }
}

echo "\n--- Итого ---\n";
echo "Удалено:   $deleted\n";
echo "Оставлено: $kept (используются)\n";
echo "Пропущено: $skipped (моложе ".($minAgeSec/3600)." ч)\n";

file_put_contents(__DIR__.'/cleanup.log', date('Y-m-d H:i:s')." deleted=$deleted kept=$kept skipped=$skipped\n", FILE_APPEND);