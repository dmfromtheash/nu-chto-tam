# Project State

## Confirmed git slice 2026-05-26

- Branch: `main`.
- Last known HEAD: `4c9774c fix: transfer guest openings on registration`.
- `origin/main` синхронизирован после push; текущий `git status -sb` показывает `main...origin/main` без ahead/behind.
- Status: clean по последнему выводу пользователя и текущей pre-edit проверке `git status --short`.

## Foundation fixes 2026-05-26

- `9037434 chore: harden production safety preflight wording` - runtime/docs wording больше не подталкивает к `seed.php --fresh` как обычному решению, preflight явно ловит production-опасные настройки.
- `3937089 fix: require csrf for save-card api` - `POST /api/save-card` требует CSRF: view кладёт token в DOM, `open-pack.js` отправляет `X-CSRF-Token`, controller возвращает `419` при ошибке.
- `22b375c fix: guard last active admin` - last admin guard считает активных админов через `role = "admin" AND is_blocked = 0`, поэтому нельзя оставить проект без активного администратора.
- `4c9774c fix: transfer guest openings on registration` - при регистрации гостевые `openings` текущего `guest_id` переносятся на нового `user_id`.

Влияние `4c9774c`:

- переносится только `openings` как история открытий;
- `guest_id` в перенесённых строках очищается, чтобы те же открытия не оставались в гостевой истории после выхода;
- `saved_cards`, `events`, `visitors`, `rate_limits` и analytics не переносились;
- это foundation для будущего Fate Portrait / "Лор Личности", где `openings` должны быть источником истины для алгоритма, а collection/saved cards остаются отдельным пользовательским слоем.

## Roadmap v4.8 next-step note

- После переноса гостевой истории следующий крупный смысловой трек: Stress/Security Test Plan, Backup/Test DB Drill, UI/UX audit, Content Universe Design Doc, Fate Portrait / "Лор Личности" Design Doc.
- Не начинать реализацию Fate Portrait сразу: сначала нужен product+math+content design doc, metadata model, категории паков, веса, confidence/coverage, premium-depth правила и правила скрытия/архивации отображения.
- Для Fate Portrait базовый принцип: портрет развивается по истории `openings` из разных категорий/паков; saved cards и collection display - отдельный слой, который не должен быть единственным источником алгоритма.
- Free-портрет должен оставаться полезным. Premium-паки могут добавлять premium-depth, редкие слои и более глубокий лор, но не должны становиться "единственной правдой".

## Stop list v4.8

- Не запускать `database/seed.php --fresh` как обычную проверку или repair-команду.
- Не трогать `database/database.sqlite` без backup/test DB workflow.
- Не запускать browser/API/admin mutation tests на live DB.
- Не начинать DB migration без отдельного read-only audit, backup, test migration и rollback plan.
- Не начинать Stripe/payment implementation без отдельного entitlements/access/limits design.
- Не кодить Fate Portrait до design doc и metadata model.
- Не смешивать docs/security/UI/content/DB/monetization в один этап.

## DB path env override 2026-05-24

- Добавлен временный env override `NCHT_DB_PATH`: runtime bootstrap, `scripts/preflight.php` и `scripts/smoke.php` используют его как `DB_PATH`, если переменная задана и не пуста.
- Обычный запуск без `NCHT_DB_PATH` остаётся прежним: используется `DB_PATH` из `config/config.php` или fallback на `database/database.sqlite`.
- `docs/BACKUP_AND_RESTORE.md` и `docs/LOCAL_DEMO.md` уточняют safe test DB workflow: тестовая база должна быть заранее созданной копией рабочей базы, `seed.php --fresh` для этого не используется.

## Cabinet JSON CSRF 2026-05-24

- Добавлена CSRF-проверка только для authenticated cabinet JSON mutations: `POST /api/cabinet/saved/update-note`, `POST /api/cabinet/saved/delete`, `POST /api/cabinet/profile/update`, `POST /api/cabinet/profile/change-password`.
- `cabinet.js` берёт токен из `data-csrf` на root-элементе кабинета и отправляет его в `X-CSRF-Token` только для этих cabinet POST-запросов.
- Admin API, auth API, guest mode, `/api/open-pack` и `/api/save-card` не менялись.

## Backup/restore workflow 2026-05-24

- Создан `docs/BACKUP_AND_RESTORE.md`: ручной PowerShell backup/restore для `database/database.sqlite`, проверка backup-файлов, тестовая копия через `DB_PATH`, restore-предупреждения и чек-лист перед Codex/deploy/demo задачами.

## Docs safety workflow 2026-05-24

- Документация разделяет первичную установку на пустой базе, обычный запуск существующего проекта, read-only проверки и опасный `seed --fresh`.
- `database/seed.php --fresh` описан как полный reset `database/database.sqlite`, а не как обычная команда для запуска или проверки.
- Перед любыми reset/seed-работами с существующей базой нужен backup: `Copy-Item database\database.sqlite "storage\exports\database-backup-$(Get-Date -Format 'yyyyMMdd-HHmmss').sqlite"`.
- `scripts/preflight.php` и `scripts/smoke.php` проверены как read-only: они читают конфиг/SQLite и делают `SELECT`, без записи в runtime-данные.
- Browser/UI-проверки могут писать `page_view`, `openings`, `rate_limits`, `sessions`/cookies и события аналитики; для чистых UI-тестов нужна копия базы.

## Admin analytics upgrade 2026-05-23

- `/admin` получил крупные KPI-карточки, период `Сегодня / 7 дней / 30 дней / Всё время`, графики активности, популярных паков, редкостей и агрегированной воронки.
- `/admin/analytics` стал полноценной страницей аналитики: KPI overview, динамика по дням, таблицы под графиками, топ паков, редкости, пользователи/гости, последние 50 событий и блок пояснений.
- `/admin/api/summary` и `/admin/api/analytics` расширены на существующих SQLite-данных без изменения schema/seed.
- Лёгкие графики сделаны на vanilla JS + SVG/CSS, без Chart.js, CDN и внешних счётчиков.
- Открытия паков считаются по `events.event_type = 'pack_opened'`; выпавшие карточки — по `openings`; сохранения — по `saved_cards`; посетители — по `visitors`.
- Ограничение аналитики: воронка агрегированная, потому что не все действия можно идеально связать с одним человеком при очистке cookies или смене visitor.

## Audit and responsive pass 2026-05-23

- Проведён осторожный аудит проекта без изменения schema/seed, auth, guest mode, cabinet, admin, analytics, API-контрактов и механики открытия карточек.
- Созданы `docs/AUDIT_REPORT.md` и `docs/RESPONSIVE_REPORT.md`.
- Выполнена низкорисковая responsive-доводка: глобальные overflow guards, touch targets, mobile login/register padding, home pack grids, `/open` 1/3/5 card sizing, admin filters/table wrappers.
- После browser-проверки дополнительно исправлен mobile scroll layout для 3-карточного `/open`: первая карта не уезжает за левую границу и раскрывается по tap/click.
- На главной убран лишний повторный запрос `/api/history` при загрузке статистики.
- Оставшиеся риски: локальные дефолтные секреты, production security headers/CSP, backup/restore, monitoring/log rotation и ручная проверка real-device touch feel.

## Что сделано

- Этап 1: структура проекта, SQLite-схема, seed, базовые core-классы, документация и главная заглушка.
- Этап 2: роутинг, auth, guest_id, API, RandomPredictionService и rate limit.
- Этап 3: рабочая главная страница с карточными паками, opening UI, reveal-анимациями, историей и мини-статистикой.
- Этап 4: личный кабинет пользователя с профилем, статистикой, коллекцией, заметками и полной историей.
- Этап 5: админ-панель для паков, карточек, пользователей и admin logs.
- Дополнение к Этапу 5: локальная аналитика посетителей и событий в SQLite.
- Этап 5.5: визуальный overhaul главной и отдельная страница `/open?pack=slug` для открытия паков.
- Этап 6: финальная локальная полировка, QA, демо-инструкции и подготовка к обычному хостингу/VPS.
- Seed создаёт 14 casual-паков и 154 карточки.
- Стиль контента остаётся бытовым, человечным, дружелюбным и слегка смешным.
- Регистрация нового аккаунта переносит гостевые `openings` текущего `guest_id` в историю нового пользователя без изменения schema.

## Рабочие frontend-возможности

- Загрузка всех активных паков через `/api/packs`.
- Открытие любого пака с главной.
- daily показывает 1 карточку.
- weekly показывает 3 карточки.
- monthly показывает 5 карточек.
- question, choice, mood и direction имеют понятный ввод.
- take-leave подписывает результат как “Взять с собой” / “Оставить дома”.
- Карточки показывают rarity текстом и визуальным стилем.
- Save-card работает для авторизованного пользователя.
- Гость получает понятное сообщение про вход/регистрацию.
- История последних 5 открытий и мини-статистика обновляются после открытий.
- Reduced motion включается переключателем и сохраняется в `localStorage`.
- `/open` показывает выбранный пак как отдельную сцену открытия.
- После открытия пака карточки сначала лежат рубашкой вверх.
- Карточки раскрываются вручную по клику или клавиатуре.
- Layout 1/3/5 карт сделан без наложений; monthly использует центрированную композицию 3+2.
- На главной мини-статистика разделяет открытые карточки и открытые паки.

## Рабочие возможности кабинета

- `GET /cabinet` показывает гостю приглашение войти, а пользователю личный кабинет.
- Профиль: username, email, дата регистрации, последний вход.
- Расширенная статистика: открытия, сохранённые карточки, любимый пак, частая редкость.
- Коллекция сохранённых карточек с фильтрами, поиском и сортировкой.
- Добавление/редактирование заметки к сохранённой карточке.
- Удаление карточки из коллекции.
- История открытий с фильтрами по паку, редкости и сохранённым.
- Смена username и пароля.

## Рабочие возможности админки

- `/admin` защищён ролью `admin`.
- Dashboard показывает пользователей, открытия, сохранения, паки, карточки, топ паков, rarity и последние логи.
- Packs: список, создание, редактирование, toggle active, сохранение sort order.
- Predictions: список, фильтры по паку/редкости/активности, поиск, создание, редактирование, toggle active.
- Users: список, роль, блокировка/разблокировка, счётчики openings/saved.
- Защита от блокировки самого себя и от потери последнего admin.
- `admin_logs` пишутся для важных действий.
- Dashboard показывает блок аналитики: посетители, события, pack_opened, регистрации, guest save-card fail.
- `/admin/analytics` показывает последние события с фильтрами по event_type, user_id, visitor_id и дате.
- IP и User-Agent в аналитике хранятся только как hash.

## Рабочие endpoints

HTML:

- `GET /`
- `GET /login`
- `GET /register`
- `GET /cabinet`
- `GET /profile`
- `GET /admin`
- `GET /admin/packs`
- `GET /admin/packs/create`
- `GET /admin/packs/edit`
- `GET /admin/predictions`
- `GET /admin/predictions/create`
- `GET /admin/predictions/edit`
- `GET /admin/users`
- `GET /admin/analytics`
- `GET /admin/logs`
- `POST /login`
- `POST /register`
- `POST /logout`

API:

- `GET /api/health`
- `GET /api/packs`
- `GET /api/auth/me`
- `POST /api/auth/register`
- `POST /api/auth/login`
- `POST /api/auth/logout`
- `POST /api/open-pack`
- `POST /api/save-card`
- `GET /api/history`
- `GET /api/stats`
- `GET /api/cabinet/summary`
- `GET /api/cabinet/saved`
- `POST /api/cabinet/saved/update-note`
- `POST /api/cabinet/saved/delete`
- `GET /api/cabinet/history`
- `POST /api/cabinet/profile/update`
- `POST /api/cabinet/profile/change-password`
- `GET /admin/api/summary`
- `GET /admin/api/packs`
- `POST /admin/api/packs/create`
- `POST /admin/api/packs/update`
- `POST /admin/api/packs/toggle`
- `POST /admin/api/packs/reorder`
- `GET /admin/api/predictions`
- `POST /admin/api/predictions/create`
- `POST /admin/api/predictions/update`
- `POST /admin/api/predictions/toggle`
- `GET /admin/api/users`
- `POST /admin/api/users/toggle-block`
- `POST /admin/api/users/update-role`
- `GET /admin/api/analytics`
- `GET /admin/api/logs`

## Проверки

```powershell
C:\php\php.exe -l public\index.php
C:\php\php.exe -l app\Views\layout.php
C:\php\php.exe -l app\Views\home.php
C:\php\php.exe -l app\Views\cabinet.php
C:\php\php.exe -l app\Controllers\CabinetController.php
C:\php\php.exe -l app\Services\CabinetService.php
C:\php\php.exe -l app\Controllers\AdminController.php
C:\php\php.exe -l app\Services\AdminService.php
C:\php\php.exe -l app\Services\AnalyticsService.php
C:\php\php.exe -l scripts\preflight.php
C:\php\php.exe -l scripts\smoke.php
C:\php\php.exe scripts\preflight.php
C:\php\php.exe scripts\smoke.php
C:\php\php.exe -S 127.0.0.1:8000 -t public
```

`database\seed.php --fresh` не входит в обычные проверки: это полный reset базы. Для reset существующей базы сначала нужен backup:

```powershell
Copy-Item database\database.sqlite "storage\exports\database-backup-$(Get-Date -Format 'yyyyMMdd-HHmmss').sqlite"
```

API:

```bash
curl http://localhost:8000/api/health
curl http://localhost:8000/api/packs
curl -X POST http://localhost:8000/api/open-pack -H "Content-Type: application/json" -d "{\"pack\":\"daily\"}"
curl -X POST http://localhost:8000/api/open-pack -H "Content-Type: application/json" -d "{\"pack\":\"weekly\"}"
curl -X POST http://localhost:8000/api/open-pack -H "Content-Type: application/json" -d "{\"pack\":\"monthly\"}"
curl http://localhost:8000/api/history
curl http://localhost:8000/api/stats
```

API/browser-проверки выше могут писать runtime-данные в SQLite, особенно `POST /api/open-pack`, page views, rate limits, sessions/cookies и события аналитики. Для чистой UI-проверки используйте копию базы.

## Что намеренно не сделано

- Достижения в UI.
- Экспорт и PNG-генерация.
- Настоящий production hardening, мониторинг, ротация логов и автоматические backup.
- Cloudflare Tunnel описан только как временное локальное демо, не production.

## Оставшиеся риски

- Нужно проверить `pdo_sqlite` на целевом хостинге.
- Нужно заменить `SESSION_SECRET` и пароль администратора перед non-local запуском.
- Rate limit минимальный и пригоден для старта, но перед production его стоит нагрузочно проверить.
- Fate Portrait / "Лор Личности" пока не реализован; перед кодом нужен отдельный product+math+content design doc и metadata model.
- Кабинет пока без пагинации и без сложных графиков; для Этапа 4 это намеренно.
- Админка без массового импорта/экспорта и без сложной аналитики; это оставлено на будущую полировку.
- Аналитика лёгкая и событийная, без BI, retention-политики и сложных графиков.
