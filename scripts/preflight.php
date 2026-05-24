<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$configPath = $root . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'config.php';
$exampleConfigPath = $root . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'config.example.php';
$config = is_file($configPath)
    ? require $configPath
    : (is_file($exampleConfigPath) ? require $exampleConfigPath : []);

if (!is_array($config)) {
    $config = [];
}

$dbPath = (string) ($config['DB_PATH'] ?? ($root . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'database.sqlite'));
$checks = [];

$add = static function (string $label, bool $ok, string $detail = '') use (&$checks): void {
    $checks[] = [$label, $ok, $detail];
};

$add('PHP 8.1+', PHP_VERSION_ID >= 80100, PHP_VERSION);
$hasPdo = class_exists('PDO') && extension_loaded('pdo');
$drivers = $hasPdo ? PDO::getAvailableDrivers() : [];
$add('PDO extension', $hasPdo, $hasPdo ? 'loaded' : 'missing');
$add('PDO SQLite', in_array('sqlite', $drivers, true), implode(', ', $drivers));
$add('config.php exists', is_file($configPath), is_file($configPath) ? 'ok' : 'using config.example.php fallback');
$add('database.sqlite exists', is_file($dbPath), $dbPath);
$add('storage writable', is_writable($root . DIRECTORY_SEPARATOR . 'storage'), 'storage/');
$add('storage/logs writable', is_writable($root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs'), 'storage/logs/');
$add('storage/exports writable', is_writable($root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'exports'), 'storage/exports/');
$add('storage/sessions writable', is_writable($root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'sessions'), 'storage/sessions/');

$secret = (string) ($config['SESSION_SECRET'] ?? '');
$add('SESSION_SECRET changed', $secret !== '' && $secret !== 'change-this-local-session-secret', $secret === '' ? 'empty' : 'check config.php');

if (is_file($dbPath) && in_array('sqlite', $drivers, true)) {
    try {
        $pdo = new PDO('sqlite:' . $dbPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $packs = (int) $pdo->query('SELECT COUNT(*) FROM packs')->fetchColumn();
        $predictions = (int) $pdo->query('SELECT COUNT(*) FROM predictions')->fetchColumn();
        $add('packs count >= 14', $packs >= 14, (string) $packs);
        $add('predictions count >= 150', $predictions >= 150, (string) $predictions);
    } catch (Throwable $exception) {
        $add('database readable', false, $exception->getMessage());
    }
}

$hasErrors = false;

foreach ($checks as [$label, $ok, $detail]) {
    if (!$ok && $label !== 'config.php exists' && $label !== 'SESSION_SECRET changed') {
        $hasErrors = true;
    }

    $status = $ok ? 'OK' : (($label === 'config.php exists' || $label === 'SESSION_SECRET changed') ? 'WARN' : 'FAIL');
    echo sprintf("[%s] %s%s\n", $status, $label, $detail !== '' ? ' - ' . $detail : '');
}

exit($hasErrors ? 1 : 0);
