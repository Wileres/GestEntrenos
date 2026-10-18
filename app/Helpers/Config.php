<?php

declare(strict_types=1);

namespace App\Helpers;

final class Config
{
    private static ?array $data = null;

    public static function get(string $path, mixed $default = null): mixed
    {
        self::$data ??= require BASE_PATH . '/config/app.php';
        $value = self::$data;
        foreach (explode('.', $path) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }
        return $value;
    }

    public static function reset(): void
    {
        self::$data = null;
    }
}
