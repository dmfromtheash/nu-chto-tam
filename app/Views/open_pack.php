<?php

declare(strict_types=1);

use App\Core\Security;

$currentUser = $currentUser ?? null;
$isAuthenticated = is_array($currentUser);
$isAdmin = $isAuthenticated && (($currentUser['role'] ?? null) === 'admin');
$displayName = $isAuthenticated ? (string) $currentUser['username'] : 'Гость';
$initialPackSlug = isset($initialPackSlug) ? (string) $initialPackSlug : '';
$csrfToken = isset($csrfToken) ? (string) $csrfToken : '';
?>
<main
    class="open-page"
    data-open-pack-app
    data-initial-pack="<?= Security::escape($initialPackSlug) ?>"
    data-authenticated="<?= $isAuthenticated ? 'true' : 'false' ?>"
    data-csrf="<?= Security::escape($csrfToken) ?>"
>
    <header class="open-topbar" aria-label="Верхняя панель открытия">
        <a class="text-button" href="/#packs">← К пакам</a>
        <a class="game-brand open-brand" href="/" aria-label="На главную">
            <span class="game-brand__mark" aria-hidden="true">?</span>
            <span>Ну что там?</span>
        </a>
        <nav class="open-topbar__actions" aria-label="Аккаунт">
            <span class="user-chip"><?= Security::escape($displayName) ?></span>
            <?php if ($isAuthenticated): ?>
                <a class="text-button" href="/cabinet">Кабинет</a>
                <?php if ($isAdmin): ?>
                    <a class="text-button" href="/admin">Админка</a>
                <?php endif; ?>
                <form method="post" action="/logout">
                    <input type="hidden" name="_csrf_token" value="<?= Security::escape($csrfToken) ?>">
                    <button class="text-button" type="submit">Выйти</button>
                </form>
            <?php else: ?>
                <a class="text-button" href="/login">Войти</a>
                <a class="text-button" href="/register">Регистрация</a>
            <?php endif; ?>
            <label class="motion-toggle game-motion-toggle">
                <input type="checkbox" data-motion-toggle>
                <span>Меньше анимаций</span>
            </label>
        </nav>
    </header>

    <section class="open-scene opening-stage" aria-labelledby="open-pack-title" data-opening-stage>
        <div class="open-scene__edge" aria-hidden="true"></div>
        <div class="open-scene__portal" aria-hidden="true"></div>

        <div class="open-scene__copy">
            <p class="eyebrow">Сцена открытия</p>
            <h1 id="open-pack-title" data-selected-pack-title>Загружаю пак</h1>
            <p data-pack-description>Пак выбран. Рандом делает вид, что всё под контролем.</p>
            <div class="open-pack-meta" data-pack-meta></div>
        </div>

        <div class="open-scene__center">
            <div class="pack-stage__glow" aria-hidden="true"></div>
            <div class="pack-stage__ring open-stage-ring" aria-hidden="true"></div>

            <div class="selected-pack-card open-selected-pack" data-selected-pack-card>
                <p>Если пак не появился, он просто очень скромный. Сейчас проверим.</p>
            </div>

            <div class="deck-preview open-deck-preview" data-deck-preview aria-hidden="true"></div>

            <div class="stage-result-layer" data-stage-result>
                <p class="stage-result-caption">Что выпало</p>
                <div class="game-result-grid card-stage-results" data-result-grid aria-live="polite">
                    <p class="empty-state">Карты пока внутри пакета. Они шуршат, но держатся.</p>
                </div>
            </div>
        </div>

        <form class="opening-controls open-controls" data-open-form>
            <div class="context-fields" data-context-fields></div>
            <button class="button open-button" type="submit" data-open-button disabled>Открыть пак</button>
        </form>

        <div class="feedback open-feedback" data-feedback role="status" aria-live="polite"></div>

        <div class="open-stage-actions" data-stage-actions>
            <button class="text-button" type="button" data-open-again disabled>Открыть ещё раз</button>
            <a class="text-button" href="/#packs">Выбрать другой пак</a>
            <?php if ($isAuthenticated): ?>
                <a class="text-button" href="/cabinet">В кабинет</a>
            <?php endif; ?>
        </div>
    </section>

    <p class="open-hint">
        Все открытия попадают в историю. Сохранять карточки в коллекцию можно после входа.
    </p>
</main>
