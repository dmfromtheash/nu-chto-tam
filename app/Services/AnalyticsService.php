<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Security;
use Throwable;

final class AnalyticsService
{
    private const COOKIE_NAME = 'card_pack_visitor';

    public function visitorId(?int $userId = null): string
    {
        $visitorId = $this->visitorIdFromCookie();

        if ($visitorId === null) {
            $visitorId = bin2hex(random_bytes(24));
            $this->setVisitorCookie($visitorId);
        }

        $this->touchVisitor($visitorId, $userId);

        return $visitorId;
    }

    public function bindUser(int $userId): void
    {
        try {
            $visitorId = $this->visitorId($userId);
            Database::query(
                'UPDATE visitors SET user_id = :user_id, last_seen_at = :last_seen_at WHERE visitor_id = :visitor_id',
                [
                    ':user_id' => $userId,
                    ':last_seen_at' => gmdate('c'),
                    ':visitor_id' => $visitorId,
                ]
            );
        } catch (Throwable $exception) {
            error_log('Analytics bind failed: ' . $exception->getMessage());
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function pageView(string $page, ?int $userId = null, ?string $guestId = null, array $payload = []): void
    {
        $this->track('page_view', [
            'page' => $page,
            'user_id' => $userId,
            'guest_id' => $guestId,
            'payload' => $payload,
        ]);
    }

    /**
     * @param array{
     *     page?: string|null,
     *     user_id?: int|null,
     *     guest_id?: string|null,
     *     entity_type?: string|null,
     *     entity_id?: int|null,
     *     payload?: array<string, mixed>|null
     * } $options
     */
    public function track(string $eventType, array $options = []): void
    {
        try {
            $userId = isset($options['user_id']) && is_numeric($options['user_id']) ? (int) $options['user_id'] : null;
            $visitorId = $this->visitorId($userId);
            $payload = isset($options['payload']) && is_array($options['payload']) ? $options['payload'] : null;

            Database::query(
                'INSERT INTO events (
                    visitor_id, user_id, guest_id, event_type, page, entity_type, entity_id,
                    payload_json, created_at, ip_hash, user_agent_hash
                ) VALUES (
                    :visitor_id, :user_id, :guest_id, :event_type, :page, :entity_type, :entity_id,
                    :payload_json, :created_at, :ip_hash, :user_agent_hash
                )',
                [
                    ':visitor_id' => $visitorId,
                    ':user_id' => $userId,
                    ':guest_id' => $options['guest_id'] ?? null,
                    ':event_type' => $this->cleanValue($eventType, 80),
                    ':page' => isset($options['page']) ? $this->cleanValue((string) $options['page'], 180) : null,
                    ':entity_type' => isset($options['entity_type']) ? $this->cleanValue((string) $options['entity_type'], 80) : null,
                    ':entity_id' => isset($options['entity_id']) && is_numeric($options['entity_id']) ? (int) $options['entity_id'] : null,
                    ':payload_json' => $payload !== null ? Security::safeJsonEncode($this->safePayload($payload)) : null,
                    ':created_at' => gmdate('c'),
                    ':ip_hash' => $this->ipHash(),
                    ':user_agent_hash' => $this->userAgentHash(),
                ]
            );
        } catch (Throwable $exception) {
            error_log('Analytics event failed: ' . $exception->getMessage());
        }
    }

    private function touchVisitor(string $visitorId, ?int $userId): void
    {
        try {
            $now = gmdate('c');
            Database::query(
                'INSERT INTO visitors (
                    visitor_id, user_id, first_seen_at, last_seen_at, ip_hash, user_agent_hash, created_at
                ) VALUES (
                    :visitor_id, :user_id, :first_seen_at, :last_seen_at, :ip_hash, :user_agent_hash, :created_at
                )
                ON CONFLICT(visitor_id) DO UPDATE SET
                    user_id = COALESCE(excluded.user_id, visitors.user_id),
                    last_seen_at = excluded.last_seen_at,
                    ip_hash = excluded.ip_hash,
                    user_agent_hash = excluded.user_agent_hash',
                [
                    ':visitor_id' => $visitorId,
                    ':user_id' => $userId,
                    ':first_seen_at' => $now,
                    ':last_seen_at' => $now,
                    ':ip_hash' => $this->ipHash(),
                    ':user_agent_hash' => $this->userAgentHash(),
                    ':created_at' => $now,
                ]
            );
        } catch (Throwable $exception) {
            error_log('Analytics visitor failed: ' . $exception->getMessage());
        }
    }

    private function visitorIdFromCookie(): ?string
    {
        $value = $_COOKIE[self::COOKIE_NAME] ?? null;

        if (!is_string($value) || preg_match('/^[a-f0-9]{48}$/', $value) !== 1) {
            return null;
        }

        return $value;
    }

    private function setVisitorCookie(string $visitorId): void
    {
        if (headers_sent()) {
            return;
        }

        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['SERVER_PORT'] ?? null) === '443');

        setcookie(self::COOKIE_NAME, $visitorId, [
            'expires' => time() + 60 * 60 * 24 * 365,
            'path' => '/',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        $_COOKIE[self::COOKIE_NAME] = $visitorId;
    }

    private function ipHash(): ?string
    {
        return Security::hashIp($_SERVER['REMOTE_ADDR'] ?? null);
    }

    private function userAgentHash(): ?string
    {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

        return is_string($userAgent) ? Security::hashString($userAgent) : null;
    }

    private function cleanValue(string $value, int $max): string
    {
        $value = trim($value);

        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $max, 'UTF-8');
        }

        return substr($value, 0, $max);
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function safePayload(array $payload): array
    {
        unset($payload['password'], $payload['password_hash'], $payload['current_password'], $payload['new_password']);

        return $payload;
    }
}
