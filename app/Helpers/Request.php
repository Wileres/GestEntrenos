<?php

declare(strict_types=1);

namespace App\Helpers;

final class Request
{
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query,
        public readonly array $post,
        public readonly array $files,
        public readonly array $server
    ) {
    }

    public static function capture(): self
    {
        $uri = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $base = self::basePath();
        if ($base !== '' && str_starts_with($uri, $base)) {
            $uri = substr($uri, strlen($base));
        }
        $path = '/' . trim(rawurldecode($uri), '/');
        return new self(strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'), $path, $_GET, $_POST, $_FILES, $_SERVER);
    }

    public static function basePath(): string
    {
        $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php');
        if (!in_array(basename($script), ['index.php', 'install.php'], true)) {
            return '';
        }
        $dir = str_replace('\\', '/', dirname($script));
        return $dir === '/' || $dir === '.' ? '' : rtrim($dir, '/');
    }

    public static function isHttps(): bool
    {
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            return true;
        }
        return Config::get('trust_proxy') && ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->post[$key] ?? $default;
    }

    public function str(string $key): string
    {
        $v = $this->post[$key] ?? '';
        return is_string($v) ? trim($v) : '';
    }

    public function q(string $key): string
    {
        $v = $this->query[$key] ?? '';
        return is_string($v) ? trim($v) : '';
    }

    /** @return int[] */
    public function intList(string $key): array
    {
        $v = $this->post[$key] ?? [];
        if (!is_array($v)) {
            return [];
        }
        return array_values(array_unique(array_filter(array_map('intval', $v), fn ($i) => $i > 0)));
    }

    public function ip(): string
    {
        return substr((string) ($this->server['REMOTE_ADDR'] ?? ''), 0, 45);
    }

    public function userAgent(): string
    {
        return substr((string) ($this->server['HTTP_USER_AGENT'] ?? ''), 0, 255);
    }
}
