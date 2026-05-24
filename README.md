# Ну что там?

> Развлекательный PHP + SQLite сайт с карточными паками, случайными предсказаниями и отдельной сценой открытия.

`Ну что там?` — portfolio/local-demo проект про короткие бытовые карточки: день, неделя, месяц, настроение, выбор, вопрос, маленький пинок и другие сценарии. Это не серьёзная астрология и не сервис точных прогнозов, а интерактивная витрина паков с лёгким casual-тоном.

Проект сделан без фреймворков: PHP, SQLite, vanilla frontend, локальная аналитика и аккуратная демо-инфраструктура. Сейчас он готов для локального показа через PHP server + Cloudflare Tunnel; production hardening ещё не завершён.

## Highlights

- Отдельная сцена открытия: `/open?pack=slug`.
- Карточки сначала лежат рубашкой вверх и раскрываются вручную по клику или клавиатуре.
- Guest mode: можно пользоваться без аккаунта, с безопасным `guest_id`.
- Auth: регистрация, вход, выход, CSRF для HTML-форм.
- Личный кабинет: профиль, статистика, история, сохранённые карточки, заметки и фильтры.
- Админка: управление паками, карточками, пользователями, admin logs.
- Локальная аналитика без внешних трекеров: события хранятся в SQLite, сырой IP/User-Agent не сохраняются.
- Local demo через Cloudflare Tunnel.
- Backup/restore workflow для защиты `database/database.sqlite`.
- Reduced motion toggle с сохранением предпочтения в `localStorage`.

## Screenshots

Скриншоты можно добавить позже в `docs/screenshots/`.

| Экран | Placeholder |
| --- | --- |
| Homepage / pack showcase | `docs/screenshots/homepage-pack-showcase.png` |
| Opening screen | `docs/screenshots/opening-screen.png` |
| User cabinet | `docs/screenshots/user-cabinet.png` |
| Admin analytics | `docs/screenshots/admin-analytics.png` |

## Tech Stack

- PHP 8.1+
- SQLite / PDO
- Vanilla JavaScript / ES Modules
- HTML / CSS
- No Laravel, Symfony, WordPress or mandatory Node.js runtime
- No paid APIs
- No external analytics

## Project Structure

```text
public/      document root, entrypoint, assets
app/         core, controllers, services, models, views
database/    schema, seed script, SQLite notes
config/      local config example and ignored local config
storage/     logs, exports, sessions; writable runtime area
docs/        architecture, demo, deployment, safety docs
scripts/     local preflight/smoke/demo helper scripts
```

## Local Run

Обычный запуск уже существующего проекта не требует `seed --fresh`. Если в `database/database.sqlite` уже есть пользователи, история, аналитика, сохранённые карточки или правки через админку, считайте эти данные пользовательскими.

Windows PowerShell:

```powershell
cd C:\Projects\web\web2
C:\php\php.exe scripts\preflight.php
C:\php\php.exe scripts\smoke.php
C:\php\php.exe -S 127.0.0.1:8000 -t public
```

Открыть сайт:

```text
http://127.0.0.1:8000
```

`scripts\preflight.php` и `scripts\smoke.php` проверяют окружение и читают SQLite через `SELECT`; они не сбрасывают базу и не пишут runtime-данные.

## First Install on an Empty Database

`database/seed.php --fresh` можно использовать только для первичной установки на пустом проекте или для намеренного полного reset. Эта команда пересоздаёт `database/database.sqlite` и стирает runtime-данные.

```powershell
cd C:\Projects\web\web2
Copy-Item config\config.example.php config\config.php
C:\php\php.exe database\seed.php --fresh
C:\php\php.exe -S 127.0.0.1:8000 -t public
```

Перед любым reset существующей базы сначала сделайте backup:

```powershell
Copy-Item database\database.sqlite "storage\exports\database-backup-$(Get-Date -Format 'yyyyMMdd-HHmmss').sqlite"
```

Подробно: [docs/BACKUP_AND_RESTORE.md](docs/BACKUP_AND_RESTORE.md).

## Local Demo Through Cloudflare Tunnel

Для временного показа локального сайта можно использовать два окна PowerShell:

```powershell
powershell -ExecutionPolicy Bypass -File scripts\start-local.ps1
```

```powershell
powershell -ExecutionPolicy Bypass -File scripts\start-tunnel.ps1
```

Quick Tunnel даёт временную ссылку `https://*.trycloudflare.com`. Это удобно для demo, но не является production deployment и не заменяет backup. Подробно: [docs/LOCAL_DEMO.md](docs/LOCAL_DEMO.md).

## Features

### Public Experience

- Главная страница как витрина паков.
- Featured-паки и категории сценариев.
- `/open?pack=slug` как отдельная сцена открытия.
- Поддержка 1/3/5 карточек для daily/weekly/monthly.
- Контекстные поля для question, choice, mood, direction и других сценариев.
- Ручное раскрытие карточек.
- Rarity UI и визуальные состояния.
- Guest-friendly сообщения для сохранения карточек.

### User Cabinet

- Профиль пользователя.
- Расширенная статистика открытий.
- Коллекция сохранённых карточек.
- Заметки к сохранённым карточкам.
- Фильтры, поиск и сортировка.
- История открытий.
- Смена username и пароля.

### Admin Area

- Доступ только для `role = admin`.
- Dashboard со сводкой.
- Управление паками и карточками.
- Управление пользователями.
- Защита от потери последнего admin.
- Admin logs.
- `/admin/analytics` с KPI, графиками, топами паков, редкостями и последними событиями.

### Local Analytics

Аналитика остаётся внутри SQLite. Проект не использует Google Analytics или внешние счётчики.

Сохраняются события вроде `page_view`, `pack_opened`, `card_saved`, auth-событий, profile/admin actions. Сырой IP и полный User-Agent не выводятся и не используются как публичные данные: в аналитике хранятся хеши.

## Safety Notes

- `database/database.sqlite` не коммитится.
- `config/config.php` не коммитится.
- `storage/logs/*`, `storage/exports/*`, `storage/sessions/*` не коммитятся.
- `database/seed.php --fresh` не является обычной командой запуска или проверки.
- Backup нужен перед database/content changes, deploy, restore, рискованными Codex-задачами и любыми reset-действиями.
- Browser/UI/API-проверки могут писать `page_view`, `openings`, `events`, `rate_limits`, `sessions/cookies`; для чистых тестов используйте копию базы.
- На hosting/VPS document root должен указывать на `public/`.
- `database/`, `config/` и `storage/` не должны быть публично доступны.

## Repository Status

- Local-demo ready: проект можно запускать локально и показывать через Cloudflare Tunnel.
- Production hardening не завершён: нужны финальные security headers/CSP, monitoring, log rotation, backup automation и полноценный deployment process.
- External analytics нет.
- SQLite runtime data намеренно игнорируется и не должна попадать в репозиторий.
- Сайт остаётся portfolio/local-demo проектом, не production SaaS.

## Roadmap

- Premium motion pipeline для opening scene и pack interactions.
- UI/UX polish главной, сцены открытия, кабинета и аналитики.
- User card import from Word.
- Production hardening: security headers/CSP, monitoring, log rotation, backup automation.
- Deployment/VPS workflow.
- Опционально: экспорт статистики, PNG-карточки, достижения в UI.

## Documentation

- [Architecture](docs/ARCHITECTURE.md)
- [Project State](docs/PROJECT_STATE.md)
- [Local Demo](docs/LOCAL_DEMO.md)
- [Deployment Notes](docs/DEPLOYMENT.md)
- [Backup and Restore](docs/BACKUP_AND_RESTORE.md)
- [Motion and Animation Plan](docs/MOTION_AND_ANIMATION_PLAN.md)
- [UI/UX Audit Notes](docs/UI_UX_AUDIT_NOTES.md)
- [Roadmap](docs/ROADMAP.md)
- [Content Guide](docs/CONTENT_GUIDE.md)

## Disclaimer

Все карточки являются развлекательным случайным контентом. Это не медицинские, финансовые, юридические или иные профессиональные рекомендации.
