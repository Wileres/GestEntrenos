<?php

declare(strict_types=1);

namespace App\Models;

use App\Helpers\Db;
use App\Helpers\Paginator;

final class User
{
    private const TRAINER_FROM = "FROM users u JOIN user_roles ur ON ur.user_id = u.id JOIN roles r ON r.id = ur.role_id AND r.name = 'trainer'";

    public static function find(int $id): ?array
    {
        return Db::one('SELECT id, username, first_name, last_name, email, phone, is_active, notes, password_hash IS NOT NULL AS has_password, last_login_at, created_at FROM users WHERE id = ?', [$id]);
    }

    public static function findTrainer(int $id): ?array
    {
        return Db::one(
            'SELECT u.id, u.first_name, u.last_name, u.email, u.phone, u.is_active, u.notes, u.notify_email, u.password_hash IS NOT NULL AS has_password, u.last_login_at, u.created_at '
            . self::TRAINER_FROM . ' WHERE u.id = ?',
            [$id]
        );
    }

    /** Tots els entrenadors (per assignar-los a equips). */
    public static function allTrainers(): array
    {
        return Db::all('SELECT u.id, u.first_name, u.last_name, u.is_active ' . self::TRAINER_FROM . ' ORDER BY u.first_name, u.last_name');
    }

    public static function searchTrainers(string $q, string $status, int $page): array
    {
        $where = ['1=1'];
        $params = [];
        if ($q !== '') {
            $where[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
            $like = '%' . addcslashes($q, '%_\\') . '%';
            array_push($params, $like, $like, $like);
        }
        if ($status === 'active' || $status === 'inactive') {
            $where[] = 'u.is_active = ?';
            $params[] = $status === 'active' ? 1 : 0;
        }
        $w = implode(' AND ', $where);
        $total = (int) Db::value('SELECT COUNT(*) ' . self::TRAINER_FROM . " WHERE $w", $params);
        $pg = Paginator::make($total, $page);
        $rows = Db::all(
            'SELECT u.id, u.first_name, u.last_name, u.email, u.is_active, u.password_hash IS NOT NULL AS has_password,
                    (SELECT COUNT(*) FROM user_teams WHERE user_id = u.id) AS teams_count '
            . self::TRAINER_FROM . " WHERE $w ORDER BY u.last_name, u.first_name LIMIT {$pg->perPage} OFFSET {$pg->offset}",
            $params
        );
        return [$rows, $pg];
    }

    public static function emailTaken(string $email, ?int $exceptId): bool
    {
        return (bool) Db::value('SELECT 1 FROM users WHERE email = ? AND id <> ? LIMIT 1', [mb_strtolower($email), $exceptId ?? 0]);
    }

    public static function findByEmail(string $email): ?array
    {
        return Db::one('SELECT id, first_name, last_name, email FROM users WHERE email = ? AND is_active = 1 AND password_hash IS NOT NULL LIMIT 1', [mb_strtolower($email)]);
    }

    public static function saveTrainer(?int $id, array $d): int
    {
        return self::saveAccount($id, $d, 2);
    }

    /** Crea o actualitza un compte amb el rol indicat (1 = administrador, 2 = entrenador). */
    public static function saveAccount(?int $id, array $d, int $roleId): int
    {
        $data = [
            'first_name' => $d['first_name'], 'last_name' => $d['last_name'], 'email' => mb_strtolower($d['email']),
            'phone' => $d['phone'] !== '' ? $d['phone'] : null, 'notes' => $d['notes'] !== '' ? $d['notes'] : null,
            'is_active' => $d['is_active'] ? 1 : 0, 'updated_at' => Db::now(),
        ];
        if ($id === null) {
            $id = Db::insert('users', $data + ['created_at' => Db::now(), 'must_change_password' => 0]);
            Db::insert('user_roles', ['user_id' => $id, 'role_id' => $roleId]);
            return $id;
        }
        Db::update('users', $data, 'id = ?', [$id]);
        return $id;
    }

    public static function teamIds(int $userId): array
    {
        return array_map('intval', Db::column('SELECT team_id FROM user_teams WHERE user_id = ?', [$userId]));
    }

    public static function teams(int $userId): array
    {
        return Db::all('SELECT c.id, c.name FROM teams c JOIN user_teams uc ON uc.team_id = c.id WHERE uc.user_id = ? ORDER BY c.name', [$userId]);
    }

    public static function syncRelations(int $userId, array $teamIds): void
    {
        Db::transaction(function () use ($userId, $teamIds) {
            Db::run('DELETE FROM user_teams WHERE user_id = ?', [$userId]);
            foreach ($teamIds as $c) {
                Db::insert('user_teams', ['user_id' => $userId, 'team_id' => $c]);
            }
        });
    }

    public static function admins(): array
    {
        return Db::all(
            "SELECT u.id, u.username, u.first_name, u.last_name, u.email, u.is_active, u.last_login_at, u.password_hash IS NOT NULL AS has_password
             FROM users u JOIN user_roles ur ON ur.user_id = u.id JOIN roles r ON r.id = ur.role_id AND r.name = 'admin'
             ORDER BY u.last_name, u.first_name"
        );
    }

    public static function findAdmin(int $id): ?array
    {
        return Db::one(
            "SELECT u.id, u.username, u.first_name, u.last_name, u.email, u.phone, u.is_active, u.password_hash IS NOT NULL AS has_password, u.last_login_at
             FROM users u JOIN user_roles ur ON ur.user_id = u.id JOIN roles r ON r.id = ur.role_id AND r.name = 'admin' WHERE u.id = ?",
            [$id]
        );
    }

    public static function countActiveAdmins(): int
    {
        return (int) Db::value("SELECT COUNT(*) FROM users u JOIN user_roles ur ON ur.user_id = u.id JOIN roles r ON r.id = ur.role_id AND r.name = 'admin' WHERE u.is_active = 1");
    }

    /** Elimina un entrenador i les seves relacions. Els tokens, notificacions i subscripcions s'esborren en cascada. */
    public static function deleteTrainer(int $id): void
    {
        Db::transaction(function () use ($id) {
            Db::run('DELETE FROM user_teams WHERE user_id = ?', [$id]);
            Db::run('DELETE FROM user_roles WHERE user_id = ?', [$id]);
            Db::run('DELETE FROM users WHERE id = ?', [$id]);
        });
    }

    public static function setNotifyEmail(int $id, bool $on): void
    {
        Db::update('users', ['notify_email' => $on ? 1 : 0], 'id = ?', [$id]);
    }

    public static function setActive(int $id, bool $active): void
    {
        Db::update('users', ['is_active' => $active ? 1 : 0, 'updated_at' => Db::now()], 'id = ?', [$id]);
    }

    public static function latestTrainers(int $limit = 5): array
    {
        return Db::all('SELECT u.id, u.first_name, u.last_name, u.created_at ' . self::TRAINER_FROM . ' ORDER BY u.id DESC LIMIT ' . $limit);
    }

    public static function countActiveTrainers(): int
    {
        return (int) Db::value('SELECT COUNT(*) ' . self::TRAINER_FROM . ' WHERE u.is_active = 1');
    }
}
