<?php

declare(strict_types=1);

namespace App\Models;

use App\Helpers\Db;
use App\Helpers\Paginator;

final class Exercise
{
    public static function search(array $f, int $page, bool $includeInactive): array
    {
        $where = [];
        $params = [];
        if (!$includeInactive) {
            $where[] = 'e.is_active = 1';
        } elseif (($f['status'] ?? '') === 'inactive') {
            $where[] = 'e.is_active = 0';
        } elseif (($f['status'] ?? '') === 'active') {
            $where[] = 'e.is_active = 1';
        }
        foreach (['name' => 'e.name', 'objective' => 'e.objective', 'material' => 'e.material'] as $key => $col) {
            if (($f[$key] ?? '') !== '') {
                $where[] = "$col LIKE ?";
                $params[] = '%' . addcslashes($f[$key], '%_\\') . '%';
            }
        }
        if ((int) ($f['category'] ?? 0) > 0) {
            $where[] = 'EXISTS (SELECT 1 FROM exercise_categories ec WHERE ec.exercise_id = e.id AND ec.category_id = ?)';
            $params[] = (int) $f['category'];
        }
        if (isset(\App\Helpers\Labels::DIFFICULTY[$f['difficulty'] ?? ''])) {
            $where[] = 'e.difficulty = ?';
            $params[] = $f['difficulty'];
        }
        if (($f['players'] ?? '') !== '' && ctype_digit($f['players'])) {
            $where[] = 'e.players = ?';
            $params[] = (int) $f['players'];
        }
        $w = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $total = (int) Db::value("SELECT COUNT(*) FROM exercises e $w", $params);
        $pg = Paginator::make($total, $f['page'] ?? 1);
        $rows = Db::all(
            "SELECT e.id, e.name, e.objective, e.duration_minutes, e.players, e.material, e.difficulty, e.is_active, (SELECT GROUP_CONCAT(c.name ORDER BY c.sort_order SEPARATOR ', ') FROM exercise_categories ec JOIN categories c ON c.id = ec.category_id WHERE ec.exercise_id = e.id) AS category_name
             FROM exercises e $w
             ORDER BY e.name LIMIT {$pg->perPage} OFFSET {$pg->offset}",
            $params
        );
        return [$rows, $pg];
    }

    public static function find(int $id): ?array
    {
        return Db::one(
            'SELECT e.*, CONCAT(u.first_name, " ", u.last_name) AS creator_name
             FROM exercises e JOIN users u ON u.id = e.created_by WHERE e.id = ?',
            [$id]
        );
    }

    public static function extraCategoryIds(int $id): array
    {
        return array_map('intval', Db::column('SELECT category_id FROM exercise_categories WHERE exercise_id = ?', [$id]));
    }

    public static function extraCategories(int $id): array
    {
        return Db::all('SELECT c.id, c.name FROM categories c JOIN exercise_categories ec ON ec.category_id = c.id WHERE ec.exercise_id = ? ORDER BY c.sort_order', [$id]);
    }

    public static function files(int $id): array
    {
        return Db::all('SELECT id, original_name, mime_type, size_bytes FROM exercise_files WHERE exercise_id = ? ORDER BY id', [$id]);
    }

    public static function file(int $fileId): ?array
    {
        return Db::one(
            'SELECT f.*, e.is_active FROM exercise_files f JOIN exercises e ON e.id = f.exercise_id WHERE f.id = ?',
            [$fileId]
        );
    }

    public static function save(?int $id, array $d, int $userId): int
    {
        $n = fn (string $k) => ($d[$k] ?? '') !== '' ? $d[$k] : null;
        $data = [
            'name' => $d['name'], 'description' => $d['description'], 'category_id' => (int) $d['extra_categories'][0],
            'objective' => $n('objective'), 'duration_minutes' => $n('duration_minutes'), 'players' => $n('players'),
            'material' => $n('material'), 'difficulty' => $n('difficulty'),
            'is_active' => $d['is_active'] ? 1 : 0, 'updated_at' => Db::now(),
        ];
        return Db::transaction(function () use ($id, $data, $d, $userId) {
            if ($id === null) {
                $id = Db::insert('exercises', $data + ['created_by' => $userId, 'created_at' => Db::now()]);
            } else {
                Db::update('exercises', $data, 'id = ?', [$id]);
            }
            Db::run('DELETE FROM exercise_categories WHERE exercise_id = ?', [$id]);
            foreach ($d['extra_categories'] as $c) {
                Db::insert('exercise_categories', ['exercise_id' => $id, 'category_id' => $c]);
            }
            return $id;
        });
    }

    public static function addFile(int $exerciseId, array $meta): int
    {
        return Db::insert('exercise_files', $meta + ['exercise_id' => $exerciseId, 'created_at' => Db::now()]);
    }

    public static function inTraining(int $id): bool
    {
        return (bool) Db::value('SELECT 1 FROM training_exercises WHERE exercise_id = ? LIMIT 1', [$id]);
    }

    public static function delete(int $id): void
    {
        Db::run('DELETE FROM exercises WHERE id = ?', [$id]);
    }

    public static function catalog(): array
    {
        return Db::all('SELECT e.id, e.name, (SELECT GROUP_CONCAT(c.name ORDER BY c.sort_order SEPARATOR ", ") FROM exercise_categories ec JOIN categories c ON c.id = ec.category_id WHERE ec.exercise_id = e.id) AS category_name FROM exercises e WHERE e.is_active = 1 ORDER BY e.name');
    }

    public static function latest(int $limit = 5): array
    {
        return Db::all('SELECT e.id, e.name, (SELECT GROUP_CONCAT(c.name ORDER BY c.sort_order SEPARATOR ", ") FROM exercise_categories ec JOIN categories c ON c.id = ec.category_id WHERE ec.exercise_id = e.id) AS category_name FROM exercises e WHERE e.is_active = 1 ORDER BY e.id DESC LIMIT ' . $limit);
    }
}
