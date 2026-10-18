<?php

declare(strict_types=1);

use App\Helpers\Env;

return [
    'name' => Env::get('APP_NAME', 'Entrenaments JSPM'),
    'env' => Env::get('APP_ENV', 'production'),
    'debug' => Env::bool('APP_DEBUG', false),
    'url' => rtrim((string) Env::get('APP_URL', ''), '/'),
    'timezone' => Env::get('APP_TIMEZONE', 'Europe/Madrid'),
    'force_https' => Env::bool('FORCE_HTTPS', true),
    'trust_proxy' => Env::bool('TRUST_PROXY', false),
    'key' => Env::get('APP_KEY', ''),
    'per_page' => Env::int('PER_PAGE', 15),
    'db' => [
        'host' => Env::get('DB_HOST', '127.0.0.1'),
        'port' => Env::int('DB_PORT', 3306),
        'name' => Env::get('DB_NAME', ''),
        'user' => Env::get('DB_USER', ''),
        'pass' => Env::get('DB_PASS', ''),
    ],
    'session' => [
        'name' => 'jspm_session',
        'idle_minutes' => Env::int('SESSION_IDLE_MINUTES', 30),
    ],
    'auth' => [
        'max_attempts' => Env::int('LOGIN_MAX_ATTEMPTS', 5),
        'lock_minutes' => Env::int('LOGIN_LOCK_MINUTES', 15),
        'reset_minutes' => Env::int('RESET_TOKEN_MINUTES', 60),
    ],
    'upload' => [
        'max_bytes' => Env::int('UPLOAD_MAX_MB', 10) * 1024 * 1024,
        'allow_mp4' => Env::bool('ALLOW_MP4', false),
    ],
];
