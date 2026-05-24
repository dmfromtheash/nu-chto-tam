<?php

declare(strict_types=1);

use App\Core\Security;
?>
<header class="admin-page-head">
    <div>
        <p class="eyebrow">Контент</p>
        <h1>Packs</h1>
        <p>Паки задают настроение и количество карточек. Физически не удаляем, только выключаем.</p>
    </div>
    <a class="button" href="/admin/packs/create">Создать пак</a>
</header>

<section class="admin-panel">
    <form data-admin-reorder>
        <div class="admin-section-head">
            <h2>Список паков</h2>
            <button class="button is-secondary" type="submit">Сохранить порядок</button>
        </div>

        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Sort</th>
                        <th>Slug</th>
                        <th>Title</th>
                        <th>Type</th>
                        <th>Cards</th>
                        <th>Theme</th>
                        <th>Active</th>
                        <th>Predictions</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($packs as $pack): ?>
                        <tr>
                            <td><?= (int) $pack['id'] ?></td>
                            <td>
                                <input class="admin-small-input" type="number" value="<?= (int) $pack['sort_order'] ?>" data-sort-id="<?= (int) $pack['id'] ?>" aria-label="sort order">
                            </td>
                            <td><code><?= Security::escape((string) $pack['slug']) ?></code></td>
                            <td><?= Security::escape((string) $pack['title']) ?></td>
                            <td><?= Security::escape((string) $pack['type']) ?></td>
                            <td><?= (int) $pack['cards_per_open'] ?></td>
                            <td><?= Security::escape((string) $pack['visual_theme']) ?></td>
                            <td>
                                <span class="status-pill <?= (int) $pack['is_active'] === 1 ? 'is-on' : 'is-off' ?>">
                                    <?= (int) $pack['is_active'] === 1 ? 'on' : 'off' ?>
                                </span>
                            </td>
                            <td><?= (int) $pack['predictions_count'] ?></td>
                            <td>
                                <div class="admin-actions">
                                    <a class="text-button" href="/admin/packs/edit?id=<?= (int) $pack['id'] ?>">Edit</a>
                                    <form data-admin-form data-endpoint="/admin/api/packs/toggle" data-success-action="reload" data-confirm="Переключить активность пака?">
                                        <input type="hidden" name="id" value="<?= (int) $pack['id'] ?>">
                                        <button class="text-button" type="submit"><?= (int) $pack['is_active'] === 1 ? 'Off' : 'On' ?></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </form>
</section>
