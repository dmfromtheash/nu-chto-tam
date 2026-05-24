<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Validator;
use App\Models\User;
use InvalidArgumentException;
use RuntimeException;

final class CabinetService
{
    private const RARITIES = ['common', 'uncommon', 'rare', 'epic', 'legendary', 'mythic'];

    /**
     * @param array<string, mixed> $user
     *
     * @return array<string, mixed>
     */
    public function summary(array $user): array
    {
        $userId = (int) $user['id'];

        $summary = Database::query(
            'SELECT
                COUNT(*) AS total_openings,
                SUM(CASE WHEN date(opened_at) = date("now") THEN 1 ELSE 0 END) AS openings_today,
                MIN(opened_at) AS first_opened_at,
                MAX(opened_at) AS last_opened_at
            FROM openings
            WHERE user_id = :user_id',
            [':user_id' => $userId]
        )->fetch();

        $savedCount = (int) Database::query(
            'SELECT COUNT(*) FROM saved_cards WHERE user_id = :user_id',
            [':user_id' => $userId]
        )->fetchColumn();

        $favoritePack = Database::query(
            'SELECT p.slug, p.title, COUNT(*) AS count
            FROM openings o
            INNER JOIN packs p ON p.id = o.pack_id
            WHERE o.user_id = :user_id
            GROUP BY p.id, p.slug, p.title
            ORDER BY count DESC, p.sort_order ASC, p.id ASC
            LIMIT 1',
            [':user_id' => $userId]
        )->fetch();

        $topRarity = Database::query(
            'SELECT rarity, COUNT(*) AS count
            FROM openings
            WHERE user_id = :user_id
            GROUP BY rarity
            ORDER BY count DESC, rarity ASC
            LIMIT 1',
            [':user_id' => $userId]
        )->fetch();

        return [
            'user' => [
                'id' => $userId,
                'username' => (string) $user['username'],
                'email' => (string) $user['email'],
                'created_at' => (string) $user['created_at'],
                'last_login_at' => $user['last_login_at'] !== null ? (string) $user['last_login_at'] : null,
            ],
            'stats' => [
                'total_openings' => (int) ($summary['total_openings'] ?? 0),
                'openings_today' => (int) ($summary['openings_today'] ?? 0),
                'saved_count' => $savedCount,
                'favorite_pack' => is_array($favoritePack) ? [
                    'slug' => (string) $favoritePack['slug'],
                    'title' => (string) $favoritePack['title'],
                    'count' => (int) $favoritePack['count'],
                ] : null,
                'top_rarity' => is_array($topRarity) ? [
                    'rarity' => (string) $topRarity['rarity'],
                    'count' => (int) $topRarity['count'],
                ] : null,
                'first_opened_at' => $summary['first_opened_at'] ?? null,
                'last_opened_at' => $summary['last_opened_at'] ?? null,
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function savedCards(int $userId): array
    {
        $rows = Database::query(
            'SELECT
                sc.id AS saved_id,
                sc.opening_id,
                sc.prediction_id,
                sc.note,
                sc.created_at AS saved_at,
                o.opened_at,
                pr.title AS prediction_title,
                pr.text AS prediction_text,
                pr.rarity,
                p.slug AS pack_slug,
                p.title AS pack_title
            FROM saved_cards sc
            INNER JOIN predictions pr ON pr.id = sc.prediction_id
            INNER JOIN packs p ON p.id = pr.pack_id
            LEFT JOIN openings o ON o.id = sc.opening_id
            WHERE sc.user_id = :user_id
            ORDER BY sc.created_at DESC, sc.id DESC',
            [':user_id' => $userId]
        )->fetchAll();

        return array_map([$this, 'savedRow'], $rows);
    }

    public function updateNote(int $userId, int $savedId, string $note): void
    {
        $note = trim($note);

        if (!Validator::maxLength($note, 500)) {
            throw new InvalidArgumentException('Заметка слишком длинная. Тут максимум 500 символов.');
        }

        $this->ensureSavedOwner($userId, $savedId);

        Database::query(
            'UPDATE saved_cards SET note = :note WHERE id = :id AND user_id = :user_id',
            [
                ':note' => $note === '' ? null : $note,
                ':id' => $savedId,
                ':user_id' => $userId,
            ]
        );
    }

    public function deleteSaved(int $userId, int $savedId): void
    {
        $this->ensureSavedOwner($userId, $savedId);

        Database::query(
            'DELETE FROM saved_cards WHERE id = :id AND user_id = :user_id',
            [
                ':id' => $savedId,
                ':user_id' => $userId,
            ]
        );
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return list<array<string, mixed>>
     */
    public function history(int $userId, array $filters): array
    {
        $params = [
            ':user_id' => $userId,
            ':saved_user_id' => $userId,
        ];
        $where = ['o.user_id = :user_id'];

        $pack = isset($filters['pack']) ? trim((string) $filters['pack']) : '';
        if ($pack !== '' && $pack !== 'all') {
            if (!Validator::slug($pack)) {
                throw new InvalidArgumentException('Фильтр пака выглядит странно.');
            }
            $where[] = 'p.slug = :pack';
            $params[':pack'] = $pack;
        }

        $rarity = isset($filters['rarity']) ? trim((string) $filters['rarity']) : '';
        if ($rarity !== '' && $rarity !== 'all') {
            if (!in_array($rarity, self::RARITIES, true)) {
                throw new InvalidArgumentException('Такой редкости нет в списке.');
            }
            $where[] = 'o.rarity = :rarity';
            $params[':rarity'] = $rarity;
        }

        $saved = isset($filters['saved']) ? (string) $filters['saved'] : '';
        if ($saved === '1') {
            $where[] = 'sc.id IS NOT NULL';
        } elseif ($saved === '0') {
            $where[] = 'sc.id IS NULL';
        }

        $limit = isset($filters['limit']) && is_numeric($filters['limit']) ? (int) $filters['limit'] : 50;
        $limit = max(1, min(100, $limit));

        $rows = Database::query(
            'SELECT
                o.id AS opening_id,
                o.opened_at,
                o.rarity,
                o.user_question,
                o.choice_a,
                o.choice_b,
                o.result_context,
                p.slug AS pack_slug,
                p.title AS pack_title,
                pr.title AS prediction_title,
                pr.text AS prediction_text,
                CASE WHEN sc.id IS NULL THEN 0 ELSE 1 END AS saved
            FROM openings o
            INNER JOIN packs p ON p.id = o.pack_id
            INNER JOIN predictions pr ON pr.id = o.prediction_id
            LEFT JOIN saved_cards sc ON sc.opening_id = o.id AND sc.user_id = :saved_user_id
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY o.opened_at DESC, o.id DESC
            LIMIT ' . $limit,
            $params
        )->fetchAll();

        return array_map([$this, 'historyRow'], $rows);
    }

    /**
     * @return array<string, mixed>
     */
    public function updateUsername(int $userId, string $username): array
    {
        $username = trim($username);

        if (!Validator::required($username)) {
            throw new InvalidArgumentException('Имя не должно быть пустым.');
        }

        if (!Validator::maxLength($username, 40)) {
            throw new InvalidArgumentException('Имя лучше держать в пределах 40 символов.');
        }

        Database::query(
            'UPDATE users SET username = :username WHERE id = :id',
            [
                ':username' => $username,
                ':id' => $userId,
            ]
        );

        $user = User::findById($userId);

        if ($user === null) {
            throw new RuntimeException('Пользователь внезапно потерялся. Попробуй обновить страницу.');
        }

        return $user;
    }

    public function changePassword(int $userId, string $currentPassword, string $newPassword, string $newPasswordConfirm): void
    {
        $user = User::findById($userId);

        if ($user === null || !is_string($user['password_hash'])) {
            throw new RuntimeException('Не удалось проверить текущий пароль.');
        }

        if (!password_verify($currentPassword, $user['password_hash'])) {
            throw new InvalidArgumentException('Текущий пароль не подошёл.');
        }

        if (strlen($newPassword) < 8) {
            throw new InvalidArgumentException('Новый пароль должен быть не короче 8 символов.');
        }

        if ($newPassword !== $newPasswordConfirm) {
            throw new InvalidArgumentException('Новый пароль и подтверждение не совпадают.');
        }

        Database::query(
            'UPDATE users SET password_hash = :password_hash WHERE id = :id',
            [
                ':password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
                ':id' => $userId,
            ]
        );
    }

    private function ensureSavedOwner(int $userId, int $savedId): void
    {
        $exists = Database::query(
            'SELECT COUNT(*) FROM saved_cards WHERE id = :id AND user_id = :user_id',
            [
                ':id' => $savedId,
                ':user_id' => $userId,
            ]
        )->fetchColumn();

        if ((int) $exists < 1) {
            throw new InvalidArgumentException('Карточка не найдена в твоей коллекции.');
        }
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function savedRow(array $row): array
    {
        return [
            'saved_id' => (int) $row['saved_id'],
            'opening_id' => $row['opening_id'] !== null ? (int) $row['opening_id'] : null,
            'prediction_id' => (int) $row['prediction_id'],
            'prediction_title' => $row['prediction_title'] !== null ? (string) $row['prediction_title'] : null,
            'prediction_text' => (string) $row['prediction_text'],
            'rarity' => (string) $row['rarity'],
            'pack_slug' => (string) $row['pack_slug'],
            'pack_title' => (string) $row['pack_title'],
            'note' => $row['note'] !== null ? (string) $row['note'] : null,
            'saved_at' => (string) $row['saved_at'],
            'opened_at' => $row['opened_at'] !== null ? (string) $row['opened_at'] : null,
        ];
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
}
