<?php

declare(strict_types=1);

namespace App\Core;

final class Session
{
    private function __construct()
    {
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function start(array $config = []): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['SERVER_PORT'] ?? null) === '443');
        $sessionPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'sessions';

        if (is_dir($sessionPath) && is_writable($sessionPath)) {
            session_save_path($sessionPath);
        }

        session_name('card_pack_session');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_start();

        if (!isset($_SESSION['_started_at'])) {
            $_SESSION['_started_at'] = time();
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        self::start();

        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        self::start();
        $_SESSION[$key] = $value;
    }

    public static function forget(string $key): void
    {
        self::start();
        unset($_SESSION[$key]);
    }

    public static function regenerate(): void
    {
        self::start();
        session_regenerate_id(true);
    }

    public static function flash(string $key, string $message): void
    {
        self::start();
        $_SESSION['_flash'][$key][] = $message;
    }

    /**
     * @return list<string>
     */
    public static function consumeFlash(string $key): array
    {
        self::start();
        $messages = $_SESSION['_flash'][$key] ?? [];
        unset($_SESSION['_flash'][$key]);

        return is_array($messages) ? array_values(array_filter($messages, 'is_string')) : [];
    }

    public static function userId(): ?int
    {
        $userId = self::get('user_id');

        if (is_int($userId)) {
            return $userId;
        }

        if (is_string($userId) && ctype_digit($userId)) {
            return (int) $userId;
        }

        return null;
    }

    public static function isAdmin(): bool
    {
        return self::get('role') === 'admin';
    }
}
