<?php

declare(strict_types=1);

namespace App\Models;

use App\Helpers\Db;

final class Team
{
    public static function all(?string $season = null): array
    {
        $sql = 'SELECT t.id, t.name, t.season, t.description, t.is_active, t.category_id, c.name AS category_name
                FROM teams t JOIN categories c ON c.id = t.category_id';
        $params = [];
        if ($season !== null && $season !== '') {
            $sql .= ' WHERE t.season = ?';
            $params[] = $season;
        }
        return Db::all($sql . ' ORDER BY c.sort_order, t.name, t.season DESC', $params);
    }

    public static function active(): array
    {
        return Db::all(
            'SELECT t.id, t.name, t.season, t.category_id, c.name AS category_name
             FROM teams t JOIN categories c ON c.id = t.category_id
             WHERE t.is_active = 1 ORDER BY c.sort_order, t.name'
        );
    }

    public static function find(int $id): ?array
    {
        return Db::one('SELECT id, category_id, name, season, description, is_active FROM teams WHERE id = ?', [$id]);
    }

    public static function save(?int $id, array $d): int
    {
        $data = [
            'category_id' => (int) $d['category_id'], 'name' => $d['name'], 'season' => $d['season'],
            'description' => $d['description'] !== '' ? $d['description'] : null,
            'is_active' => $d['is_active'] ? 1 : 0, 'updated_at' => Db::now(),
        ];
        if ($id === null) {
            return Db::insert('teams', $data + ['created_at' => Db::now()]);
        }
        Db::update('teams', $data, 'id = ?', [$id]);
        return $id;
    }

    public static function duplicate(int $categoryId, string $name, string $season, ?int $exceptId): bool
    {
        return (bool) Db::value(
            'SELECT 1 FROM teams WHERE category_id = ? AND name = ? AND season = ? AND id <> ? LIMIT 1',
            [$categoryId, $name, $season, $exceptId ?? 0]
        );
    }

    public static function inUse(int $id): bool
    {
        return (bool) Db::value(
            'SELECT (SELECT COUNT(*) FROM user_teams WHERE team_id = :a) + (SELECT COUNT(*) FROM training_teams WHERE team_id = :b)',
            ['a' => $id, 'b' => $id]
        );
    }

    public static function delete(int $id): void
    {
        Db::run('DELETE FROM teams WHERE id = ?', [$id]);
    }
}
