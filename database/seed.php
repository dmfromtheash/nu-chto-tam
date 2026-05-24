<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script can only be run from CLI.\n");
    exit(1);
}

$options = getopt('', ['fresh', 'admin-email:', 'admin-password:', 'help']);

if (isset($options['help'])) {
    echo "Usage:\n";
    echo "  php database/seed.php --fresh\n";
    echo "  php database/seed.php --admin-email=admin@example.test --admin-password=local-password\n";
    exit(0);
}

$basePath = dirname(__DIR__);
$config = loadConfig($basePath);
$fresh = array_key_exists('fresh', $options);
$dbPath = resolvePath((string) $config['DB_PATH'], $basePath);
$schemaPath = $basePath . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'schema.sql';
$now = gmdate('c');

if (!is_file($schemaPath)) {
    throw new RuntimeException('schema.sql was not found.');
}

if (!is_dir(dirname($dbPath))) {
    mkdir(dirname($dbPath), 0775, true);
}

if ($fresh && is_file($dbPath)) {
    unlink($dbPath);
}

$pdo = new PDO('sqlite:' . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
$pdo->exec('PRAGMA foreign_keys = ON');
$pdo->exec((string) file_get_contents($schemaPath));

$packs = [
    ['daily', 'Ну что там сегодня?', 'Быстрая карточка на день: что за настроение, куда не спешить и где не драматизировать раньше времени.', 'daily', 'morning-kitchen', 1, 1, 1, 10],
    ['weekly', 'Спойлер недели', 'Три карточки на неделю: старт, середина и финальный взгляд человека, который вроде держится.', 'weekly', 'desk-notes', 3, 1, 0, 20],
    ['monthly', 'Сводка на месяц', 'Пять карточек на месяц — не план жизни, а нормальная сводка, чтобы не лететь совсем вслепую.', 'monthly', 'calendar-stickers', 5, 1, 0, 30],
    ['mood', 'Какой сегодня вайб?', 'Выбери настроение, а рандом сделает вид, что понял, хотя сам только что проснулся.', 'mood', 'soft-chaos', 1, null, 0, 40],
    ['question', 'Спроси у рандома', 'Задай вопрос. Рандом ответит не по делу, но иногда подозрительно вовремя.', 'question', 'sticky-note', 1, null, 0, 50],
    ['choice', 'А или Б? Ну давай посмотрим', 'Для случаев, когда оба варианта выглядят как идея века и ошибка месяца одновременно.', 'choice', 'two-mugs', 1, null, 0, 60],
    ['take-leave', 'Взять с собой / оставить дома', 'Две карточки: что сегодня пригодится, а что можно не таскать за собой как пакет с пакетами.', 'choice', 'backpack', 2, null, 0, 70],
    ['direction', 'Куда вообще грести?', 'Маленький компас дня: к людям, к делу, к себе или подальше от лишнего шума.', 'mood', 'paper-map', 1, null, 0, 80],
    ['action', 'Маленький пинок', 'Одна маленькая штука, которую можно сделать без героизма, пафосной музыки и новой жизни с понедельника.', 'action', 'sneaker', 1, null, 0, 90],
    ['rare', 'Ого, редкая штука', 'Пак для странных, смешных и особенно удачных карточек. Открывать с лёгким прищуром.', 'rare', 'lucky-sock', 1, null, 0, 100],
    ['not-now', 'Не сейчас', 'Для решений, которым нужен не драматичный финал, а нормальный человеческий тормоз.', 'choice', 'pause-button', 1, null, 0, 110],
    ['inner-weather', 'Карта внутренней погоды', 'Небольшая метеосводка внутри головы. Иногда облачно, иногда просто хочется кофе.', 'mood', 'window-rain', 1, null, 0, 120],
    ['normal-advice', 'Совет от нормального человека', 'Совет, который мог бы сказать адекватный человек с кружкой чая и без презентации на 40 слайдов.', 'light', 'clean-cup', 1, null, 0, 130],
    ['send-to-friend', 'Кинь другу', 'Карточки, которые хочется отправить человеку и написать: «вот, это почему-то про тебя».', 'light', 'chat-bubble', 1, null, 0, 140],
];

$packStatement = $pdo->prepare(
    'INSERT INTO packs (
        slug, title, description, type, visual_theme, cards_per_open, daily_limit,
        is_daily_special, is_active, sort_order, created_at, updated_at
    ) VALUES (
        :slug, :title, :description, :type, :visual_theme, :cards_per_open, :daily_limit,
        :is_daily_special, 1, :sort_order, :created_at, :updated_at
    )
    ON CONFLICT(slug) DO UPDATE SET
        title = excluded.title,
        description = excluded.description,
        type = excluded.type,
        visual_theme = excluded.visual_theme,
        cards_per_open = excluded.cards_per_open,
        daily_limit = excluded.daily_limit,
        is_daily_special = excluded.is_daily_special,
        is_active = excluded.is_active,
        sort_order = excluded.sort_order,
        updated_at = excluded.updated_at'
);

foreach ($packs as [$slug, $title, $description, $type, $theme, $cardsPerOpen, $dailyLimit, $isDailySpecial, $sortOrder]) {
    $packStatement->execute([
        ':slug' => $slug,
        ':title' => $title,
        ':description' => $description,
        ':type' => $type,
        ':visual_theme' => $theme,
        ':cards_per_open' => $cardsPerOpen,
        ':daily_limit' => $dailyLimit,
        ':is_daily_special' => $isDailySpecial,
        ':sort_order' => $sortOrder,
        ':created_at' => $now,
        ':updated_at' => $now,
    ]);
}

$packIds = [];
foreach ($pdo->query('SELECT id, slug FROM packs') as $row) {
    $packIds[(string) $row['slug']] = (int) $row['id'];
}

$predictionTexts = require $basePath . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'predictions_content.php';

$predictionStatement = $pdo->prepare(
    'INSERT INTO predictions (
        pack_id, title, text, rarity, mood_tag, tone_tag, is_active, created_at, updated_at
    ) VALUES (
        :pack_id, :title, :text, :rarity, :mood_tag, :tone_tag, 1, :created_at, :updated_at
    )'
);

foreach ($predictionTexts as $slug => $texts) {
    if (!isset($packIds[$slug])) {
        continue;
    }

    $existingCount = (int) fetchValue($pdo, 'SELECT COUNT(*) FROM predictions WHERE pack_id = :pack_id', [
        ':pack_id' => $packIds[$slug],
    ]);

    if ($existingCount > 0) {
        continue;
    }

    foreach (array_values($texts) as $card) {
        $predictionStatement->execute([
            ':pack_id' => $packIds[$slug],
            ':title' => (string) $card['title'],
            ':text' => (string) $card['text'],
            ':rarity' => (string) $card['rarity'],
            ':mood_tag' => (string) $card['mood_tag'],
            ':tone_tag' => (string) $card['tone_tag'],
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);
    }
}

$achievements = [
    ['first-card', 'Первая карточка', 'Открыть первую случайную карточку.', 'spark', 'openings_total', 1],
    ['daily-three', 'Три дня подряд', 'Открыть дневной пак три раза.', 'calendar', 'daily_openings', 3],
    ['week-explorer', 'Смотрел неделю в глаза', 'Открыть недельный пак.', 'route', 'pack_weekly_opened', 1],
    ['collector-ten', 'Папка с карточками', 'Сохранить десять карточек.', 'stack', 'saved_cards_total', 10],
    ['rare-find', 'Ого, выпало', 'Получить карточку редкости rare или выше.', 'gem', 'rarity_rare_plus', 1],
    ['question-seeker', 'Задал рандому вопрос', 'Открыть пак вопроса.', 'question', 'pack_question_opened', 1],
];

$achievementStatement = $pdo->prepare(
    'INSERT INTO achievements (
        slug, title, description, icon, condition_type, condition_value, is_active
    ) VALUES (
        :slug, :title, :description, :icon, :condition_type, :condition_value, 1
    )
    ON CONFLICT(slug) DO UPDATE SET
        title = excluded.title,
        description = excluded.description,
        icon = excluded.icon,
        condition_type = excluded.condition_type,
        condition_value = excluded.condition_value,
        is_active = excluded.is_active'
);

foreach ($achievements as [$slug, $title, $description, $icon, $conditionType, $conditionValue]) {
    $achievementStatement->execute([
        ':slug' => $slug,
        ':title' => $title,
        ':description' => $description,
        ':icon' => $icon,
        ':condition_type' => $conditionType,
        ':condition_value' => $conditionValue,
    ]);
}

$settings = [
    'site_name' => (string) $config['APP_NAME'],
    'site_tagline' => 'Случайные casual-карточки: немного юмора, немного здравого смысла и никаких обещаний от вселенной.',
    'maintenance_mode' => '0',
    'guest_openings_enabled' => '1',
    'rate_limit_enabled' => !empty($config['RATE_LIMIT_ENABLED']) ? '1' : '0',
    'max_saved_cards_per_user' => '200',
    'content_disclaimer' => 'Все карточки являются развлекательным случайным контентом и не являются медицинскими, финансовыми или юридическими рекомендациями.',
];

$settingStatement = $pdo->prepare(
    'INSERT INTO site_settings ("key", value) VALUES (:key, :value)
    ON CONFLICT("key") DO UPDATE SET value = excluded.value'
);

foreach ($settings as $key => $value) {
    $settingStatement->execute([
        ':key' => $key,
        ':value' => $value,
    ]);
}

$adminEmail = trim((string) ($options['admin-email'] ?? $config['ADMIN_EMAIL'] ?? ''));
$adminPassword = (string) ($options['admin-password'] ?? $config['ADMIN_DEFAULT_PASSWORD'] ?? '');
$adminCreated = false;

if ($adminEmail !== '' && $adminPassword !== '') {
    $adminUsername = adminUsernameFromEmail($adminEmail);
    $adminStatement = $pdo->prepare(
        'INSERT INTO users (
            username, email, password_hash, role, avatar_color, created_at, is_blocked
        ) VALUES (
            :username, :email, :password_hash, "admin", :avatar_color, :created_at, 0
        )
        ON CONFLICT(email) DO UPDATE SET
            username = excluded.username,
            password_hash = excluded.password_hash,
            role = "admin",
            avatar_color = excluded.avatar_color,
            is_blocked = 0'
    );

    $adminStatement->execute([
        ':username' => $adminUsername,
        ':email' => $adminEmail,
        ':password_hash' => password_hash($adminPassword, PASSWORD_DEFAULT),
        ':avatar_color' => '#d7b56d',
        ':created_at' => $now,
    ]);

    $adminCreated = true;
}

$packCount = (int) fetchValue($pdo, 'SELECT COUNT(*) FROM packs');
$predictionCount = (int) fetchValue($pdo, 'SELECT COUNT(*) FROM predictions');
$achievementCount = (int) fetchValue($pdo, 'SELECT COUNT(*) FROM achievements');
$settingsCount = (int) fetchValue($pdo, 'SELECT COUNT(*) FROM site_settings');

echo "Seed complete.\n";
echo "Database: {$dbPath}\n";
echo "Fresh reset: " . ($fresh ? 'yes' : 'no') . "\n";
echo "Packs: {$packCount}\n";
echo "Predictions: {$predictionCount}\n";
echo "Achievements: {$achievementCount}\n";
echo "Settings: {$settingsCount}\n";
echo "Admin user: " . ($adminCreated ? $adminEmail : 'skipped') . "\n";

if ($adminCreated && $adminPassword === 'change-me-local-only') {
    echo "Warning: default local admin password is configured. Change it before any non-local use.\n";
}

/**
 * @return array<string, mixed>
 */
function loadConfig(string $basePath): array
{
    $defaults = [
        'APP_ENV' => 'local',
        'APP_URL' => 'http://localhost:8000',
        'APP_NAME' => 'Ну что там?',
        'DB_PATH' => $basePath . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'database.sqlite',
        'SESSION_SECRET' => 'change-this-local-session-secret',
        'ADMIN_EMAIL' => 'admin@example.test',
        'ADMIN_DEFAULT_PASSWORD' => 'change-me-local-only',
        'RATE_LIMIT_ENABLED' => true,
        'DEFAULT_TIMEZONE' => 'UTC',
    ];

    $configPath = $basePath . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'config.php';
    $examplePath = $basePath . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'config.example.php';
    $loaded = [];

    if (is_file($configPath)) {
        $loaded = require $configPath;
    } elseif (is_file($examplePath)) {
        $loaded = require $examplePath;
    }

    if (!is_array($loaded)) {
        $loaded = [];
    }

    return array_merge($defaults, $loaded);
}

function resolvePath(string $path, string $basePath): string
{
    if ($path === '') {
        return $basePath . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'database.sqlite';
    }

    if (preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1 || str_starts_with($path, '/') || str_starts_with($path, '\\')) {
        return $path;
    }

    return $basePath . DIRECTORY_SEPARATOR . ltrim($path, '/\\');
}

/**
 * @param array<string, mixed> $params
 */
function fetchValue(PDO $pdo, string $sql, array $params = []): mixed
{
    $statement = $pdo->prepare($sql);
    $statement->execute($params);

    return $statement->fetchColumn();
}

function adminUsernameFromEmail(string $email): string
{
    $localPart = strstr($email, '@', true);
    $candidate = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $localPart);

    return $candidate !== '' ? $candidate : 'admin';
}
