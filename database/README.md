# Database

## Состав

`database/schema.sql` описывает SQLite-схему проекта:

- `users`
- `packs`
- `predictions`
- `openings`
- `saved_cards`
- `achievements`
- `user_achievements`
- `admin_logs`
- `visitors`
- `events`
- `site_settings`
- `rate_limits`

В схеме есть внешние ключи, индексы и CHECK constraints для ролей, типов паков и редкостей.

`visitors` и `events` используются для лёгкой внутренней аналитики. Сырой IP и полный User-Agent не сохраняются, только `ip_hash` и `user_agent_hash`.

## Стартовый контент

`seed.php` создаёт 14 стартовых паков и 154 карточки. Тон карточек casual: бытовой, дружелюбный, немного смешной, без серьёзной астрологии, без зодиакального деления и без пафосной эзотерики.

Все тексты являются развлекательным случайным контентом.

## Создание базы

```bash
php database/seed.php --fresh
```

Скрипт создаёт `database/database.sqlite`, применяет `schema.sql` и добавляет стартовые данные.

## Fresh reset

```bash
php database/seed.php --fresh
```

Флаг `--fresh` удаляет текущий SQLite-файл и создаёт его заново. Это удобно для локальной разработки, но не подходит для production без backup.

## Повторный запуск без fresh

```bash
php database/seed.php
```

Повторный запуск обновляет стартовые паки, достижения и настройки. Карточки добавляются только в те стартовые паки, где их ещё нет, чтобы не создавать дубликаты.

## Администратор

Seed берёт администратора из `config/config.php`:

- `ADMIN_EMAIL`
- `ADMIN_DEFAULT_PASSWORD`

Также можно передать значения через CLI:

```bash
php database/seed.php --admin-email=admin@example.test --admin-password=local-password
```

Пароль сохраняется через `password_hash`. Не используйте дефолтный пароль вне локальной разработки.

## Проверка данных

Если установлен `sqlite3`:

```bash
sqlite3 database/database.sqlite ".tables"
sqlite3 database/database.sqlite "SELECT COUNT(*) FROM packs;"
sqlite3 database/database.sqlite "SELECT COUNT(*) FROM predictions;"
sqlite3 database/database.sqlite "SELECT slug, title FROM packs ORDER BY sort_order, id;"
```

Если `sqlite3` CLI недоступен:

```bash
php -r "$pdo=new PDO('sqlite:database/database.sqlite'); echo $pdo->query('SELECT COUNT(*) FROM packs')->fetchColumn(), PHP_EOL;"
php -r "$pdo=new PDO('sqlite:database/database.sqlite'); echo $pdo->query('SELECT COUNT(*) FROM predictions')->fetchColumn(), PHP_EOL;"
```

## Изменение стартовых данных

- Пакеты редактируются в массиве `$packs` внутри `database/seed.php`.
- Тексты карточек редактируются в `$predictionTexts`.
- Редкости распределяются через массив `$rarities`.
- Достижения редактируются в `$achievements`.
- Настройки редактируются в `$settings`.

После изменения сидов для чистой проверки запустите:

```bash
php database/seed.php --fresh
```
