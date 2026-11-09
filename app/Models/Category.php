<?php

declare(strict_types=1);

namespace App\Models;

use App\Helpers\Db;

final class Category
{
    public static function all(): array
    {
        return Db::all('SELECT id, name, sort_order, is_active FROM categories ORDER BY sort_order, name');
    }

    public static function active(): array
    {
        return Db::all('SELECT id, name FROM categories WHERE is_active = 1 ORDER BY sort_order, name');
    }

    public static function find(int $id): ?array
    {
        return Db::one('SELECT id, name, sort_order, is_active FROM categories WHERE id = ?', [$id]);
    }

    public static function save(?int $id, array $d): int
    {
        $data = ['name' => $d['name'], 'sort_order' => (int) $d['sort_order'], 'is_active' => $d['is_active'] ? 1 : 0, 'updated_at' => Db::now()];
        if ($id === null) {
            return Db::insert('categories', $data + ['created_at' => Db::now()]);
        }
        Db::update('categories', $data, 'id = ?', [$id]);
        return $id;
    }

    public static function nameTaken(string $name, ?int $exceptId): bool
    {
        return (bool) Db::value('SELECT 1 FROM categories WHERE name = ? AND id <> ? LIMIT 1', [$name, $exceptId ?? 0]);
    }

    public static function inUse(int $id): bool
    {
        return (bool) Db::value('SELECT 1 FROM teams WHERE category_id = ? LIMIT 1', [$id]);
    }

    public static function delete(int $id): void
    {
        Db::run('DELETE FROM categories WHERE id = ?', [$id]);
    }
}
