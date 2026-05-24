<?php

declare(strict_types=1);

use App\Core\Security;

$currentPack = (string) ($filters['pack_id'] ?? 'all');
$currentRarity = (string) ($filters['rarity'] ?? 'all');
$currentActive = (string) ($filters['active'] ?? 'all');
$currentSearch = (string) ($filters['search'] ?? '');
$preview = static function (string $text): string {
    $short = function_exists('mb_substr') ? mb_substr($text, 0, 120, 'UTF-8') : substr($text, 0, 120);
    return $short . (strlen($text) > strlen($short) ? '...' : '');
};
?>
<header class="admin-page-head">
    <div>
        <p class="eyebrow">Контент</p>
        <h1>Predictions</h1>
        <p>Карточки должны звучать дружелюбно, бытово и без серьёзного пророческого лица.</p>
    </div>
    <a class="button" href="/admin/predictions/create">Создать карточку</a>
</header>

<section class="admin-panel">
    <form class="admin-filter-bar" method="get" action="/admin/predictions">
        <label>
            <span>Пак</span>
            <select name="pack_id">
                <option value="all">Все паки</option>
                <?php foreach ($packs as $pack): ?>
                    <option value="<?= (int) $pack['id'] ?>" <?= $currentPack === (string) $pack['id'] ? 'selected' : '' ?>>
                        <?= Security::escape((string) $pack['title']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            <span>Редкость</span>
            <select name="rarity">
                <option value="all">Все</option>
                <?php foreach ($rarities as $rarity): ?>
                    <option value="<?= Security::escape((string) $rarity) ?>" <?= $currentRarity === $rarity ? 'selected' : '' ?>>
                        <?= Security::escape((string) $rarity) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            <span>Активность</span>
            <select name="active">
                <option value="all" <?= $currentActive === 'all' ? 'selected' : '' ?>>Все</option>
                <option value="1" <?= $currentActive === '1' ? 'selected' : '' ?>>Active</option>
                <option value="0" <?= $currentActive === '0' ? 'selected' : '' ?>>Inactive</option>
            </select>
        </label>
        <label class="span-2">
            <span>Поиск</span>
            <input type="search" name="search" value="<?= Security::escape($currentSearch) ?>" placeholder="кусочек текста">
        </label>
        <button class="button is-secondary" type="submit">Фильтровать</button>
    </form>
</section>

<section class="admin-panel">
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Pack</th>
                    <th>Title</th>
                    <th>Preview</th>
                    <th>Rarity</th>
                    <th>Tags</th>
                    <th>Active</th>
                    <th>Updated</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($predictions as $prediction): ?>
                    <tr>
                        <td><?= (int) $prediction['id'] ?></td>
                        <td><?= Security::escape((string) $prediction['pack_title']) ?></td>
                        <td><?= Security::escape((string) ($prediction['title'] ?? '')) ?></td>
                        <td><?= Security::escape($preview((string) $prediction['text'])) ?></td>
                        <td><span class="rarity-badge rarity-<?= Security::escape((string) $prediction['rarity']) ?>"><?= Security::escape((string) $prediction['rarity']) ?></span></td>
                        <td><?= Security::escape(trim((string) ($prediction['mood_tag'] ?? '') . ' ' . (string) ($prediction['tone_tag'] ?? ''))) ?></td>
                        <td>
                            <span class="status-pill <?= (int) $prediction['is_active'] === 1 ? 'is-on' : 'is-off' ?>">
                                <?= (int) $prediction['is_active'] === 1 ? 'on' : 'off' ?>
                            </span>
                        </td>
                        <td><?= Security::escape((string) $prediction['updated_at']) ?></td>
                        <td>
                            <div class="admin-actions">
                                <a class="text-button" href="/admin/predictions/edit?id=<?= (int) $prediction['id'] ?>">Edit</a>
                                <form data-admin-form data-endpoint="/admin/api/predictions/toggle" data-success-action="reload" data-confirm="Переключить активность карточки?">
                                    <input type="hidden" name="id" value="<?= (int) $prediction['id'] ?>">
                                    <button class="text-button" type="submit"><?= (int) $prediction['is_active'] === 1 ? 'Off' : 'On' ?></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($predictions === []): ?>
                    <tr><td colspan="9">Ничего не найдено. Фильтр слишком строго посмотрел на жизнь.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
