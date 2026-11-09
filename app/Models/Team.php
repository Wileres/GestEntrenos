<?php

declare(strict_types=1);

namespace App\Models;

use App\Helpers\Db;

/** Equip: pertany a una categoria, té jugadors, entrenadors i un horari d'entrenament. */
final class Team
{
    private const SELECT = 'SELECT t.id, t.name, t.category_id, t.is_active, c.name AS category_name FROM teams t LEFT JOIN categories c ON c.id = t.category_id';

    public static function all(): array
    {
        return Db::all(self::SELECT . ' ORDER BY c.sort_order IS NULL, c.sort_order, c.name, t.name');
    }

    public static function active(): array
    {
        return Db::all(self::SELECT . ' WHERE t.is_active = 1 ORDER BY c.sort_order IS NULL, c.sort_order, c.name, t.name');
    }

    /** Llista per a la gestió: amb recompte de jugadors i entrenadors. */
    public static function overview(): array
    {
        return Db::all(
            'SELECT t.id, t.name, t.category_id, t.is_active, c.name AS category_name,
                    (SELECT COUNT(*) FROM players p WHERE p.team_id = t.id AND p.is_active = 1) AS players_count,
                    (SELECT GROUP_CONCAT(CONCAT(u.first_name, " ", u.last_name) ORDER BY u.first_name SEPARATOR ", ") FROM user_teams ut JOIN users u ON u.id = ut.user_id WHERE ut.team_id = t.id) AS trainers,
                    (SELECT COUNT(*) FROM team_schedules s WHERE s.team_id = t.id) AS own_schedule
             FROM teams t LEFT JOIN categories c ON c.id = t.category_id
             ORDER BY c.sort_order IS NULL, c.sort_order, c.name, t.name'
        );
    }

    public static function find(int $id): ?array
    {
        return Db::one(self::SELECT . ' WHERE t.id = ?', [$id]);
    }

    public static function save(?int $id, array $d): int
    {
        $data = [
            'name' => $d['name'], 'category_id' => $d['category_id'] ?: null,
            'is_active' => $d['is_active'] ? 1 : 0, 'updated_at' => Db::now(),
        ];
        if ($id === null) {
            return Db::insert('teams', $data + ['created_at' => Db::now()]);
        }
        Db::update('teams', $data, 'id = ?', [$id]);
        return $id;
    }

    public static function nameTaken(string $name, ?int $exceptId): bool
    {
        return (bool) Db::value('SELECT 1 FROM teams WHERE name = ? AND id <> ? LIMIT 1', [$name, $exceptId ?? 0]);
    }

    public static function inUse(int $id): bool
    {
        return (bool) Db::value(
            'SELECT (SELECT COUNT(*) FROM players WHERE team_id = :a) + (SELECT COUNT(*) FROM user_teams WHERE team_id = :b)
                  + (SELECT COUNT(*) FROM exercise_teams WHERE team_id = :c) + (SELECT COUNT(*) FROM exercises WHERE team_id = :d)
                  + (SELECT COUNT(*) FROM training_teams WHERE team_id = :e)',
            ['a' => $id, 'b' => $id, 'c' => $id, 'd' => $id, 'e' => $id]
        );
    }

    public static function delete(int $id): void
    {
        Db::run('DELETE FROM teams WHERE id = ?', [$id]);
    }

    /** Entrenadors assignats a un equip. */
    public static function trainers(int $teamId): array
    {
        return Db::all(
            'SELECT u.id, u.first_name, u.last_name, u.email FROM user_teams ut JOIN users u ON u.id = ut.user_id WHERE ut.team_id = ? ORDER BY u.first_name, u.last_name',
            [$teamId]
        );
    }

    public static function trainerIds(int $teamId): array
    {
        return array_map('intval', Db::column('SELECT user_id FROM user_teams WHERE team_id = ?', [$teamId]));
    }

    public static function syncTrainers(int $teamId, array $userIds): void
    {
        Db::transaction(function () use ($teamId, $userIds) {
            Db::run('DELETE FROM user_teams WHERE team_id = ?', [$teamId]);
            foreach ($userIds as $uid) {
                Db::insert('user_teams', ['user_id' => $uid, 'team_id' => $teamId]);
            }
        });
    }

    /** Busca un equip pel nom (sense distingir majúscules ni accents) o el crea, assignant-li la categoria que coincideixi amb el nom. */
    public static function findOrCreate(string $name): array
    {
        $row = Db::one('SELECT id FROM teams WHERE name = ?', [$name]);
        if ($row) {
            return ['id' => (int) $row['id'], 'created' => false];
        }
        $cat = self::guessCategory($name);
        $id = Db::insert('teams', ['name' => $name, 'category_id' => $cat, 'is_active' => 1, 'created_at' => Db::now(), 'updated_at' => Db::now()]);
        return ['id' => $id, 'created' => true];
    }

    /** Categoria el nom de la qual és el prefix del nom de l'equip ("Benjamí B" → "Benjamí"). */
    public static function guessCategory(string $teamName): ?int
    {
        $norm = fn (string $s) => self::normalize($s);
        $t = $norm($teamName);
        $best = null;
        $bestLen = 0;
        foreach (Category::all() as $c) {
            $n = $norm($c['name']);
            if ($n === '' || !str_starts_with($t, $n)) {
                continue;
            }
            $next = substr($t, strlen($n), 1);
            if ($next !== '' && ctype_alnum($next)) {
                continue;
            }
            if (strlen($n) > $bestLen) {
                $best = (int) $c['id'];
                $bestLen = strlen($n);
            }
        }
        return $best;
    }

    public static function normalize(string $s): string
    {
        $s = mb_strtolower(trim($s));
        $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
        $s = $t !== false ? $t : $s;
        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', $s));
    }
}
