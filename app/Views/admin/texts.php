<?php

declare(strict_types=1);

use App\Core\Security;

/** @var array<string, list<array<string, string>>> $groups */
$groups = isset($groups) && is_array($groups) ? $groups : [];
$uiTexts = isset($uiTexts) && is_array($uiTexts) ? $uiTexts : [];
$ui = static fn (string $key, string $fallback): string => (string) ($uiTexts[$key] ?? $fallback);
?>
<section class="admin-page-head">
    <div>
        <p class="eyebrow">UI copy</p>
        <h1><?= Security::escape($ui('admin.texts.title', 'Тексты сайта')) ?></h1>
        <p>
            <?= Security::escape($ui('admin.texts.description', 'Здесь редактируются статичные надписи интерфейса. Названия паков и тексты карточек меняются в разделах Packs и Predictions.')) ?>
        </p>
    </div>
</section>

<section class="admin-panel admin-text-editor">
    <?php foreach ($groups as $groupName => $items): ?>
        <section class="admin-text-group" aria-labelledby="text-group-<?= Security::escape(md5((string) $groupName)) ?>">
            <div class="admin-section-head">
                <h2 id="text-group-<?= Security::escape(md5((string) $groupName)) ?>"><?= Security::escape((string) $groupName) ?></h2>
            </div>

            <div class="admin-text-list">
                <?php foreach ($items as $item): ?>
                    <?php
                    $key = (string) ($item['key'] ?? '');
                    $label = (string) ($item['label'] ?? $key);
                    $value = (string) ($item['value'] ?? $item['default'] ?? '');
                    $type = (string) ($item['type'] ?? 'input');
                    ?>
                    <form class="admin-text-row" data-admin-form data-endpoint="/admin/api/texts/update">
                        <input type="hidden" name="key" value="<?= Security::escape($key) ?>">
                        <label>
                            <span><?= Security::escape($label) ?></span>
                            <small><?= Security::escape($key) ?></small>
                            <?php if ($type === 'textarea'): ?>
                                <textarea name="value" rows="3" required><?= Security::escape($value) ?></textarea>
                            <?php else: ?>
                                <input type="text" name="value" value="<?= Security::escape($value) ?>" required>
                            <?php endif; ?>
                        </label>
                        <button class="button" type="submit">Сохранить</button>
                    </form>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endforeach; ?>
</section>
