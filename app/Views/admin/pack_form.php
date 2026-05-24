<?php

declare(strict_types=1);

use App\Core\Security;

$isEdit = $mode === 'edit' && is_array($pack);
$endpoint = $isEdit ? '/admin/api/packs/update' : '/admin/api/packs/create';
$title = $isEdit ? 'Редактировать пак' : 'Создать пак';
$value = static fn (string $key, mixed $default = ''): string => Security::escape((string) ($pack[$key] ?? $default));
$checked = static fn (string $key, mixed $default = 1): string => (int) ($pack[$key] ?? $default) === 1 ? 'checked' : '';
?>
<header class="admin-page-head">
    <div>
        <p class="eyebrow">Packs</p>
        <h1><?= Security::escape($title) ?></h1>
        <p>Название и описание держим casual. Без “врат” и прочего тяжёлого дыма.</p>
    </div>
    <a class="button is-secondary" href="/admin/packs">Назад к списку</a>
</header>

<section class="admin-panel">
    <form
        class="admin-form"
        data-admin-form
        data-admin-content-form
        data-endpoint="<?= Security::escape($endpoint) ?>"
        <?php if (!$isEdit): ?>
            data-success-redirect="/admin/packs"
        <?php endif; ?>
    >
        <?php if ($isEdit): ?>
            <input type="hidden" name="id" value="<?= (int) $pack['id'] ?>">
        <?php endif; ?>

        <div class="admin-form-grid">
            <label>
                <span>Slug</span>
                <input type="text" name="slug" value="<?= $value('slug') ?>" placeholder="normal-advice" required>
            </label>
            <label>
                <span>Type</span>
                <select name="type" required>
                    <?php foreach ($packTypes as $type): ?>
                        <option value="<?= Security::escape((string) $type) ?>" <?= ($pack['type'] ?? 'daily') === $type ? 'selected' : '' ?>>
                            <?= Security::escape((string) $type) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="span-2">
                <span>Title</span>
                <input type="text" name="title" value="<?= $value('title') ?>" required>
            </label>
            <label class="span-2">
                <span>Description</span>
                <textarea name="description" rows="4"><?= $value('description') ?></textarea>
            </label>
            <label>
                <span>Visual theme</span>
                <input type="text" name="visual_theme" value="<?= $value('visual_theme', 'default') ?>" required>
            </label>
            <label>
                <span>Cards per open</span>
                <input type="number" name="cards_per_open" min="1" max="10" value="<?= $value('cards_per_open', 1) ?>" required>
            </label>
            <label>
                <span>Daily limit</span>
                <input type="number" name="daily_limit" min="1" value="<?= $value('daily_limit') ?>">
            </label>
            <label>
                <span>Sort order</span>
                <input type="number" name="sort_order" value="<?= $value('sort_order', 0) ?>">
            </label>
            <label class="checkbox-line">
                <input type="checkbox" name="is_daily_special" value="1" <?= $checked('is_daily_special', 0) ?>>
                <span>Daily special</span>
            </label>
            <label class="checkbox-line">
                <input type="checkbox" name="is_active" value="1" <?= $checked('is_active', 1) ?>>
                <span>Active</span>
            </label>
        </div>

        <div class="button-row">
            <button class="button" type="submit"><?= $isEdit ? 'Сохранить пак' : 'Создать пак' ?></button>
            <a class="text-button" href="/admin/packs">Отмена</a>
        </div>
        <div class="inline-feedback admin-form-feedback" data-admin-inline-feedback aria-live="polite" hidden></div>
    </form>
</section>
