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
            'SELECT u.id, u.first_name, u.last_name, u.email, u.phone, u.is_active, u.notes, u.password_hash IS NOT NULL AS has_password, u.last_login_at, u.created_at '
            . self::TRAINER_FROM . ' WHERE u.id = ?',
            [$id]
        );
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
                    (SELECT COUNT(*) FROM user_categories WHERE user_id = u.id) AS categories_count '
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
        $data = [
            'first_name' => $d['first_name'], 'last_name' => $d['last_name'], 'email' => mb_strtolower($d['email']),
            'phone' => $d['phone'] !== '' ? $d['phone'] : null, 'notes' => $d['notes'] !== '' ? $d['notes'] : null,
            'is_active' => $d['is_active'] ? 1 : 0, 'updated_at' => Db::now(),
        ];
        if ($id === null) {
            $id = Db::insert('users', $data + ['created_at' => Db::now(), 'must_change_password' => 0]);
            Db::insert('user_roles', ['user_id' => $id, 'role_id' => 2]);
            return $id;
        }
        Db::update('users', $data, 'id = ?', [$id]);
        return $id;
    }

    public static function categoryIds(int $userId): array
    {
        return array_map('intval', Db::column('SELECT category_id FROM user_categories WHERE user_id = ?', [$userId]));
    }

    public static function categories(int $userId): array
    {
        return Db::all('SELECT c.id, c.name FROM categories c JOIN user_categories uc ON uc.category_id = c.id WHERE uc.user_id = ? ORDER BY c.sort_order', [$userId]);
    }

    public static function syncRelations(int $userId, array $categoryIds): void
    {
        Db::transaction(function () use ($userId, $categoryIds) {
            Db::run('DELETE FROM user_categories WHERE user_id = ?', [$userId]);
            foreach ($categoryIds as $c) {
                Db::insert('user_categories', ['user_id' => $userId, 'category_id' => $c]);
            }
        });
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
