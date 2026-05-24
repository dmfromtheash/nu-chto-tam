<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class User
{
    /**
     * @return array<string, mixed>|null
     */
    public static function findById(int $id): ?array
    {
        $statement = Database::query(
            'SELECT id, username, email, password_hash, role, avatar_color, created_at, last_login_at, is_blocked
            FROM users WHERE id = :id LIMIT 1',
            [':id' => $id]
        );
        $user = $statement->fetch();

        return is_array($user) ? $user : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function findByEmail(string $email): ?array
    {
        $statement = Database::query(
            'SELECT id, username, email, password_hash, role, avatar_color, created_at, last_login_at, is_blocked
            FROM users WHERE lower(email) = lower(:email) LIMIT 1',
            [':email' => $email]
        );
        $user = $statement->fetch();

        return is_array($user) ? $user : null;
    }

    public static function emailExists(string $email): bool
    {
        $statement = Database::query(
            'SELECT COUNT(*) FROM users WHERE lower(email) = lower(:email)',
            [':email' => $email]
        );

        return (int) $statement->fetchColumn() > 0;
    }

    public static function create(string $username, string $email, string $passwordHash): int
    {
        $pdo = Database::connect();
        $statement = $pdo->prepare(
            'INSERT INTO users (username, email, password_hash, role, avatar_color, created_at, is_blocked)
            VALUES (:username, :email, :password_hash, "user", :avatar_color, :created_at, 0)'
        );
        $statement->execute([
            ':username' => $username,
            ':email' => $email,
            ':password_hash' => $passwordHash,
            ':avatar_color' => self::avatarColor($email),
            ':created_at' => gmdate('c'),
        ]);

        return (int) $pdo->lastInsertId();
    }

    public static function updateLastLoginAt(int $id): void
    {
        Database::query(
            'UPDATE users SET last_login_at = :last_login_at WHERE id = :id',
            [
                ':last_login_at' => gmdate('c'),
                ':id' => $id,
            ]
        );
    }

    /**
     * @param array<string, mixed> $user
     *
     * @return array<string, mixed>
     */
    public static function publicData(array $user): array
    {
        return [
            'id' => (int) $user['id'],
            'username' => (string) $user['username'],
            'email' => (string) $user['email'],
            'role' => (string) $user['role'],
        ];
    }

    private static function avatarColor(string $seed): string
    {
        $colors = ['#d7b56d', '#67d0c0', '#e08282', '#8fa7ff', '#b99cff', '#90d08a'];
        $index = hexdec(substr(hash('sha256', $seed), 0, 2)) % count($colors);

        return $colors[$index];
    }
}
