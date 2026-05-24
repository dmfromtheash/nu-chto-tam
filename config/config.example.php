<?php

declare(strict_types=1);

return [
    'APP_ENV' => 'local',
    'APP_URL' => 'http://localhost:8000',
    'APP_NAME' => 'Ну что там?',
    'DB_PATH' => dirname(__DIR__) . '/database/database.sqlite',
    'SESSION_SECRET' => 'change-this-local-session-secret',
    'ADMIN_EMAIL' => 'admin@example.test',
    'ADMIN_DEFAULT_PASSWORD' => 'change-me-local-only',
    'RATE_LIMIT_ENABLED' => true,
    'DEFAULT_TIMEZONE' => 'UTC',
];
