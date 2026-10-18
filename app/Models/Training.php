<?php

declare(strict_types=1);

namespace App\Models;

use App\Helpers\Db;
use App\Helpers\Paginator;

final class Training
{
    /** Condició SQL que limita els entrenaments publicats als equips (categories) d'un entrenador. */
    private const VISIBLE_TO_TRAINER = "t.status = 'published' AND
        EXISTS (SELECT 1 FROM training_categories tc JOIN user_categories uc ON uc.category_id = tc.category_id WHERE tc.training_id = t.id AND uc.user_id = :uid1)";

    public static function find(int $id): ?array
    {
        return Db::one('SELECT * FROM training_sessions t WHERE t.id = ?', [$id]);
    }

    /** Retorna l'entrenament només si l'usuari pot consultar-lo. */
    public static function findVisible(int $id, array $user): ?array
    {
        if ($user['role'] === 'admin') {
            return self::find($id);
        }
        return Db::one('SELECT t.* FROM training_sessions t WHERE t.id = :id AND ' . self::VISIBLE_TO_TRAINER, ['id' => $id, 'uid1' => $user['id']]);
    }

    public static function search(array $user, array $f, int $page): array
    {
        $where = [];
        $params = [];
        if ($user['role'] !== 'admin') {
            $where[] = self::VISIBLE_TO_TRAINER;
            $params += ['uid1' => $user['id']];
        } elseif (isset(\App\Helpers\Labels::TRAINING_STATUS[$f['status'] ?? ''])) {
            $where[] = 't.status = :status';
            $params['status'] = $f['status'];
        }
        if (($f['when'] ?? '') === 'past') {
            $where[] = 't.session_date < CURDATE()';
        } elseif (($f['when'] ?? 'upcoming') === 'upcoming') {
            $where[] = 't.session_date >= CURDATE()';
        }
        if ((int) ($f['category'] ?? 0) > 0) {
            $where[] = 'EXISTS (SELECT 1 FROM training_categories x WHERE x.training_id = t.id AND x.category_id = :cat)';
            $params['cat'] = (int) $f['category'];
        }
        $w = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $total = (int) Db::value("SELECT COUNT(*) FROM training_sessions t $w", $params);
        $pg = Paginator::make($total, $f['page'] ?? 1);
        $order = ($f['when'] ?? 'upcoming') === 'upcoming' ? 'ASC' : 'DESC';
        $rows = Db::all(
            "SELECT t.id, t.title, t.session_date, t.start_time, t.duration_minutes, t.status, t.season,
                    (SELECT GROUP_CONCAT(c.name ORDER BY c.sort_order SEPARATOR ', ') FROM training_categories tc JOIN categories c ON c.id = tc.category_id WHERE tc.training_id = t.id) AS categories
             FROM training_sessions t $w ORDER BY t.session_date $order, t.start_time $order LIMIT {$pg->perPage} OFFSET {$pg->offset}",
            $params
        );
        return [$rows, $pg];
    }

    public static function upcomingFor(array $user, int $limit = 5): array
    {
        $params = [];
        $w = "t.status IN ('published') AND t.session_date >= CURDATE()";
        if ($user['role'] !== 'admin') {
            $w = 't.session_date >= CURDATE() AND ' . self::VISIBLE_TO_TRAINER;
            $params = ['uid1' => $user['id']];
        }
        return Db::all("SELECT t.id, t.title, t.session_date, t.start_time, t.status FROM training_sessions t WHERE $w ORDER BY t.session_date, t.start_time LIMIT $limit", $params);
    }

    public static function latest(int $limit = 5): array
    {
        return Db::all('SELECT id, title, session_date, start_time, status FROM training_sessions ORDER BY id DESC LIMIT ' . $limit);
    }

    public static function categories(int $id): array
    {
        return Db::all('SELECT c.id, c.name FROM categories c JOIN training_categories tc ON tc.category_id = c.id WHERE tc.training_id = ? ORDER BY c.sort_order', [$id]);
    }

    public static function exercises(int $id): array
    {
        return Db::all(
            'SELECT te.id, te.exercise_id, te.position, te.duration_minutes, te.notes, e.name, e.is_active
             FROM training_exercises te JOIN exercises e ON e.id = te.exercise_id WHERE te.training_id = ? ORDER BY te.position, te.id',
            [$id]
        );
    }

    public static function files(int $id): array
    {
        return Db::all('SELECT id, original_name, mime_type, size_bytes FROM training_files WHERE training_id = ? ORDER BY id', [$id]);
    }

    public static function file(int $fileId): ?array
    {
        return Db::one('SELECT * FROM training_files WHERE id = ?', [$fileId]);
    }

    public static function addFile(int $trainingId, array $meta): int
    {
        return Db::insert('training_files', $meta + ['training_id' => $trainingId, 'created_at' => Db::now()]);
    }

    /**
     * @param array{categories:int[],exercises:array<int,array{exercise_id:int,duration:?int,notes:string}>} $rel
     */
    public static function save(?int $id, array $d, array $rel, int $userId): int
    {
        $data = [
            'title' => $d['title'], 'description' => $d['description'] !== '' ? $d['description'] : null,
            'session_date' => $d['session_date'], 'start_time' => $d['start_time'],
            'duration_minutes' => (int) $d['duration_minutes'], 'season' => $d['season'],
            'notes' => $d['notes'] !== '' ? $d['notes'] : null, 'updated_at' => Db::now(),
        ];
        return Db::transaction(function () use ($id, $data, $rel, $userId) {
            if ($id === null) {
                $id = Db::insert('training_sessions', $data + ['status' => 'draft', 'created_by' => $userId, 'created_at' => Db::now()]);
            } else {
                Db::update('training_sessions', $data, 'id = ?', [$id]);
            }
            Db::run('DELETE FROM training_categories WHERE training_id = ?', [$id]);
            Db::run('DELETE FROM training_exercises WHERE training_id = ?', [$id]);
            foreach ($rel['categories'] as $c) {
                Db::insert('training_categories', ['training_id' => $id, 'category_id' => $c]);
            }
            foreach (array_values($rel['exercises']) as $pos => $ex) {
                Db::insert('training_exercises', [
                    'training_id' => $id, 'exercise_id' => $ex['exercise_id'], 'position' => $pos + 1,
                    'duration_minutes' => $ex['duration'], 'notes' => $ex['notes'] !== '' ? $ex['notes'] : null,
                ]);
            }
            return $id;
        });
    }

    public static function setStatus(int $id, string $status): void
    {
        Db::update('training_sessions', ['status' => $status, 'updated_at' => Db::now()], 'id = ?', [$id]);
    }

    public static function delete(int $id): array
    {
        $files = Db::column('SELECT stored_name FROM training_files WHERE training_id = ?', [$id]);
        Db::run('DELETE FROM training_sessions WHERE id = ?', [$id]);
        return $files;
    }
}
