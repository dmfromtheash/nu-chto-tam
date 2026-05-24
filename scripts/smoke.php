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

if (!is_file($dbPath)) {
    fwrite(STDERR, "database.sqlite not found. Run: php database/seed.php --fresh\n");
    exit(1);
}

if (!class_exists('PDO') || !in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    fwrite(STDERR, "PDO SQLite is not available.\n");
    exit(1);
}

try {
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $tables = ['users', 'packs', 'predictions', 'openings', 'saved_cards', 'visitors', 'events'];
    foreach ($tables as $table) {
        $stmt = $pdo->prepare('SELECT name FROM sqlite_master WHERE type = "table" AND name = :name');
        $stmt->execute([':name' => $table]);
        if ($stmt->fetchColumn() === false) {
            throw new RuntimeException('Missing table: ' . $table);
        }
    }

    $packs = (int) $pdo->query('SELECT COUNT(*) FROM packs WHERE is_active = 1')->fetchColumn();
    $predictions = (int) $pdo->query('SELECT COUNT(*) FROM predictions WHERE is_active = 1')->fetchColumn();
    $admins = (int) $pdo->query('SELECT COUNT(*) FROM users WHERE role = "admin"')->fetchColumn();

    echo "Smoke OK\n";
    echo "Active packs: {$packs}\n";
    echo "Active predictions: {$predictions}\n";
    echo "Admin users: {$admins}\n";

    if ($packs < 14 || $predictions < 150 || $admins < 1) {
        exit(1);
    }
} catch (Throwable $exception) {
    fwrite(STDERR, 'Smoke failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
