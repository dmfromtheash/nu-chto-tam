<?php

declare(strict_types=1);

use App\Core\Security;

$currentUser = $currentUser ?? null;
$messages = $messages ?? [];
$errors = $errors ?? [];
$uiTexts = isset($uiTexts) && is_array($uiTexts) ? $uiTexts : [];
$ui = static fn (string $key, string $fallback): string => (string) ($uiTexts[$key] ?? $fallback);
$isAuthenticated = is_array($currentUser);
$isAdmin = $isAuthenticated && (($currentUser['role'] ?? null) === 'admin');
$displayName = $isAuthenticated ? (string) $currentUser['username'] : $ui('common.guest', 'Гость');
?>
<main class="game-home" data-card-app data-authenticated="<?= $isAuthenticated ? 'true' : 'false' ?>">
    <header class="game-topbar" aria-label="Верхняя панель">
        <a class="game-brand" href="/" aria-label="На главную">
            <span class="game-brand__mark" aria-hidden="true">?</span>
            <span><?= Security::escape($ui('common.brand', 'Ну что там?')) ?></span>
        </a>

        <nav class="game-topbar__actions" aria-label="Аккаунт">
            <span class="user-chip"><?= Security::escape($displayName) ?></span>
            <?php if ($isAuthenticated): ?>
                <a class="text-button" href="/cabinet"><?= Security::escape($ui('common.cabinet', 'Кабинет')) ?></a>
                <?php if ($isAdmin): ?>
                    <a class="text-button" href="/admin"><?= Security::escape($ui('common.admin', 'Админка')) ?></a>
                <?php endif; ?>
                <form method="post" action="/logout">
                    <input type="hidden" name="_csrf_token" value="<?= Security::escape($csrfToken) ?>">
                    <button class="text-button" type="submit"><?= Security::escape($ui('common.logout', 'Выйти')) ?></button>
                </form>
            <?php else: ?>
                <a class="text-button" href="/login"><?= Security::escape($ui('common.login', 'Войти')) ?></a>
                <a class="text-button" href="/register"><?= Security::escape($ui('common.register', 'Регистрация')) ?></a>
            <?php endif; ?>
            <label class="motion-toggle game-motion-toggle">
                <input type="checkbox" data-motion-toggle>
                <span><?= Security::escape($ui('common.reduced_motion', 'Меньше анимаций')) ?></span>
            </label>
        </nav>
    </header>

    <section class="game-hero home-hero" aria-labelledby="page-title">
        <p class="eyebrow"><?= Security::escape($ui('home.hero.eyebrow', 'Карточный рандом для обычных дней')) ?></p>
        <h1 id="page-title"><?= Security::escape($ui('home.hero.title', 'Ну что там?')) ?></h1>
        <p class="game-hero__lead"><?= Security::escape($ui('home.hero.short_lead', 'Выбери пак под день, настроение или большой заход — и открой карту без лишней драмы.')) ?></p>
        <div class="home-hero__actions">
            <a class="button" href="#section-start"><?= Security::escape($ui('home.hero.choose_button', 'Выбрать пак')) ?></a>
        </div>
    </section>

    <?php foreach ($messages as $message): ?>
        <p class="notice is-success"><?= Security::escape($message) ?></p>
    <?php endforeach; ?>
    <?php foreach ($errors as $error): ?>
        <p class="notice is-error"><?= Security::escape($error) ?></p>
    <?php endforeach; ?>

    <div class="main-home-layout">
        <aside class="home-pack-nav" aria-labelledby="pack-nav-title">
            <div class="pack-nav__panel">
                <div class="pack-nav__head">
                    <p class="eyebrow"><?= Security::escape($ui('home.pack_nav.eyebrow', 'Навигация')) ?></p>
                    <h2 id="pack-nav-title"><?= Security::escape($ui('home.pack_nav.title', 'Разделы паков')) ?></h2>
                    <p><?= Security::escape($ui('home.pack_nav.note', 'Быстрый переход по витрине.')) ?></p>
                </div>
                <nav class="pack-nav__links" data-pack-nav-links aria-label="<?= Security::escape($ui('home.pack_nav.aria', 'Разделы паков')) ?>">
                    <a class="pack-nav__link is-active" href="#section-start" data-pack-nav-link aria-current="true">
                        <span aria-hidden="true">✦</span>
                        <strong><?= Security::escape($ui('home.featured.title', 'С чего начать')) ?></strong>
                    </a>
                    <a class="pack-nav__link" href="#section-fast" data-pack-nav-link>
                        <span aria-hidden="true">⚡</span>
                        <strong><?= Security::escape($ui('pack.category.quick.title', 'Быстрый старт')) ?></strong>
                    </a>
                    <a class="pack-nav__link" href="#section-periods" data-pack-nav-link>
                        <span aria-hidden="true">◷</span>
                        <strong><?= Security::escape($ui('pack.category.period.title', 'Периоды и сводки')) ?></strong>
                    </a>
                    <a class="pack-nav__link" href="#section-choice" data-pack-nav-link>
                        <span aria-hidden="true">?</span>
                        <strong><?= Security::escape($ui('pack.category.choice.title', 'Вопросы и выбор')) ?></strong>
                    </a>
                    <a class="pack-nav__link" href="#section-rare-friends" data-pack-nav-link>
                        <span aria-hidden="true">◆</span>
                        <strong><?= Security::escape($ui('pack.category.extra.title', 'Редкое и для друзей')) ?></strong>
                    </a>
                </nav>
            </div>
        </aside>

        <div class="home-main-column">
            <section class="featured-packs" id="section-start" data-pack-section aria-labelledby="featured-packs-title">
                <div class="section-heading">
                    <div>
                        <p class="eyebrow"><?= Security::escape($ui('home.featured.eyebrow', 'Витрина')) ?></p>
                        <h2 id="featured-packs-title"><?= Security::escape($ui('home.featured.title', 'С чего начать')) ?></h2>
                        <p class="section-heading__note">Три понятных входа: быстро на день, шире на период и честно про внутренний вайб.</p>
                    </div>
                </div>
                <div class="featured-pack-grid" data-featured-packs aria-live="polite">
                    <p class="empty-state">Подбираю три пака для старта.</p>
                </div>
            </section>

            <section class="pack-collection home-pack-collection" id="packs" aria-labelledby="packs-title">
                <div class="section-heading">
                    <div>
                        <p class="eyebrow"><?= Security::escape($ui('home.packs.eyebrow', 'Коллекция паков')) ?></p>
                        <h2 id="packs-title"><?= Security::escape($ui('home.packs.title', 'Выбери бустер для открытия')) ?></h2>
                        <p class="section-heading__note">Остальные сценарии ниже: короткие, большие, вопросные и для друзей.</p>
                    </div>
                    <div class="section-actions">
                        <button class="text-button" type="button" data-refresh-packs><?= Security::escape($ui('common.refresh', 'Обновить')) ?></button>
                    </div>
                </div>
                <div class="booster-rail-shell">
                    <div class="booster-rail booster-rail--home" data-pack-grid aria-live="polite">
                        <p class="empty-state"><?= Security::escape($ui('home.packs.loading', 'Загружаю паки. Рандом ищет чистую кружку.')) ?></p>
                    </div>
                </div>
            </section>
        </div>

        <aside class="home-sidebar" aria-label="История и статистика">
            <section class="panel-section compact-panel soft-panel" aria-labelledby="stats-title">
                <div class="section-heading">
                    <div>
                        <p class="eyebrow"><?= Security::escape($ui('home.stats.eyebrow', 'Мини-сводка')) ?></p>
                        <h2 id="stats-title"><?= Security::escape($ui('home.stats.title', 'Статистика')) ?></h2>
                    </div>
                </div>
                <div class="stats-grid compact-stats" data-stats>
                    <p class="empty-state"><?= Security::escape($ui('home.stats.empty', 'Открой первый пак — здесь появится короткая сводка.')) ?></p>
                </div>
            </section>

            <section class="panel-section compact-panel soft-panel" aria-labelledby="history-title">
                <div class="section-heading">
                    <div>
                        <p class="eyebrow"><?= Security::escape($ui('home.history.eyebrow', 'Последнее')) ?></p>
                        <h2 id="history-title"><?= Security::escape($ui('home.history.title', 'История')) ?></h2>
                    </div>
                    <button class="text-button" type="button" data-refresh-history><?= Security::escape($ui('common.refresh', 'Обновить')) ?></button>
                </div>
                <div class="history-list compact-history" data-history>
                    <p class="empty-state"><?= Security::escape($ui('home.history.empty', 'Пока пусто. Последние открытия появятся здесь.')) ?></p>
                </div>
            </section>
        </aside>
    </div>
</main>
