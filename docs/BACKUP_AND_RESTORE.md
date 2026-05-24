# Backup and Restore

## 1. Зачем нужен backup

`database/database.sqlite` — главный рабочий state-файл проекта. В нём живут не только стартовые паки и карточки, но и пользовательские runtime-данные:

- пользователи и роли;
- история открытий;
- сохранённые карточки;
- аналитика и события;
- visitors и rate limits;
- правки через админку;
- site settings и другой рабочий SQLite-state.

Потеря `database/database.sqlite` означает потерю пользователей, аналитики, истории, сохранённых карточек и изменений, сделанных через админку. `database/seed.php --fresh` не является restore-командой: он пересоздаёт базу и стирает текущие runtime-данные.

## 2. Когда обязательно делать backup

Делайте backup перед любой задачей, где есть риск затронуть данные:

- перед любыми database/content changes;
- перед admin/content import;
- перед browser/UI regression, если тесты идут на рабочей базе;
- перед deploy или переносом проекта;
- перед правками `database/schema.sql`;
- перед правками `database/seed.php`;
- перед любыми сомнительными Codex-задачами;
- перед `seed.php --fresh`, если пользователь когда-либо явно разрешит полный reset.

Если сомневаетесь, нужен ли backup, считайте, что нужен.

## 3. Быстрый backup в PowerShell

Запускать из корня проекта:

```powershell
Copy-Item database\database.sqlite "storage\exports\database-backup-$(Get-Date -Format 'yyyyMMdd-HHmmss').sqlite"
```

Что важно:

- команда создаёт копию текущей базы с timestamp в имени;
- `storage/exports` находится вне `public/`, поэтому обычный document root не отдаёт backup напрямую;
- backup нельзя коммитить;
- backup может содержать пользовательские данные и должен храниться как чувствительный файл;
- не отправляйте backup друзьям, в чат или в публичное облако без отдельного решения по приватности.

## 4. Проверка, что backup создан

Посмотреть последние backup-файлы:

```powershell
Get-ChildItem storage\exports\database-backup-*.sqlite | Sort-Object LastWriteTime -Descending | Select-Object -First 5 Name, Length, LastWriteTime
```

Проверьте:

- файл появился в списке;
- `Length` не равен `0`;
- размер backup примерно похож на размер `database/database.sqlite`.

Посмотреть размер текущей рабочей базы:

```powershell
Get-Item database\database.sqlite | Select-Object Name, Length, LastWriteTime
```

Небольшая разница в размере нормальна, если база менялась между копиями. Нулевой или неожиданно маленький backup нельзя считать надёжным.

## 5. Как сделать тестовую копию базы для UI/browser-проверок

UI/browser/API-проверки могут писать runtime-события в SQLite: `page_view`, `openings`, `events`, `rate_limits`, `sessions/cookies`. Если нужно сохранить рабочую базу полностью неизменной, не запускайте такие проверки на `database/database.sqlite`.

Проект поддерживает путь к базе через `DB_PATH` в config, а также временный env override `NCHT_DB_PATH`. Если `NCHT_DB_PATH` задан и не пустой, runtime, `scripts/preflight.php` и `scripts/smoke.php` используют его как путь к SQLite. Если переменная не задана, обычное поведение не меняется: проект берёт `DB_PATH` из `config/config.php` или fallback на `database/database.sqlite`.

Безопасная идея для ручной проверки:

1. Сначала сделать backup рабочей базы.
2. Скопировать `database/database.sqlite` в отдельный файл вне `public/`, например в `storage/exports/local-test-database.sqlite`.
3. Временно указать тестовый путь через `NCHT_DB_PATH`.
4. Запустить read-only проверки и PHP server уже с тестовым `DB_PATH`.
5. Выполнять UI/browser/API-проверки на тестовой копии.
6. После проверки остановить PHP server и очистить переменную окружения.

Пример PowerShell-команд для будущего запуска на уже созданной тестовой копии:

```powershell
$env:NCHT_DB_PATH = "C:\Projects\web\web2\storage\exports\local-test-database.sqlite"
C:\php\php.exe scripts\preflight.php
C:\php\php.exe scripts\smoke.php
C:\php\php.exe -S 127.0.0.1:8000 -t public
```

После тестов очистите переменную:

```powershell
Remove-Item Env:\NCHT_DB_PATH
```

`NCHT_DB_PATH` не создаёт тестовую базу сам. Файл нужно заранее создать как копию рабочей базы, а не через `seed.php --fresh`. Не подменяйте живую базу без backup и не редактируйте `config/config.php` вслепую, если можно использовать env override.

## 6. Restore: как восстановить базу

Restore — опасная операция, потому что она заменяет текущую рабочую базу выбранным backup-файлом. Делайте её только осознанно.

Осторожный ручной порядок:

1. Остановить PHP server и tunnel, если они запущены.
2. Сделать backup текущего состояния перед restore.
3. Выбрать нужный backup-файл.
4. Скопировать выбранный backup поверх `database/database.sqlite`.
5. Снова запустить PHP server.
6. Проверить состояние read-only скриптами или read-only endpoint.

Backup текущего состояния перед restore:

```powershell
Copy-Item database\database.sqlite "storage\exports\database-before-restore-$(Get-Date -Format 'yyyyMMdd-HHmmss').sqlite"
```

Пример restore из конкретного backup-файла:

```powershell
Copy-Item "storage\exports\database-backup-20260524-153000.sqlite" database\database.sqlite
```

Замените `database-backup-20260524-153000.sqlite` на реальное имя выбранного backup. Не запускайте restore при работающем PHP server: можно получить гонку записи или неконсистентное состояние.

Read-only проверки после restore:

```powershell
C:\php\php.exe scripts\preflight.php
C:\php\php.exe scripts\smoke.php
```

`scripts/preflight.php` и `scripts/smoke.php` читают config/SQLite и выполняют `SELECT`-проверки. Они не пишут в базу или runtime-файлы. Если нужен HTTP-check, используйте только read-only endpoint вроде `/api/health`; помните, что browser/UI-заходы могут писать `page_view` и cookies/sessions.

## 7. Что нельзя делать

- Не хранить backup в `public/`.
- Не отправлять backup друзьям.
- Не коммитить backup.
- Не запускать `seed.php --fresh` вместо restore.
- Не делать restore при работающем PHP server.
- Не удалять старые backup без проверки.
- Не считать Cloudflare Tunnel backup-решением.
- Не считать docs/scripts заменой реального backup.
- Не держать единственный backup только на том же диске, если данные важны.
- Не подменять `database/database.sqlite` без свежего backup текущего состояния.

## 8. Backup перед Codex-задачами

Короткий чек-лист перед новой задачей:

- Режим задачи docs-only, frontend-only, read-only или database?
- Трогается ли `database/database.sqlite`?
- Трогаются ли `database/schema.sql` или `database/seed.php`?
- Трогается ли admin/content/import?
- Будут ли browser/API actions?
- Могут ли они писать `page_view`, `openings`, `events`, `rate_limits`, `sessions/cookies`?
- Нужен ли backup?

Если ответ неочевиден — сделать backup до начала работы.

Для Codex-задач безопасная формулировка:

```text
Перед любыми действиями, которые могут затронуть SQLite или runtime-данные, сначала предложи backup:
Copy-Item database\database.sqlite "storage\exports\database-backup-$(Get-Date -Format 'yyyyMMdd-HHmmss').sqlite"
Не запускай seed.php --fresh без явного разрешения.
```

## 9. Backup перед deploy/local demo

Перед показом друзьям через Cloudflare Tunnel можно сделать backup, особенно если вы собираетесь открывать паки, проверять регистрацию, кабинет или админку на рабочей базе. Tunnel не является backup-решением: он только открывает локальный сайт наружу.

Перед переносом на hosting/VPS backup обязателен:

- сохранить копию текущего `database/database.sqlite`;
- отдельно сохранить актуальный `config/config.php` безопасным способом;
- проверить, что document root указывает на `public/`;
- убедиться, что `database/`, `config/` и `storage/` не публичны;
- проверить, что `storage/logs`, `storage/exports`, `storage/sessions` writable для PHP-процесса;
- проверить `pdo_sqlite`.

После переноса сначала используйте read-only проверки. Не запускайте `seed.php --fresh` на перенесённой рабочей базе.

## 10. Короткая памятка

Сделать backup:

```powershell
Copy-Item database\database.sqlite "storage\exports\database-backup-$(Get-Date -Format 'yyyyMMdd-HHmmss').sqlite"
```

Посмотреть последние backup:

```powershell
Get-ChildItem storage\exports\database-backup-*.sqlite | Sort-Object LastWriteTime -Descending | Select-Object -First 5 Name, Length, LastWriteTime
```

Что не запускать вместо restore:

```text
seed.php --fresh
database/seed.php --fresh
php database/seed.php --fresh
C:\php\php.exe database\seed.php --fresh
```

Когда звать Codex только на read-only audit:

- если нужно понять состояние базы, но не менять её;
- если нужно проверить документацию или план;
- если задача касается deploy/demo и есть риск случайно задеть runtime;
- если нужно оценить, какие команды безопасны, до запуска любых browser/API/write actions.
