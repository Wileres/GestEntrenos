<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Config;
use App\Helpers\Db;
use App\Helpers\Session;

final class AuthService
{
    private static ?array $user = null;
    private static bool $loaded = false;

    public static function user(): ?array
    {
        if (self::$loaded) {
            return self::$user;
        }
        $id = Session::get('user_id');
        self::$user = null;
        if (is_int($id)) {
            self::$user = Db::one(
                'SELECT u.id, u.username, u.first_name, u.last_name, u.email, u.phone, u.must_change_password, r.name AS role
                 FROM users u JOIN user_roles ur ON ur.user_id = u.id JOIN roles r ON r.id = ur.role_id
                 WHERE u.id = ? AND u.is_active = 1 AND u.password_hash IS NOT NULL LIMIT 1',
                [$id]
            );
            if (self::$user === null) {
                Session::forget('user_id');
            }
        }
        self::$loaded = true;
        return self::$user;
    }

    public static function resetCache(): void
    {
        self::$user = null;
        self::$loaded = false;
    }

    public static function isAdmin(): bool
    {
        return (self::user()['role'] ?? null) === 'admin';
    }

    private static function normalize(string $identifier): string
    {
        return mb_substr(mb_strtolower(trim($identifier)), 0, 190);
    }

    public static function isLocked(string $identifier, string $ip): bool
    {
        $max = (int) Config::get('auth.max_attempts');
        $since = date('Y-m-d H:i:s', time() - (int) Config::get('auth.lock_minutes') * 60);
        $byId = (int) Db::value('SELECT COUNT(*) FROM login_attempts WHERE identifier = ? AND created_at >= ?', [self::normalize($identifier), $since]);
        $byIp = (int) Db::value('SELECT COUNT(*) FROM login_attempts WHERE ip_address = ? AND created_at >= ?', [$ip, $since]);
        return $byId >= $max || $byIp >= $max * 4;
    }

    /**
     * @return array{ok:bool,error:?string}
     */
    public static function attempt(string $identifier, string $password, string $ip): array
    {
        $generic = 'Usuari o contrasenya incorrectes.';
        $norm = self::normalize($identifier);

        if ($norm === '' || self::isLocked($norm, $ip)) {
            if ($norm !== '') {
                AuditService::log('login_blocked', 'user', null, 'error', 'Bloqueig temporal per massa intents', null);
                return ['ok' => false, 'error' => 'Massa intents fallits. Torna-ho a provar d\'aquí a uns minuts.'];
            }
            return ['ok' => false, 'error' => $generic];
        }

        $user = Db::one(
            'SELECT id, password_hash, is_active FROM users WHERE (username = ? OR email = ?) LIMIT 1',
            [$norm, $norm]
        );
        static $dummy = null;
        $dummy ??= password_hash('dummy-password-for-timing', PASSWORD_DEFAULT);
        $hash = ($user && $user['password_hash']) ? $user['password_hash'] : $dummy;
        $valid = password_verify($password, $hash) && $user && $user['password_hash'] && (int) $user['is_active'] === 1;

        if (!$valid) {
            Db::insert('login_attempts', ['identifier' => $norm, 'ip_address' => $ip, 'created_at' => Db::now()]);
            AuditService::log('login_failed', 'user', $user ? (int) $user['id'] : null, 'error', 'Intent d\'accés fallit', $user ? (int) $user['id'] : null);
            return ['ok' => false, 'error' => $generic];
        }

        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            Db::update('users', ['password_hash' => password_hash($password, PASSWORD_DEFAULT)], 'id = ?', [$user['id']]);
        }
        Db::run('DELETE FROM login_attempts WHERE identifier = ?', [$norm]);
        Db::update('users', ['last_login_at' => Db::now()], 'id = ?', [$user['id']]);
        self::login((int) $user['id']);
        AuditService::log('login', 'user', (int) $user['id'], 'ok', null, (int) $user['id']);
        return ['ok' => true, 'error' => null];
    }

    public static function login(int $userId): void
    {
        Session::regenerate();
        Session::set('user_id', $userId);
        self::resetCache();
    }

    public static function logout(): void
    {
        $id = self::user()['id'] ?? null;
        if ($id) {
            AuditService::log('logout', 'user', (int) $id, 'ok', null, (int) $id);
        }
        Session::destroy();
        self::resetCache();
    }

    public static function setPassword(int $userId, string $newPassword): void
    {
        Db::update('users', [
            'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
            'must_change_password' => 0,
            'updated_at' => Db::now(),
        ], 'id = ?', [$userId]);
    }

    public static function verifyPassword(int $userId, string $password): bool
    {
        $hash = Db::value('SELECT password_hash FROM users WHERE id = ?', [$userId]);
        return is_string($hash) && password_verify($password, $hash);
    }
}
