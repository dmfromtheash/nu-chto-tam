<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Security;
use InvalidArgumentException;
use RuntimeException;

final class RandomPredictionService
{
    /**
     * @var array<string, int>
     */
    private array $rarityWeights = [
        'common' => 50,
        'uncommon' => 25,
        'rare' => 14,
        'epic' => 7,
        'legendary' => 3,
        'mythic' => 1,
    ];

    /**
     * @param string|int $packSlugOrId
     * @param array<string, mixed> $context
     *
     * @return array{pack: array<string, mixed>, cards: list<array<string, mixed>>}
     */
    public function openPack(string|int $packSlugOrId, array $context = [], ?int $userId = null, ?string $guestId = null): array
    {
        if ($userId === null && ($guestId === null || $guestId === '')) {
            throw new InvalidArgumentException('Не удалось определить пользователя или гостя.');
        }

        $pdo = Database::connect();
        $pack = $this->findActivePack($packSlugOrId);

        if ($pack === null) {
            throw new InvalidArgumentException('Пак не найден или выключен.');
        }

        $cardsToOpen = $this->cardsToOpen($pack);
        $cards = [];
        $avoidPredictionIds = [];
        $lastPredictionId = $this->lastPredictionId((int) $pack['id'], $userId, $guestId);

        if ($lastPredictionId !== null) {
            $avoidPredictionIds[] = $lastPredictionId;
        }

        for ($index = 0; $index < $cardsToOpen; $index++) {
            $prediction = $this->pickPrediction((int) $pack['id'], $avoidPredictionIds);
            $avoidPredictionIds[] = (int) $prediction['id'];
            $resultContext = $this->resultContext($pack, $context, $index);
            $openedAt = gmdate('c');

            $statement = $pdo->prepare(
                'INSERT INTO openings (
                    user_id, guest_id, pack_id, prediction_id, rarity,
                    user_question, choice_a, choice_b, result_context,
                    opened_at, ip_hash, user_agent_hash
                ) VALUES (
                    :user_id, :guest_id, :pack_id, :prediction_id, :rarity,
                    :user_question, :choice_a, :choice_b, :result_context,
                    :opened_at, :ip_hash, :user_agent_hash
                )'
            );
            $statement->execute([
                ':user_id' => $userId,
                ':guest_id' => $userId === null ? $guestId : null,
                ':pack_id' => (int) $pack['id'],
                ':prediction_id' => (int) $prediction['id'],
                ':rarity' => (string) $prediction['rarity'],
                ':user_question' => $this->contextString($context, 'user_question'),
                ':choice_a' => $this->contextString($context, 'choice_a'),
                ':choice_b' => $this->contextString($context, 'choice_b'),
                ':result_context' => $resultContext,
                ':opened_at' => $openedAt,
                ':ip_hash' => Security::hashIp($_SERVER['REMOTE_ADDR'] ?? null),
                ':user_agent_hash' => Security::hashString($_SERVER['HTTP_USER_AGENT'] ?? null),
            ]);

            $cards[] = [
                'opening_id' => (int) $pdo->lastInsertId(),
                'prediction_id' => (int) $prediction['id'],
                'title' => $prediction['title'] !== null ? (string) $prediction['title'] : null,
                'text' => (string) $prediction['text'],
                'rarity' => (string) $prediction['rarity'],
                'mood_tag' => $prediction['mood_tag'] !== null ? (string) $prediction['mood_tag'] : null,
                'tone_tag' => $prediction['tone_tag'] !== null ? (string) $prediction['tone_tag'] : null,
                'result_context' => $resultContext,
            ];
        }

        return [
            'pack' => [
                'id' => (int) $pack['id'],
                'slug' => (string) $pack['slug'],
                'title' => (string) $pack['title'],
            ],
            'cards' => $cards,
        ];
    }

    /**
     * @param string|int $packSlugOrId
     *
     * @return array<string, mixed>|null
     */
    private function findActivePack(string|int $packSlugOrId): ?array
    {
        if (is_int($packSlugOrId) || (is_string($packSlugOrId) && ctype_digit($packSlugOrId))) {
            $statement = Database::query(
                'SELECT * FROM packs WHERE id = :id AND is_active = 1 LIMIT 1',
                [':id' => (int) $packSlugOrId]
            );
        } else {
            $statement = Database::query(
                'SELECT * FROM packs WHERE slug = :slug AND is_active = 1 LIMIT 1',
                [':slug' => (string) $packSlugOrId]
            );
        }

        $pack = $statement->fetch();

        return is_array($pack) ? $pack : null;
    }

    /**
     * @param array<string, mixed> $pack
     */
    private function cardsToOpen(array $pack): int
    {
        return match ((string) $pack['type']) {
            'daily' => 1,
            'weekly' => 3,
            'monthly' => 5,
            default => max(1, min(10, (int) $pack['cards_per_open'])),
        };
    }

    /**
     * @param list<int> $avoidPredictionIds
     *
     * @return array<string, mixed>
     */
    private function pickPrediction(int $packId, array $avoidPredictionIds): array
    {
        $rarity = $this->weightedRarity();
        $prediction = $this->randomPrediction($packId, $rarity, $avoidPredictionIds);

        if ($prediction === null) {
            $prediction = $this->randomPrediction($packId, null, $avoidPredictionIds);
        }

        if ($prediction === null) {
            $prediction = $this->randomPrediction($packId, $rarity, []);
        }

        if ($prediction === null) {
            $prediction = $this->randomPrediction($packId, null, []);
        }

        if ($prediction === null) {
            throw new RuntimeException('В паке пока нет активных карточек.');
        }

        return $prediction;
    }

    /**
     * @param list<int> $avoidPredictionIds
     *
     * @return array<string, mixed>|null
     */
    private function randomPrediction(int $packId, ?string $rarity, array $avoidPredictionIds): ?array
    {
        $params = [':pack_id' => $packId];
        $where = 'pack_id = :pack_id AND is_active = 1';

        if ($rarity !== null) {
            $where .= ' AND rarity = :rarity';
            $params[':rarity'] = $rarity;
        }

        if ($avoidPredictionIds !== []) {
            $placeholders = [];
            foreach (array_values(array_unique($avoidPredictionIds)) as $index => $id) {
                $key = ':avoid_' . $index;
                $placeholders[] = $key;
                $params[$key] = $id;
            }
            $where .= ' AND id NOT IN (' . implode(', ', $placeholders) . ')';
        }

        $statement = Database::connect()->prepare(
            'SELECT id, title, text, rarity, mood_tag, tone_tag
            FROM predictions
            WHERE ' . $where . '
            ORDER BY RANDOM()
            LIMIT 1'
        );
        $statement->execute($params);
        $prediction = $statement->fetch();

        return is_array($prediction) ? $prediction : null;
    }

    private function weightedRarity(): string
    {
        $total = array_sum($this->rarityWeights);
        $roll = random_int(1, $total);
        $cursor = 0;

        foreach ($this->rarityWeights as $rarity => $weight) {
            $cursor += $weight;

            if ($roll <= $cursor) {
                return $rarity;
            }
        }

        return 'common';
    }

    private function lastPredictionId(int $packId, ?int $userId, ?string $guestId): ?int
    {
        if ($userId !== null) {
            $statement = Database::query(
                'SELECT prediction_id FROM openings
                WHERE pack_id = :pack_id AND user_id = :user_id
                ORDER BY opened_at DESC, id DESC
                LIMIT 1',
                [
                    ':pack_id' => $packId,
                    ':user_id' => $userId,
                ]
            );
        } else {
            $statement = Database::query(
                'SELECT prediction_id FROM openings
                WHERE pack_id = :pack_id AND guest_id = :guest_id
                ORDER BY opened_at DESC, id DESC
                LIMIT 1',
                [
                    ':pack_id' => $packId,
                    ':guest_id' => $guestId,
                ]
            );
        }

        $predictionId = $statement->fetchColumn();

        return $predictionId !== false ? (int) $predictionId : null;
    }

    /**
     * @param array<string, mixed> $pack
     * @param array<string, mixed> $context
     */
    private function resultContext(array $pack, array $context, int $index): string
    {
        $data = [
            'pack_slug' => (string) $pack['slug'],
            'pack_type' => (string) $pack['type'],
            'card_index' => $index + 1,
        ];

        foreach (['mood', 'direction', 'user_question', 'choice_a', 'choice_b'] as $key) {
            $value = $this->contextString($context, $key);

            if ($value !== null) {
                $data[$key] = $value;
            }
        }

        if ((string) $pack['slug'] === 'take-leave') {
            $data['slot'] = $index === 0 ? 'take' : 'leave';
        }

        return Security::safeJsonEncode($data);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function contextString(array $context, string $key): ?string
    {
        $value = $context[$key] ?? null;

        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, 500, 'UTF-8');
        }

        return substr($value, 0, 500);
    }
}
