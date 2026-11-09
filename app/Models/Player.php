<?php

declare(strict_types=1);

namespace App\Models;

use App\Helpers\Db;
use App\Helpers\Paginator;

final class Player
{
    public static function search(array $f, int $page): array
    {
        $where = [];
        $params = [];
        if (($f['q'] ?? '') !== '') {
            $where[] = 'p.full_name LIKE ?';
            $params[] = '%' . addcslashes($f['q'], '%_\\') . '%';
        }
        if ((int) ($f['team'] ?? 0) > 0) {
            $where[] = 'p.team_id = ?';
            $params[] = (int) $f['team'];
        }
        if ((int) ($f['category'] ?? 0) > 0) {
            $where[] = 't.category_id = ?';
            $params[] = (int) $f['category'];
        }
        if (($f['status'] ?? '') === 'active' || ($f['status'] ?? '') === 'inactive') {
            $where[] = 'p.is_active = ?';
            $params[] = $f['status'] === 'active' ? 1 : 0;
        }
        $w = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $from = 'FROM players p JOIN teams t ON t.id = p.team_id';
        $total = (int) Db::value("SELECT COUNT(*) $from $w", $params);
        $pg = Paginator::make($total, $page);
        $rows = Db::all(
            "SELECT p.id, p.full_name, p.birth_date, p.is_active, p.team_id, t.name AS team_name $from $w ORDER BY t.name, p.full_name LIMIT {$pg->perPage} OFFSET {$pg->offset}",
            $params
        );
        return [$rows, $pg];
    }

    public static function find(int $id): ?array
    {
        return Db::one('SELECT p.id, p.full_name, p.birth_date, p.team_id, p.is_active, t.name AS team_name FROM players p JOIN teams t ON t.id = p.team_id WHERE p.id = ?', [$id]);
    }

    public static function forTeam(int $teamId, bool $onlyActive = true): array
    {
        return Db::all(
            'SELECT id, full_name, birth_date, is_active FROM players WHERE team_id = ?' . ($onlyActive ? ' AND is_active = 1' : '') . ' ORDER BY full_name',
            [$teamId]
        );
    }

    public static function save(?int $id, array $d): int
    {
        $data = [
            'full_name' => $d['full_name'], 'birth_date' => $d['birth_date'] ?: null, 'team_id' => (int) $d['team_id'],
            'is_active' => $d['is_active'] ? 1 : 0, 'updated_at' => Db::now(),
        ];
        if ($id === null) {
            return Db::insert('players', $data + ['created_at' => Db::now()]);
        }
        Db::update('players', $data, 'id = ?', [$id]);
        return $id;
    }

    public static function delete(int $id): void
    {
        Db::run('DELETE FROM players WHERE id = ?', [$id]);
    }

    public static function findByNameAndBirth(string $name, ?string $birth): ?array
    {
        return Db::one('SELECT id, team_id FROM players WHERE full_name = ? AND birth_date <=> ? LIMIT 1', [$name, $birth]);
    }

    public static function countActive(): int
    {
        return (int) Db::value('SELECT COUNT(*) FROM players WHERE is_active = 1');
    }
}
