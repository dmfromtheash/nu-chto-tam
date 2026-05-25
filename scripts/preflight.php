<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$configPath = $root . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'config.php';
$exampleConfigPath = $root . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'config.example.php';
$configExists = is_file($configPath);
$exampleConfigExists = is_file($exampleConfigPath);
$config = $configExists
    ? require $configPath
    : ($exampleConfigExists ? require $exampleConfigPath : []);

if (!is_array($config)) {
    $config = [];
}

$envDbPath = getenv('NCHT_DB_PATH');
if (is_string($envDbPath) && trim($envDbPath) !== '') {
    $config['DB_PATH'] = trim($envDbPath);
}

$dbPath = (string) ($config['DB_PATH'] ?? ($root . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'database.sqlite'));
$appEnvRaw = trim((string) ($config['APP_ENV'] ?? 'production'));
$appEnv = strtolower($appEnvRaw);
$isProduction = in_array($appEnv, ['production', 'prod'], true);
$secret = (string) ($config['SESSION_SECRET'] ?? '');
$normalizedSecret = strtolower(trim($secret));
$unsafeSecretValues = [
    '',
    'change-this-local-session-secret',
    'local-development-secret',
    'example-session-secret',
    'default-session-secret',
    'changeme',
    'change-me',
];
$secretLooksUnsafe = in_array($normalizedSecret, $unsafeSecretValues, true)
    || str_contains($normalizedSecret, 'change-this')
    || str_contains($normalizedSecret, 'example')
    || str_contains($normalizedSecret, 'local-session-secret');
$secretDetail = $normalizedSecret === ''
    ? 'empty'
    : 'looks like a default/local/example value; check config.php';
$productionIssues = [];

if (!$configExists) {
    $productionIssues[] = $exampleConfigExists ? 'config.example.php fallback is active' : 'config.php is missing';
}

if ($secretLooksUnsafe) {
    $productionIssues[] = 'SESSION_SECRET is not production-safe';
}

$checks = [];

$add = static function (string $label, bool $ok, string $detail = '', string $severity = 'fail') use (&$checks): void {
    $severity = in_array($severity, ['fail', 'warn'], true) ? $severity : 'fail';
    $checks[] = [$label, $ok, $detail, $severity];
};

$add('PHP 8.1+', PHP_VERSION_ID >= 80100, PHP_VERSION);
$hasPdo = class_exists('PDO') && extension_loaded('pdo');
$drivers = $hasPdo ? PDO::getAvailableDrivers() : [];
$add('PDO extension', $hasPdo, $hasPdo ? 'loaded' : 'missing');
$add('PDO SQLite', in_array('sqlite', $drivers, true), implode(', ', $drivers));
$add(
    'config.php exists',
    $configExists,
    $configExists ? 'ok' : ($exampleConfigExists ? 'using config.example.php fallback' : 'missing; bootstrap defaults may be used'),
    $isProduction ? 'fail' : 'warn'
);
$add('APP_ENV set', $appEnvRaw !== '', $appEnvRaw !== '' ? $appEnvRaw : 'empty', 'warn');
$add('database.sqlite exists', is_file($dbPath), $dbPath);
$add('storage writable', is_writable($root . DIRECTORY_SEPARATOR . 'storage'), 'storage/');
$add('storage/logs writable', is_writable($root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs'), 'storage/logs/');
$add('storage/exports writable', is_writable($root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'exports'), 'storage/exports/');
$add('storage/sessions writable', is_writable($root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'sessions'), 'storage/sessions/');
$add(
    'SESSION_SECRET production-safe',
    !$secretLooksUnsafe,
    $secretLooksUnsafe ? $secretDetail : 'ok',
    $isProduction ? 'fail' : 'warn'
);
$add(
    'production safety gate',
    !$isProduction || $productionIssues === [],
    $isProduction
        ? ($productionIssues === [] ? 'production checks passed' : implode('; ', $productionIssues))
        : 'not production env',
    'fail'
);

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

foreach ($checks as [$label, $ok, $detail, $severity]) {
    if (!$ok && $severity === 'fail') {
        $hasErrors = true;
    }

    $status = $ok ? 'OK' : ($severity === 'warn' ? 'WARN' : 'FAIL');
    echo sprintf("[%s] %s%s\n", $status, $label, $detail !== '' ? ' - ' . $detail : '');
}

exit($hasErrors ? 1 : 0);
