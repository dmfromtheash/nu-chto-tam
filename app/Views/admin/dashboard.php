<?php

declare(strict_types=1);

use App\Core\Security;

$analytics = isset($summary['analytics']) && is_array($summary['analytics']) ? $summary['analytics'] : [];
$today = isset($summary['analytics_today']) && is_array($summary['analytics_today']) ? $summary['analytics_today'] : [];
$all = isset($summary['analytics_all']) && is_array($summary['analytics_all']) ? $summary['analytics_all'] : [];
$kpis = isset($analytics['kpis']) && is_array($analytics['kpis']) ? $analytics['kpis'] : [];
$todayKpis = isset($today['kpis']) && is_array($today['kpis']) ? $today['kpis'] : [];
$allKpis = isset($all['kpis']) && is_array($all['kpis']) ? $all['kpis'] : [];
$changes = isset($analytics['kpi_changes']) && is_array($analytics['kpi_changes']) ? $analytics['kpi_changes'] : [];
$todayChanges = isset($today['kpi_changes']) && is_array($today['kpi_changes']) ? $today['kpi_changes'] : [];
$period = isset($analytics['period']) && is_array($analytics['period']) ? $analytics['period'] : ['key' => '7d', 'label' => '7 дней'];
$periodKey = (string) ($period['key'] ?? '7d');
$topPacks = isset($analytics['top_packs']) && is_array($analytics['top_packs']) ? $analytics['top_packs'] : [];
$topPack = $topPacks[0] ?? null;
$number = static fn (mixed $value): string => number_format((int) $value, 0, '.', ' ');
$changeLabel = static function (array $source, string $key): ?array {
    $change = $source[$key] ?? null;
    return is_array($change) ? $change : null;
};
$periodOptions = [
    'today' => 'Сегодня',
    '7d' => '7 дней',
    '30d' => '30 дней',
    'all' => 'Всё время',
];
$cards = [
    [
        'label' => 'Посетители сегодня',
        'value' => $number($todayKpis['visitors'] ?? 0),
        'note' => 'Те, кто отметился сегодня по visitor cookie.',
        'change' => $changeLabel($todayChanges, 'visitors'),
    ],
    [
        'label' => 'Посетители всего',
        'value' => $number($allKpis['visitors'] ?? 0),
        'note' => 'Все visitor-записи за время работы сайта.',
        'change' => null,
    ],
    [
        'label' => 'Пользователи',
        'value' => $number($allKpis['registered_users'] ?? 0),
        'note' => 'Зарегистрированные аккаунты, включая админов.',
        'change' => null,
    ],
    [
        'label' => 'Открытия паков',
        'value' => $number($kpis['pack_openings'] ?? 0),
        'note' => 'Сколько раз запускали сцену открытия за выбранный период.',
        'change' => $changeLabel($changes, 'pack_openings'),
    ],
    [
        'label' => 'Выпавшие карточки',
        'value' => $number($kpis['card_openings'] ?? 0),
        'note' => 'Отдельные карточки, выданные пользователям.',
        'change' => $changeLabel($changes, 'card_openings'),
    ],
    [
        'label' => 'Сохранённые карточки',
        'value' => $number($kpis['saved_cards'] ?? 0),
        'note' => 'Сколько карточек забрали в коллекцию.',
        'change' => $changeLabel($changes, 'saved_cards'),
    ],
    [
        'label' => 'Гостевые попытки сохранить',
        'value' => $number($kpis['guest_save_attempts'] ?? 0),
        'note' => 'Гости часто пытаются сохранить карточки — это сигнал интереса.',
        'change' => $changeLabel($changes, 'guest_save_attempts'),
    ],
    [
        'label' => 'Регистрации сегодня',
        'value' => $number($todayKpis['registrations'] ?? 0),
        'note' => 'Новые аккаунты за сегодняшний день.',
        'change' => $changeLabel($todayChanges, 'registrations'),
    ],
    [
        'label' => 'Активность сегодня',
        'value' => $number($todayKpis['events'] ?? 0),
        'note' => 'Все события сайта за сегодня.',
        'change' => $changeLabel($todayChanges, 'events'),
    ],
    [
        'label' => 'Самый популярный пак',
        'value' => is_array($topPack) ? (string) $topPack['title'] : 'Пока нет',
        'note' => is_array($topPack)
            ? 'Открытий: ' . $number($topPack['opens'] ?? 0) . ', карточек: ' . $number($topPack['cards'] ?? 0)
            : 'Появится после первых открытий паков.',
        'change' => null,
    ],
];
?>
<header class="admin-page-head admin-hero-panel">
    <div>
        <p class="eyebrow">Админская сводка</p>
        <h1>Что происходит на сайте</h1>
        <p>Короткая панель по посетителям, открытиям, сохранениям и пакетам. Данные берутся из SQLite, без внешних счётчиков.</p>
    </div>
    <form class="period-switch" method="get" action="/admin" aria-label="Период статистики">
        <?php foreach ($periodOptions as $key => $label): ?>
            <button class="<?= $periodKey === $key ? 'is-active' : '' ?>" type="submit" name="period" value="<?= Security::escape($key) ?>">
                <?= Security::escape($label) ?>
            </button>
        <?php endforeach; ?>
    </form>
</header>

<section class="analytics-kpi-grid" aria-label="Ключевые показатели">
    <?php foreach ($cards as $card): ?>
        <?php $change = $card['change']; ?>
        <article class="analytics-kpi-card">
            <span><?= Security::escape((string) $card['label']) ?></span>
            <strong><?= Security::escape((string) $card['value']) ?></strong>
            <p><?= Security::escape((string) $card['note']) ?></p>
            <?php if (is_array($change)): ?>
                <small class="metric-change is-<?= Security::escape((string) ($change['direction'] ?? 'flat')) ?>">
                    <?= Security::escape((string) ($change['label'] ?? 'без изменений')) ?>
                </small>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
</section>

<section class="admin-grid-two analytics-wide-grid">
    <article class="admin-panel analytics-panel analytics-panel--wide">
        <div class="admin-section-head">
            <div>
                <h2>Активность за период</h2>
                <p class="muted-line">Просмотры, открытия, сохранения, регистрации и входы по дням.</p>
            </div>
            <a class="text-button" href="/admin/analytics?period=<?= Security::escape($periodKey) ?>">Открыть аналитику</a>
        </div>
        <div class="chart-box" data-admin-chart="activity">
            <script type="application/json"><?= Security::safeJsonEncode($analytics['activity_by_day'] ?? []) ?></script>
        </div>
    </article>

    <article class="admin-panel analytics-panel">
        <div class="admin-section-head">
            <div>
                <h2>Популярные паки</h2>
                <p class="muted-line">Топ по событиям открытия, рядом — выпавшие карточки и сохранения.</p>
            </div>
        </div>
        <div class="chart-box" data-admin-chart="packs">
            <script type="application/json"><?= Security::safeJsonEncode($topPacks) ?></script>
        </div>
    </article>
</section>

<section class="admin-grid-two">
    <article class="admin-panel analytics-panel">
        <div class="admin-section-head">
            <div>
                <h2>Редкости карточек</h2>
                <p class="muted-line">Распределение выпавших карточек по редкости.</p>
            </div>
        </div>
        <div class="chart-box" data-admin-chart="rarities">
            <script type="application/json"><?= Security::safeJsonEncode($analytics['rarities'] ?? []) ?></script>
        </div>
    </article>

    <article class="admin-panel analytics-panel">
        <div class="admin-section-head">
            <div>
                <h2>Агрегированная воронка</h2>
                <p class="muted-line">Связи между людьми не склеиваются идеально, поэтому это честная сводка за период.</p>
            </div>
        </div>
        <div class="chart-box" data-admin-chart="funnel">
            <script type="application/json"><?= Security::safeJsonEncode($analytics['funnel'] ?? []) ?></script>
        </div>
    </article>
</section>

<section class="admin-panel">
    <div class="admin-section-head">
        <div>
            <h2>Как читать эти цифры</h2>
            <p class="muted-line">Коротко, чтобы не путать паки и карточки.</p>
        </div>
    </div>
    <div class="analytics-note-grid">
        <?php foreach (($analytics['data_notes'] ?? []) as $note): ?>
            <p><?= Security::escape((string) $note) ?></p>
        <?php endforeach; ?>
    </div>
</section>
