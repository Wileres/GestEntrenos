<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Config;
use App\Helpers\Db;

/** Tokens d'activació i de recuperació: només es guarda el hash SHA-256. */
final class TokenService
{
    public static function generate(): string
    {
        return bin2hex(random_bytes(32));
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    private static function wellFormed(string $token): bool
    {
        return (bool) preg_match('/^[a-f0-9]{64}$/', $token);
    }

    // ---- Activació ----

    public static function createActivation(int $userId): string
    {
        return Db::transaction(function () use ($userId) {
            self::revokeActivation($userId);
            $token = self::generate();
            Db::insert('activation_tokens', ['user_id' => $userId, 'token_hash' => self::hash($token), 'created_at' => Db::now()]);
            return $token;
        });
    }

    public static function revokeActivation(int $userId): int
    {
        return Db::update('activation_tokens', ['revoked_at' => Db::now()], 'user_id = ? AND used_at IS NULL AND revoked_at IS NULL', [$userId]);
    }

    public static function hasPendingActivation(int $userId): bool
    {
        return (bool) Db::value('SELECT 1 FROM activation_tokens WHERE user_id = ? AND used_at IS NULL AND revoked_at IS NULL LIMIT 1', [$userId]);
    }

    public static function findActivation(string $token): ?array
    {
        if (!self::wellFormed($token)) {
            return null;
        }
        return Db::one(
            'SELECT t.id AS token_id, u.id AS user_id, u.first_name, u.email
             FROM activation_tokens t JOIN users u ON u.id = t.user_id
             WHERE t.token_hash = ? AND t.used_at IS NULL AND t.revoked_at IS NULL AND u.is_active = 1',
            [self::hash($token)]
        );
    }

    public static function consumeActivation(string $token, string $password): ?int
    {
        return Db::transaction(function () use ($token, $password) {
            $row = self::findActivation($token);
            if ($row === null) {
                return null;
            }
            $claimed = Db::update('activation_tokens', ['used_at' => Db::now()], 'id = ? AND used_at IS NULL AND revoked_at IS NULL', [$row['token_id']]);
            if ($claimed !== 1) {
                return null;
            }
            AuthService::setPassword((int) $row['user_id'], $password);
            return (int) $row['user_id'];
        });
    }

    // ---- Recuperació de contrasenya ----

    public static function createReset(int $userId): string
    {
        return Db::transaction(function () use ($userId) {
            Db::update('password_reset_tokens', ['used_at' => Db::now()], 'user_id = ? AND used_at IS NULL', [$userId]);
            $token = self::generate();
            Db::insert('password_reset_tokens', [
                'user_id' => $userId,
                'token_hash' => self::hash($token),
                'expires_at' => date('Y-m-d H:i:s', time() + (int) Config::get('auth.reset_minutes') * 60),
                'created_at' => Db::now(),
            ]);
            return $token;
        });
    }

    public static function findReset(string $token): ?array
    {
        if (!self::wellFormed($token)) {
            return null;
        }
        return Db::one(
            'SELECT t.id AS token_id, u.id AS user_id
             FROM password_reset_tokens t JOIN users u ON u.id = t.user_id
             WHERE t.token_hash = ? AND t.used_at IS NULL AND t.expires_at > ? AND u.is_active = 1',
            [self::hash($token), Db::now()]
        );
    }

    public static function consumeReset(string $token, string $password): ?int
    {
        return Db::transaction(function () use ($token, $password) {
            $row = self::findReset($token);
            if ($row === null) {
                return null;
            }
            $claimed = Db::update('password_reset_tokens', ['used_at' => Db::now()], 'id = ? AND used_at IS NULL', [$row['token_id']]);
            if ($claimed !== 1) {
                return null;
            }
            AuthService::setPassword((int) $row['user_id'], $password);
            Db::update('password_reset_tokens', ['used_at' => Db::now()], 'user_id = ? AND used_at IS NULL', [$row['user_id']]);
            return (int) $row['user_id'];
        });
    }
}
