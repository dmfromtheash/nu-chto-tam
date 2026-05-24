<?php

declare(strict_types=1);

use App\Core\Security;

$analytics = isset($analytics) && is_array($analytics) ? $analytics : [];
$kpis = isset($analytics['kpis']) && is_array($analytics['kpis']) ? $analytics['kpis'] : [];
$changes = isset($analytics['kpi_changes']) && is_array($analytics['kpi_changes']) ? $analytics['kpi_changes'] : [];
$period = isset($analytics['period']) && is_array($analytics['period']) ? $analytics['period'] : ['key' => '7d', 'label' => '7 дней'];
$periodKey = (string) ($period['key'] ?? '7d');
$currentType = (string) ($filters['event_type'] ?? 'all');
$currentUserId = (string) ($filters['user_id'] ?? '');
$currentVisitorId = (string) ($filters['visitor_id'] ?? '');
$currentDate = (string) ($filters['date'] ?? '');
$number = static fn (mixed $value): string => number_format((int) $value, 0, '.', ' ');
$periodOptions = [
    'today' => 'Сегодня',
    '7d' => '7 дней',
    '30d' => '30 дней',
    'all' => 'Всё время',
];
$kpiCards = [
    ['key' => 'visitors', 'label' => 'Посетители', 'note' => 'Visitor cookie, активный в выбранный период.'],
    ['key' => 'pack_openings', 'label' => 'Открытия паков', 'note' => 'Запуски сцены открытия по events.pack_opened.'],
    ['key' => 'card_openings', 'label' => 'Выпавшие карточки', 'note' => 'Строки таблицы openings.'],
    ['key' => 'saved_cards', 'label' => 'Сохранённые карточки', 'note' => 'Карточки, добавленные в коллекцию.'],
    ['key' => 'registrations', 'label' => 'Регистрации', 'note' => 'Новые аккаунты за период.'],
    ['key' => 'guest_save_attempts', 'label' => 'Попытки сохранения гостями', 'note' => 'Гость хотел сохранить, но ещё не вошёл.'],
    ['key' => 'active_users', 'label' => 'Активные пользователи', 'note' => 'Зарегистрированные с событиями за период.'],
];
?>
<header class="admin-page-head admin-hero-panel">
    <div>
        <p class="eyebrow">Локальная аналитика</p>
        <h1>Аналитика сайта</h1>
        <p>Что открывают, где залипают и какие карточки забирают в коллекцию. IP и User-Agent в сыром виде не показываются.</p>
    </div>
</header>

<section class="admin-panel">
    <form class="admin-filter-bar analytics-filter-bar" method="get" action="/admin/analytics">
        <label>
            <span>Период</span>
            <select name="period">
                <?php foreach ($periodOptions as $key => $label): ?>
                    <option value="<?= Security::escape($key) ?>" <?= $periodKey === $key ? 'selected' : '' ?>>
                        <?= Security::escape($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            <span>Тип события</span>
            <select name="event_type">
                <option value="all">Все события</option>
                <?php foreach ($eventTypes as $type): ?>
                    <option value="<?= Security::escape((string) $type) ?>" <?= $currentType === $type ? 'selected' : '' ?>>
                        <?= Security::escape((string) (($analytics['event_labels'][$type] ?? null) ?: $type)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            <span>ID пользователя</span>
            <input type="number" name="user_id" value="<?= Security::escape($currentUserId) ?>" placeholder="например 1">
        </label>
        <label class="span-2">
            <span>ID посетителя</span>
            <input type="text" name="visitor_id" value="<?= Security::escape($currentVisitorId) ?>" placeholder="полный visitor id для поиска">
        </label>
        <label>
            <span>Дата</span>
            <input type="date" name="date" value="<?= Security::escape($currentDate) ?>">
        </label>
        <button class="button is-secondary" type="submit">Показать</button>
        <a class="text-button" href="/admin/analytics">Сбросить</a>
    </form>
</section>

<section class="analytics-kpi-grid" aria-label="Обзор аналитики">
    <?php foreach ($kpiCards as $card): ?>
        <?php $change = isset($changes[$card['key']]) && is_array($changes[$card['key']]) ? $changes[$card['key']] : null; ?>
        <article class="analytics-kpi-card">
            <span><?= Security::escape($card['label']) ?></span>
            <strong><?= $number($kpis[$card['key']] ?? 0) ?></strong>
            <p><?= Security::escape($card['note']) ?></p>
            <?php if ($change !== null): ?>
                <small class="metric-change is-<?= Security::escape((string) ($change['direction'] ?? 'flat')) ?>">
                    <?= Security::escape((string) ($change['label'] ?? 'без изменений')) ?>
                </small>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
</section>

<section class="admin-panel analytics-panel">
    <div class="admin-section-head">
        <div>
            <h2>Активность по дням</h2>
            <p class="muted-line">Динамика действий на сайте: просмотры, открытия, сохранения, регистрации и входы.</p>
        </div>
    </div>
    <div class="chart-box chart-box--large" data-admin-chart="activity">
        <script type="application/json"><?= Security::safeJsonEncode($analytics['activity_by_day'] ?? []) ?></script>
    </div>
    <div class="admin-table-wrap analytics-table">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Дата</th>
                    <th>Просмотры</th>
                    <th>Открытия паков</th>
                    <th>Сохранения</th>
                    <th>Регистрации</th>
                    <th>Входы</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (($analytics['activity_by_day'] ?? []) as $day): ?>
                    <tr>
                        <td><?= Security::escape((string) $day['date']) ?></td>
                        <td><?= (int) ($day['page_view'] ?? 0) ?></td>
                        <td><?= (int) ($day['pack_opened'] ?? 0) ?></td>
                        <td><?= (int) ($day['card_saved'] ?? 0) ?></td>
                        <td><?= (int) ($day['register'] ?? 0) ?></td>
                        <td><?= (int) ($day['login'] ?? 0) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (($analytics['activity_by_day'] ?? []) === []): ?>
                    <tr><td colspan="6">Пока данных мало. Открой пару паков или зайди как гость — графики оживут.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="admin-grid-two">
    <article class="admin-panel analytics-panel">
        <div class="admin-section-head">
            <div>
                <h2>Популярные паки</h2>
                <p class="muted-line">Открытия паков считаются по событиям, карточки — по фактическим выпадениям.</p>
            </div>
        </div>
        <div class="chart-box" data-admin-chart="packs">
            <script type="application/json"><?= Security::safeJsonEncode($analytics['top_packs'] ?? []) ?></script>
        </div>
        <div class="admin-table-wrap analytics-table">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Пак</th>
                        <th>Открытий</th>
                        <th>Карточек</th>
                        <th>Сохранений</th>
                        <th>Доля</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (($analytics['top_packs'] ?? []) as $pack): ?>
                        <tr>
                            <td><?= Security::escape((string) $pack['title']) ?><br><small><?= Security::escape((string) $pack['slug']) ?></small></td>
                            <td><?= (int) $pack['opens'] ?></td>
                            <td><?= (int) $pack['cards'] ?></td>
                            <td><?= (int) $pack['saves'] ?></td>
                            <td><?= Security::escape((string) $pack['share']) ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (($analytics['top_packs'] ?? []) === []): ?>
                        <tr><td colspan="5">За этот период паки ещё не открывали.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </article>

    <article class="admin-panel analytics-panel">
        <div class="admin-section-head">
            <div>
                <h2>Редкости</h2>
                <p class="muted-line">Легендарные, эпические и мифические видны отдельно — без гаданий и фейковых чисел.</p>
            </div>
        </div>
        <div class="chart-box" data-admin-chart="rarities">
            <script type="application/json"><?= Security::safeJsonEncode($analytics['rarities'] ?? []) ?></script>
        </div>
    </article>
</section>

<section class="admin-grid-two">
    <article class="admin-panel analytics-panel">
        <div class="admin-section-head">
            <div>
                <h2>Воронка</h2>
                <p class="muted-line">Агрегированная воронка за выбранный период.</p>
            </div>
        </div>
        <div class="chart-box" data-admin-chart="funnel">
            <script type="application/json"><?= Security::safeJsonEncode($analytics['funnel'] ?? []) ?></script>
        </div>
    </article>

    <article class="admin-panel analytics-panel">
        <div class="admin-section-head">
            <div>
                <h2>Пользователи и гости</h2>
                <p class="muted-line">Кто проявлял активность за период.</p>
            </div>
        </div>
        <?php $usersGuests = isset($analytics['users_guests']) && is_array($analytics['users_guests']) ? $analytics['users_guests'] : []; ?>
        <div class="mini-metric-grid">
            <div><span>Гости</span><strong><?= $number($usersGuests['guest_visitors'] ?? 0) ?></strong></div>
            <div><span>Активные пользователи</span><strong><?= $number($usersGuests['active_registered_users'] ?? 0) ?></strong></div>
            <div><span>Новые регистрации</span><strong><?= $number($usersGuests['new_registrations'] ?? 0) ?></strong></div>
        </div>
        <div class="admin-table-wrap analytics-table">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID пользователя</th>
                        <th>Имя</th>
                        <th>Последняя активность</th>
                        <th>Событий</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (($usersGuests['recent_active_users'] ?? []) as $user): ?>
                        <tr>
                            <td><?= (int) $user['id'] ?></td>
                            <td><?= Security::escape((string) $user['username']) ?></td>
                            <td><?= Security::escape((string) $user['last_event_at']) ?></td>
                            <td><?= (int) $user['events_count'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (($usersGuests['recent_active_users'] ?? []) === []): ?>
                        <tr><td colspan="4">За этот период зарегистрированные пользователи не шумели.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </article>
</section>

<section class="admin-panel">
    <div class="admin-section-head">
        <div>
            <h2>Последние события</h2>
            <p class="muted-line">Компактный список последних 50 событий по текущим фильтрам. Visitor показывается укороченно.</p>
        </div>
    </div>
    <div class="admin-table-wrap analytics-table">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Когда</th>
                    <th>Событие</th>
                    <th>Страница</th>
                    <th>Пользователь</th>
                    <th>Посетитель</th>
                    <th>Сущность</th>
                    <th>Детали</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($events as $event): ?>
                    <tr>
                        <td><?= (int) $event['id'] ?></td>
                        <td><?= Security::escape((string) $event['created_at']) ?></td>
                        <td><code><?= Security::escape((string) ($event['event_label'] ?? $event['event_type'])) ?></code></td>
                        <td><?= Security::escape((string) ($event['page'] ?? '-')) ?></td>
                        <td><?= Security::escape((string) ($event['username'] ?? $event['user_id'] ?? '-')) ?></td>
                        <td><code><?= Security::escape((string) ($event['visitor_id_short'] ?? $event['visitor_id'] ?? '-')) ?></code></td>
                        <td><?= Security::escape((string) ($event['entity_type'] ?? '-')) ?> #<?= Security::escape((string) ($event['entity_id'] ?? '-')) ?></td>
                        <td><span class="payload-summary"><?= Security::escape((string) ($event['payload_summary'] ?? '')) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($events === []): ?>
                    <tr><td colspan="8">За этот период событий не найдено.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="admin-panel">
    <div class="admin-section-head">
        <div>
            <h2>Как читать эти цифры</h2>
            <p class="muted-line">Несколько честных ограничений локальной аналитики.</p>
        </div>
    </div>
    <div class="analytics-note-grid">
        <?php foreach (($analytics['data_notes'] ?? []) as $note): ?>
            <p><?= Security::escape((string) $note) ?></p>
        <?php endforeach; ?>
        <p>Открытия паков — это сколько раз запускали сцену открытия. Выпавшие карточки — отдельные карточки, которые сайт выдал пользователям.</p>
        <p>Если человек очистил cookies, он станет новым visitor. Это нормально для локальной cookie-аналитики.</p>
    </div>
</section>
