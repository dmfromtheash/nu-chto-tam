<?php

declare(strict_types=1);

use App\Core\Security;

$isEdit = $mode === 'edit' && is_array($prediction);
$endpoint = $isEdit ? '/admin/api/predictions/update' : '/admin/api/predictions/create';
$title = $isEdit ? 'Редактировать карточку' : 'Создать карточку';
$value = static fn (string $key, mixed $default = ''): string => Security::escape((string) ($prediction[$key] ?? $default));
$checked = static fn (string $key, mixed $default = 1): string => (int) ($prediction[$key] ?? $default) === 1 ? 'checked' : '';
?>
<header class="admin-page-head">
    <div>
        <p class="eyebrow">Predictions</p>
        <h1><?= Security::escape($title) ?></h1>
        <p>Коротко, человечно, без давления и без обещаний будущего.</p>
    </div>
    <a class="button is-secondary" href="/admin/predictions">Назад к списку</a>
</header>

<section class="admin-panel">
    <form
        class="admin-form"
        data-admin-form
        data-admin-content-form
        data-endpoint="<?= Security::escape($endpoint) ?>"
        <?php if (!$isEdit): ?>
            data-success-redirect="/admin/predictions"
        <?php endif; ?>
    >
        <?php if ($isEdit): ?>
            <input type="hidden" name="id" value="<?= (int) $prediction['id'] ?>">
        <?php endif; ?>

        <div class="admin-form-grid">
            <label>
                <span>Pack</span>
                <select name="pack_id" required>
                    <?php foreach ($packs as $pack): ?>
                        <option value="<?= (int) $pack['id'] ?>" <?= (int) ($prediction['pack_id'] ?? 0) === (int) $pack['id'] ? 'selected' : '' ?>>
                            <?= Security::escape((string) $pack['title']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span>Rarity</span>
                <select name="rarity" required>
                    <?php foreach ($rarities as $rarity): ?>
                        <option value="<?= Security::escape((string) $rarity) ?>" <?= ($prediction['rarity'] ?? 'common') === $rarity ? 'selected' : '' ?>>
                            <?= Security::escape((string) $rarity) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="span-2">
                <span>Title</span>
                <input type="text" name="title" maxlength="120" value="<?= $value('title') ?>">
            </label>
            <label class="span-2">
                <span>Text</span>
                <textarea name="text" rows="7" maxlength="1000" required><?= $value('text') ?></textarea>
            </label>
            <label>
                <span>Mood tag</span>
                <input type="text" name="mood_tag" maxlength="80" value="<?= $value('mood_tag') ?>">
            </label>
            <label>
                <span>Tone tag</span>
                <input type="text" name="tone_tag" maxlength="80" value="<?= $value('tone_tag') ?>">
            </label>
            <label class="checkbox-line">
                <input type="checkbox" name="is_active" value="1" <?= $checked('is_active', 1) ?>>
                <span>Active</span>
            </label>
        </div>

        <div class="button-row">
            <button class="button" type="submit"><?= $isEdit ? 'Сохранить карточку' : 'Создать карточку' ?></button>
            <a class="text-button" href="/admin/predictions">Отмена</a>
        </div>
        <div class="inline-feedback admin-form-feedback" data-admin-inline-feedback aria-live="polite" hidden></div>
    </form>
</section>
