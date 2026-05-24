# Архитектура

## Общая идея

Проект строится как небольшой PHP-сайт для обычного хостинга. Публичная точка входа находится в `public/index.php`, прикладной код лежит вне публичной директории в `app/`, конфигурация хранится в `config/`, база и seed-скрипты находятся в `database/`.

Контентная идея: случайные casual-карточки в бытовом, человечном и слегка ироничном стиле. Это не серьёзная астрология, не деление по знакам зодиака и не точные прогнозы.

## Backend слой

- `app/Core/Router.php` обрабатывает `GET` и `POST` маршруты.
- `AuthController` отвечает за HTML и API auth.
- `ApiController` отдаёт health, packs, me, open-pack, save-card, history и stats.
- `CabinetController` отдаёт страницу `/cabinet` и API личного кабинета.
- `AdminController` отдаёт `/admin`, страницы управления и admin API.
- `AuthService` регистрирует, логинит и разлогинивает пользователя.
- `CabinetService` собирает профиль, статистику, сохранённые карточки, историю и настройки профиля.
- `AdminService` управляет паками, карточками, пользователями, dashboard summary и `admin_logs`.
- `AnalyticsService` создаёт visitor cookie, обновляет `visitors` и пишет события в `events`.
- `GuestService` создаёт безопасный `guest_id` для работы без аккаунта.
- `RandomPredictionService` выбирает карточки по весам редкости и пишет `openings`.
- `RateLimiter` использует таблицу `rate_limits`.

## Frontend слой

Главная страница в `app/Views/home.php` является витриной паков и entry point. `public/assets/js/app.js` загружает данные из API, строит список паков, обновляет компактную историю и статистику. Само открытие паков вынесено на `/open?pack=slug`.

Главная использует:

- `GET /api/packs`
- `GET /api/auth/me`
- `GET /api/history`
- `GET /api/stats`

Страница открытия находится в `app/Views/open_pack.php`. `public/assets/js/open-pack.js` загружает выбранный пак, отправляет контекст question/choice/mood/direction, вызывает `POST /api/open-pack`, выкладывает карточки рубашкой вверх и раскрывает каждую вручную по клику. `public/assets/css/open-pack.css` отвечает за сцену открытия, а `public/assets/css/game-cards.css` - за бустеры, игровые карточки, rarity и reduced motion.

Страница `/open` использует:

- `GET /api/packs`
- `POST /api/open-pack`
- `POST /api/save-card`

Кабинет находится в `app/Views/cabinet.php`. `public/assets/js/cabinet.js` загружает данные кабинета, фильтрует коллекцию, обновляет заметки, удаляет сохранённые карточки и отправляет формы профиля.

Кабинет использует:

- `GET /api/cabinet/summary`
- `GET /api/cabinet/saved`
- `POST /api/cabinet/saved/update-note`
- `POST /api/cabinet/saved/delete`
- `GET /api/cabinet/history`
- `POST /api/cabinet/profile/update`
- `POST /api/cabinet/profile/change-password`

Админка находится в `app/Views/admin/*`. Для действий используется `public/assets/js/admin.js`: формы отправляют JSON в `/admin/api/*` с CSRF-токеном. Стили отделены в `public/assets/css/admin.css`.

Админка использует:

- `GET /admin/api/summary`
- `GET /admin/api/packs`
- `POST /admin/api/packs/create|update|toggle|reorder`
- `GET /admin/api/predictions`
- `POST /admin/api/predictions/create|update|toggle`
- `GET /admin/api/users`
- `POST /admin/api/users/toggle-block|update-role`
- `GET /admin/api/analytics`
- `GET /admin/api/logs`

## Analytics

Локальная аналитика хранится в SQLite в таблицах `visitors` и `events`. Сервис не отправляет данные наружу и не сохраняет сырой IP/User-Agent: только `ip_hash` и `user_agent_hash`.

События пишутся best-effort: ошибка записи аналитики логируется, но не ломает основное действие. Сейчас фиксируются page views, открытия паков, сохранения карточек, auth-события, изменения профиля и admin actions.

## Auth и guest

Авторизованные открытия пишутся с `user_id`. Гостевые открытия пишутся с `guest_id`, который хранится в session/cookie и не содержит персональных данных.

Перенос гостевой истории в аккаунт пока не реализован. Это можно добавить позже без изменения текущих таблиц.

## Admin access

Админка доступна только авторизованному пользователю с `role = admin`. HTML-страницы без роли не показывают данные, admin API возвращает `403`. POST-действия требуют CSRF и пишут `admin_logs` без паролей и секретов.

## RandomPredictionService

Сервис:

1. Находит активный pack по slug или id.
2. Определяет количество карточек: daily 1, weekly 3, monthly 5, остальные по `cards_per_open`.
3. Выбирает rarity по весам.
4. Ищет активную карточку нужной редкости.
5. Если такой редкости нет, берёт любую активную карточку пака.
6. Избегает повтора последней карточки для этого user/guest, если есть альтернатива.
7. Пишет каждое выпадение в `openings`.

Сейчас `openings` хранит каждую выпавшую карточку отдельной строкой. Для UI “открытые паки” вычисляются по доступному контексту выпадений и `cards_per_open`, поэтому точная аналитика pack sessions без отдельного session id намеренно не усложнялась на этом этапе.

## Что осталось

После локального демо: production hardening, мониторинг, регулярные backup и настоящий deploy-процесс.
