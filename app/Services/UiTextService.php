<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Security;
use InvalidArgumentException;
use Throwable;

final class UiTextService
{
    private const PREFIX = 'ui_text.';

    /**
     * @return array<string, list<array{key: string, label: string, default: string, type: string}>>
     */
    public function groups(): array
    {
        $values = $this->values();
        $groups = [];

        foreach (self::registry() as $item) {
            $group = $item['group'];
            $groups[$group] ??= [];
            $groups[$group][] = [
                'key' => $item['key'],
                'label' => $item['label'],
                'default' => $item['default'],
                'type' => $item['type'],
                'value' => $values[$item['key']] ?? $item['default'],
            ];
        }

        return $groups;
    }

    /**
     * @return array<string, string>
     */
    public function publicMap(): array
    {
        $values = $this->values();
        $map = [];

        foreach (self::registry() as $item) {
            $map[$item['key']] = $values[$item['key']] ?? $item['default'];
        }

        return $map;
    }

    public function update(string $key, string $value, int $adminId): void
    {
        $item = $this->findRegistryItem($key);

        if ($item === null) {
            throw new InvalidArgumentException('Неизвестный текстовый ключ.');
        }

        $value = trim($value);
        $maxLength = $item['type'] === 'textarea' ? 3000 : 240;

        if ($value === '') {
            throw new InvalidArgumentException('Текст не должен быть пустым.');
        }

        if (function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') > $maxLength : strlen($value) > $maxLength) {
            throw new InvalidArgumentException('Текст слишком длинный для этого поля.');
        }

        Database::query(
            'INSERT INTO site_settings ("key", value)
            VALUES (:key, :value)
            ON CONFLICT("key") DO UPDATE SET value = excluded.value',
            [
                ':key' => self::PREFIX . $key,
                ':value' => $value,
            ]
        );

        Database::query(
            'INSERT INTO admin_logs (admin_user_id, action, entity_type, entity_id, payload_json, created_at)
            VALUES (:admin_user_id, :action, :entity_type, :entity_id, :payload_json, :created_at)',
            [
                ':admin_user_id' => $adminId,
                ':action' => 'update_ui_text',
                ':entity_type' => 'ui_text',
                ':entity_id' => null,
                ':payload_json' => Security::safeJsonEncode(['key' => $key]),
                ':created_at' => gmdate('c'),
            ]
        );
    }

    /**
     * @return array<string, string>
     */
    private function values(): array
    {
        try {
            $rows = Database::query(
                'SELECT "key", value FROM site_settings WHERE "key" LIKE :prefix',
                [':prefix' => self::PREFIX . '%']
            )->fetchAll();
        } catch (Throwable) {
            return [];
        }

        $values = [];
        foreach ($rows as $row) {
            $key = substr((string) $row['key'], strlen(self::PREFIX));
            $values[$key] = (string) $row['value'];
        }

        return $values;
    }

    /**
     * @return array{key: string, group: string, label: string, default: string, type: string}|null
     */
    private function findRegistryItem(string $key): ?array
    {
        foreach (self::registry() as $item) {
            if ($item['key'] === $key) {
                return $item;
            }
        }

        return null;
    }

    /**
     * @return list<array{key: string, group: string, label: string, default: string, type: string}>
     */
    private static function registry(): array
    {
        return [
            ['key' => 'common.brand', 'group' => 'Общее', 'label' => 'Название сайта', 'default' => 'Ну что там?', 'type' => 'input'],
            ['key' => 'common.guest', 'group' => 'Общее', 'label' => 'Гость', 'default' => 'Гость', 'type' => 'input'],
            ['key' => 'common.login', 'group' => 'Общее', 'label' => 'Войти', 'default' => 'Войти', 'type' => 'input'],
            ['key' => 'common.register', 'group' => 'Общее', 'label' => 'Регистрация', 'default' => 'Регистрация', 'type' => 'input'],
            ['key' => 'common.logout', 'group' => 'Общее', 'label' => 'Выйти', 'default' => 'Выйти', 'type' => 'input'],
            ['key' => 'common.cabinet', 'group' => 'Общее', 'label' => 'Кабинет', 'default' => 'Кабинет', 'type' => 'input'],
            ['key' => 'common.admin', 'group' => 'Общее', 'label' => 'Админка', 'default' => 'Админка', 'type' => 'input'],
            ['key' => 'common.home', 'group' => 'Общее', 'label' => 'На главную', 'default' => 'На главную', 'type' => 'input'],
            ['key' => 'common.refresh', 'group' => 'Общее', 'label' => 'Обновить', 'default' => 'Обновить', 'type' => 'input'],
            ['key' => 'common.reduced_motion', 'group' => 'Общее', 'label' => 'Меньше анимаций', 'default' => 'Меньше анимаций', 'type' => 'input'],

            ['key' => 'home.hero.eyebrow', 'group' => 'Главная', 'label' => 'Hero: верхняя подпись', 'default' => 'Карточный рандом для обычных дней', 'type' => 'input'],
            ['key' => 'home.hero.title', 'group' => 'Главная', 'label' => 'Hero: заголовок', 'default' => 'Ну что там?', 'type' => 'input'],
            ['key' => 'home.hero.lead', 'group' => 'Главная', 'label' => 'Hero: основной текст', 'default' => 'Выбери пак, открой карту и посмотри, что сегодня придумал рандом.', 'type' => 'textarea'],
            ['key' => 'home.hero.note', 'group' => 'Главная', 'label' => 'Hero: пояснение', 'default' => 'Иногда он шутит, иногда попадает в точку, иногда уверенно несёт красивую ерунду. Без зодиака, важных обещаний и драматичных взглядов в небо.', 'type' => 'textarea'],
            ['key' => 'home.hero.choose_button', 'group' => 'Главная', 'label' => 'Кнопка выбора пака', 'default' => 'Выбрать пак', 'type' => 'input'],
            ['key' => 'home.hero.random_button', 'group' => 'Главная', 'label' => 'Кнопка случайного пака', 'default' => 'Случайный пак', 'type' => 'input'],
            ['key' => 'home.featured.eyebrow', 'group' => 'Главная', 'label' => 'Featured: верхняя подпись', 'default' => 'Витрина', 'type' => 'input'],
            ['key' => 'home.featured.title', 'group' => 'Главная', 'label' => 'Featured: заголовок', 'default' => 'С чего начать', 'type' => 'input'],
            ['key' => 'home.packs.eyebrow', 'group' => 'Главная', 'label' => 'Коллекция: верхняя подпись', 'default' => 'Коллекция паков', 'type' => 'input'],
            ['key' => 'home.packs.title', 'group' => 'Главная', 'label' => 'Коллекция: заголовок', 'default' => 'Выбери бустер для открытия', 'type' => 'input'],
            ['key' => 'home.packs.loading', 'group' => 'Главная', 'label' => 'Коллекция: загрузка', 'default' => 'Загружаю паки. Рандом ищет чистую кружку.', 'type' => 'textarea'],
            ['key' => 'home.packs.error', 'group' => 'Главная', 'label' => 'Коллекция: ошибка', 'default' => 'Паки не загрузились. Рандом ушёл за печеньем.', 'type' => 'textarea'],
            ['key' => 'home.pack.open', 'group' => 'Главная', 'label' => 'Кнопка на бустере', 'default' => 'Открыть', 'type' => 'input'],
            ['key' => 'home.stats.eyebrow', 'group' => 'Главная', 'label' => 'Статистика: верхняя подпись', 'default' => 'Мини-сводка', 'type' => 'input'],
            ['key' => 'home.stats.title', 'group' => 'Главная', 'label' => 'Статистика: заголовок', 'default' => 'Статистика', 'type' => 'input'],
            ['key' => 'home.stats.empty', 'group' => 'Главная', 'label' => 'Статистика: пусто', 'default' => 'Открой первый пак — и тут появится маленькая бухгалтерия хаоса.', 'type' => 'textarea'],
            ['key' => 'home.history.eyebrow', 'group' => 'Главная', 'label' => 'История: верхняя подпись', 'default' => 'Последнее', 'type' => 'input'],
            ['key' => 'home.history.title', 'group' => 'Главная', 'label' => 'История: заголовок', 'default' => 'История', 'type' => 'input'],
            ['key' => 'home.history.empty', 'group' => 'Главная', 'label' => 'История: пусто', 'default' => 'Пока пусто. Рандом ещё не успел оставить следы.', 'type' => 'textarea'],

            ['key' => 'pack.category.quick.title', 'group' => 'Категории паков', 'label' => 'Категория: быстрый старт', 'default' => 'Быстрый старт', 'type' => 'input'],
            ['key' => 'pack.category.quick.text', 'group' => 'Категории паков', 'label' => 'Описание: быстрый старт', 'default' => 'Паки на один короткий взгляд: день, настроение, совет и маленькое действие.', 'type' => 'textarea'],
            ['key' => 'pack.category.period.title', 'group' => 'Категории паков', 'label' => 'Категория: периоды', 'default' => 'Периоды и сводки', 'type' => 'input'],
            ['key' => 'pack.category.period.text', 'group' => 'Категории паков', 'label' => 'Описание: периоды', 'default' => 'Неделя, месяц и внутренняя погода, когда хочется посмотреть чуть шире.', 'type' => 'textarea'],
            ['key' => 'pack.category.choice.title', 'group' => 'Категории паков', 'label' => 'Категория: выбор', 'default' => 'Вопросы и выбор', 'type' => 'input'],
            ['key' => 'pack.category.choice.text', 'group' => 'Категории паков', 'label' => 'Описание: выбор', 'default' => 'Для вопросов, развилок, пауз и вариантов, которые выглядят подозрительно одинаково.', 'type' => 'textarea'],
            ['key' => 'pack.category.extra.title', 'group' => 'Категории паков', 'label' => 'Категория: редкое', 'default' => 'Редкое и для друзей', 'type' => 'input'],
            ['key' => 'pack.category.extra.text', 'group' => 'Категории паков', 'label' => 'Описание: редкое', 'default' => 'Странные удачные карточки и штуки, которые хочется кому-нибудь кинуть.', 'type' => 'textarea'],

            ['key' => 'cabinet.guest.title', 'group' => 'Кабинет', 'label' => 'Гостевой экран: заголовок', 'default' => 'Кабинет доступен после входа.', 'type' => 'input'],
            ['key' => 'cabinet.guest.text', 'group' => 'Кабинет', 'label' => 'Гостевой экран: текст', 'default' => 'История и коллекция любят знать, кому принадлежат. Войди или зарегистрируйся, и карточки перестанут жить в режиме “кажется, я где-то это видел”.', 'type' => 'textarea'],
            ['key' => 'cabinet.title', 'group' => 'Кабинет', 'label' => 'Кабинет: заголовок', 'default' => 'Твои карточки, заметки и следы рандома', 'type' => 'input'],
            ['key' => 'cabinet.note', 'group' => 'Кабинет', 'label' => 'Кабинет: описание', 'default' => 'Тут без торжественных фанфар: просто профиль, сохранённые карточки, история открытий и немного статистики, которая делает вид, что всё поняла.', 'type' => 'textarea'],

            ['key' => 'admin.nav.dashboard', 'group' => 'Админка', 'label' => 'Навигация: Dashboard', 'default' => 'Dashboard', 'type' => 'input'],
            ['key' => 'admin.nav.packs', 'group' => 'Админка', 'label' => 'Навигация: Packs', 'default' => 'Packs', 'type' => 'input'],
            ['key' => 'admin.nav.predictions', 'group' => 'Админка', 'label' => 'Навигация: Predictions', 'default' => 'Predictions', 'type' => 'input'],
            ['key' => 'admin.nav.users', 'group' => 'Админка', 'label' => 'Навигация: Users', 'default' => 'Users', 'type' => 'input'],
            ['key' => 'admin.nav.analytics', 'group' => 'Админка', 'label' => 'Навигация: Analytics', 'default' => 'Analytics', 'type' => 'input'],
            ['key' => 'admin.nav.logs', 'group' => 'Админка', 'label' => 'Навигация: Logs', 'default' => 'Logs', 'type' => 'input'],
            ['key' => 'admin.nav.texts', 'group' => 'Админка', 'label' => 'Навигация: Texts', 'default' => 'Texts', 'type' => 'input'],
            ['key' => 'admin.texts.title', 'group' => 'Админка', 'label' => 'Страница текстов: заголовок', 'default' => 'Тексты сайта', 'type' => 'input'],
            ['key' => 'admin.texts.description', 'group' => 'Админка', 'label' => 'Страница текстов: описание', 'default' => 'Здесь редактируются статичные надписи интерфейса. Названия паков и тексты карточек меняются в разделах Packs и Predictions.', 'type' => 'textarea'],
        ];
    }
}
