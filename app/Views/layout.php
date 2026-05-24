<?php

declare(strict_types=1);

use App\Core\Security;

$pageTitle = isset($title) ? (string) $title : (string) config_value('APP_NAME', 'Ну что там?');
$pageStyles = isset($pageStyles) && is_array($pageStyles) ? $pageStyles : [];
$pageScripts = isset($pageScripts) && is_array($pageScripts) ? $pageScripts : [];
$uiTexts = isset($uiTexts) && is_array($uiTexts) ? $uiTexts : [];
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="dark">
    <title><?= Security::escape($pageTitle) ?></title>
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'%3E%3Crect width='64' height='64' rx='16' fill='%2314122f'/%3E%3Ccircle cx='32' cy='32' r='20' fill='%238ee7e0'/%3E%3Ctext x='32' y='42' text-anchor='middle' font-size='34' font-family='Arial' font-weight='700' fill='%23121122'%3E?%3C/text%3E%3C/svg%3E">
    <link rel="stylesheet" href="<?= Security::escape(asset_url('css/base.css')) ?>">
    <?php foreach ($pageStyles as $style): ?>
        <?php if (is_string($style) && $style !== ''): ?>
            <link rel="stylesheet" href="<?= Security::escape(asset_url($style)) ?>">
        <?php endif; ?>
    <?php endforeach; ?>
    <script>
        window.NCHT_TEXTS = <?= Security::safeJsonEncode($uiTexts) ?>;
    </script>
    <script type="module" src="<?= Security::escape(asset_url('js/app.js')) ?>"></script>
    <?php foreach ($pageScripts as $script): ?>
        <?php if (is_string($script) && $script !== ''): ?>
            <script type="module" src="<?= Security::escape(asset_url($script)) ?>"></script>
        <?php endif; ?>
    <?php endforeach; ?>
</head>
<body>
    <?= $content ?>
</body>
</html>
