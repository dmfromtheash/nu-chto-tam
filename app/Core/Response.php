<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class Response
{
    private function __construct()
    {
    }

    public static function json(mixed $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo Security::safeJsonEncode($data);
        exit;
    }

    public static function redirect(string $url, int $status = 302): never
    {
        http_response_code($status);
        header('Location: ' . $url);
        exit;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function view(string $view, array $data = [], ?string $layout = 'layout'): void
    {
        $viewsPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Views';
        $viewFile = $viewsPath . DIRECTORY_SEPARATOR . $view . '.php';

        if (!is_file($viewFile)) {
            throw new RuntimeException('View not found: ' . $view);
        }

        extract($data, EXTR_SKIP);

        ob_start();
        require $viewFile;
        $content = ob_get_clean();

        if ($layout === null) {
            echo $content;
            return;
        }

        $layoutFile = $viewsPath . DIRECTORY_SEPARATOR . $layout . '.php';

        if (!is_file($layoutFile)) {
            throw new RuntimeException('Layout not found: ' . $layout);
        }

        require $layoutFile;
    }
}
