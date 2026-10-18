<?php

declare(strict_types=1);

namespace App\Helpers;

final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        session_name((string) Config::get('session.name'));
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => Request::isHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();

        $idle = (int) Config::get('session.idle_minutes') * 60;
        $last = $_SESSION['_last_activity'] ?? time();
        if (time() - $last > $idle) {
            self::destroy();
            session_start();
            $_SESSION['_expired'] = true;
        }
        $_SESSION['_last_activity'] = time();
    }

    public static function regenerate(): void
    {
        session_regenerate_id(true);
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            if (ini_get('session.use_cookies')) {
                $p = session_get_cookie_params();
                setcookie(session_name(), '', [
                    'expires' => time() - 3600, 'path' => $p['path'], 'domain' => $p['domain'],
                    'secure' => $p['secure'], 'httponly' => true, 'samesite' => 'Lax',
                ]);
            }
            session_destroy();
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function pull(string $key, mixed $default = null): mixed
    {
        $v = $_SESSION[$key] ?? $default;
        unset($_SESSION[$key]);
        return $v;
    }

    public static function flash(string $type, string $message): void
    {
        $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
    }

    public static function pullFlash(): array
    {
        return self::pull('_flash', []);
    }

    /** Guarda dades de formulari i errors per al proper redireccionament. */
    public static function flashForm(array $old, array $errors): void
    {
        unset($old['password'], $old['password_confirmation'], $old['current_password'], $old['smtp_password'], $old['_csrf']);
        $_SESSION['_old'] = $old;
        $_SESSION['_errors'] = $errors;
    }
}
