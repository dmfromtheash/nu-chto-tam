# Motion and Animation Plan

## 1. Цель motion-дизайна

Анимации в "Ну что там?" должны работать как часть продукта pack-opening, а не как слой украшений поверх сайта. Главная должна ощущаться как витрина паков: пользователь выбирает понятный бустер, видит живой отклик на hover/focus/tap и переходит в отдельную сцену открытия. `/open?pack=slug` должен быть главным местом premium-motion: sealed pack -> opening -> cards dealt -> manual reveal -> save/again.

Текущий проект уже задаёт важные ограничения:

- все указанные для исследования файлы найдены;
- главная строит featured-паки и сетку паков через `app/Views/home.php` и `public/assets/js/app.js`;
- `/open` использует `app/Views/open_pack.php`, `public/assets/js/open-pack.js`, `public/assets/css/open-pack.css` и `public/assets/css/game-cards.css`;
- карточки сначала создаются рубашкой вверх, затем раскрываются вручную по клику или клавиатуре;
- front/back карточек держатся в одной геометрии через shared card container и `aspect-ratio: 5 / 7`;
- текст карточек подгоняется в `open-pack.js`, чтобы не резаться и не становиться микроскопическим;
- есть системный `prefers-reduced-motion` и пользовательский toggle через `html[data-reduced-motion="true"]`;
- browser/API-проверки могут писать runtime-события в SQLite, поэтому motion-проверки на чистых данных нужно делать только на копии базы.

Роль premium-motion: усилить ощущение открытия, материала, редкости и выбора, не превращая главную в dashboard и не ломая доступность. Правую колонку главной в первом motion-этапе не трогать.

## 2. Почему простой CSS-smoke не сработал

Простой CSS-туман вокруг featured-паков плохо подходит как основное premium-решение.

- Он выглядит плоско: несколько radial-gradient/blur-слоёв дают ауру, но не настоящую глубину, объём, завихрение или физику дыма.
- Им трудно управлять: smoke должен реагировать на состояние pack object, hover/press/open/revealed и reduced motion, а CSS-псевдоэлемент вокруг карточки быстро превращается в набор неочевидных magic numbers.
- Он легко ломает читаемость: blur и свет вокруг featured-паков могут залезать под текст, снижать контраст и отвлекать от CTA.
- Он может быть тяжёлым: большой animated blur/filter вокруг нескольких карточек на главной провоцирует repaint/compositing cost, особенно на мобильных и при широких glow-областях.
- Он плохо масштабируется: когда сетка перестраивается, smoke-слой может конфликтовать с `overflow`, scroll areas, z-index, sticky nav и responsive card sizes.
- Он решает не ту задачу: пользователю важнее контролируемый opening sequence и понятные states, чем постоянный декоративный туман вокруг витрины.

Текущие CSS-ауры, glow, hover lifts и `featuredMistPulse` можно оставить как лёгкий визуальный язык, но не стоит повторять прежний CSS-smoke как главный путь к premium-качеству. Дым/туман высокого уровня должен быть отдельным asset/effect experiment с performance gate.

## 3. Сравнение подходов

| Подход | Для чего подходит | Где использовать в проекте | Плюсы | Риски | Рекомендация |
| --- | --- | --- | --- | --- | --- |
| CSS transitions/transforms | Hover/focus/tap states, лёгкие lifts, opacity, transform, focus affordance, reduced-motion fallback | `.mini-booster`, `.featured-pack`, `.game-card`, buttons, selected pack shell | Уже есть в проекте, без зависимостей, хорошо ложится на `prefers-reduced-motion`, быстро проверяется | Не даёт сложный дым, много keyframes быстро становится хрупким, blur/filter дорогие | Использовать как базовый слой. Анимировать в первую очередь `transform` и `opacity`, не layout-свойства |
| SVG filters/procedural noise | Эксперименты с дымом/туманом, текстурами, displacement, мягкими portal/fog overlays | Только изолированный layer на `/open`, не вокруг всей главной сетки | `feTurbulence` умеет генерировать cloud/noise texture; можно получить более органичный smoke без Canvas | `feGaussianBlur`, `feDisplacementMap` и большие filter regions могут быть тяжёлыми; отличаются по браузерам | Использовать только после отдельного performance test на малом слое и с reduced-motion off-switch |
| GSAP | Контролируемые timeline-анимации DOM/SVG: staged reveal, паузы, callbacks, sequencing, cleanup | `/open` sequence: pack press -> glow -> deck preview -> dealt backs -> result layout. Возможно для редких staged hover states | Timeline API удобен для порядка, callbacks и отмены; хорошо подходит, когда CSS-state machine становится сложной | Новая зависимость, риск переанимировать интерфейс, нужен local/self-hosted runtime без CDN | Не подключать сразу. Рассмотреть одним локальным frontend-only experiment, если CSS/vanilla sequence станет трудно контролировать |
| Rive | Интерактивный premium pack object со state machine: idle, hover, press, opening, revealed | Один главный pack object на `/open`; возможно крупный hero pack на главной, но не вся сетка | State machines, runtime для web, Canvas/WebGL2 варианты, хороший handoff от motion-дизайна к runtime | Требует `.riv`, runtime/WASM, canvas sizing, fallback, performance budget; overkill для простых hover | Подходит для premium pack object, но как отдельный второй этап после CSS/GSAP прототипа |
| Lottie/dotLottie | Декоративные particles/glow/loop accents, small celebration bursts, idle accents | Малые overlay-акценты на `/open`: sparkles, short reveal accent, empty/loading states | Удобно для дизайнерских vector loops; dotLottie Web поддерживает `.json` и `.lottie`, player controls, разные render backends | Не лучшая логика для сложного interactive pack state; много Lottie на странице может лагать | Использовать точечно для декоративных коротких loops, не для core reveal logic |
| Figma/FigJam/Figma MCP | Визуальное направление, composition, spacing, states, handoff, annotations, state diagrams | Перед implementation: кадры главной, `/open` states, timing notes, component states; Codex получает дизайн-контекст | Помогает договориться о композиции и states до кода; Figma MCP может передавать дизайн-контекст агенту | Figma не доказывает runtime performance и не заменяет browser profiling | Использовать как planning/handoff, не как источник готовой production-анимации |
| Canvas/WebGL | Сложные particles, физика дыма, full-scene effects, шейдеры, мини-игровые сцены | Отложенный experiment для `/open`, если Rive/Lottie/SVG не хватает | Максимальная свобода и качество для процедурных эффектов | Самый высокий риск по поддержке, производительности, доступности и debug; легко сделать overkill | Отложить до доказанной необходимости. Не использовать для простых hover/open effects |

Основания из внешних источников: Rive web runtime работает через canvas и state machines, а Rive рекомендует выбирать Canvas/WebGL2 по сложности и размеру runtime; dotLottie Web является официальным player для Lottie/dotLottie и поддерживает разные render backends; GSAP Timeline даёт sequencing, callbacks и control; Figma Dev Mode/MCP полезен для handoff и передачи design context; MDN описывает `prefers-reduced-motion` как способ снизить non-essential motion; web.dev рекомендует держать performance-анимации на `transform`/`opacity`; MDN `feTurbulence` описывает как генератор Perlin-noise текстур вроде clouds.

## 4. Рекомендованный пайплайн

Решение для этого проекта:

- CSS: только базовые hover/focus/transitions, лёгкие состояния, материал карточек, state visibility и reduced-motion fallbacks. Основной performance rule: `transform` и `opacity`, минимум animated blur/filter.
- GSAP: для staged reveal и timeline-анимаций DOM/SVG, если opening sequence перестанет удобно описываться текущими классами `is-opening`, `has-results`, `is-awaiting-reveal`, `is-revealed`. Подключать только одну animation-библиотеку, локально, без CDN, после отдельного решения.
- Rive: для интерактивного premium pack object с состояниями `idle`, `hover`, `press`, `open`, `revealed`. Рендерить один объект на `/open`, а не много объектов в сетке. Для vanilla/PHP подключение должно быть frontend-only: локальный runtime/asset, canvas с фиксированным responsive box, fallback на существующий HTML/CSS pack.
- Lottie/dotLottie: для декоративных particles/glow/loop accents, не для core state logic. Хорошие кандидаты: короткий reveal burst, idle sparkle около pack object, success accent после сохранения. Использовать локальный `.json`/`.lottie` asset и один player на сцену.
- Figma: для композиции, визуального направления, spacing, states и handoff в Codex. В Figma делать frame set: home featured pack states, `/open` sealed/opening/revealed/reduced motion states, timing notes. Figma MCP полезен, когда нужен дизайн-контекст, но финальную реализацию всё равно проверять в браузерах.
- SVG filters: только после отдельного performance test. Допустим один небольшой `feTurbulence`/blur/displacement layer на `/open`, ограниченный box, без перекрытия текста.
- Canvas/WebGL: отложить, пока не доказана необходимость. Если нужен настоящий procedural fog или мини-игровая физика, сначала сделать isolated prototype вне основного UX и сравнить с Rive/Lottie.

Организация assets для будущих задач:

- `public/assets/animations/rive/` для `.riv`;
- `public/assets/animations/lottie/` для `.json`/`.lottie`;
- `public/assets/animations/svg/` для SVG filters/prototypes;
- `public/assets/vendor/` только после явного решения о vendored runtime;
- рядом с asset держать короткий `README.md` с источником, версией, лицензией, reduced-motion fallback и performance notes.

Без тяжёлой зависимости:

- первый prototype делать на текущем CSS/vanilla JS;
- не подключать CDN;
- не ставить npm/composer packages;
- если библиотека всё-таки нужна, добавить одну локальную зависимость как отдельную frontend-only задачу с review of size/license/version.

## 5. Первые 3 безопасные animation-задачи

### 1. Booster material micro-interactions без smoke

- Цель: усилить ощущение кликабельного pack object на главной без возвращения прежнего CSS-smoke.
- Что трогать: только `public/assets/css/game-cards.css`, при необходимости малый участок `public/assets/js/app.js` для non-persistent class toggles. Правая колонка главной не трогается.
- Что не трогать: PHP, backend, API, SQLite, auth, admin, analytics, `database/*`, runtime scripts.
- Инструмент: CSS transitions/transforms, существующие `.mini-booster`, `.featured-pack`, `.featured-pack__booster`, `prefers-reduced-motion`.
- Как проверить: desktop/mobile widths 320, 390, 768, 1366, 1920; hover/focus/tap states; no body horizontal overflow; no text clipping; no console errors. Для browser-проверки использовать копию базы или режим, где не важны `page_view`.
- Reduced motion fallback: убрать floating/hover movement, оставить статический focus ring, contrast и CTA.
- Риск: низкий. Главный риск - перебор glow/blur и ухудшение читаемости.

### 2. `/open` staged reveal на существующих DOM states

- Цель: сделать opening sequence более ритуальным: press -> короткое напряжение сцены -> backs dealt -> manual reveal, сохраняя ручное раскрытие.
- Что трогать: `public/assets/js/open-pack.js`, `public/assets/css/open-pack.css`, `public/assets/css/game-cards.css`.
- Что не трогать: `POST /api/open-pack` contract, backend, database, auth, save-card logic, card text fitting, front/back geometry.
- Инструмент: сначала CSS/vanilla JS на текущих классах `is-opening`, `has-results`, `is-awaiting-reveal`, `is-revealed`; GSAP только если нужен явно управляемый timeline с callbacks/cleanup.
- Как проверить: `daily`, `weekly`, `monthly`, `question`, `choice`, `mood`, `direction`, `take-leave`; card count 1/3/5; first card visible on narrow scroll; front/back same size; keyboard reveal works; no layout shift.
- Reduced motion fallback: `wait()` уже обнуляет delay при reduced motion; дополнительно sequence должен сразу показывать backs/results без long loops и без 3D flip, через opacity/visibility.
- Риск: средний. Риск сломать manual reveal или текстовый fit, поэтому задача должна быть отдельной и малой.

### 3. Один isolated premium pack object prototype

- Цель: проверить, даёт ли Rive или Lottie/dotLottie качественный pack object лучше CSS, без внедрения в основной UX.
- Что трогать: только будущий isolated frontend prototype или отдельный feature branch: один local asset в `public/assets/animations/...`, один small integration wrapper, fallback на текущий `.pack-booster`.
- Что не трогать: главную правую колонку, backend/API/database/auth/admin/analytics, existing card reveal, seed/schema.
- Инструмент: Rive для interactive state machine `idle/hover/press/open/revealed`; Lottie/dotLottie только если это декоративный burst/particles без интерактивной логики.
- Как проверить: asset size, first paint impact, CPU/memory, canvas sizing, keyboard/focus fallback, normal/reduced motion, desktop/mobile widths. Сравнить с CSS/vanilla вариантом до принятия.
- Reduced motion fallback: не autoplay loop; показывать статичный poster/HTML pack; press/open state без particles.
- Риск: средний/высокий. Нужна дисциплина: один объект, один runtime, no CDN, no package install без отдельного решения.

## 6. Чего не делать

- Не возвращать прежний CSS-smoke как основное решение.
- Не подключать сразу несколько animation-библиотек.
- Не делать тяжёлый animated blur/filter без performance check.
- Не использовать Canvas/WebGL для простых hover/open effects.
- Не ломать manual reveal.
- Не менять backend/API/database ради motion.
- Не ухудшать читаемость текста.
- Не копировать Hearthstone/Blizzard или чужие ассеты/рамки/логотипы 1-в-1.
- Не анимировать `width`, `height`, `top`, `left`, grid layout или font-size в runtime sequence.
- Не оставлять infinite loops включёнными в reduced motion mode.
- Не ставить npm/composer packages и не добавлять CDN без отдельной задачи и явного review.
- Не запускать browser/API сценарии на рабочей базе, если они пишут `page_view`, `openings`, `events`, `rate_limits`, `sessions` или cookies.

## 7. Как проверять производительность

Минимальный performance protocol для каждой future motion-задачи:

- Браузеры: Chrome, Edge, Brave, Firefox, Opera.
- Ширины: 320, 360, 390, 430, 768, 820, 1024, 1280, 1366, 1440, 1680, 1920.
- Страницы: `/`, `/open?pack=daily`, `/open?pack=weekly`, `/open?pack=monthly`, плюс один special pack с context fields.
- Проверить normal mode и reduced motion mode.
- Субъективно: animation не должна дёргаться, задерживать input, мешать чтению или создавать ощущение dashboard на главной.
- DevTools Performance: нет long tasks, нет постоянного layout/recalculate style на декоративных loops, нет всплеска paint на каждый frame.
- Memory/CPU: idle loops не должны держать высокий CPU; canvas/player должен cleanup при удалении/переходе.
- Layout: no body horizontal overflow, no unexpected scrollbars, no card overlap, no layout shift при opening/reveal.
- Console: no errors, no failed asset requests.
- Accessibility: keyboard reveal работает; focus visible не исчезает; motion toggle и `prefers-reduced-motion` дают понятный static path.

Для чистой проверки использовать копию `database/database.sqlite`, потому что открытие страниц/API может писать `page_view`, `openings`, `events`, `rate_limits`, `sessions`/cookies.

## 8. Как работать с reduced motion

Правило: core UX должен работать без анимаций.

- Все декоративные loops, particles, glow, fog, aura breathing и floating должны отключаться или резко упрощаться.
- Manual reveal должен оставаться понятным: карточка до клика выглядит закрытой, после клика явно раскрыта.
- Не полагаться только на движение для объяснения состояния. Использовать текст, aria-label, disabled state, focus style и visible CTA.
- Использовать оба существующих механизма: системный `@media (prefers-reduced-motion: reduce)` и пользовательский `html[data-reduced-motion="true"]`.
- JS-timeline должен читать тот же state, что уже есть в `app.js` и `open-pack.js`: при reduced motion delays должны быть `0` или минимальными.
- Rive/Lottie loops в reduced motion не autoplay; вместо них static poster или existing HTML/CSS fallback.
- 3D flip в reduced motion можно заменять мгновенным opacity/visibility switch, как уже сделано для `.game-card__front`/`.game-card__back`.

## 9. Как передавать задачу Codex

Шаблон будущего Codex-промта:

```text
Ты работаешь с локальным проектом "Ну что там?" в C:\Projects\web\web2.

Режим: FRONTEND-ONLY SINGLE ANIMATION TASK.

Цель:
[Описать одну конкретную animation-задачу: например, улучшить staged reveal на /open без изменения API.]

Можно трогать только:
- public/assets/css/open-pack.css
- public/assets/css/game-cards.css
- public/assets/js/open-pack.js
[или другой узкий список frontend/assets-файлов]

Нельзя трогать:
- database/database.sqlite
- database/schema.sql
- database/seed.php
- app/Core, app/Controllers, app/Services, app/Models
- auth/admin/analytics/API contracts
- config/config.php
- scripts/*

Запрещено:
- запускать seed.php --fresh;
- менять или сбрасывать SQLite;
- добавлять npm/composer packages;
- добавлять CDN;
- менять backend/database/auth/admin/analytics;
- делать массовый redesign;
- менять правую колонку главной, если задача не про неё.

Требования:
- сохранить manual reveal;
- front/back карточек должны быть одинакового размера;
- не резать текст и не делать его микроскопическим;
- обязательно reduced motion fallback через prefers-reduced-motion и html[data-reduced-motion="true"];
- не использовать прежний CSS-smoke как основное решение;
- одна animation-задача за раз.

Проверки:
- сначала статически прочитать затронутые файлы;
- проверить desktop/mobile widths;
- проверить normal и reduced motion mode;
- проверить no body horizontal overflow, no console errors, no layout shift;
- browser/API проверки выполнять только на копии базы, если они могут писать page_view/openings/events/rate_limits/sessions/cookies.
```

## 10. Короткое решение

Сейчас выбираем: CSS/vanilla как базовый слой и первый безопасный prototype на existing DOM states. Главный фокус - `/open` staged reveal и аккуратные booster micro-interactions без возврата CSS-smoke.

Откладываем: Rive, Lottie/dotLottie, SVG procedural fog, Canvas/WebGL и GSAP production-подключение до отдельного performance-gated experiment. Rive выглядит лучшим кандидатом для одного premium pack object со state machine, но не для всей сетки паков.

Самый безопасный первый prototype: CSS-only material micro-interactions для `.mini-booster`/`.featured-pack` без smoke, без новых зависимостей, с reduced-motion fallback и проверкой responsive/overflow. Следующий prototype - staged reveal на `/open` поверх уже существующих классов `is-opening`, `has-results`, `is-awaiting-reveal`, `is-revealed`.
