<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__, 2));
define('APP_PATH', BASE_PATH . DIRECTORY_SEPARATOR . 'app');
define('CONFIG_PATH', BASE_PATH . DIRECTORY_SEPARATOR . 'config');

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';

    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file = APP_PATH . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';

    if (is_file($file)) {
        require $file;
    }
});

require APP_PATH . DIRECTORY_SEPARATOR . 'Core' . DIRECTORY_SEPARATOR . 'helpers.php';

$defaultConfig = [
    'APP_ENV' => 'production',
    'APP_URL' => '',
    'APP_NAME' => 'Ну что там?',
    'DB_PATH' => BASE_PATH . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'database.sqlite',
    'SESSION_SECRET' => '',
    'ADMIN_EMAIL' => '',
    'ADMIN_DEFAULT_PASSWORD' => '',
    'RATE_LIMIT_ENABLED' => true,
    'DEFAULT_TIMEZONE' => 'UTC',
];

$configFile = CONFIG_PATH . DIRECTORY_SEPARATOR . 'config.php';
$exampleConfigFile = CONFIG_PATH . DIRECTORY_SEPARATOR . 'config.example.php';
$loadedConfig = [];

if (is_file($configFile)) {
    $loadedConfig = require $configFile;
} elseif (is_file($exampleConfigFile)) {
    $loadedConfig = require $exampleConfigFile;
}

if (!is_array($loadedConfig)) {
    $loadedConfig = [];
}

$config = array_merge($defaultConfig, $loadedConfig);
$appEnv = (string) ($config['APP_ENV'] ?? 'production');
$isDebug = in_array($appEnv, ['local', 'development', 'dev', 'testing'], true);

date_default_timezone_set((string) ($config['DEFAULT_TIMEZONE'] ?? 'UTC'));

ini_set('display_errors', $isDebug ? '1' : '0');
ini_set('display_startup_errors', $isDebug ? '1' : '0');
ini_set('log_errors', '1');
error_reporting($isDebug ? E_ALL : (E_ALL & ~E_DEPRECATED & ~E_STRICT));

set_exception_handler(static function (Throwable $exception) use ($isDebug): void {
    http_response_code(500);

    if ($isDebug) {
        echo '<pre>' . htmlspecialchars((string) $exception, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</pre>';
        return;
    }

    error_log((string) $exception);
    echo 'Application error.';
});

\App\Core\Session::start($config);

return $config;
