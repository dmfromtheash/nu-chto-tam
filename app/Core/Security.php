<?php

declare(strict_types=1);

namespace App\Core;

use JsonException;

final class Security
{
    private function __construct()
    {
    }

    public static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function generateCsrfToken(): string
    {
        Session::start();

        $token = bin2hex(random_bytes(32));
        Session::set('_csrf_token', $token);

        return $token;
    }

    public static function csrfToken(): string
    {
        Session::start();

        $token = Session::get('_csrf_token');

        if (is_string($token) && $token !== '') {
            return $token;
        }

        return self::generateCsrfToken();
    }

    public static function verifyCsrfToken(?string $token): bool
    {
        Session::start();
        $storedToken = Session::get('_csrf_token');

        return is_string($token)
            && is_string($storedToken)
            && hash_equals($storedToken, $token);
    }

    public static function hashIp(?string $ip): ?string
    {
        return self::hashString($ip);
    }

    public static function hashString(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $secret = (string) config_value('SESSION_SECRET', '');

        return hash_hmac('sha256', $value, $secret !== '' ? $secret : 'local-development-secret');
    }

    /**
     * @param mixed $data
     *
     * @throws JsonException
     */
    public static function safeJsonEncode(mixed $data): string
    {
        return json_encode(
            $data,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
    }
}
