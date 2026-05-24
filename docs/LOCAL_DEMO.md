# Локальное демо через Cloudflare Tunnel

Эта инструкция нужна только для показа локального сайта “Ну что там?” через временную ссылку Cloudflare Quick Tunnel. Она не меняет код сайта, базу, schema/seed, auth, кабинет, админку или аналитику.

## Что требуется

- Windows PowerShell.
- PHP по пути `C:\php\php.exe`.
- Рабочий локальный проект с папкой `public`.
- Установленный `cloudflared`.
- Доступ из сети к `https://api.trycloudflare.com/tunnel`.

Quick Tunnel зависит от Cloudflare API. Если `api.trycloudflare.com` уходит в timeout, это не ошибка PHP-сайта и не проблема маршрутов проекта.

## 1. Запустить локальный сайт

Откройте первое окно PowerShell:

```powershell
cd C:\Projects\web\web2
powershell -ExecutionPolicy Bypass -File scripts\start-local.ps1
```

Скрипт проверит:

- что есть папка `public`;
- что существует `C:\php\php.exe`;
- что запуск идёт из корня проекта.

Он запустит:

```powershell
C:\php\php.exe -S 127.0.0.1:8000 -t public
```

После запуска сайт доступен здесь:

```text
http://127.0.0.1:8000
```

Важно: `scripts\start-local.ps1` не запускает `seed --fresh` автоматически и не сбрасывает SQLite.

Перед демо на уже существующей базе можно выполнить read-only проверки:

```powershell
C:\php\php.exe scripts\preflight.php
C:\php\php.exe scripts\smoke.php
```

По коду эти скрипты читают конфиг и SQLite, проверяют окружение и таблицы/счётчики через `SELECT`, но не пишут в `database/database.sqlite` и runtime-файлы.

Если нужно запустить local demo на тестовой копии SQLite, используйте env override `NCHT_DB_PATH` и заранее созданную копию базы вне `public/`. Команды и ограничения описаны в `docs/BACKUP_AND_RESTORE.md`.

## 2. Запустить Cloudflare Tunnel

Откройте второе окно PowerShell:

```powershell
cd C:\Projects\web\web2
powershell -ExecutionPolicy Bypass -File scripts\start-tunnel.ps1
```

Скрипт проверит:

- что `cloudflared` доступен в `PATH`;
- что локальный сайт отвечает на `http://127.0.0.1:8000`.

Если всё нормально, он запустит:

```powershell
cloudflared tunnel --edge-ip-version 4 --url http://127.0.0.1:8000 --no-autoupdate
```

В выводе `cloudflared` появится временная ссылка вида:

```text
https://something.trycloudflare.com
```

Пока открыты оба окна PowerShell, ссылка работает. Если закрыть PHP-сервер или `cloudflared`, демо перестанет открываться.

## Если cloudflared не найден

Установите через winget:

```powershell
winget install --id Cloudflare.cloudflared -e
```

После установки откройте новое окно PowerShell и проверьте:

```powershell
cloudflared --version
```

## Диагностика timeout

Если tunnel падает с ошибкой:

```text
failed to request quick Tunnel: Post "https://api.trycloudflare.com/tunnel": context deadline exceeded
```

запустите:

```powershell
cd C:\Projects\web\web2
powershell -ExecutionPolicy Bypass -File scripts\check-tunnel.ps1
```

Скрипт проверяет:

- локальный сайт `http://127.0.0.1:8000`;
- `cloudflared --version`;
- DNS для `api.trycloudflare.com`;
- TCP-доступ к `api.trycloudflare.com:443`;
- `Invoke-WebRequest` POST к `https://api.trycloudflare.com/tunnel`;
- `curl.exe -4 -v -X POST` к тому же API;
- наличие `%USERPROFILE%\.cloudflared\config.yaml`.

Если DNS работает, но HTTPS POST уходит в timeout, проблема почти наверняка вне PHP-проекта: сеть, VPN, firewall, антивирус, провайдерский маршрут или временная недоступность Cloudflare API.

Что попробовать:

1. Включить или выключить VPN.
2. Попробовать мобильный интернет.
3. Проверить firewall/антивирус и правила для `cloudflared.exe`.
4. Попробовать позже.
5. В будущем использовать named tunnel через аккаунт Cloudflare.

## Быстрая проверка сайта перед отправкой ссылки

Проверки в браузере и часть API-запросов могут создавать runtime-записи в SQLite: `page_view`, `openings`, `rate_limits`, `sessions`/cookies и связанные события аналитики. Если нужна чистая UI-проверка без следов в рабочей базе, сначала работайте на копии `database/database.sqlite`.

Перед демо или проверками на рабочей базе используйте backup/restore workflow: `docs/BACKUP_AND_RESTORE.md`.

Проверьте в браузере:

- `http://127.0.0.1:8000/`
- `http://127.0.0.1:8000/api/packs`
- `http://127.0.0.1:8000/api/history`
- `http://127.0.0.1:8000/api/stats`

Если локальные URL отвечают, а Quick Tunnel не создаётся, чинить PHP-код проекта не нужно. Нужно диагностировать доступ к Cloudflare API.
