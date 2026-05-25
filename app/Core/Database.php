<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOStatement;
use RuntimeException;

final class Database
{
    private static ?PDO $pdo = null;

    private function __construct()
    {
    }

    /**
     * @param array<string, mixed>|null $config
     */
    public static function connect(?array $config = null): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $config ??= $GLOBALS['config'] ?? [];
        $path = (string) ($config['DB_PATH'] ?? '');

        if ($path === '') {
            throw new RuntimeException('SQLite database path is not configured.');
        }

        if (!is_file($path)) {
            throw new RuntimeException('SQLite database file was not found. Check DB_PATH and restore the expected database file. For a first empty install, initialize the database deliberately after backup/restore review.');
        }

        $pdo = new PDO('sqlite:' . $path);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        $pdo->exec('PRAGMA foreign_keys = ON');

        self::$pdo = $pdo;

        return self::$pdo;
    }

    /**
     * @param array<int|string, mixed> $params
     */
    public static function query(string $sql, array $params = []): PDOStatement
    {
        $statement = self::connect()->prepare($sql);
        $statement->execute($params);

        return $statement;
    }
}
