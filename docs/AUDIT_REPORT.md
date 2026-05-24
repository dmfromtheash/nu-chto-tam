# Audit Report

Дата: 2026-05-23

## Admin analytics update 2026-05-23

- Area: backend/frontend/accessibility/responsive
- Status: fixed
- Severity: low/medium

Что изменено: `/admin` и `/admin/analytics` получили русскоязычные KPI, фильтр периода, графики активности, популярные паки, распределение редкостей, агрегированную воронку, таблицы с точными числами и понятные empty states. `/admin/api/summary` и `/admin/api/analytics` расширены на существующих данных SQLite без schema/seed changes.

Как считаются ключевые метрики:

- Открытия паков: `events.event_type = 'pack_opened'`.
- Выпавшие карточки: строки `openings`.
- Сохранённые карточки: строки `saved_cards`.
- Посетители за период: `visitors.last_seen_at`; новые посетители: `visitors.first_seen_at`.
- Регистрации: `users.created_at`.
- Гостевые попытки сохранения: `events.event_type = 'save_failed_guest'`.

Ограничения, оставленные документированными:

- Воронка агрегированная за период, а не строгая user journey: visitor cookie может измениться после очистки cookies.
- Топ паков по открытиям опирается на события `pack_opened`; если исторические события неполные, рядом показываются фактические выпавшие карточки из `openings`, но открытий паков не выдумывается.
- Recent events показывают укороченный `visitor_id` и краткий payload summary; сырые IP, User-Agent, пароли, токены, секреты и хеши не выводятся.

## Что проверено

- Backend: `public/index.php`, `app/Core/*`, `app/Controllers/*`, `app/Services/*`, `app/Models/*`.
- Frontend: `app/Views/*`, `app/Views/admin/*`, `public/assets/css/*`, `public/assets/js/*`.
- Data/scripts: `database/schema.sql`, `database/seed.php`, `scripts/preflight.php`, `scripts/smoke.php`.
- Docs/config basics: `README.md`, `docs/PROJECT_STATE.md`, `config/config.php`, `.gitignore`.
- Runtime state in `storage/*` was treated as local artifacts, not source code.

## Найденные проблемы

| Severity | Area | Status | Problem |
| --- | --- | --- | --- |
| high | security | documented | `config/config.php` uses local default `SESSION_SECRET` and default admin password values. This is acceptable only for local demo. |
| medium | security | documented | No full production hardening package: no CSP/security header policy, no HTTPS-only deployment config, no log rotation/backup policy in code. |
| medium | security | skipped because high-risk | Public JSON auth endpoints do not use CSRF tokens. They require `Content-Type: application/json`, but changing CSRF policy would affect API clients and auth behavior. |
| medium | responsive | fixed | Several mobile/wide layouts could still create accidental horizontal pressure through wide grids, fixed table/filter tracks, or oversized card variables. |
| low | performance | fixed | Home stats loaded `/api/history` a second time while history was already loaded separately. |
| low | accessibility | fixed | Touch devices needed a clearer non-hover card affordance and more consistent touch target sizing. |
| low | responsive | fixed | Login/register panels and button rows needed tighter mobile padding and stacking. |
| low | responsive | fixed | Admin filter bars could overflow around tablet widths because they used six fixed min columns. |
| low | responsive | fixed | On narrow `/open` 3-card results, shared card-grid CSS could keep center alignment and leave the first card partially outside the initial scroll position. |
| cosmetic | docs | fixed | No audit/responsive reports existed for this pass. |

## Что исправлено

- Added global overflow guards and media-safe touch sizing in `public/assets/css/base.css`.
- Improved login/register mobile padding, stacked button rows, and min-width handling for form controls.
- Tightened home wide max-width and made pack category grids adapt with `auto-fit` plus a 320-380px single-column fallback.
- Added a coarse pointer/touch card affordance without changing click-to-reveal or hover rarity glow.
- Tuned `/open` card variables for tablet and phone widths while preserving 1/3/5 layouts, equal front/back sizing, and manual reveal.
- Kept `/open` one-card results centered and 3/5-card results scrollable on narrow screens.
- Fixed narrow `/open` 3-card scroll alignment so the first card starts inside the scroll area and can be tapped/revealed.
- Changed admin filter grids to responsive `auto-fit` columns and improved horizontal table wrappers for touch scrolling.
- Removed the duplicate `/api/history` request from home stats loading.

## Browser checks performed

- Checked `/`, `/login`, `/register` at 320, 360, 390, 430, 768, 820, 1024, 1280, 1366, 1440, 1680, 1920, 2560: no body horizontal overflow or console errors.
- Checked `/open?pack=daily`, `/open?pack=weekly`, `/open?pack=monthly` across the same width set: card counts, click reveal, equal front/back sizes, no card overlap, no body overflow.
- Checked special open flows at 390px: `question`, `choice`, `mood`, `direction`, `take-leave`; context fields/radios worked and cards revealed by click.
- Checked authenticated `/cabinet`, `/admin`, `/admin/analytics`, `/admin/packs`, `/admin/predictions`, `/admin/users`, `/admin/logs` at 320, 390, 768, 1024, 1366, 1920: no body horizontal overflow or console errors.
- Checked reduced motion toggle on `/`: it updates `html[data-reduced-motion]` without console errors.

## Что оставлено как риск

- Production secrets must be replaced outside this audit: `SESSION_SECRET`, default admin password, and any deployment-specific config.
- Full HTTP security headers/CSP, backup automation, retention policy, monitoring, and log rotation are documented but not implemented.
- API auth CSRF policy remains unchanged to avoid breaking existing API/auth behavior.
- No schema/seed changes were made; `database/seed.php --fresh` remains destructive and should not be used on production data without backup.
- There is no git repository metadata in this workspace, so file change review must be done by file inspection rather than `git diff`.

## Что требует ручной проверки

- Manual visual pass on real devices, especially touch scrolling feel for 3/5 cards and long admin tables.
- Authenticated mutation flows not changed here but still worth manual confirmation: save-card, note edit/delete, profile/password forms, admin create/edit/toggle actions.
- Production deployment configuration, headers, HTTPS, backups, log rotation, and secret rotation.

## Production-рекомендации

- Replace `SESSION_SECRET` with a long random value and change the seeded admin password before any non-local use.
- Keep document root pointed to `public/`; keep `database/database.sqlite`, `config/config.php`, and `storage/*` outside public access.
- Add backup/restore instructions for SQLite and test restore before launch.
- Add security headers and CSP after validating inline scripts/styles and admin workflows.
- Add lightweight monitoring for PHP errors, failed admin actions, and SQLite file writability.
