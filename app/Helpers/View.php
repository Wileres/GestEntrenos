<?php

declare(strict_types=1);

namespace App\Helpers;

final class View
{
    public static array $old = [];
    public static array $errors = [];

    public static function render(string $template, array $data = [], string $layout = 'main', int $status = 200): void
    {
        http_response_code($status);
        self::$old = array_merge(Session::pull('_old', []), $data['old'] ?? []);
        self::$errors = array_merge(Session::pull('_errors', []), $data['errors'] ?? []);
        $content = self::capture($template, $data);
        header('Content-Type: text/html; charset=utf-8');
        echo self::capture('layouts/' . $layout, $data + ['content' => $content]);
    }

    public static function capture(string $template, array $data = []): string
    {
        $file = BASE_PATH . '/app/Views/' . $template . '.php';
        extract($data, EXTR_SKIP);
        ob_start();
        try {
            require $file;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string) ob_get_clean();
    }

    public static function component(string $name, array $data = []): void
    {
        echo self::capture('components/' . $name, $data);
    }
}
