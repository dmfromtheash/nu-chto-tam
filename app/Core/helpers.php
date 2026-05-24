<?php

declare(strict_types=1);

use App\Core\Security;

if (!function_exists('base_path')) {
    function base_path(string $path = ''): string
    {
        $base = dirname(__DIR__, 2);

        return $path === '' ? $base : $base . DIRECTORY_SEPARATOR . ltrim($path, '/\\');
    }
}

if (!function_exists('app_path')) {
    function app_path(string $path = ''): string
    {
        return base_path('app' . ($path === '' ? '' : DIRECTORY_SEPARATOR . ltrim($path, '/\\')));
    }
}

if (!function_exists('storage_path')) {
    function storage_path(string $path = ''): string
    {
        return base_path('storage' . ($path === '' ? '' : DIRECTORY_SEPARATOR . ltrim($path, '/\\')));
    }
}

if (!function_exists('config_value')) {
    function config_value(string $key, mixed $default = null): mixed
    {
        global $config;

        return $config[$key] ?? $default;
    }
}

if (!function_exists('h')) {
    function h(mixed $value): string
    {
        return Security::escape((string) $value);
    }
}

if (!function_exists('asset_url')) {
    function asset_url(string $path): string
    {
        return '/assets/' . ltrim($path, '/');
    }
}
