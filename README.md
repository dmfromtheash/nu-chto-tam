# Ну что там?

## Admin analytics upgrade

2026-05-23 админская статистика стала нагляднее без внешней аналитики и без изменения публичной части сайта. `/admin` и `/admin/analytics` показывают русскоязычные KPI, фильтр периода, SVG/CSS-графики активности, популярные паки, распределение редкостей, агрегированную воронку и последние события.

- Открытия паков считаются по `events.event_type = 'pack_opened'`.
- Выпавшие карточки считаются по строкам `openings`.
- Сохранения считаются по `saved_cards`.
- Посетители считаются по `visitors`; за период используется `last_seen_at`, новые посетители — `first_seen_at`.
- Последние события не показывают сырые IP/User-Agent, пароли, хеши и секретные payload-поля.

Развлекательный PHP-проект про случайные карточные паки: день, неделя, месяц, настроение, выбор, маленький пинок и другие бытовые категории. Это не серьёзная астрология, не сайт по знакам зодиака и не сервис точных прогнозов. Стиль контента: casual, человечный, дружелюбный, немного ироничный, без пафосной эзотерики.

Сейчас реализованы этапы 1–6: фундамент, SQLite, seed-данные, backend API, auth/guest_id, рабочая главная, отдельная сцена открытия паков, личный кабинет, админ-панель, локальная аналитика и финальная демо-документация.

## Технический стек

- PHP 8.1+
- SQLite через PDO
- HTML5
- CSS3
- Vanilla JavaScript / ES Modules
- Без Laravel, Symfony, WordPress и обязательной Node.js production-зависимости
- Без платных API и внешних AI/астрологических API

## Структура папок

```text
public/              публичная директория сайта
public/assets/       CSS, JS, изображения и звуки
app/Core/            bootstrap, Router, Database, Response, Session, Security, Validator
app/Controllers/     HTML/API контроллеры
app/Models/          модели данных
app/Services/        auth, guest_id, rate limit, random prediction service, cabinet, admin, analytics
app/Views/           PHP-шаблоны
config/              локальная конфигурация
database/            SQLite schema.sql, seed.php и заметки
storage/             logs/exports, writable
docs/                архитектура, roadmap и состояние проекта
```

## Первичная установка на пустой базе

`database/seed.php --fresh` используйте только для пустого проекта, где `database/database.sqlite` ещё не содержит пользовательских данных, или для намеренного полного reset. Эта команда пересоздаёт SQLite-базу.

```bash
cp config/config.example.php config/config.php
php database/seed.php --fresh
php -S localhost:8000 -t public
```

PowerShell на этой машине может требовать полный путь:

```powershell
C:\php\php.exe database/seed.php --fresh
C:\php\php.exe -S localhost:8000 -t public
```

Откройте [http://localhost:8000](http://localhost:8000).

`ADMIN_DEFAULT_PASSWORD` нужен только для локального seed. Не используйте дефолтный пароль в production.

## Быстрый локальный запуск существующего проекта

Обычный запуск уже существующего проекта не требует `seed --fresh`. Если в `database/database.sqlite` уже есть пользователи, аналитика, история, сохранённые карточки или правки из админки, считайте эти данные пользовательскими.

Windows PowerShell:

```powershell
cd C:\path\to\web2
if (-not (Test-Path config\config.php)) { Copy-Item config\config.example.php config\config.php }
C:\php\php.exe scripts\preflight.php
C:\php\php.exe scripts\smoke.php
C:\php\php.exe -S 127.0.0.1:8000 -t public
```

Открыть сайт: [http://127.0.0.1:8000](http://127.0.0.1:8000).

`scripts\preflight.php` и `scripts\smoke.php` проверены как read-only: они читают конфиг и SQLite, выполняют `SELECT`/проверки окружения и не пишут в БД или runtime-файлы. Если `smoke.php` сообщает, что базы нет, не применяйте `--fresh` к существующей рабочей базе.

## Опасный reset/seed

`database/seed.php --fresh` полностью сбрасывает `database/database.sqlite`: пользователей, историю, сохранённые карточки, аналитику, правки через админку и другие runtime-данные. Перед любым reset существующей базы сначала сделайте backup:

```powershell
Copy-Item database\database.sqlite "storage\exports\database-backup-$(Get-Date -Format 'yyyyMMdd-HHmmss').sqlite"
```

Только после явного решения о полном reset можно запускать:

```powershell
C:\php\php.exe database\seed.php --fresh
```

## Демо через Cloudflare Tunnel

1. Запустите локальный PHP-сервер:

```powershell
C:\php\php.exe -S 127.0.0.1:8000 -t public
```

2. Во втором окне PowerShell запустите tunnel:

```powershell
cloudflared tunnel --url http://127.0.0.1:8000
```

3. Скопируйте временную `https://*.trycloudflare.com` ссылку и отправьте друзьям.

Ограничения: компьютер должен быть включён, PHP-сервер и окно `cloudflared` должны работать, ссылка временная. Это демо, не production.

Можно использовать helper scripts:

```powershell
powershell -ExecutionPolicy Bypass -File scripts\start-local.ps1
powershell -ExecutionPolicy Bypass -File scripts\start-tunnel.ps1
powershell -ExecutionPolicy Bypass -File scripts\check-tunnel.ps1
```

Актуальная подробная инструкция: `docs/LOCAL_DEMO.md`.

Для демо нужны два окна PowerShell:

1. PHP server: `powershell -ExecutionPolicy Bypass -File scripts\start-local.ps1`
2. Cloudflare tunnel: `powershell -ExecutionPolicy Bypass -File scripts\start-tunnel.ps1`

Quick Tunnel зависит от доступа к `https://api.trycloudflare.com/tunnel`. Если локальный сайт на `http://127.0.0.1:8000` работает, но `cloudflared` получает `context deadline exceeded` или timeout при POST к Cloudflare API, это проблема сети/VPN/firewall/доступа к Cloudflare API, а не ошибка PHP-сайта. Для диагностики используйте `scripts\check-tunnel.ps1`.

## Что есть на главной

- Hero-блок и верхняя панель гостя/пользователя.
- Витрина 14 активных паков из `GET /api/packs`.
- Переходы на отдельную сцену `/open?pack=slug`.
- Открытие паков на `/open` через `POST /api/open-pack`.
- Ручное раскрытие карточек по клику: сначала видна рубашка, потом предсказание.
- Контекстные поля для question, choice, mood и direction на странице открытия.
- Отображение 1/3/5 карточек для daily/weekly/monthly без наложений.
- Rarity-бейджи и визуальные состояния.
- Сохранение карточки через `POST /api/save-card`.
- Для гостя дружелюбное сообщение с входом/регистрацией.
- Последние 5 открытий через `GET /api/history`.
- Мини-статистика через `GET /api/stats`: карточки и паки показываются отдельно.
- Переключатель “Меньше анимаций” с сохранением в `localStorage`.

## Что есть в кабинете

`GET /cabinet` доступен авторизованному пользователю. Гость видит понятное приглашение войти или зарегистрироваться.

- Профиль: username, email, регистрация, последний вход.
- Расширенная статистика: открытия, сегодня, сохранённые карточки, любимый пак, частая редкость, первые/последние открытия.
- Коллекция сохранённых карточек.
- Заметки к сохранённым карточкам.
- Удаление карточки из коллекции.
- История открытий с фильтрами по паку, редкости и сохранённым.
- Фильтры коллекции по редкости, поиску и сортировке.
- Смена username и пароля.

## Что есть в админке

`GET /admin` доступен только пользователю с `role = admin`. Первый admin создаётся через `database/seed.php` из `config/config.php` или `config/config.example.php`: `ADMIN_EMAIL` и `ADMIN_DEFAULT_PASSWORD`. Дефолтный пароль годится только для локальной разработки.

- Dashboard со статистикой сайта.
- Управление паками: список, создание, редактирование, включение/выключение, sort order.
- Управление карточками: список, фильтры, поиск, создание, редактирование, включение/выключение.
- Пользователи: список, роль, блокировка/разблокировка, счётчики открытий и сохранений.
- Защита от случайного удаления последнего admin.
- Admin logs для важных действий.
- Внутренняя аналитика: посетители, события, топ event_type, топ открываемых паков и последние события.

## Локальная аналитика

Аналитика хранится только в SQLite, без Google Analytics и внешних сервисов. Сырой IP не сохраняется: пишутся `ip_hash` и `user_agent_hash`.

Пишутся события:

- `page_view`
- `pack_opened`
- `card_saved`
- `save_failed_guest`
- `register`, `login`, `logout`
- `profile_update`, `password_change`
- `admin_action`

## HTML маршруты

- `GET /` - рабочая главная страница.
- `GET /login` - минимальная форма входа.
- `GET /register` - минимальная форма регистрации.
- `GET /cabinet` - личный кабинет.
- `GET /profile` - alias на кабинет.
- `GET /admin` - dashboard админки.
- `GET /admin/packs` - управление паками.
- `GET /admin/predictions` - управление карточками.
- `GET /admin/users` - пользователи.
- `GET /admin/analytics` - внутренняя аналитика.
- `GET /admin/logs` - журнал действий.
- `POST /login` - вход с CSRF.
- `POST /register` - регистрация с CSRF.
- `POST /logout` - выход с CSRF.

## API endpoints

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

## Быстрые проверки

```bash
curl http://localhost:8000/api/health
curl http://localhost:8000/api/packs
```

Open daily:

```bash
curl -X POST http://localhost:8000/api/open-pack \
  -H "Content-Type: application/json" \
  -d "{\"pack\":\"daily\"}"
```

Open question:

```bash
curl -X POST http://localhost:8000/api/open-pack \
  -H "Content-Type: application/json" \
  -d "{\"pack\":\"question\",\"user_question\":\"Стоит ли сегодня начинать новое?\"}"
```

Open choice:

```bash
curl -X POST http://localhost:8000/api/open-pack \
  -H "Content-Type: application/json" \
  -d "{\"pack\":\"choice\",\"choice_a\":\"Сделать сейчас\",\"choice_b\":\"Отложить\"}"
```

История и статистика:

```bash
curl http://localhost:8000/api/history
curl http://localhost:8000/api/stats
```

Кабинет через cookie jar:

```bash
curl -c cookies.txt -b cookies.txt -X POST http://localhost:8000/api/auth/register \
  -H "Content-Type: application/json" \
  -d "{\"username\":\"tester\",\"email\":\"tester@example.com\",\"password\":\"password123\",\"password_confirm\":\"password123\"}"

curl -c cookies.txt -b cookies.txt -X POST http://localhost:8000/api/open-pack \
  -H "Content-Type: application/json" \
  -d "{\"pack\":\"daily\"}"

curl -c cookies.txt -b cookies.txt http://localhost:8000/api/cabinet/summary
curl -c cookies.txt -b cookies.txt http://localhost:8000/api/cabinet/history
curl -c cookies.txt -b cookies.txt http://localhost:8000/api/cabinet/saved
```

Для гостевой истории через curl используйте cookie jar:

```bash
curl -c cookies.txt -b cookies.txt http://localhost:8000/api/auth/me
curl -c cookies.txt -b cookies.txt -X POST http://localhost:8000/api/open-pack -H "Content-Type: application/json" -d "{\"pack\":\"daily\"}"
curl -c cookies.txt -b cookies.txt http://localhost:8000/api/history
```

## Проверки разработки

Эти проверки не требуют сброса базы:

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
C:\php\php.exe scripts\preflight.php
C:\php\php.exe scripts\smoke.php
C:\php\php.exe -S 127.0.0.1:8000 -t public
```

Не добавляйте `database\seed.php --fresh` в обычные проверки: это reset базы, а не smoke-test.

Если `sqlite3` установлен:

```bash
sqlite3 database/database.sqlite "SELECT COUNT(*) FROM packs;"
sqlite3 database/database.sqlite "SELECT COUNT(*) FROM predictions;"
sqlite3 database/database.sqlite "SELECT COUNT(*) FROM openings;"
```

Если `sqlite3` CLI недоступен:

```bash
php -r "$pdo=new PDO('sqlite:database/database.sqlite'); echo $pdo->query('SELECT COUNT(*) FROM openings')->fetchColumn(), PHP_EOL;"
```

Browser/UI-проверки и некоторые API-запросы могут писать runtime-события в SQLite: `page_view`, `openings`, `rate_limits`, `sessions`/cookies и связанные записи аналитики. Для чистых UI-тестов используйте копию базы.

## Audit and responsive pass

2026-05-23 проведён осторожный аудит и responsive-доводка без redesign, изменения schema/seed, auth, cabinet, admin, analytics и механики открытия карточек.

- Добавлены `docs/AUDIT_REPORT.md` и `docs/RESPONSIVE_REPORT.md`.
- Доведены layout guards, touch targets, login/register mobile padding, home pack grids, `/open` card sizing for 1/3/5 cards, cabinet inherited responsive behavior, admin filter bars and table wrappers.
- Дополнительно проверена и исправлена mobile-точка `/open` для 3 карт: первая карта теперь стартует внутри scroll-зоны и нормально раскрывается по tap/click.
- Убран лишний повторный запрос `/api/history` на главной при загрузке статистики.
- Оставшиеся production-риски: заменить `SESSION_SECRET`, сменить дефолтный admin password, добавить security headers/CSP, backup/restore, monitoring и log rotation.

## Hosting/VPS notes

- Document root должен указывать на `public/`.
- `config/config.php` создаётся вручную из `config/config.example.php`.
- `SESSION_SECRET` нужно заменить на длинную случайную строку.
- Дефолтный admin password нужно заменить сразу после установки.
- `database/database.sqlite` должен быть вне публичного доступа. В текущей структуре он вне `public/`.
- `storage/logs`, `storage/exports`, `storage/sessions` должны быть writable.
- На сервере должен быть включён `pdo_sqlite`.
- Не запускайте `seed.php --fresh` на живом сайте или рабочей локальной базе без backup и явного решения о полном reset.
- Backup базы: сохранить копию `database/database.sqlite`.

Пример backup в PowerShell:

```powershell
Copy-Item database\database.sqlite "storage\exports\database-backup-$(Get-Date -Format 'yyyyMMdd-HHmmss').sqlite"
```

Подробный workflow: `docs/BACKUP_AND_RESTORE.md`.

Что нельзя коммитить:

- `config/config.php`
- `database/database.sqlite`
- `storage/logs/*`
- `storage/exports/*`
- `storage/sessions/*`

## Что ещё не реализовано

- Экспорт статистики.
- PNG-скачивание карточек.
- Достижения в UI.
- Полный production hardening, мониторинг и настоящий деплой.

## Security notes

- Реальные секреты не хранятся в репозитории.
- `config/config.php` и `database/database.sqlite` добавлены в `.gitignore`.
- Пароли хранятся через `password_hash`.
- Аналитика не хранит сырой IP или полный User-Agent, только хеши.
- SQLite подключается через PDO с `ERRMODE_EXCEPTION`.
- Включаются foreign keys.
- Есть CSRF для HTML-форм, guest_id без персональных данных и базовый rate limit.
- Все карточки являются развлекательным случайным контентом и не являются медицинскими, финансовыми или юридическими рекомендациями.
