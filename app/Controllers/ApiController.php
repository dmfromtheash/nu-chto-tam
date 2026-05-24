<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Response;
use App\Core\Session;
use App\Models\User;
use App\Services\AnalyticsService;
use App\Services\AuthService;
use App\Services\GuestService;
use App\Services\RandomPredictionService;
use App\Services\RateLimiter;
use InvalidArgumentException;
use Throwable;

final class ApiController
{
    public function __construct(
        private readonly AuthService $auth,
        private readonly GuestService $guest,
        private readonly RandomPredictionService $predictionService,
        private readonly RateLimiter $rateLimiter,
        private readonly AnalyticsService $analytics
    ) {
    }

    public function health(): never
    {
        try {
            $pdo = Database::connect();

            Response::json([
                'ok' => true,
                'php' => PHP_VERSION,
                'database' => true,
                'packs' => (int) $pdo->query('SELECT COUNT(*) FROM packs')->fetchColumn(),
                'predictions' => (int) $pdo->query('SELECT COUNT(*) FROM predictions')->fetchColumn(),
            ]);
        } catch (Throwable $exception) {
            Response::json([
                'ok' => false,
                'database' => false,
                'error' => $exception->getMessage(),
            ], 500);
        }
    }

    public function packs(): never
    {
        try {
            $statement = Database::query(
                'SELECT id, slug, title, description, type, visual_theme, cards_per_open, is_daily_special
                FROM packs
                WHERE is_active = 1
                ORDER BY sort_order ASC, id ASC'
            );

            Response::json([
                'ok' => true,
                'packs' => array_map(static function (array $pack): array {
                    return [
                        'id' => (int) $pack['id'],
                        'slug' => (string) $pack['slug'],
                        'title' => (string) $pack['title'],
                        'description' => $pack['description'] !== null ? (string) $pack['description'] : null,
                        'type' => (string) $pack['type'],
                        'visual_theme' => (string) $pack['visual_theme'],
                        'cards_per_open' => (int) $pack['cards_per_open'],
                        'is_daily_special' => (int) $pack['is_daily_special'],
                    ];
                }, $statement->fetchAll()),
            ]);
        } catch (Throwable $exception) {
            Response::json(['ok' => false, 'error' => 'Не удалось получить список паков.'], 500);
        }
    }

    public function me(): never
    {
        $user = $this->auth->currentUser();

        if ($user !== null) {
            Response::json([
                'ok' => true,
                'authenticated' => true,
                'user' => User::publicData($user),
            ]);
        }

        Response::json([
            'ok' => true,
            'authenticated' => false,
            'guest_id' => $this->guest->getOrCreateGuestId(),
        ]);
    }

    public function openPack(): never
    {
        $input = $this->jsonInput();
        $pack = $input['pack'] ?? $input['pack_id'] ?? null;

        if (!is_string($pack) && !is_int($pack)) {
            Response::json(['ok' => false, 'error' => 'Укажи pack: slug или id пака.'], 422);
        }

        $user = $this->auth->currentUser();
        $userId = $user !== null ? (int) $user['id'] : null;
        $guestId = $userId === null ? $this->guest->getOrCreateGuestId() : null;
        $identifier = $userId !== null ? 'user:' . $userId : 'guest:' . $guestId;
        $limit = $this->rateLimiter->hit('api', $identifier, 'open-pack', 60, 600);

        if (!$limit['allowed']) {
            $this->tooManyAttempts();
        }

        try {
            $context = $this->openContext($input);
            $result = $this->predictionService->openPack($pack, $context, $userId, $guestId);
            $this->analytics->track('pack_opened', [
                'page' => '/api/open-pack',
                'user_id' => $userId,
                'guest_id' => $guestId,
                'entity_type' => 'pack',
                'entity_id' => (int) $result['pack']['id'],
                'payload' => [
                    'pack_slug' => (string) $result['pack']['slug'],
                    'cards_count' => count($result['cards']),
                ],
            ]);

            Response::json([
                'ok' => true,
                'pack' => $result['pack'],
                'cards' => $result['cards'],
                'meta' => [
                    'guest_id' => $guestId,
                    'authenticated' => $userId !== null,
                ],
            ]);
        } catch (InvalidArgumentException $exception) {
            Response::json(['ok' => false, 'error' => $exception->getMessage()], 422);
        } catch (Throwable $exception) {
            Response::json(['ok' => false, 'error' => 'Не удалось открыть пак.'], 500);
        }
    }

    public function saveCard(): never
    {
        $user = $this->auth->currentUser();

        if ($user === null) {
            $this->analytics->track('save_failed_guest', [
                'page' => '/api/save-card',
                'guest_id' => $this->guest->getOrCreateGuestId(),
            ]);
            Response::json([
                'ok' => false,
                'error' => 'Чтобы сохранить карточку в коллекцию, нужно войти или зарегистрироваться.',
            ], 401);
        }

        $input = $this->jsonInput();
        $openingId = $input['opening_id'] ?? null;

        if (!is_int($openingId) && !(is_string($openingId) && ctype_digit($openingId))) {
            Response::json(['ok' => false, 'error' => 'Укажи opening_id.'], 422);
        }

        $userId = (int) $user['id'];
        $pdo = Database::connect();
        $openingStatement = $pdo->prepare(
            'SELECT id, prediction_id FROM openings WHERE id = :id AND user_id = :user_id LIMIT 1'
        );
        $openingStatement->execute([
            ':id' => (int) $openingId,
            ':user_id' => $userId,
        ]);
        $opening = $openingStatement->fetch();

        if (!is_array($opening)) {
            Response::json(['ok' => false, 'error' => 'Открытие не найдено для текущего пользователя.'], 404);
        }

        $existingStatement = $pdo->prepare(
            'SELECT id FROM saved_cards WHERE user_id = :user_id AND opening_id = :opening_id LIMIT 1'
        );
        $existingStatement->execute([
            ':user_id' => $userId,
            ':opening_id' => (int) $openingId,
        ]);
        $existingId = $existingStatement->fetchColumn();

        if ($existingId !== false) {
            $this->analytics->track('card_saved', [
                'page' => '/api/save-card',
                'user_id' => $userId,
                'entity_type' => 'opening',
                'entity_id' => (int) $openingId,
                'payload' => [
                    'saved_card_id' => (int) $existingId,
                    'already_saved' => true,
                ],
            ]);
            Response::json([
                'ok' => true,
                'message' => 'Карточка уже сохранена.',
                'saved_card_id' => (int) $existingId,
            ]);
        }

        $insert = $pdo->prepare(
            'INSERT INTO saved_cards (user_id, opening_id, prediction_id, note, created_at)
            VALUES (:user_id, :opening_id, :prediction_id, :note, :created_at)'
        );
        $insert->execute([
            ':user_id' => $userId,
            ':opening_id' => (int) $opening['id'],
            ':prediction_id' => (int) $opening['prediction_id'],
            ':note' => null,
            ':created_at' => gmdate('c'),
        ]);
        $savedCardId = (int) $pdo->lastInsertId();
        $this->analytics->track('card_saved', [
            'page' => '/api/save-card',
            'user_id' => $userId,
            'entity_type' => 'opening',
            'entity_id' => (int) $opening['id'],
            'payload' => [
                'saved_card_id' => $savedCardId,
                'prediction_id' => (int) $opening['prediction_id'],
            ],
        ]);

        Response::json([
            'ok' => true,
            'message' => 'Карточка сохранена.',
            'saved_card_id' => $savedCardId,
        ]);
    }

    public function history(): never
    {
        $user = $this->auth->currentUser();
        $userId = $user !== null ? (int) $user['id'] : null;
        $guestId = $userId === null ? $this->guest->getOrCreateGuestId() : null;

        if ($userId !== null) {
            $statement = Database::query(
                'SELECT
                    o.id AS opening_id, o.opened_at, o.rarity, o.user_question, o.choice_a, o.choice_b, o.result_context,
                    p.slug AS pack_slug, p.title AS pack_title,
                    pr.title AS prediction_title, pr.text AS prediction_text,
                    CASE WHEN sc.id IS NULL THEN 0 ELSE 1 END AS saved
                FROM openings o
                INNER JOIN packs p ON p.id = o.pack_id
                INNER JOIN predictions pr ON pr.id = o.prediction_id
                LEFT JOIN saved_cards sc ON sc.opening_id = o.id AND sc.user_id = :saved_user_id
                WHERE o.user_id = :user_id
                ORDER BY o.opened_at DESC, o.id DESC
                LIMIT 50',
                [
                    ':saved_user_id' => $userId,
                    ':user_id' => $userId,
                ]
            );
        } else {
            $statement = Database::query(
                'SELECT
                    o.id AS opening_id, o.opened_at, o.rarity, o.user_question, o.choice_a, o.choice_b, o.result_context,
                    p.slug AS pack_slug, p.title AS pack_title,
                    pr.title AS prediction_title, pr.text AS prediction_text,
                    0 AS saved
                FROM openings o
                INNER JOIN packs p ON p.id = o.pack_id
                INNER JOIN predictions pr ON pr.id = o.prediction_id
                WHERE o.guest_id = :guest_id
                ORDER BY o.opened_at DESC, o.id DESC
                LIMIT 50',
                [':guest_id' => $guestId]
            );
        }

        Response::json([
            'ok' => true,
            'history' => array_map([$this, 'historyRow'], $statement->fetchAll()),
            'meta' => [
                'guest_id' => $guestId,
                'authenticated' => $userId !== null,
            ],
        ]);
    }

    public function stats(): never
    {
        $user = $this->auth->currentUser();
        $userId = $user !== null ? (int) $user['id'] : null;
        $guestId = $userId === null ? $this->guest->getOrCreateGuestId() : null;
        $condition = $userId !== null ? 'o.user_id = :owner_id' : 'o.guest_id = :owner_id';
        $ownerId = $userId !== null ? $userId : $guestId;

        $summary = Database::query(
            'SELECT
                COUNT(*) AS total_openings,
                SUM(CASE WHEN date(o.opened_at) = date("now") THEN 1 ELSE 0 END) AS openings_today,
                MIN(o.opened_at) AS first_opened_at,
                MAX(o.opened_at) AS last_opened_at
            FROM openings o
            WHERE ' . $condition,
            [':owner_id' => $ownerId]
        )->fetch();

        $packs = Database::query(
            'SELECT p.slug, COUNT(*) AS count
            FROM openings o
            INNER JOIN packs p ON p.id = o.pack_id
            WHERE ' . $condition . '
            GROUP BY p.slug
            ORDER BY p.slug ASC',
            [':owner_id' => $ownerId]
        )->fetchAll();

        $rarities = Database::query(
            'SELECT o.rarity, COUNT(*) AS count
            FROM openings o
            WHERE ' . $condition . '
            GROUP BY o.rarity
            ORDER BY o.rarity ASC',
            [':owner_id' => $ownerId]
        )->fetchAll();

        $eventCondition = $userId !== null ? 'e.user_id = :owner_id' : 'e.guest_id = :owner_id';
        $packOpenings = Database::query(
            'SELECT
                COUNT(*) AS total_pack_openings,
                SUM(CASE WHEN date(e.created_at) = date("now") THEN 1 ELSE 0 END) AS pack_openings_today
            FROM events e
            WHERE e.event_type = "pack_opened" AND ' . $eventCondition,
            [':owner_id' => $ownerId]
        )->fetch();

        $packOpeningSlugs = Database::query(
            'SELECT p.slug, COUNT(*) AS count
            FROM events e
            INNER JOIN packs p ON p.id = e.entity_id AND e.entity_type = "pack"
            WHERE e.event_type = "pack_opened" AND ' . $eventCondition . '
            GROUP BY p.slug
            ORDER BY p.slug ASC',
            [':owner_id' => $ownerId]
        )->fetchAll();

        Response::json([
            'ok' => true,
            'stats' => [
                'total_openings' => (int) ($summary['total_openings'] ?? 0),
                'openings_today' => (int) ($summary['openings_today'] ?? 0),
                'pack_openings_total' => (int) ($packOpenings['total_pack_openings'] ?? 0),
                'pack_openings_today' => (int) ($packOpenings['pack_openings_today'] ?? 0),
                'packs_opened_by_slug' => $this->countsByKey($packs, 'slug'),
                'pack_openings_by_slug' => $this->countsByKey($packOpeningSlugs, 'slug'),
                'rarity_counts' => $this->countsByKey($rarities, 'rarity'),
                'first_opened_at' => $summary['first_opened_at'] ?? null,
                'last_opened_at' => $summary['last_opened_at'] ?? null,
            ],
            'meta' => [
                'guest_id' => $guestId,
                'authenticated' => $userId !== null,
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonInput(): array
    {
        $raw = (string) file_get_contents('php://input');
        $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));

        if ($raw !== '' && !str_contains($contentType, 'application/json')) {
            Response::json([
                'ok' => false,
                'error' => 'Для API-запроса нужен Content-Type: application/json.',
            ], 415);
        }

        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        if (!is_array($decoded)) {
            Response::json(['ok' => false, 'error' => 'Некорректный JSON.'], 400);
        }

        return $decoded;
    }

    /**
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    private function openContext(array $input): array
    {
        $context = [];

        foreach (['mood', 'direction', 'user_question', 'choice_a', 'choice_b'] as $key) {
            if (isset($input[$key]) && is_string($input[$key])) {
                $context[$key] = trim($input[$key]);
            }
        }

        return $context;
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function historyRow(array $row): array
    {
        return [
            'opening_id' => (int) $row['opening_id'],
            'opened_at' => (string) $row['opened_at'],
            'pack_slug' => (string) $row['pack_slug'],
            'pack_title' => (string) $row['pack_title'],
            'prediction_title' => $row['prediction_title'] !== null ? (string) $row['prediction_title'] : null,
            'prediction_text' => (string) $row['prediction_text'],
            'rarity' => (string) $row['rarity'],
            'user_question' => $row['user_question'] !== null ? (string) $row['user_question'] : null,
            'choice_a' => $row['choice_a'] !== null ? (string) $row['choice_a'] : null,
            'choice_b' => $row['choice_b'] !== null ? (string) $row['choice_b'] : null,
            'result_context' => $row['result_context'] !== null ? (string) $row['result_context'] : null,
            'saved' => (int) $row['saved'] === 1,
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

    private function tooManyAttempts(): never
    {
        Response::json([
            'ok' => false,
            'error' => 'Слишком много попыток. Попробуй чуть позже.',
        ], 429);
    }
}
