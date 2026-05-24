<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Security;
use App\Core\Validator;
use InvalidArgumentException;
use PDO;
use RuntimeException;

final class AdminService
{
    private const PACK_TYPES = ['daily', 'weekly', 'monthly', 'mood', 'question', 'choice', 'dark', 'light', 'action', 'rare'];
    private const RARITIES = ['common', 'uncommon', 'rare', 'epic', 'legendary', 'mythic'];
    private const ANALYTICS_PERIODS = ['today', '7d', '30d', 'all'];
    private const ACTIVITY_EVENT_TYPES = ['page_view', 'pack_opened', 'card_saved', 'register', 'login'];
    private const EVENT_LABELS = [
        'page_view' => 'Просмотры страниц',
        'pack_opened' => 'Открытия паков',
        'card_saved' => 'Сохранения карточек',
        'register' => 'Регистрации',
        'login' => 'Входы',
        'save_failed_guest' => 'Попытки сохранения гостями',
        'logout' => 'Выходы',
        'admin_action' => 'Действия админа',
        'profile_update' => 'Обновления профиля',
        'password_change' => 'Смена пароля',
    ];
    private const RARITY_LABELS = [
        'common' => 'обычная',
        'uncommon' => 'необычная',
        'rare' => 'редкая',
        'epic' => 'эпическая',
        'legendary' => 'легендарная',
        'mythic' => 'мифическая',
    ];

    /**
     * @return list<string>
     */
    public function packTypes(): array
    {
        return self::PACK_TYPES;
    }

    /**
     * @return list<string>
     */
    public function rarities(): array
    {
        return self::RARITIES;
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(array $filters = []): array
    {
        $pdo = Database::connect();

        $counts = [
            'total_users' => $this->count('users'),
            'active_users' => (int) $pdo->query('SELECT COUNT(*) FROM users WHERE is_blocked = 0')->fetchColumn(),
            'blocked_users' => (int) $pdo->query('SELECT COUNT(*) FROM users WHERE is_blocked = 1')->fetchColumn(),
            'total_openings' => $this->count('openings'),
            'openings_today' => (int) $pdo->query('SELECT COUNT(*) FROM openings WHERE date(opened_at) = date("now")')->fetchColumn(),
            'total_saved_cards' => $this->count('saved_cards'),
            'total_packs' => $this->count('packs'),
            'active_packs' => (int) $pdo->query('SELECT COUNT(*) FROM packs WHERE is_active = 1')->fetchColumn(),
            'total_predictions' => $this->count('predictions'),
            'active_predictions' => (int) $pdo->query('SELECT COUNT(*) FROM predictions WHERE is_active = 1')->fetchColumn(),
        ];

        $topPacks = $pdo->query(
            'SELECT p.slug, p.title, COUNT(o.id) AS openings_count
            FROM openings o
            INNER JOIN packs p ON p.id = o.pack_id
            GROUP BY p.id, p.slug, p.title
            ORDER BY openings_count DESC, p.sort_order ASC, p.id ASC
            LIMIT 5'
        )->fetchAll();

        $rarities = $pdo->query(
            'SELECT rarity, COUNT(*) AS count
            FROM openings
            GROUP BY rarity
            ORDER BY count DESC, rarity ASC'
        )->fetchAll();

        $recentOpenings = $pdo->query(
            'SELECT
                o.id,
                o.opened_at,
                o.rarity,
                COALESCE(u.username, o.guest_id, "guest") AS owner_label,
                p.title AS pack_title,
                pr.title AS prediction_title,
                pr.text AS prediction_text
            FROM openings o
            LEFT JOIN users u ON u.id = o.user_id
            INNER JOIN packs p ON p.id = o.pack_id
            INNER JOIN predictions pr ON pr.id = o.prediction_id
            ORDER BY o.opened_at DESC, o.id DESC
            LIMIT 10'
        )->fetchAll();

        return [
            'counts' => $counts,
            'top_packs' => array_map([$this, 'topPackRow'], $topPacks),
            'rarity_counts' => $this->countsByKey($rarities, 'rarity'),
            'recent_openings' => array_map([$this, 'recentOpeningRow'], $recentOpenings),
            'recent_logs' => $this->logs(10),
            'analytics' => $this->analyticsOverview($filters + ['period' => $filters['period'] ?? '7d']),
            'analytics_today' => $this->analyticsOverview(['period' => 'today']),
            'analytics_all' => $this->analyticsOverview(['period' => 'all']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function analyticsSummary(): array
    {
        return $this->analyticsOverview(['period' => 'today']);
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return array<string, mixed>
     */
    public function analyticsOverview(array $filters = []): array
    {
        $period = $this->analyticsPeriod($filters);
        $kpis = $this->analyticsKpis($period);
        $previous = $this->previousAnalyticsPeriod($period);
        $previousKpis = $previous !== null ? $this->analyticsKpis($previous) : null;

        return [
            'period' => $period,
            'kpis' => $kpis,
            'kpi_changes' => $this->kpiChanges($kpis, $previousKpis),
            'activity_by_day' => $this->activityByDay($period),
            'top_packs' => $this->analyticsTopPacks($period),
            'rarities' => $this->analyticsRarities($period),
            'funnel' => $this->analyticsFunnel($period, $kpis),
            'users_guests' => $this->usersAndGuests($period, $kpis),
            'event_labels' => self::EVENT_LABELS,
            'rarity_labels' => self::RARITY_LABELS,
            'data_notes' => $this->analyticsDataNotes(),
            'recent_events' => $this->analyticsEvents($filters + ['limit' => 50]),
        ];
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return list<array<string, mixed>>
     */
    public function analyticsEvents(array $filters = []): array
    {
        $where = [];
        $params = [];

        $eventType = isset($filters['event_type']) ? trim((string) $filters['event_type']) : '';
        if ($eventType !== '' && $eventType !== 'all') {
            $where[] = 'e.event_type = :event_type';
            $params[':event_type'] = $eventType;
        }

        $userId = isset($filters['user_id']) ? trim((string) $filters['user_id']) : '';
        if ($userId !== '') {
            if (!ctype_digit($userId)) {
                throw new InvalidArgumentException('user_id должен быть числом.');
            }
            $where[] = 'e.user_id = :user_id';
            $params[':user_id'] = (int) $userId;
        }

        $visitorId = isset($filters['visitor_id']) ? trim((string) $filters['visitor_id']) : '';
        if ($visitorId !== '') {
            $where[] = 'e.visitor_id = :visitor_id';
            $params[':visitor_id'] = $visitorId;
        }

        $date = isset($filters['date']) ? trim((string) $filters['date']) : '';
        if ($date !== '') {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
                throw new InvalidArgumentException('Дата должна быть в формате YYYY-MM-DD.');
            }
            $where[] = 'date(e.created_at) = date(:event_date)';
            $params[':event_date'] = $date;
        }

        if ($date === '') {
            [$periodSql, $periodParams] = $this->periodWhere($this->analyticsPeriod($filters), 'e.created_at', 'events_filter');
            if ($periodSql !== '') {
                $where[] = $periodSql;
                $params += $periodParams;
            }
        }

        $limit = isset($filters['limit']) && is_numeric($filters['limit']) ? (int) $filters['limit'] : 100;
        $limit = max(1, min(200, $limit));
        $whereSql = $where !== [] ? 'WHERE ' . implode(' AND ', $where) : '';

        $rows = Database::query(
            'SELECT
                e.id,
                e.visitor_id,
                e.user_id,
                e.guest_id,
                e.event_type,
                e.page,
                e.entity_type,
                e.entity_id,
                e.payload_json,
                e.created_at,
                u.username,
                u.email
            FROM events e
            LEFT JOIN users u ON u.id = e.user_id
            ' . $whereSql . '
            ORDER BY e.created_at DESC, e.id DESC
            LIMIT ' . $limit,
            $params
        )->fetchAll();

        return array_map([$this, 'eventRow'], $rows);
    }

    /**
     * @return list<string>
     */
    public function eventTypes(): array
    {
        $rows = Database::query(
            'SELECT event_type FROM events GROUP BY event_type ORDER BY event_type ASC'
        )->fetchAll();

        return array_map(static fn (array $row): string => (string) $row['event_type'], $rows);
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return array{key:string,label:string,from:?string,to:?string,days:?int,is_all:bool}
     */
    private function analyticsPeriod(array $filters): array
    {
        $period = isset($filters['period']) ? trim((string) $filters['period']) : '7d';
        $period = in_array($period, self::ANALYTICS_PERIODS, true) ? $period : '7d';

        $date = isset($filters['date']) ? trim((string) $filters['date']) : '';
        if ($date !== '') {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
                throw new InvalidArgumentException('Дата должна быть в формате YYYY-MM-DD.');
            }

            return [
                'key' => 'date',
                'label' => 'Выбранная дата',
                'from' => $date,
                'to' => $date,
                'days' => 1,
                'is_all' => false,
            ];
        }

        $today = gmdate('Y-m-d');

        return match ($period) {
            'today' => [
                'key' => 'today',
                'label' => 'Сегодня',
                'from' => $today,
                'to' => $today,
                'days' => 1,
                'is_all' => false,
            ],
            '30d' => [
                'key' => '30d',
                'label' => '30 дней',
                'from' => gmdate('Y-m-d', strtotime('-29 days') ?: time()),
                'to' => $today,
                'days' => 30,
                'is_all' => false,
            ],
            'all' => [
                'key' => 'all',
                'label' => 'Всё время',
                'from' => null,
                'to' => null,
                'days' => null,
                'is_all' => true,
            ],
            default => [
                'key' => '7d',
                'label' => '7 дней',
                'from' => gmdate('Y-m-d', strtotime('-6 days') ?: time()),
                'to' => $today,
                'days' => 7,
                'is_all' => false,
            ],
        };
    }

    /**
     * @param array{key:string,label:string,from:?string,to:?string,days:?int,is_all:bool} $period
     *
     * @return array{key:string,label:string,from:?string,to:?string,days:?int,is_all:bool}|null
     */
    private function previousAnalyticsPeriod(array $period): ?array
    {
        if ($period['is_all'] || $period['from'] === null || $period['to'] === null || $period['days'] === null) {
            return null;
        }

        $days = (int) $period['days'];
        $from = (new \DateTimeImmutable($period['from']))->modify('-' . $days . ' days')->format('Y-m-d');
        $to = (new \DateTimeImmutable($period['to']))->modify('-' . $days . ' days')->format('Y-m-d');

        return [
            'key' => 'previous_' . $period['key'],
            'label' => 'Предыдущий период',
            'from' => $from,
            'to' => $to,
            'days' => $days,
            'is_all' => false,
        ];
    }

    /**
     * @param array{key:string,label:string,from:?string,to:?string,days:?int,is_all:bool} $period
     *
     * @return array{0:string,1:array<string, mixed>}
     */
    private function periodWhere(array $period, string $column, string $prefix): array
    {
        if ($period['is_all']) {
            return ['', []];
        }

        return [
            'date(' . $column . ') BETWEEN date(:' . $prefix . '_from) AND date(:' . $prefix . '_to)',
            [
                ':' . $prefix . '_from' => $period['from'],
                ':' . $prefix . '_to' => $period['to'],
            ],
        ];
    }

    /**
     * @param array{key:string,label:string,from:?string,to:?string,days:?int,is_all:bool} $period
     * @param array<string, mixed> $params
     */
    private function countByPeriod(string $table, string $column, array $period, string $extraWhere = '', array $params = []): int
    {
        [$periodSql, $periodParams] = $this->periodWhere($period, $column, str_replace('.', '_', $table . '_' . $column));
        $where = [];
        if ($extraWhere !== '') {
            $where[] = $extraWhere;
        }
        if ($periodSql !== '') {
            $where[] = $periodSql;
            $params += $periodParams;
        }

        $whereSql = $where !== [] ? ' WHERE ' . implode(' AND ', $where) : '';

        return (int) Database::query('SELECT COUNT(*) FROM ' . $table . $whereSql, $params)->fetchColumn();
    }

    /**
     * @param array{key:string,label:string,from:?string,to:?string,days:?int,is_all:bool} $period
     *
     * @return array<string, int>
     */
    private function analyticsKpis(array $period): array
    {
        [$eventWhere, $eventParams] = $this->periodWhere($period, 'e.created_at', 'kpi_events');
        $eventDateSql = $eventWhere !== '' ? ' AND ' . $eventWhere : '';

        $activeUsers = (int) Database::query(
            'SELECT COUNT(DISTINCT e.user_id) FROM events e WHERE e.user_id IS NOT NULL' . $eventDateSql,
            $eventParams
        )->fetchColumn();

        return [
            'visitors' => $this->countByPeriod('visitors', 'last_seen_at', $period),
            'new_visitors' => $this->countByPeriod('visitors', 'first_seen_at', $period),
            'registered_users' => $this->count('users'),
            'active_users' => $activeUsers,
            'events' => $this->countByPeriod('events', 'created_at', $period),
            'pack_openings' => $this->countByPeriod('events', 'created_at', $period, 'event_type = :pack_opened', [':pack_opened' => 'pack_opened']),
            'card_openings' => $this->countByPeriod('openings', 'opened_at', $period),
            'saved_cards' => $this->countByPeriod('saved_cards', 'created_at', $period),
            'guest_save_attempts' => $this->countByPeriod('events', 'created_at', $period, 'event_type = :guest_save', [':guest_save' => 'save_failed_guest']),
            'registrations' => $this->countByPeriod('users', 'created_at', $period),
        ];
    }

    /**
     * @param array<string, int> $current
     * @param array<string, int>|null $previous
     *
     * @return array<string, array<string, mixed>>
     */
    private function kpiChanges(array $current, ?array $previous): array
    {
        if ($previous === null) {
            return [];
        }

        $changes = [];
        foreach ($current as $key => $value) {
            $old = $previous[$key] ?? 0;
            if ($old === 0 && $value === 0) {
                $changes[$key] = ['label' => 'без изменений', 'direction' => 'flat', 'percent' => 0];
                continue;
            }

            if ($old === 0) {
                $changes[$key] = ['label' => 'новые данные', 'direction' => 'up', 'percent' => null];
                continue;
            }

            $percent = (int) round((($value - $old) / $old) * 100);
            $changes[$key] = [
                'label' => $percent === 0 ? 'без изменений' : (($percent > 0 ? '+' : '') . $percent . '%'),
                'direction' => $percent > 0 ? 'up' : ($percent < 0 ? 'down' : 'flat'),
                'percent' => $percent,
            ];
        }

        return $changes;
    }

    /**
     * @param array{key:string,label:string,from:?string,to:?string,days:?int,is_all:bool} $period
     *
     * @return list<array<string, mixed>>
     */
    private function activityByDay(array $period): array
    {
        [$periodSql, $params] = $this->periodWhere($period, 'e.created_at', 'activity');
        $where = ['e.event_type IN ("' . implode('","', self::ACTIVITY_EVENT_TYPES) . '")'];
        if ($periodSql !== '') {
            $where[] = $periodSql;
        }

        $rows = Database::query(
            'SELECT date(e.created_at) AS event_date, e.event_type, COUNT(*) AS count
            FROM events e
            WHERE ' . implode(' AND ', $where) . '
            GROUP BY event_date, e.event_type
            ORDER BY event_date ASC',
            $params
        )->fetchAll();

        $days = [];
        if (!$period['is_all'] && $period['from'] !== null && $period['to'] !== null) {
            $cursor = new \DateTimeImmutable($period['from']);
            $end = new \DateTimeImmutable($period['to']);
            while ($cursor <= $end) {
                $days[$cursor->format('Y-m-d')] = $this->emptyActivityDay($cursor->format('Y-m-d'));
                $cursor = $cursor->modify('+1 day');
            }
        }

        foreach ($rows as $row) {
            $date = (string) $row['event_date'];
            if (!isset($days[$date])) {
                $days[$date] = $this->emptyActivityDay($date);
            }
            $type = (string) $row['event_type'];
            $days[$date][$type] = (int) $row['count'];
        }

        ksort($days);

        return array_values($days);
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyActivityDay(string $date): array
    {
        $day = ['date' => $date];
        foreach (self::ACTIVITY_EVENT_TYPES as $type) {
            $day[$type] = 0;
        }

        return $day;
    }

    /**
     * @param array{key:string,label:string,from:?string,to:?string,days:?int,is_all:bool} $period
     *
     * @return list<array<string, mixed>>
     */
    private function analyticsTopPacks(array $period): array
    {
        $packs = [];

        $eventRows = $this->packEventRows($period);
        $cardRows = $this->packCardRows($period);
        $saveRows = $this->packSaveRows($period);

        foreach ($eventRows as $row) {
            $slug = (string) $row['slug'];
            $packs[$slug] = $this->packMetricBase($row);
            $packs[$slug]['opens'] = (int) $row['count'];
        }

        foreach ($cardRows as $row) {
            $slug = (string) $row['slug'];
            $packs[$slug] ??= $this->packMetricBase($row);
            $packs[$slug]['cards'] = (int) $row['count'];
        }

        foreach ($saveRows as $row) {
            $slug = (string) $row['slug'];
            $packs[$slug] ??= $this->packMetricBase($row);
            $packs[$slug]['saves'] = (int) $row['count'];
        }

        $totalOpens = array_sum(array_map(static fn (array $row): int => (int) $row['opens'], $packs));
        $totalCards = array_sum(array_map(static fn (array $row): int => (int) $row['cards'], $packs));
        foreach ($packs as &$pack) {
            $basis = $totalOpens > 0 ? $totalOpens : max(1, $totalCards);
            $value = $totalOpens > 0 ? (int) $pack['opens'] : (int) $pack['cards'];
            $pack['share'] = round(($value / $basis) * 100, 1);
        }
        unset($pack);

        usort($packs, static fn (array $a, array $b): int => ((int) $b['opens'] <=> (int) $a['opens'])
            ?: ((int) $b['cards'] <=> (int) $a['cards'])
            ?: ((int) $b['saves'] <=> (int) $a['saves'])
            ?: strcmp((string) $a['title'], (string) $b['title']));

        return array_slice(array_values($packs), 0, 10);
    }

    /**
     * @param array{key:string,label:string,from:?string,to:?string,days:?int,is_all:bool} $period
     *
     * @return list<array<string, mixed>>
     */
    private function packEventRows(array $period): array
    {
        [$periodSql, $params] = $this->periodWhere($period, 'e.created_at', 'pack_events');
        $where = ['e.event_type = "pack_opened"', 'e.entity_type = "pack"'];
        if ($periodSql !== '') {
            $where[] = $periodSql;
        }

        return Database::query(
            'SELECT p.slug, p.title, COUNT(e.id) AS count
            FROM events e
            INNER JOIN packs p ON p.id = e.entity_id
            WHERE ' . implode(' AND ', $where) . '
            GROUP BY p.id, p.slug, p.title
            ORDER BY count DESC, p.sort_order ASC, p.id ASC',
            $params
        )->fetchAll();
    }

    /**
     * @param array{key:string,label:string,from:?string,to:?string,days:?int,is_all:bool} $period
     *
     * @return list<array<string, mixed>>
     */
    private function packCardRows(array $period): array
    {
        [$periodSql, $params] = $this->periodWhere($period, 'o.opened_at', 'pack_cards');
        $where = $periodSql !== '' ? 'WHERE ' . $periodSql : '';

        return Database::query(
            'SELECT p.slug, p.title, COUNT(o.id) AS count
            FROM openings o
            INNER JOIN packs p ON p.id = o.pack_id
            ' . $where . '
            GROUP BY p.id, p.slug, p.title
            ORDER BY count DESC, p.sort_order ASC, p.id ASC',
            $params
        )->fetchAll();
    }

    /**
     * @param array{key:string,label:string,from:?string,to:?string,days:?int,is_all:bool} $period
     *
     * @return list<array<string, mixed>>
     */
    private function packSaveRows(array $period): array
    {
        [$periodSql, $params] = $this->periodWhere($period, 'sc.created_at', 'pack_saves');
        $where = $periodSql !== '' ? 'WHERE ' . $periodSql : '';

        return Database::query(
            'SELECT p.slug, p.title, COUNT(sc.id) AS count
            FROM saved_cards sc
            LEFT JOIN openings o ON o.id = sc.opening_id
            LEFT JOIN predictions pr ON pr.id = sc.prediction_id
            INNER JOIN packs p ON p.id = COALESCE(o.pack_id, pr.pack_id)
            ' . $where . '
            GROUP BY p.id, p.slug, p.title
            ORDER BY count DESC, p.sort_order ASC, p.id ASC',
            $params
        )->fetchAll();
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function packMetricBase(array $row): array
    {
        return [
            'slug' => (string) $row['slug'],
            'title' => (string) $row['title'],
            'opens' => 0,
            'cards' => 0,
            'saves' => 0,
            'share' => 0.0,
        ];
    }

    /**
     * @param array{key:string,label:string,from:?string,to:?string,days:?int,is_all:bool} $period
     *
     * @return list<array<string, mixed>>
     */
    private function analyticsRarities(array $period): array
    {
        [$periodSql, $params] = $this->periodWhere($period, 'o.opened_at', 'rarities');
        $where = $periodSql !== '' ? 'WHERE ' . $periodSql : '';
        $rows = Database::query(
            'SELECT o.rarity, COUNT(*) AS count
            FROM openings o
            ' . $where . '
            GROUP BY o.rarity',
            $params
        )->fetchAll();
        $counts = $this->countsByKey($rows, 'rarity');

        return array_map(static fn (string $rarity): array => [
            'rarity' => $rarity,
            'label' => self::RARITY_LABELS[$rarity],
            'count' => (int) ($counts[$rarity] ?? 0),
        ], self::RARITIES);
    }

    /**
     * @param array{key:string,label:string,from:?string,to:?string,days:?int,is_all:bool} $period
     * @param array<string, int> $kpis
     *
     * @return array<string, int>
     */
    private function analyticsFunnel(array $period, array $kpis): array
    {
        [$periodSql, $params] = $this->periodWhere($period, 'e.created_at', 'funnel_events');
        $dateSql = $periodSql !== '' ? ' AND ' . $periodSql : '';

        $openedVisitors = (int) Database::query(
            'SELECT COUNT(DISTINCT e.visitor_id) FROM events e WHERE e.event_type = "pack_opened"' . $dateSql,
            $params
        )->fetchColumn();

        return [
            'visitors' => (int) $kpis['visitors'],
            'opened_pack' => $openedVisitors,
            'guest_save_attempts' => (int) $kpis['guest_save_attempts'],
            'registrations' => (int) $kpis['registrations'],
            'saved_cards' => (int) $kpis['saved_cards'],
        ];
    }

    /**
     * @param array{key:string,label:string,from:?string,to:?string,days:?int,is_all:bool} $period
     * @param array<string, int> $kpis
     *
     * @return array<string, mixed>
     */
    private function usersAndGuests(array $period, array $kpis): array
    {
        [$periodSql, $params] = $this->periodWhere($period, 'e.created_at', 'users_guests');
        $dateSql = $periodSql !== '' ? ' AND ' . $periodSql : '';

        $guestVisitors = (int) Database::query(
            'SELECT COUNT(DISTINCT e.visitor_id) FROM events e WHERE e.user_id IS NULL' . $dateSql,
            $params
        )->fetchColumn();

        $recentUsers = Database::query(
            'SELECT u.id, u.username, MAX(e.created_at) AS last_event_at, COUNT(e.id) AS events_count
            FROM users u
            INNER JOIN events e ON e.user_id = u.id
            WHERE e.user_id IS NOT NULL' . $dateSql . '
            GROUP BY u.id, u.username
            ORDER BY last_event_at DESC
            LIMIT 8',
            $params
        )->fetchAll();

        return [
            'guest_visitors' => $guestVisitors,
            'active_registered_users' => (int) $kpis['active_users'],
            'new_registrations' => (int) $kpis['registrations'],
            'recent_active_users' => array_map(static fn (array $row): array => [
                'id' => (int) $row['id'],
                'username' => (string) $row['username'],
                'last_event_at' => (string) $row['last_event_at'],
                'events_count' => (int) $row['events_count'],
            ], $recentUsers),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function analyticsDataNotes(): array
    {
        $packEvents = (int) Database::query('SELECT COUNT(*) FROM events WHERE event_type = "pack_opened"')->fetchColumn();

        return [
            'pack_openings' => $packEvents > 0
                ? 'Открытия паков считаются по событиям pack_opened.'
                : 'Точных событий pack_opened пока нет: исторические открытия паков нельзя восстановить без риска завысить multi-card паки.',
            'card_openings' => 'Выпавшие карточки считаются по строкам таблицы openings.',
            'visitors' => 'Visitor — технический cookie-идентификатор. После очистки cookies человек станет новым visitor.',
            'guest_save_attempts' => 'Попытки сохранения гостями показывают интерес к коллекции до входа.',
        ];
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return array{0:list<string>,1:array<string, mixed>}
     */
    private function analyticsEventFilters(array $filters): array
    {
        $where = [];
        $params = [];

        $eventType = isset($filters['event_type']) ? trim((string) $filters['event_type']) : '';
        if ($eventType !== '' && $eventType !== 'all') {
            $where[] = 'e.event_type = :event_type';
            $params[':event_type'] = $eventType;
        }

        $userId = isset($filters['user_id']) ? trim((string) $filters['user_id']) : '';
        if ($userId !== '') {
            if (!ctype_digit($userId)) {
                throw new InvalidArgumentException('user_id должен быть числом.');
            }
            $where[] = 'e.user_id = :user_id';
            $params[':user_id'] = (int) $userId;
        }

        $visitorId = isset($filters['visitor_id']) ? trim((string) $filters['visitor_id']) : '';
        if ($visitorId !== '') {
            $where[] = 'e.visitor_id = :visitor_id';
            $params[':visitor_id'] = $visitorId;
        }

        $date = isset($filters['date']) ? trim((string) $filters['date']) : '';
        if ($date !== '') {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
                throw new InvalidArgumentException('Дата должна быть в формате YYYY-MM-DD.');
            }
            $where[] = 'date(e.created_at) = date(:event_date)';
            $params[':event_date'] = $date;
        } else {
            [$periodSql, $periodParams] = $this->periodWhere($this->analyticsPeriod($filters), 'e.created_at', 'event_list');
            if ($periodSql !== '') {
                $where[] = $periodSql;
                $params += $periodParams;
            }
        }

        return [$where, $params];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function packs(): array
    {
        $rows = Database::query(
            'SELECT
                p.id,
                p.slug,
                p.title,
                p.description,
                p.type,
                p.visual_theme,
                p.cards_per_open,
                p.daily_limit,
                p.is_daily_special,
                p.is_active,
                p.sort_order,
                p.created_at,
                p.updated_at,
                COUNT(pr.id) AS predictions_count
            FROM packs p
            LEFT JOIN predictions pr ON pr.pack_id = p.id
            GROUP BY p.id
            ORDER BY p.sort_order ASC, p.id ASC'
        )->fetchAll();

        return array_map([$this, 'packRow'], $rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function packOptions(): array
    {
        $rows = Database::query(
            'SELECT id, slug, title FROM packs ORDER BY sort_order ASC, id ASC'
        )->fetchAll();

        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'slug' => (string) $row['slug'],
            'title' => (string) $row['title'],
        ], $rows);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findPack(int $id): ?array
    {
        $row = Database::query(
            'SELECT
                id, slug, title, description, type, visual_theme, cards_per_open,
                daily_limit, is_daily_special, is_active, sort_order, created_at, updated_at
            FROM packs
            WHERE id = :id
            LIMIT 1',
            [':id' => $id]
        )->fetch();

        return is_array($row) ? $this->packRow($row + ['predictions_count' => 0]) : null;
    }

    /**
     * @param array<string, mixed> $input
     */
    public function createPack(array $input, int $adminId): int
    {
        $data = $this->validatePackData($input);
        $this->ensurePackSlugUnique($data['slug']);

        $pdo = Database::connect();
        $statement = $pdo->prepare(
            'INSERT INTO packs (
                slug, title, description, type, visual_theme, cards_per_open, daily_limit,
                is_daily_special, is_active, sort_order, created_at, updated_at
            ) VALUES (
                :slug, :title, :description, :type, :visual_theme, :cards_per_open, :daily_limit,
                :is_daily_special, :is_active, :sort_order, :created_at, :updated_at
            )'
        );
        $now = gmdate('c');
        $statement->execute([
            ':slug' => $data['slug'],
            ':title' => $data['title'],
            ':description' => $data['description'],
            ':type' => $data['type'],
            ':visual_theme' => $data['visual_theme'],
            ':cards_per_open' => $data['cards_per_open'],
            ':daily_limit' => $data['daily_limit'],
            ':is_daily_special' => $data['is_daily_special'],
            ':is_active' => $data['is_active'],
            ':sort_order' => $data['sort_order'],
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);

        $id = (int) $pdo->lastInsertId();
        $this->log($adminId, 'create_pack', 'pack', $id, ['new' => $data]);

        return $id;
    }

    /**
     * @param array<string, mixed> $input
     */
    public function updatePack(array $input, int $adminId): void
    {
        $id = $this->intField($input, 'id');
        $old = $this->findPack($id);

        if ($old === null) {
            throw new InvalidArgumentException('Пак не найден.');
        }

        $data = $this->validatePackData($input);
        $this->ensurePackSlugUnique($data['slug'], $id);

        Database::query(
            'UPDATE packs SET
                slug = :slug,
                title = :title,
                description = :description,
                type = :type,
                visual_theme = :visual_theme,
                cards_per_open = :cards_per_open,
                daily_limit = :daily_limit,
                is_daily_special = :is_daily_special,
                is_active = :is_active,
                sort_order = :sort_order,
                updated_at = :updated_at
            WHERE id = :id',
            [
                ':slug' => $data['slug'],
                ':title' => $data['title'],
                ':description' => $data['description'],
                ':type' => $data['type'],
                ':visual_theme' => $data['visual_theme'],
                ':cards_per_open' => $data['cards_per_open'],
                ':daily_limit' => $data['daily_limit'],
                ':is_daily_special' => $data['is_daily_special'],
                ':is_active' => $data['is_active'],
                ':sort_order' => $data['sort_order'],
                ':updated_at' => gmdate('c'),
                ':id' => $id,
            ]
        );

        $this->log($adminId, 'update_pack', 'pack', $id, [
            'changed' => $this->changedFields($old, $data, array_keys($data)),
        ]);
    }

    public function togglePack(int $id, int $adminId): int
    {
        $pack = $this->findPack($id);

        if ($pack === null) {
            throw new InvalidArgumentException('Пак не найден.');
        }

        $newStatus = (int) $pack['is_active'] === 1 ? 0 : 1;
        Database::query(
            'UPDATE packs SET is_active = :is_active, updated_at = :updated_at WHERE id = :id',
            [
                ':is_active' => $newStatus,
                ':updated_at' => gmdate('c'),
                ':id' => $id,
            ]
        );

        $this->log($adminId, 'toggle_pack', 'pack', $id, [
            'old_is_active' => (int) $pack['is_active'],
            'new_is_active' => $newStatus,
        ]);

        return $newStatus;
    }

    /**
     * @param list<array<string, mixed>> $items
     */
    public function reorderPacks(array $items, int $adminId): void
    {
        $pdo = Database::connect();
        $pdo->beginTransaction();

        try {
            $statement = $pdo->prepare('UPDATE packs SET sort_order = :sort_order, updated_at = :updated_at WHERE id = :id');
            $changes = [];

            foreach ($items as $item) {
                $id = isset($item['id']) && is_numeric($item['id']) ? (int) $item['id'] : 0;
                $sortOrder = isset($item['sort_order']) && is_numeric($item['sort_order']) ? (int) $item['sort_order'] : 0;

                if ($id < 1) {
                    continue;
                }

                $statement->execute([
                    ':sort_order' => $sortOrder,
                    ':updated_at' => gmdate('c'),
                    ':id' => $id,
                ]);
                $changes[] = ['id' => $id, 'sort_order' => $sortOrder];
            }

            $pdo->commit();
            $this->log($adminId, 'reorder_pack', 'pack', null, ['items' => $changes]);
        } catch (\Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return list<array<string, mixed>>
     */
    public function predictions(array $filters = []): array
    {
        $where = [];
        $params = [];

        $pack = isset($filters['pack_id']) ? trim((string) $filters['pack_id']) : '';
        if ($pack !== '' && $pack !== 'all') {
            if (!ctype_digit($pack)) {
                throw new InvalidArgumentException('Фильтр пака выглядит странно.');
            }
            $where[] = 'pr.pack_id = :pack_id';
            $params[':pack_id'] = (int) $pack;
        }

        $rarity = isset($filters['rarity']) ? trim((string) $filters['rarity']) : '';
        if ($rarity !== '' && $rarity !== 'all') {
            if (!in_array($rarity, self::RARITIES, true)) {
                throw new InvalidArgumentException('Такой редкости нет.');
            }
            $where[] = 'pr.rarity = :rarity';
            $params[':rarity'] = $rarity;
        }

        $active = isset($filters['active']) ? trim((string) $filters['active']) : '';
        if ($active === '1' || $active === '0') {
            $where[] = 'pr.is_active = :active';
            $params[':active'] = (int) $active;
        }

        $search = isset($filters['search']) ? trim((string) $filters['search']) : '';
        if ($search !== '') {
            $where[] = '(pr.title LIKE :search OR pr.text LIKE :search)';
            $params[':search'] = '%' . $search . '%';
        }

        $whereSql = $where !== [] ? 'WHERE ' . implode(' AND ', $where) : '';

        $rows = Database::query(
            'SELECT
                pr.id,
                pr.pack_id,
                pr.title,
                pr.text,
                pr.rarity,
                pr.mood_tag,
                pr.tone_tag,
                pr.is_active,
                pr.created_at,
                pr.updated_at,
                p.slug AS pack_slug,
                p.title AS pack_title
            FROM predictions pr
            INNER JOIN packs p ON p.id = pr.pack_id
            ' . $whereSql . '
            ORDER BY pr.updated_at DESC, pr.id DESC
            LIMIT 200',
            $params
        )->fetchAll();

        return array_map([$this, 'predictionRow'], $rows);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findPrediction(int $id): ?array
    {
        $row = Database::query(
            'SELECT
                pr.id,
                pr.pack_id,
                pr.title,
                pr.text,
                pr.rarity,
                pr.mood_tag,
                pr.tone_tag,
                pr.is_active,
                pr.created_at,
                pr.updated_at,
                p.slug AS pack_slug,
                p.title AS pack_title
            FROM predictions pr
            INNER JOIN packs p ON p.id = pr.pack_id
            WHERE pr.id = :id
            LIMIT 1',
            [':id' => $id]
        )->fetch();

        return is_array($row) ? $this->predictionRow($row) : null;
    }

    /**
     * @param array<string, mixed> $input
     */
    public function createPrediction(array $input, int $adminId): int
    {
        $data = $this->validatePredictionData($input);
        $pdo = Database::connect();
        $statement = $pdo->prepare(
            'INSERT INTO predictions (
                pack_id, title, text, rarity, mood_tag, tone_tag, is_active, created_at, updated_at
            ) VALUES (
                :pack_id, :title, :text, :rarity, :mood_tag, :tone_tag, :is_active, :created_at, :updated_at
            )'
        );
        $now = gmdate('c');
        $statement->execute([
            ':pack_id' => $data['pack_id'],
            ':title' => $data['title'],
            ':text' => $data['text'],
            ':rarity' => $data['rarity'],
            ':mood_tag' => $data['mood_tag'],
            ':tone_tag' => $data['tone_tag'],
            ':is_active' => $data['is_active'],
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);

        $id = (int) $pdo->lastInsertId();
        $this->log($adminId, 'create_prediction', 'prediction', $id, ['new' => $data]);

        return $id;
    }

    /**
     * @param array<string, mixed> $input
     */
    public function updatePrediction(array $input, int $adminId): void
    {
        $id = $this->intField($input, 'id');
        $old = $this->findPrediction($id);

        if ($old === null) {
            throw new InvalidArgumentException('Карточка не найдена.');
        }

        $data = $this->validatePredictionData($input);
        Database::query(
            'UPDATE predictions SET
                pack_id = :pack_id,
                title = :title,
                text = :text,
                rarity = :rarity,
                mood_tag = :mood_tag,
                tone_tag = :tone_tag,
                is_active = :is_active,
                updated_at = :updated_at
            WHERE id = :id',
            [
                ':pack_id' => $data['pack_id'],
                ':title' => $data['title'],
                ':text' => $data['text'],
                ':rarity' => $data['rarity'],
                ':mood_tag' => $data['mood_tag'],
                ':tone_tag' => $data['tone_tag'],
                ':is_active' => $data['is_active'],
                ':updated_at' => gmdate('c'),
                ':id' => $id,
            ]
        );

        $this->log($adminId, 'update_prediction', 'prediction', $id, [
            'changed' => $this->changedFields($old, $data, array_keys($data)),
        ]);
    }

    public function togglePrediction(int $id, int $adminId): int
    {
        $prediction = $this->findPrediction($id);

        if ($prediction === null) {
            throw new InvalidArgumentException('Карточка не найдена.');
        }

        $newStatus = (int) $prediction['is_active'] === 1 ? 0 : 1;
        Database::query(
            'UPDATE predictions SET is_active = :is_active, updated_at = :updated_at WHERE id = :id',
            [
                ':is_active' => $newStatus,
                ':updated_at' => gmdate('c'),
                ':id' => $id,
            ]
        );

        $this->log($adminId, 'toggle_prediction', 'prediction', $id, [
            'old_is_active' => (int) $prediction['is_active'],
            'new_is_active' => $newStatus,
        ]);

        return $newStatus;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function users(): array
    {
        $rows = Database::query(
            'SELECT
                u.id,
                u.username,
                u.email,
                u.role,
                u.created_at,
                u.last_login_at,
                u.is_blocked,
                COUNT(DISTINCT o.id) AS openings_count,
                COUNT(DISTINCT sc.id) AS saved_count
            FROM users u
            LEFT JOIN openings o ON o.user_id = u.id
            LEFT JOIN saved_cards sc ON sc.user_id = u.id
            GROUP BY u.id
            ORDER BY u.created_at DESC, u.id DESC'
        )->fetchAll();

        return array_map([$this, 'userRow'], $rows);
    }

    public function toggleUserBlock(int $targetUserId, int $adminId): int
    {
        if ($targetUserId === $adminId) {
            throw new InvalidArgumentException('Себя блокировать нельзя. Даже если день странный.');
        }

        $user = $this->findUser($targetUserId);

        if ($user === null) {
            throw new InvalidArgumentException('Пользователь не найден.');
        }

        $newStatus = (int) $user['is_blocked'] === 1 ? 0 : 1;
        Database::query(
            'UPDATE users SET is_blocked = :is_blocked WHERE id = :id',
            [
                ':is_blocked' => $newStatus,
                ':id' => $targetUserId,
            ]
        );

        $this->log($adminId, $newStatus === 1 ? 'block_user' : 'unblock_user', 'user', $targetUserId, [
            'old_is_blocked' => (int) $user['is_blocked'],
            'new_is_blocked' => $newStatus,
        ]);

        return $newStatus;
    }

    public function updateUserRole(int $targetUserId, string $role, int $adminId): void
    {
        $role = trim($role);

        if (!in_array($role, ['user', 'admin'], true)) {
            throw new InvalidArgumentException('Роль может быть только user или admin.');
        }

        $user = $this->findUser($targetUserId);

        if ($user === null) {
            throw new InvalidArgumentException('Пользователь не найден.');
        }

        if ((string) $user['role'] === 'admin' && $role !== 'admin' && $this->adminCount() <= 1) {
            throw new InvalidArgumentException('Нельзя оставить проект без единого admin.');
        }

        if ($targetUserId === $adminId && (string) $user['role'] === 'admin' && $role !== 'admin' && $this->adminCount() <= 1) {
            throw new InvalidArgumentException('Нельзя снять с себя роль admin, пока ты единственный admin.');
        }

        Database::query(
            'UPDATE users SET role = :role WHERE id = :id',
            [
                ':role' => $role,
                ':id' => $targetUserId,
            ]
        );

        $this->log($adminId, 'update_user_role', 'user', $targetUserId, [
            'old_role' => (string) $user['role'],
            'new_role' => $role,
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function logs(int $limit = 100): array
    {
        $limit = max(1, min(100, $limit));
        $rows = Database::query(
            'SELECT
                al.id,
                al.admin_user_id,
                al.action,
                al.entity_type,
                al.entity_id,
                al.payload_json,
                al.created_at,
                u.username AS admin_username,
                u.email AS admin_email
            FROM admin_logs al
            LEFT JOIN users u ON u.id = al.admin_user_id
            ORDER BY al.created_at DESC, al.id DESC
            LIMIT ' . $limit
        )->fetchAll();

        return array_map([$this, 'logRow'], $rows);
    }

    private function count(string $table): int
    {
        $allowed = ['users', 'openings', 'saved_cards', 'packs', 'predictions'];

        if (!in_array($table, $allowed, true)) {
            throw new RuntimeException('Unsupported count table.');
        }

        return (int) Database::connect()->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
    }

    /**
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    private function validatePackData(array $input): array
    {
        $slug = strtolower(trim((string) ($input['slug'] ?? '')));
        $title = trim((string) ($input['title'] ?? ''));
        $description = trim((string) ($input['description'] ?? ''));
        $type = trim((string) ($input['type'] ?? ''));
        $visualTheme = trim((string) ($input['visual_theme'] ?? 'default'));
        $cardsPerOpen = $this->intField($input, 'cards_per_open', 1);
        $dailyLimitRaw = $input['daily_limit'] ?? null;
        $dailyLimit = $dailyLimitRaw === null || trim((string) $dailyLimitRaw) === '' ? null : (int) $dailyLimitRaw;

        if (!Validator::required($slug) || !Validator::slug($slug)) {
            throw new InvalidArgumentException('Slug нужен в формате kebab-case.');
        }

        if (!Validator::required($title)) {
            throw new InvalidArgumentException('Название пака обязательно.');
        }

        if (!Validator::maxLength($title, 140)) {
            throw new InvalidArgumentException('Название пака слишком длинное.');
        }

        if (!Validator::maxLength($description, 1000)) {
            throw new InvalidArgumentException('Описание лучше держать до 1000 символов.');
        }

        if (!in_array($type, self::PACK_TYPES, true)) {
            throw new InvalidArgumentException('Тип пака не поддерживается.');
        }

        if (!Validator::required($visualTheme) || !Validator::maxLength($visualTheme, 80)) {
            throw new InvalidArgumentException('visual_theme должен быть короткой строкой.');
        }

        if ($cardsPerOpen < 1 || $cardsPerOpen > 10) {
            throw new InvalidArgumentException('cards_per_open должен быть от 1 до 10.');
        }

        if ($dailyLimit !== null && $dailyLimit < 1) {
            throw new InvalidArgumentException('daily_limit должен быть пустым или больше 0.');
        }

        return [
            'slug' => $slug,
            'title' => $title,
            'description' => $description === '' ? null : $description,
            'type' => $type,
            'visual_theme' => $visualTheme,
            'cards_per_open' => $cardsPerOpen,
            'daily_limit' => $dailyLimit,
            'is_daily_special' => $this->boolField($input, 'is_daily_special'),
            'is_active' => $this->boolField($input, 'is_active'),
            'sort_order' => $this->intField($input, 'sort_order', 0),
        ];
    }

    /**
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    private function validatePredictionData(array $input): array
    {
        $packId = $this->intField($input, 'pack_id');
        $title = trim((string) ($input['title'] ?? ''));
        $text = trim((string) ($input['text'] ?? ''));
        $rarity = trim((string) ($input['rarity'] ?? 'common'));
        $moodTag = trim((string) ($input['mood_tag'] ?? ''));
        $toneTag = trim((string) ($input['tone_tag'] ?? ''));

        if ($packId < 1 || !$this->packExists($packId)) {
            throw new InvalidArgumentException('Выбери существующий пак.');
        }

        if ($title !== '' && !Validator::maxLength($title, 120)) {
            throw new InvalidArgumentException('Заголовок карточки слишком длинный.');
        }

        if (!Validator::required($text)) {
            throw new InvalidArgumentException('Текст карточки обязателен.');
        }

        if (!Validator::maxLength($text, 1000)) {
            throw new InvalidArgumentException('Текст карточки лучше держать до 1000 символов.');
        }

        if (!in_array($rarity, self::RARITIES, true)) {
            throw new InvalidArgumentException('Редкость не поддерживается.');
        }

        if (!Validator::maxLength($moodTag, 80) || !Validator::maxLength($toneTag, 80)) {
            throw new InvalidArgumentException('Теги лучше держать до 80 символов.');
        }

        return [
            'pack_id' => $packId,
            'title' => $title === '' ? null : $title,
            'text' => $text,
            'rarity' => $rarity,
            'mood_tag' => $moodTag === '' ? null : $moodTag,
            'tone_tag' => $toneTag === '' ? null : $toneTag,
            'is_active' => $this->boolField($input, 'is_active'),
        ];
    }

    private function ensurePackSlugUnique(string $slug, ?int $exceptId = null): void
    {
        if ($exceptId === null) {
            $exists = Database::query('SELECT COUNT(*) FROM packs WHERE slug = :slug', [':slug' => $slug])->fetchColumn();
        } else {
            $exists = Database::query(
                'SELECT COUNT(*) FROM packs WHERE slug = :slug AND id != :id',
                [
                    ':slug' => $slug,
                    ':id' => $exceptId,
                ]
            )->fetchColumn();
        }

        if ((int) $exists > 0) {
            throw new InvalidArgumentException('Пак с таким slug уже есть.');
        }
    }

    private function packExists(int $packId): bool
    {
        return (int) Database::query('SELECT COUNT(*) FROM packs WHERE id = :id', [':id' => $packId])->fetchColumn() > 0;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findUser(int $id): ?array
    {
        $row = Database::query(
            'SELECT id, username, email, role, created_at, last_login_at, is_blocked
            FROM users
            WHERE id = :id
            LIMIT 1',
            [':id' => $id]
        )->fetch();

        return is_array($row) ? $row : null;
    }

    private function adminCount(): int
    {
        return (int) Database::query('SELECT COUNT(*) FROM users WHERE role = "admin"')->fetchColumn();
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function log(int $adminId, string $action, ?string $entityType, ?int $entityId, array $payload = []): void
    {
        Database::query(
            'INSERT INTO admin_logs (admin_user_id, action, entity_type, entity_id, payload_json, created_at)
            VALUES (:admin_user_id, :action, :entity_type, :entity_id, :payload_json, :created_at)',
            [
                ':admin_user_id' => $adminId,
                ':action' => $action,
                ':entity_type' => $entityType,
                ':entity_id' => $entityId,
                ':payload_json' => $payload === [] ? null : Security::safeJsonEncode($payload),
                ':created_at' => gmdate('c'),
            ]
        );
    }

    /**
     * @param array<string, mixed> $input
     */
    private function intField(array $input, string $key, ?int $default = null): int
    {
        $value = $input[$key] ?? null;

        if ($value === null || $value === '') {
            if ($default !== null) {
                return $default;
            }

            throw new InvalidArgumentException('Поле ' . $key . ' обязательно.');
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        throw new InvalidArgumentException('Поле ' . $key . ' должно быть числом.');
    }

    /**
     * @param array<string, mixed> $input
     */
    private function boolField(array $input, string $key): int
    {
        $value = $input[$key] ?? 0;

        return in_array($value, [1, '1', true, 'true', 'on', 'yes'], true) ? 1 : 0;
    }

    /**
     * @param array<string, mixed> $old
     * @param array<string, mixed> $new
     * @param list<string> $keys
     *
     * @return array<string, array<string, mixed>>
     */
    private function changedFields(array $old, array $new, array $keys): array
    {
        $changed = [];

        foreach ($keys as $key) {
            $oldValue = $old[$key] ?? null;
            $newValue = $new[$key] ?? null;

            if ((string) $oldValue !== (string) $newValue) {
                $changed[$key] = [
                    'old' => $oldValue,
                    'new' => $newValue,
                ];
            }
        }

        return $changed;
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function packRow(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'slug' => (string) $row['slug'],
            'title' => (string) $row['title'],
            'description' => $row['description'] !== null ? (string) $row['description'] : null,
            'type' => (string) $row['type'],
            'visual_theme' => (string) $row['visual_theme'],
            'cards_per_open' => (int) $row['cards_per_open'],
            'daily_limit' => $row['daily_limit'] !== null ? (int) $row['daily_limit'] : null,
            'is_daily_special' => (int) $row['is_daily_special'],
            'is_active' => (int) $row['is_active'],
            'sort_order' => (int) $row['sort_order'],
            'created_at' => (string) $row['created_at'],
            'updated_at' => (string) $row['updated_at'],
            'predictions_count' => (int) ($row['predictions_count'] ?? 0),
        ];
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function predictionRow(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'pack_id' => (int) $row['pack_id'],
            'pack_slug' => (string) $row['pack_slug'],
            'pack_title' => (string) $row['pack_title'],
            'title' => $row['title'] !== null ? (string) $row['title'] : null,
            'text' => (string) $row['text'],
            'rarity' => (string) $row['rarity'],
            'mood_tag' => $row['mood_tag'] !== null ? (string) $row['mood_tag'] : null,
            'tone_tag' => $row['tone_tag'] !== null ? (string) $row['tone_tag'] : null,
            'is_active' => (int) $row['is_active'],
            'created_at' => (string) $row['created_at'],
            'updated_at' => (string) $row['updated_at'],
        ];
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function userRow(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'username' => (string) $row['username'],
            'email' => $row['email'] !== null ? (string) $row['email'] : null,
            'role' => (string) $row['role'],
            'created_at' => (string) $row['created_at'],
            'last_login_at' => $row['last_login_at'] !== null ? (string) $row['last_login_at'] : null,
            'is_blocked' => (int) $row['is_blocked'],
            'openings_count' => (int) $row['openings_count'],
            'saved_count' => (int) $row['saved_count'],
        ];
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function logRow(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'admin_user_id' => $row['admin_user_id'] !== null ? (int) $row['admin_user_id'] : null,
            'admin_username' => $row['admin_username'] !== null ? (string) $row['admin_username'] : null,
            'admin_email' => $row['admin_email'] !== null ? (string) $row['admin_email'] : null,
            'action' => (string) $row['action'],
            'entity_type' => $row['entity_type'] !== null ? (string) $row['entity_type'] : null,
            'entity_id' => $row['entity_id'] !== null ? (int) $row['entity_id'] : null,
            'payload_json' => $row['payload_json'] !== null ? (string) $row['payload_json'] : null,
            'created_at' => (string) $row['created_at'],
        ];
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function eventRow(array $row): array
    {
        $visitorId = (string) $row['visitor_id'];
        $payload = $this->safeStoredPayload($row['payload_json'] !== null ? (string) $row['payload_json'] : null);

        return [
            'id' => (int) $row['id'],
            'visitor_id' => $this->shortVisitorId($visitorId),
            'visitor_id_short' => $this->shortVisitorId($visitorId),
            'user_id' => $row['user_id'] !== null ? (int) $row['user_id'] : null,
            'guest_id' => $row['guest_id'] !== null ? (string) $row['guest_id'] : null,
            'event_type' => (string) $row['event_type'],
            'event_label' => self::EVENT_LABELS[(string) $row['event_type']] ?? (string) $row['event_type'],
            'page' => $row['page'] !== null ? (string) $row['page'] : null,
            'entity_type' => $row['entity_type'] !== null ? (string) $row['entity_type'] : null,
            'entity_id' => $row['entity_id'] !== null ? (int) $row['entity_id'] : null,
            'payload_json' => $payload['json'],
            'payload_summary' => $payload['summary'],
            'created_at' => (string) $row['created_at'],
            'username' => $row['username'] !== null ? (string) $row['username'] : null,
        ];
    }

    private function shortVisitorId(string $visitorId): string
    {
        if (strlen($visitorId) <= 14) {
            return $visitorId;
        }

        return substr($visitorId, 0, 6) . '...' . substr($visitorId, -4);
    }

    /**
     * @return array{json:?string,summary:string}
     */
    private function safeStoredPayload(?string $payloadJson): array
    {
        if ($payloadJson === null || trim($payloadJson) === '') {
            return ['json' => null, 'summary' => ''];
        }

        $decoded = json_decode($payloadJson, true);
        if (!is_array($decoded)) {
            return ['json' => null, 'summary' => 'payload не JSON'];
        }

        $safe = $this->redactPayload($decoded);
        $summaryParts = [];
        foreach ($safe as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $summaryParts[] = $key . ': ' . (string) $value;
            }
        }

        return [
            'json' => Security::safeJsonEncode($safe),
            'summary' => implode(', ', array_slice($summaryParts, 0, 4)),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function redactPayload(array $payload): array
    {
        $blocked = ['password', 'password_hash', 'current_password', 'new_password', 'session_secret', 'token', '_csrf_token', 'csrf_token'];
        foreach ($payload as $key => $value) {
            if (in_array(strtolower((string) $key), $blocked, true)) {
                unset($payload[$key]);
                continue;
            }

            if (is_array($value)) {
                $payload[$key] = $this->redactPayload($value);
            }
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function topPackRow(array $row): array
    {
        return [
            'slug' => (string) $row['slug'],
            'title' => (string) $row['title'],
            'openings_count' => (int) $row['openings_count'],
        ];
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function recentOpeningRow(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'opened_at' => (string) $row['opened_at'],
            'rarity' => (string) $row['rarity'],
            'owner_label' => (string) $row['owner_label'],
            'pack_title' => (string) $row['pack_title'],
            'prediction_title' => $row['prediction_title'] !== null ? (string) $row['prediction_title'] : null,
            'prediction_text' => (string) $row['prediction_text'],
        ];
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return array<string, int>
     */
    private function countsByKey(array $rows, string $key): array
    {
        $result = [];

        foreach ($rows as $row) {
            $result[(string) $row[$key]] = (int) $row['count'];
        }

        return $result;
    }
}
