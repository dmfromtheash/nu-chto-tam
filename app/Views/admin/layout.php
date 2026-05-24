<?php

declare(strict_types=1);

use App\Core\Security;

$pageTitle = isset($title) ? (string) $title : 'Admin';
$activeNav = isset($activeNav) ? (string) $activeNav : '';
$csrfToken = isset($csrfToken) ? (string) $csrfToken : '';
$currentUser = isset($currentUser) && is_array($currentUser) ? $currentUser : null;
$uiTexts = isset($uiTexts) && is_array($uiTexts) ? $uiTexts : [];
$ui = static fn (string $key, string $fallback): string => (string) ($uiTexts[$key] ?? $fallback);
$navItems = [
    'dashboard' => ['/admin', $ui('admin.nav.dashboard', 'Dashboard')],
    'packs' => ['/admin/packs', $ui('admin.nav.packs', 'Packs')],
    'predictions' => ['/admin/predictions', $ui('admin.nav.predictions', 'Predictions')],
    'users' => ['/admin/users', $ui('admin.nav.users', 'Users')],
    'analytics' => ['/admin/analytics', $ui('admin.nav.analytics', 'Analytics')],
    'texts' => ['/admin/texts', $ui('admin.nav.texts', 'Texts')],
    'logs' => ['/admin/logs', $ui('admin.nav.logs', 'Logs')],
];
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="dark">
    <title><?= Security::escape($pageTitle) ?></title>
    <link rel="stylesheet" href="<?= Security::escape(asset_url('css/base.css')) ?>">
    <link rel="stylesheet" href="<?= Security::escape(asset_url('css/admin.css')) ?>">
    <script>
        window.NCHT_TEXTS = <?= Security::safeJsonEncode($uiTexts) ?>;
    </script>
    <script type="module" src="<?= Security::escape(asset_url('js/admin.js')) ?>"></script>
</head>
<body>
    <div class="admin-shell" data-admin-root data-csrf="<?= Security::escape($csrfToken) ?>">
        <aside class="admin-sidebar" aria-label="Админ-навигация">
            <a class="brand admin-brand" href="/admin">
                <span class="brand-mark" aria-hidden="true">?</span>
                <span>Admin</span>
            </a>
            <nav class="admin-nav">
                <?php foreach ($navItems as $key => [$href, $label]): ?>
                    <a href="<?= Security::escape($href) ?>" class="<?= $activeNav === $key ? 'is-active' : '' ?>">
                        <?= Security::escape($label) ?>
                    </a>
                <?php endforeach; ?>
                <a href="/">На сайт</a>
            </nav>
            <?php if ($currentUser !== null): ?>
                <div class="admin-user">
                    <span><?= Security::escape((string) $currentUser['username']) ?></span>
                    <small><?= Security::escape((string) $currentUser['role']) ?></small>
                </div>
            <?php endif; ?>
        </aside>

        <main class="admin-main">
            <div class="admin-feedback" data-admin-feedback role="status" aria-live="polite" hidden></div>
            <?= $content ?>
        </main>
    </div>
</body>
</html>
