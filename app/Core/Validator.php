<?php

declare(strict_types=1);

namespace App\Core;

final class Validator
{
    private function __construct()
    {
    }

    public static function required(mixed $value): bool
    {
        return !($value === null || (is_string($value) && trim($value) === ''));
    }

    public static function maxLength(?string $value, int $max): bool
    {
        if ($value === null) {
            return true;
        }

        $length = function_exists('mb_strlen')
            ? mb_strlen($value, 'UTF-8')
            : strlen($value);

        return $length <= $max;
    }

    public static function email(?string $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    public static function slug(?string $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        return preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $value) === 1;
    }
}
