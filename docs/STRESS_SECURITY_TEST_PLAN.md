# Stress/Security Test Plan

## 1. Назначение

Этот документ описывает безопасный план будущих stress, security и abuse-проверок проекта "Ну что там?".

Это не запуск тестов и не инструкция выполнять нагрузку прямо сейчас. Любые browser/API/admin mutation tests, stress tests и проверки, которые могут писать runtime-данные, нельзя запускать на live `database/database.sqlite`.

Live `database/database.sqlite` считается рабочей пользовательской SQLite-базой. Для mutation/stress/browser проверок нужна тестовая копия базы через `NCHT_DB_PATH`.

## 2. Safety Rules

- Считать `database/database.sqlite` live runtime DB.
- Не запускать `database/seed.php`.
- Не запускать `database/seed.php --fresh`.
- Не использовать `seed --fresh` как restore, repair или подготовку test DB.
- Для mutation/browser/API/admin tests использовать только копию SQLite через `NCHT_DB_PATH`.
- Перед сомнительными действиями делать backup рабочей базы.
- Не отправлять backup или test DB в публичные места, чаты, публичные облака или репозиторий.
- Не коммитить backup, test DB, runtime logs, sessions или exports.
- Не читать и не выводить `config/config.php`.
- Не выводить секреты. Проверять production-sensitive config только через безопасные статусы вроде `scripts/preflight.php`.
- Не смешивать test execution и code fixes в один этап.

## 3. Test Environment Plan

Безопасная схема для будущих mutation/stress/browser проверок:

1. Остановить лишние локальные серверы, если есть риск перепутать окружение.
2. Сделать backup рабочей базы, если задача потенциально затрагивает runtime-данные.
3. Скопировать `database/database.sqlite` в отдельный test DB файл вне `public/`, например:

```powershell
Copy-Item database\database.sqlite storage\exports\local-test-database.sqlite
```

4. Запустить проверки и PHP server с env override:

```powershell
$env:NCHT_DB_PATH = "C:\Projects\web\web2\storage\exports\local-test-database.sqlite"
C:\php\php.exe scripts\preflight.php
C:\php\php.exe scripts\smoke.php
C:\php\php.exe -S 127.0.0.1:8000 -t public
```

5. Выполнять browser/API/admin mutation tests только пока `NCHT_DB_PATH` указывает на test DB.
6. После тестов остановить PHP server и очистить переменную:

```powershell
Remove-Item Env:\NCHT_DB_PATH
```

7. Test DB после проверки удалить или архивировать как чувствительный файл. Если в ней есть пользователи, история, события или админские правки, обращаться с ней как с пользовательскими данными.
8. Не коммитить `storage/exports/local-test-database.sqlite` и любые backup/test DB файлы.

## 4. Группы Будущих Проверок

### A. Read-Only Checks

Цель: подтвердить базовое состояние без записи в runtime.

- `scripts/preflight.php`.
- `scripts/smoke.php`.
- Route inventory по `public/index.php`.
- Review security headers and CSP policy по коду/docs без внедрения.
- Review public/private directory exposure: `public/` как document root, `config/`, `database/`, `storage/` вне public.
- Docs/config consistency: README, deployment, local demo, backup/restore notes.

Ожидание: эти проверки не пишут в SQLite, если не открывать обычные browser pages. HTTP/browser заходы на `/`, `/open`, `/cabinet`, `/admin` могут писать `page_view` и cookies/sessions, поэтому они не входят в live read-only набор.

### B. Open-Pack Abuse/Stress

Цель: проверить публичный write endpoint `POST /api/open-pack` и рост runtime-таблиц.

- Repeated `POST /api/open-pack` для одного guest/user.
- Rate limit behavior: текущий лимит для `open-pack` - 60 попыток на 600 секунд на identifier.
- Invalid pack slug/id.
- Missing pack.
- Invalid context payloads: пустые поля, очень длинные строки, лишние поля, malformed JSON, wrong content-type.
- Multi-card packs: daily 1, weekly 3, monthly 5, остальные по `cards_per_open`.
- Рост таблиц `openings`, `events`, `rate_limits`.
- Поведение при rate limit: безопасный JSON error, без PHP warning/trace.

Риски: эта группа намеренно пишет `openings`, `events` и `rate_limits`. Только test DB.

### C. Save-Card Security

Цель: подтвердить CSRF, auth и ownership для `POST /api/save-card`.

- Missing CSRF token.
- Invalid CSRF token.
- Valid CSRF token.
- Guest save attempt должен возвращать auth/guest response, не записывая user collection.
- Invalid `opening_id`.
- `opening_id`, принадлежащий другому пользователю.
- Duplicate save behavior.
- Проверить, что saved card создаётся только для текущего authenticated user.

Риски: создаёт `saved_cards` и `events`. Только test DB.

### D. Auth/Register/Login/Logout

Цель: проверить auth behavior, rate limit, session handling и новый перенос гостевой истории.

- Invalid credentials.
- Repeated login/register attempts and rate limit.
- Registration with existing email.
- Registration with malformed email/short password.
- Guest openings transfer on registration: `openings.guest_id` текущего guest переносится в `user_id`, а `guest_id` очищается.
- Login existing account не должен переносить guest history в этом этапе.
- Logout сохраняет guest mode behavior.
- JSON auth API policy gap: `/api/auth/register`, `/api/auth/login`, `/api/auth/logout` пока без CSRF; это нужно фиксировать отдельной API-policy задачей, если принимается решение.
- Session regeneration checks: register/login/logout должны менять session id по ожидаемой логике.

Риски: создаёт пользователей, меняет session, пишет analytics/events/rate_limits/openings при сценарии transfer. Только test DB.

### E. Cabinet Mutations

Цель: проверить authenticated cabinet JSON mutations.

- Update saved-card note.
- Delete saved card.
- Profile update.
- Change password.
- CSRF missing/invalid для каждого mutation endpoint.
- Ownership checks: нельзя менять/удалять чужие saved cards.
- Invalid payloads and wrong content-type.
- Expected safe JSON errors.

Риски: меняет `saved_cards`, `users`, `events`. Только test DB.

### F. Admin Negative Tests

Цель: проверить admin boundaries and destructive guards.

- Block/unblock users.
- Role update.
- Last active admin guard: нельзя заблокировать или понизить последнего active admin.
- Self-block guard.
- CSRF missing/invalid для admin POST endpoints.
- Non-admin access к `/admin` и `/admin/api/*`.
- Blocked user behavior.
- Admin logs записываются без секретов.

Риски: меняет users/content/admin_logs/events. Только test DB.

### G. XSS/Escaping Light Probes

Цель: лёгкие harmless probes для escape boundaries без вредных payloads.

- Admin content fields: pack title/description, prediction title/body, UI texts.
- User notes.
- Username/profile fields.
- Cabinet/history/rendering paths.
- Open-pack card rendering.

Использовать только безвредные строки, например:

```text
<test-tag data-check="xss">plain text</test-tag>
"quotes" & symbols < >
```

Не использовать payloads, которые пытаются выполнить реальный JavaScript, воровать cookies или обходить browser protections. Не хранить probes в live DB.

### H. Input Limits/Fuzzing Light

Цель: проверить безопасные ошибки и отсутствие trace output на странных входных данных.

- Empty strings.
- Very long strings.
- Malformed JSON.
- Wrong `Content-Type`.
- Numeric edge cases: `0`, negative ids, huge ids, non-digit ids.
- Special symbols and emoji.
- Missing required fields.
- Extra fields in JSON.

Ожидание: controlled `400`, `401`, `403`, `404`, `415`, `419`, `422` или `429`, без PHP notices, stack traces и raw SQL details.

### I. Production/VPS Checks

Цель: проверить production assumptions без разворачивания новой архитектуры.

- Document root должен быть `public/`.
- `config/`, `database/`, `storage/` не должны быть публично доступны.
- `config.example.php` fallback не должен использоваться в production.
- `SESSION_SECRET` должен быть production-safe; значение не выводить.
- `/api/health` не должен раскрывать лишние сведения.
- Future headers checks: CSP, HSTS, `X-Frame-Options`/`frame-ancestors`, `X-Content-Type-Options`, referrer policy.
- HTTPS/proxy checks: `Secure` cookies при HTTPS, корректность PHP-FPM/proxy headers.
- Writable dirs: `storage/logs`, `storage/exports`, `storage/sessions`.

Эта группа в основном read-only/server-config. HTTP checks обычных страниц могут писать `page_view`; для чистого браузерного прохода использовать test DB.

### J. Backup/Restore Drill

Цель: доказать, что backup и restore workflow работает без `seed --fresh`.

- Создать backup.
- Проверить, что файл существует и `Length` не равен `0`.
- Restore делать не поверх live DB, а в test copy.
- Запустить `scripts/preflight.php` и `scripts/smoke.php` на restored test copy через `NCHT_DB_PATH`.
- Проверить, что restored test copy содержит ожидаемые таблицы и данные.
- Никогда не использовать `seed --fresh` как restore.

Риски: backup/test DB содержит пользовательские данные. Не коммитить и не публиковать.

### K. Performance Smoke

Цель: грубо оценить локальную деградацию без destructive load.

- Локальные короткие проверки только на test DB.
- Не делать production/load attack.
- Не запускать агрессивный параллельный flood.
- Следить за ростом `openings`, `events`, `rate_limits`.
- Проверить, что repeated requests дают controlled errors/rate limit, а не PHP fatal/timeout.
- Для frontend motion/browser checks смотреть console errors, layout shift, horizontal overflow, reduced motion path.

## 5. Risk Matrix

| Group | Risk level | Live DB allowed? | Needs test DB? | Can automate later? | Likely follow-up type |
| --- | --- | --- | --- | --- | --- |
| A. Read-only checks | Low | Yes, only proven SELECT/code/docs checks | No | Yes | docs/test |
| B. Open-pack abuse/stress | High | No | Yes | Yes | test/security/code |
| C. Save-card security | High | No | Yes | Yes | test/security |
| D. Auth/register/login/logout | High | No | Yes | Yes | test/security/code |
| E. Cabinet mutations | High | No | Yes | Yes | test/security/code |
| F. Admin negative tests | High | No | Yes | Yes | test/security/code |
| G. XSS/escaping light probes | Medium/High | No | Yes | Partly | security/frontend/code |
| H. Input limits/fuzzing light | Medium | No for mutation endpoints | Yes for mutation endpoints | Yes | test/security/code |
| I. Production/VPS checks | Medium | Conditional: code/server review yes, browser page checks no | For browser checks | Partly | docs/security/deploy |
| J. Backup/restore drill | Medium | Backup yes, restore no | Yes | Partly | ops/docs/test |
| K. Performance smoke | Medium/High | No | Yes | Partly | test/frontend/security |

## 6. Execution Order

Рекомендуемый порядок будущих этапов:

1. Read-only checks: route inventory, docs/config consistency, security headers review.
2. Test DB drill: подготовить копию базы через `NCHT_DB_PATH` и подтвердить preflight/smoke на копии.
3. Save-card/auth/open-pack negative tests на test DB.
4. Cabinet/admin mutation tests на test DB.
5. XSS/escaping light probes and input fuzzing на test DB.
6. Backup/restore drill только на test copy.
7. Performance smoke без destructive load.

Каждый этап должен быть отдельной маленькой задачей. Если тест находит баг, fix должен быть отдельным code-step с собственными проверками.

## 7. What Not To Do

- Не запускать stress tests на live `database/database.sqlite`.
- Не делать destructive load.
- Не использовать реальные личные данные.
- Не коммитить backups/test DB.
- Не запускать `database/seed.php`.
- Не запускать `database/seed.php --fresh`.
- Не смешивать test execution и fixing в один этап.
- Не подключать payments/Stripe.
- Не начинать DB migration.
- Не менять production security headers в рамках test-plan задачи.
- Не добавлять новые зависимости или automation scripts без отдельного решения.

## 8. Future Automation Ideas

Не реализовывать сейчас. Возможные будущие маленькие этапы:

- `scripts/test-db-smoke.ps1`: проверяет, что `NCHT_DB_PATH` задан, указывает вне `public/`, файл существует, и запускает preflight/smoke на test DB.
- Safe HTTP test scripts: маленькие сценарии для `/api/open-pack`, `/api/save-card`, `/api/auth/*`, cabinet/admin negative cases с обязательной проверкой `NCHT_DB_PATH`.
- Separate fixtures/test DB later: подготовленная обезличенная SQLite-копия для повторяемых тестов.
- Lightweight route inventory script: выводит GET/POST маршруты и помечает state-changing endpoints.
- Security regression checklist per endpoint: expected status codes, CSRF requirement, auth/role requirement, expected DB writes.

Любая автоматизация mutation tests должна сначала проверять, что она не работает с live `database/database.sqlite`.
