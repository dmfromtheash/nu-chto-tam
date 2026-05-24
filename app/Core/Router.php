<?php

declare(strict_types=1);

namespace App\Core;

final class Router
{
    /**
     * @var array<string, array<string, callable>>
     */
    private array $routes = [
        'GET' => [],
        'POST' => [],
    ];

    public function get(string $path, callable $handler): void
    {
        $this->routes['GET'][$this->normalizePath($path)] = $handler;
    }

    public function post(string $path, callable $handler): void
    {
        $this->routes['POST'][$this->normalizePath($path)] = $handler;
    }

    public function dispatch(): void
    {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
        $path = $this->normalizePath(is_string($path) ? $path : '/');

        $handler = $this->routes[$method][$path] ?? null;

        if ($handler !== null) {
            $handler();
            return;
        }

        if ($this->expectsJson($path)) {
            Response::json([
                'ok' => false,
                'error' => 'Маршрут не найден.',
            ], 404);
        }

        http_response_code(404);
        echo '<!doctype html><html lang="ru"><head><meta charset="utf-8"><title>404</title></head>';
        echo '<body><h1>404</h1><p>Страница не найдена.</p><p><a href="/">На главную</a></p></body></html>';
    }

    private function normalizePath(string $path): string
    {
        $path = '/' . trim($path, '/');

        return $path === '/' ? '/' : rtrim($path, '/');
    }

    private function expectsJson(string $path): bool
    {
        $accept = (string) ($_SERVER['HTTP_ACCEPT'] ?? '');

        return str_starts_with($path, '/api')
            || str_contains(strtolower($accept), 'application/json');
    }
}
