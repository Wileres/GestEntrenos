<?php

declare(strict_types=1);

namespace App\Models;

use App\Helpers\Db;

final class Attendance
{
    public const STATUS = ['present' => 'Hi és', 'late' => 'Ha arribat tard', 'absent' => 'No hi és'];

    private const SESSION = 'SELECT s.id, s.team_id, s.session_date, s.start_time, s.duration_minutes, s.status, s.source, t.name AS team_name,
        (SELECT COUNT(*) FROM attendance_records r WHERE r.session_id = s.id) AS marked,
        (SELECT COUNT(*) FROM players p WHERE p.team_id = s.team_id AND p.is_active = 1) AS players
        FROM attendance_sessions s JOIN teams t ON t.id = s.team_id';

    public static function find(int $id): ?array
    {
        return Db::one(self::SESSION . ' WHERE s.id = ?', [$id]);
    }

    /** Fitxes entre dues dates; si $userId és donat, només dels equips d'aquest entrenador. */
    public static function sessions(string $from, string $to, ?int $userId = null, ?int $teamId = null, string $order = 'ASC'): array
    {
        $where = ['s.session_date BETWEEN ? AND ?'];
        $params = [$from, $to];
        if ($userId !== null) {
            $where[] = 'EXISTS (SELECT 1 FROM user_teams ut WHERE ut.team_id = s.team_id AND ut.user_id = ?)';
            $params[] = $userId;
        }
        if ($teamId !== null) {
            $where[] = 's.team_id = ?';
            $params[] = $teamId;
        }
        $order = $order === 'DESC' ? 'DESC' : 'ASC';
        return Db::all(self::SESSION . ' WHERE ' . implode(' AND ', $where) . " ORDER BY s.session_date $order, s.start_time $order, t.name", $params);
    }

    public static function mayManage(array $user, array $session): bool
    {
        if ($user['role'] === 'admin') {
            return true;
        }
        return (bool) Db::value('SELECT 1 FROM user_teams WHERE user_id = ? AND team_id = ?', [$user['id'], $session['team_id']]);
    }

    /** @return array<int,string> jugador => estat */
    public static function records(int $sessionId): array
    {
        $out = [];
        foreach (Db::all('SELECT player_id, status FROM attendance_records WHERE session_id = ?', [$sessionId]) as $r) {
            $out[(int) $r['player_id']] = $r['status'];
        }
        return $out;
    }

    /** Desa l'assistència. Un estat buit esborra la marca del jugador. */
    public static function saveRecords(int $sessionId, array $statuses, int $userId): void
    {
        Db::transaction(function () use ($sessionId, $statuses, $userId) {
            foreach ($statuses as $playerId => $status) {
                if ($status === null) {
                    Db::run('DELETE FROM attendance_records WHERE session_id = ? AND player_id = ?', [$sessionId, $playerId]);
                    continue;
                }
                Db::run(
                    'INSERT INTO attendance_records (session_id, player_id, status, marked_by, marked_at) VALUES (?, ?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE status = VALUES(status), marked_by = VALUES(marked_by), marked_at = VALUES(marked_at)',
                    [$sessionId, $playerId, $status, $userId, Db::now()]
                );
            }
        });
    }

    public static function setCancelled(int $id, bool $cancelled): void
    {
        Db::update('attendance_sessions', ['status' => $cancelled ? 'cancelled' : 'scheduled'], 'id = ?', [$id]);
    }

    public static function addManual(int $teamId, string $date, string $time, int $duration): bool
    {
        $stmt = Db::run(
            "INSERT IGNORE INTO attendance_sessions (team_id, session_date, start_time, duration_minutes, status, source, created_at) VALUES (?, ?, ?, ?, 'scheduled', 'manual', ?)",
            [$teamId, $date, $time, $duration, Db::now()]
        );
        return $stmt->rowCount() > 0;
    }

    /** Entrenaments publicats (contingut) d'un equip en una data. */
    public static function trainingsFor(int $teamId, string $date): array
    {
        return Db::all(
            "SELECT t.id, t.title, t.start_time FROM training_sessions t JOIN training_teams tt ON tt.training_id = t.id
             WHERE tt.team_id = ? AND t.session_date = ? AND t.status = 'published' ORDER BY t.start_time",
            [$teamId, $date]
        );
    }

    /** Faltes i retards d'un període, per equip i jugador (només fitxes no cancel·lades). */
    public static function incidents(string $from, string $to): array
    {
        return Db::all(
            "SELECT t.id AS team_id, t.name AS team_name, p.id AS player_id, p.full_name, s.session_date, r.status
             FROM attendance_records r
             JOIN attendance_sessions s ON s.id = r.session_id AND s.status = 'scheduled'
             JOIN players p ON p.id = r.player_id
             JOIN teams t ON t.id = s.team_id
             WHERE s.session_date BETWEEN ? AND ? AND r.status IN ('absent','late')
             ORDER BY t.name, p.full_name, s.session_date",
            [$from, $to]
        );
    }

    /** Entrenaments ja passats i sense cap assistència marcada. */
    public static function unmarked(string $from, string $to): array
    {
        return Db::all(
            "SELECT s.id, s.session_date, s.start_time, t.name AS team_name
             FROM attendance_sessions s JOIN teams t ON t.id = s.team_id
             WHERE s.session_date BETWEEN ? AND ? AND s.session_date <= CURDATE() AND s.status = 'scheduled'
               AND EXISTS (SELECT 1 FROM players p WHERE p.team_id = s.team_id AND p.is_active = 1)
               AND NOT EXISTS (SELECT 1 FROM attendance_records r WHERE r.session_id = s.id)
             ORDER BY s.session_date, s.start_time, t.name",
            [$from, $to]
        );
    }

    /** Estadística d'assistència d'un equip en un període, per jugador. */
    public static function teamStats(int $teamId, string $from, string $to): array
    {
        $sessions = (int) Db::value("SELECT COUNT(*) FROM attendance_sessions s WHERE s.team_id = ? AND s.status = 'scheduled' AND s.session_date BETWEEN ? AND ? AND EXISTS (SELECT 1 FROM attendance_records r WHERE r.session_id = s.id)", [$teamId, $from, $to]);
        $rows = Db::all(
            "SELECT p.id, p.full_name, p.is_active,
                    COALESCE(SUM(r.status = 'present'), 0) AS present,
                    COALESCE(SUM(r.status = 'late'), 0) AS late,
                    COALESCE(SUM(r.status = 'absent'), 0) AS absent
             FROM players p
             LEFT JOIN (SELECT r.player_id, r.status FROM attendance_records r JOIN attendance_sessions s ON s.id = r.session_id
                        WHERE s.team_id = ? AND s.status = 'scheduled' AND s.session_date BETWEEN ? AND ?) r ON r.player_id = p.id
             WHERE p.team_id = ? AND p.is_active = 1
             GROUP BY p.id, p.full_name, p.is_active ORDER BY p.full_name",
            [$teamId, $from, $to, $teamId]
        );
        return ['sessions' => $sessions, 'rows' => $rows];
    }

    public static function absencesThisWeek(): int
    {
        $mon = date('Y-m-d', strtotime('monday this week'));
        $sun = date('Y-m-d', strtotime('sunday this week'));
        return (int) Db::value(
            "SELECT COUNT(*) FROM attendance_records r JOIN attendance_sessions s ON s.id = r.session_id AND s.status = 'scheduled'
             WHERE r.status = 'absent' AND s.session_date BETWEEN ? AND ?",
            [$mon, $sun]
        );
    }
}
