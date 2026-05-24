<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Session;

final class GuestService
{
    private const COOKIE_NAME = 'guest_id';

    public function getOrCreateGuestId(): string
    {
        $sessionGuestId = Session::get(self::COOKIE_NAME);

        if (is_string($sessionGuestId) && $this->isValidGuestId($sessionGuestId)) {
            $this->queueCookie($sessionGuestId);
            return $sessionGuestId;
        }

        $cookieGuestId = $_COOKIE[self::COOKIE_NAME] ?? null;

        if (is_string($cookieGuestId) && $this->isValidGuestId($cookieGuestId)) {
            Session::set(self::COOKIE_NAME, $cookieGuestId);
            $this->queueCookie($cookieGuestId);
            return $cookieGuestId;
        }

        $guestId = bin2hex(random_bytes(32));
        Session::set(self::COOKIE_NAME, $guestId);
        $this->queueCookie($guestId);

        return $guestId;
    }

    public function currentGuestId(): ?string
    {
        $sessionGuestId = Session::get(self::COOKIE_NAME);

        return is_string($sessionGuestId) && $this->isValidGuestId($sessionGuestId)
            ? $sessionGuestId
            : null;
    }

    private function isValidGuestId(string $guestId): bool
    {
        return strlen($guestId) === 64 && ctype_xdigit($guestId);
    }

    private function queueCookie(string $guestId): void
    {
        if (headers_sent()) {
            return;
        }

        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['SERVER_PORT'] ?? null) === '443');

        setcookie(self::COOKIE_NAME, $guestId, [
            'expires' => time() + 60 * 60 * 24 * 365,
            'path' => '/',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}
