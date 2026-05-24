# UI/UX Audit Notes

Это не список обязательных задач, а дизайн-аудит. Внедрять по одному блоку, без массового redesign.

Контекст: заметки подготовлены по результатам Figma/UI-аудита доски "Ну что там? — UI/UX Audit Board". Figma MCP упёрся в лимит Starter-плана и не смог записать заметки на FigJam-доску, поэтому результаты зафиксированы в документации проекта.

## 1. Problems

- **High — Главная:** главный путь теряется из-за конкуренции hero, боковых панелей, статистики, истории и сетки паков. Нужно оставить один главный CTA и сделать витрину паков главным продуктовым блоком.
- **High — Opening screen:** сцена уже близка к нужной, но карта, текст и CTA ощущаются как hero-layout, а не ritual opening. Нужно собрать всё вокруг центрального stage.
- **High — Admin analytics:** KPI и графики имеют почти одинаковый визуальный вес. Нужен верхний summary: что изменилось, какой пак лидирует, где проблема.
- **High — Cabinet:** кабинет выглядит как плотная форма/панель, а не личная коллекция. Главным должен быть блок saved cards, профиль и настройки вторичны.

## 2. Homepage Redesign Notes

- **High — Первый экран:** logo/nav + короткий promise + один CTA "Открыть пак".
- **High — Пакеты:** группировать по сценариям: сегодня, настроение, выбор, вопрос, неделя/месяц.
- **Medium — Статистика и история:** опустить ниже как supporting layer.
- **Medium — Dashboard feel:** убрать ощущение dashboard: меньше боковых колонок, больше воздуха, крупнее карточки.

## 3. Opening Screen Notes

- **High — Visual focus:** центральная карта/пак должны быть главным фокусом.
- **High — Состояния:** sealed pack -> opening -> revealed -> save/again.
- **Medium — Material feel:** карте добавить material feel: rim light, shadow stack, тонкая рамка, controlled glow.
- **Medium — Boosters:** контекстные поля оформить как "boosters", не как обычные формы.

## 4. Admin Analytics Notes

- **High — Структура:** summary -> primary KPI -> main chart -> pack performance -> recent events.
- **Medium — KPI:** число + период + delta + пояснение формулы.
- **Medium — Графики:** сгруппировать по смыслу: activity, content performance, conversion.
- **Low — Sidebar/filters:** сайдбар сделать тише, период/фильтр перенести ближе к заголовку.

## 5. Design System Draft

### Палитра

- `#0B1021` — night
- `#121A2D` — surface
- `#1B2440` — elevated
- `#F7C65B` — gold CTA
- `#65E6C8` — teal magic
- `#8F7CF6` — violet aura
- `#FF8A70` — coral

### Типографика

- H1: 48-56 Bold
- H2: 32-40 Bold
- Card title: 20-24
- Body: 16-18
- Meta: 12-14

### Spacing

- 8px base
- 16/24 внутри карточек
- 48/64 между блоками
- 96 между крупными секциями

### Карточки

- Pack cards крупнее.
- Rarity/tag.
- Один CTA.
- Стабильные состояния.

### Статистика

- KPI card = label, value, delta, period, mini trend/tooltip.

## 6. Next Version Mockups

- **Homepage:** header -> hero CTA -> 3 featured packs -> categorized pack grid -> stats/history below.
- **Opening:** quiet header -> context chips -> central pack/card stage -> CTA -> reveal results.
- **Admin:** sidebar -> period header -> KPI row -> full-width activity chart -> performance blocks -> recent events.
- **Cabinet:** collection hero -> compact profile -> stats row -> saved cards grid -> filters/settings side panel.

## Рекомендуемый порядок внедрения

1. Главная: упростить первый экран и усилить CTA.
2. Главная: улучшить группировку паков.
3. Opening: усилить ritual opening stage.
4. Admin analytics: улучшить визуальную иерархию.
5. Cabinet: сделать сохранённые карточки главным блоком.

## Статус фиксации

- Файл создан.
- Код не менялся.
- База не трогалась.
- `seed --fresh` не запускался.
