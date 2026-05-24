# Deployment Notes

Это практичные заметки для обычного PHP-хостинга или VPS. Это не полный production hardening.

## Требования

- PHP 8.1+;
- `pdo_sqlite`;
- возможность указать document root;
- writable директории `storage/logs`, `storage/exports`, `storage/sessions`;
- возможность хранить SQLite файл вне публичной директории.

## Document root

Document root должен указывать на:

```text
public/
```

Нельзя направлять document root на корень проекта, иначе `config/`, `database/` и `storage/` могут стать публичными.

## Конфиг

Создайте вручную:

```text
config/config.php
```

На основе:

```text
config/config.example.php
```

Перед демо/production обязательно:

- заменить `SESSION_SECRET`;
- заменить `ADMIN_DEFAULT_PASSWORD` или сменить пароль admin после входа;
- проверить `APP_URL`;
- включить подходящий `APP_ENV`.

## SQLite

Текущий путь по умолчанию:

```text
database/database.sqlite
```

Файл находится вне `public/`, поэтому обычный document root его не отдаёт. Обычный запуск существующего проекта и deployment не требуют `seed --fresh`: переносите текущий `database/database.sqlite` как пользовательские runtime-данные.

Перед переносом живой базы, reset или любыми работами с риском изменения базы сделайте backup:

```powershell
Copy-Item database\database.sqlite "storage\exports\database-backup-$(Get-Date -Format 'yyyyMMdd-HHmmss').sqlite"
```

Подробный backup/restore workflow: `docs/BACKUP_AND_RESTORE.md`.

Не запускайте `php database\seed.php --fresh` на живом сайте или рабочей локальной базе без backup и явного решения о полном reset. `--fresh` допустим только для пустого проекта/копии базы или намеренного пересоздания всех данных.

Для безопасной проверки окружения без сброса базы:

```powershell
C:\php\php.exe scripts\preflight.php
C:\php\php.exe scripts\smoke.php
```

Эти два скрипта по коду выполняют read-only проверки и `SELECT`-запросы к SQLite. Browser/UI-проверки и POST-запросы могут писать `page_view`, `openings`, `rate_limits`, `sessions`/cookies и события аналитики, поэтому для чистых UI-тестов используйте копию базы.

## Writable директории

Должны быть доступны на запись PHP-процессу:

```text
storage/logs
storage/exports
storage/sessions
```

## Apache

В `public/.htaccess` уже есть базовые правила для запрета листинга и fallback. На хостинге проверьте, что `.htaccess` разрешён.

## VPS

Для VPS можно использовать Apache или Nginx + PHP-FPM. Главное:

- root указывает на `public/`;
- PHP-FPM имеет доступ к `storage/*` и `database/database.sqlite`;
- `pdo_sqlite` установлен;
- логи и backup настроены отдельно.

## Security checklist

- `config/config.php` не коммитится;
- `database/database.sqlite` не коммитится;
- `storage/logs/*`, `storage/exports/*`, `storage/sessions/*` не коммитятся;
- сырой IP/User-Agent не сохраняются, только hash;
- пароли хранятся через `password_hash`;
- admin POST actions защищены CSRF;
- admin actions пишутся в `admin_logs` и `events`;
- дефолтный admin password не используется за пределами локальной разработки.
