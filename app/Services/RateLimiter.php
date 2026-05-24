<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Security;

final class RateLimiter
{
    /**
     * @return array{allowed: bool, retry_after: int}
     */
    public function hit(string $scope, string $identifier, string $action, int $maxAttempts, int $windowSeconds): array
    {
        if (!config_value('RATE_LIMIT_ENABLED', true)) {
            return ['allowed' => true, 'retry_after' => 0];
        }

        $pdo = Database::connect();
        $identifierHash = Security::hashString($identifier) ?? hash('sha256', $identifier);
        $now = time();
        $nowText = gmdate('c', $now);

        $statement = $pdo->prepare(
            'SELECT id, attempts, window_started_at
            FROM rate_limits
            WHERE scope = :scope AND identifier_hash = :identifier_hash AND action = :action
            LIMIT 1'
        );
        $statement->execute([
            ':scope' => $scope,
            ':identifier_hash' => $identifierHash,
            ':action' => $action,
        ]);
        $row = $statement->fetch();

        if (!is_array($row)) {
            $insert = $pdo->prepare(
                'INSERT INTO rate_limits (scope, identifier_hash, action, attempts, window_started_at, last_attempt_at)
                VALUES (:scope, :identifier_hash, :action, 1, :window_started_at, :last_attempt_at)'
            );
            $insert->execute([
                ':scope' => $scope,
                ':identifier_hash' => $identifierHash,
                ':action' => $action,
                ':window_started_at' => $nowText,
                ':last_attempt_at' => $nowText,
            ]);

            return ['allowed' => true, 'retry_after' => 0];
        }

        $windowStarted = strtotime((string) $row['window_started_at']) ?: $now;
        $elapsed = $now - $windowStarted;

        if ($elapsed >= $windowSeconds) {
            $reset = $pdo->prepare(
                'UPDATE rate_limits
                SET attempts = 1, window_started_at = :window_started_at, last_attempt_at = :last_attempt_at
                WHERE id = :id'
            );
            $reset->execute([
                ':window_started_at' => $nowText,
                ':last_attempt_at' => $nowText,
                ':id' => (int) $row['id'],
            ]);

            return ['allowed' => true, 'retry_after' => 0];
        }

        if ((int) $row['attempts'] >= $maxAttempts) {
            return [
                'allowed' => false,
                'retry_after' => max(1, $windowSeconds - $elapsed),
            ];
        }

        $update = $pdo->prepare(
            'UPDATE rate_limits
            SET attempts = attempts + 1, last_attempt_at = :last_attempt_at
            WHERE id = :id'
        );
        $update->execute([
            ':last_attempt_at' => $nowText,
            ':id' => (int) $row['id'],
        ]);

        return ['allowed' => true, 'retry_after' => 0];
    }

    public function clientIp(): string
    {
        return (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    }
}
