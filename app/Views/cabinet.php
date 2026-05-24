<?php

declare(strict_types=1);

use App\Core\Security;

$currentUser = $currentUser ?? null;
$messages = $messages ?? [];
$errors = $errors ?? [];
$isAuthenticated = is_array($currentUser);
$displayName = $isAuthenticated ? (string) $currentUser['username'] : 'Гость';
$appName = (string) config_value('APP_NAME', 'Ну что там?');
$displayInitial = function_exists('mb_substr') ? mb_substr($displayName, 0, 1, 'UTF-8') : substr($displayName, 0, 1);
?>
<main class="app-shell cabinet-shell" data-cabinet-app data-authenticated="<?= $isAuthenticated ? 'true' : 'false' ?>">
    <header class="topbar" aria-label="Верхняя панель">
        <a class="brand" href="/" aria-label="На главную">
            <span class="brand-mark" aria-hidden="true">?</span>
            <span><?= Security::escape($appName) ?></span>
        </a>

        <nav class="topbar-actions" aria-label="Аккаунт">
            <span class="user-chip"><?= Security::escape($displayName) ?></span>
            <a class="text-button" href="/">На главную</a>
            <?php if ($isAuthenticated): ?>
                <form method="post" action="/logout">
                    <input type="hidden" name="_csrf_token" value="<?= Security::escape($csrfToken) ?>">
                    <button class="text-button" type="submit">Выйти</button>
                </form>
            <?php else: ?>
                <a class="text-button" href="/login">Войти</a>
                <a class="text-button" href="/register">Регистрация</a>
            <?php endif; ?>
        </nav>
    </header>

    <?php foreach ($messages as $message): ?>
        <p class="notice is-success"><?= Security::escape($message) ?></p>
    <?php endforeach; ?>
    <?php foreach ($errors as $error): ?>
        <p class="notice is-error"><?= Security::escape($error) ?></p>
    <?php endforeach; ?>

    <?php if (!$isAuthenticated): ?>
        <section class="guest-gate" aria-labelledby="guest-gate-title">
            <p class="eyebrow">Кабинет</p>
            <h1 id="guest-gate-title">Кабинет доступен после входа.</h1>
            <p>
                История и коллекция любят знать, кому принадлежат. Войди или зарегистрируйся,
                и карточки перестанут жить в режиме “кажется, я где-то это видел”.
            </p>
            <div class="hero-actions">
                <a class="button" href="/login">Войти</a>
                <a class="button is-secondary" href="/register">Зарегистрироваться</a>
                <a class="text-button" href="/">Вернуться на главную</a>
            </div>
        </section>
    <?php else: ?>
        <section class="cabinet-hero" aria-labelledby="cabinet-title">
            <div>
                <p class="eyebrow">Личный кабинет</p>
                <h1 id="cabinet-title">Твои карточки, заметки и следы рандома</h1>
                <p class="hero-note">
                    Тут без торжественных фанфар: просто профиль, сохранённые карточки,
                    история открытий и немного статистики, которая делает вид, что всё поняла.
                </p>
            </div>
            <div class="profile-card" data-profile-card>
                <div class="profile-avatar" aria-hidden="true"><?= Security::escape($displayInitial) ?></div>
                <div>
                    <h2 data-profile-username><?= Security::escape($displayName) ?></h2>
                    <p data-profile-email><?= Security::escape((string) $currentUser['email']) ?></p>
                    <p class="muted-line">Профиль загружается. Он просто поправляет табличку на двери.</p>
                </div>
            </div>
        </section>

        <div class="feedback cabinet-feedback" data-cabinet-feedback role="status" aria-live="polite"></div>

        <section class="stats-strip" aria-label="Быстрая статистика" data-cabinet-stats>
            <article class="stat-card">
                <span>Открытий всего</span>
                <strong>0</strong>
            </article>
            <article class="stat-card">
                <span>Сегодня</span>
                <strong>0</strong>
            </article>
            <article class="stat-card">
                <span>В коллекции</span>
                <strong>0</strong>
            </article>
            <article class="stat-card">
                <span>Любимый пак</span>
                <strong>Пока не выбран</strong>
            </article>
        </section>

        <section class="cabinet-grid" aria-label="Кабинет">
            <div class="cabinet-main">
                <section class="panel-section cabinet-panel" aria-labelledby="saved-title">
                    <div class="section-heading">
                        <div>
                            <p class="eyebrow">Коллекция</p>
                            <h2 id="saved-title">Сохранённые карточки</h2>
                        </div>
                        <button class="text-button" type="button" data-refresh-saved>Обновить</button>
                    </div>

                    <div class="filter-row" aria-label="Фильтры коллекции">
                        <label>
                            <span>Редкость</span>
                            <select data-saved-rarity>
                                <option value="all">Все</option>
                                <option value="common">Обычные</option>
                                <option value="uncommon">Необычные</option>
                                <option value="rare">Редкие</option>
                                <option value="epic">Эпические</option>
                                <option value="legendary">Легендарные</option>
                                <option value="mythic">Мифические</option>
                            </select>
                        </label>
                        <label class="filter-grow">
                            <span>Поиск</span>
                            <input type="search" data-saved-search placeholder="Фраза, пак или заметка">
                        </label>
                        <label>
                            <span>Сортировка</span>
                            <select data-saved-sort>
                                <option value="new">Новые сначала</option>
                                <option value="old">Старые сначала</option>
                                <option value="rarity">По редкости</option>
                            </select>
                        </label>
                    </div>

                    <div class="saved-list" data-saved-list aria-live="polite">
                        <p class="empty-state">Коллекция загружается. Карточки ищут приличную позу.</p>
                    </div>
                </section>

                <section class="panel-section cabinet-panel" aria-labelledby="history-title">
                    <div class="section-heading">
                        <div>
                            <p class="eyebrow">История</p>
                            <h2 id="history-title">Последние открытия</h2>
                        </div>
                        <button class="text-button" type="button" data-refresh-history>Обновить</button>
                    </div>

                    <form class="filter-row" data-history-filters aria-label="Фильтры истории">
                        <label>
                            <span>Пак</span>
                            <select data-history-pack>
                                <option value="all">Все паки</option>
                            </select>
                        </label>
                        <label>
                            <span>Редкость</span>
                            <select data-history-rarity>
                                <option value="all">Все</option>
                                <option value="common">Обычные</option>
                                <option value="uncommon">Необычные</option>
                                <option value="rare">Редкие</option>
                                <option value="epic">Эпические</option>
                                <option value="legendary">Легендарные</option>
                                <option value="mythic">Мифические</option>
                            </select>
                        </label>
                        <label>
                            <span>Лимит</span>
                            <select data-history-limit>
                                <option value="50">50</option>
                                <option value="100">100</option>
                            </select>
                        </label>
                        <label class="checkbox-line">
                            <input type="checkbox" data-history-saved>
                            <span>Только сохранённые</span>
                        </label>
                    </form>

                    <div class="history-table" data-cabinet-history aria-live="polite">
                        <p class="empty-state">История пока чистая. Рандом ещё не успел оставить следы.</p>
                    </div>
                </section>
            </div>

            <aside class="cabinet-side" aria-label="Профиль и настройки">
                <section class="panel-section cabinet-panel" aria-labelledby="profile-title">
                    <p class="eyebrow">Профиль</p>
                    <h2 id="profile-title">Данные аккаунта</h2>
                    <dl class="profile-facts" data-profile-facts>
                        <div>
                            <dt>Email</dt>
                            <dd><?= Security::escape((string) $currentUser['email']) ?></dd>
                        </div>
                        <div>
                            <dt>Регистрация</dt>
                            <dd>Загрузка...</dd>
                        </div>
                        <div>
                            <dt>Последний вход</dt>
                            <dd>Загрузка...</dd>
                        </div>
                    </dl>
                </section>

                <section class="panel-section cabinet-panel" aria-labelledby="settings-title">
                    <p class="eyebrow">Настройки</p>
                    <h2 id="settings-title">Профиль</h2>

                    <form class="settings-form" data-profile-form>
                        <label>
                            <span>Username</span>
                            <input type="text" name="username" value="<?= Security::escape($displayName) ?>" maxlength="40" required>
                        </label>
                        <button class="button" type="submit">Сохранить имя</button>
                    </form>

                    <form class="settings-form" data-password-form>
                        <label>
                            <span>Текущий пароль</span>
                            <input type="password" name="current_password" autocomplete="current-password" required>
                        </label>
                        <label>
                            <span>Новый пароль</span>
                            <input type="password" name="new_password" autocomplete="new-password" minlength="8" required>
                        </label>
                        <label>
                            <span>Повтор нового пароля</span>
                            <input type="password" name="new_password_confirm" autocomplete="new-password" minlength="8" required>
                        </label>
                        <button class="button is-secondary" type="submit">Сменить пароль</button>
                    </form>

                    <label class="motion-toggle cabinet-motion-toggle">
                        <input type="checkbox" data-cabinet-motion-toggle>
                        <span>Меньше анимаций</span>
                    </label>
                </section>
            </aside>
        </section>
    <?php endif; ?>
</main>
