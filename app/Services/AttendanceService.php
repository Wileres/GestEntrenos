<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Db;
use App\Models\Holiday;
use App\Models\Schedule;
use App\Models\Team;

/** Creació automàtica de les fitxes d'assistència a partir dels horaris i el calendari de festius. */
final class AttendanceService
{
    /** Dies per endavant que es preparen automàticament. */
    public const HORIZON_DAYS = 45;

    /**
     * Crea les fitxes que falten entre dues dates (mai en el passat ni en festius). Idempotent.
     *
     * @return int fitxes creades
     */
    public static function ensureSessions(string $from, string $to, ?int $teamId = null): int
    {
        $today = date('Y-m-d');
        $from = max($from, $today);
        if ($from > $to) {
            return 0;
        }
        $holidays = Holiday::map($from, $to);
        $created = 0;
        foreach (Team::active() as $team) {
            if ($teamId !== null && (int) $team['id'] !== $teamId) {
                continue;
            }
            $byDay = [];
            foreach (Schedule::effectiveForTeam($team) as $row) {
                $byDay[(int) $row['weekday']][] = $row;
            }
            if (!$byDay) {
                continue;
            }
            for ($d = new \DateTimeImmutable($from); $d->format('Y-m-d') <= $to; $d = $d->modify('+1 day')) {
                $date = $d->format('Y-m-d');
                if (isset($holidays[$date])) {
                    continue;
                }
                foreach ($byDay[(int) $d->format('N')] ?? [] as $row) {
                    $stmt = Db::run(
                        "INSERT IGNORE INTO attendance_sessions (team_id, session_date, start_time, duration_minutes, status, source, created_at) VALUES (?, ?, ?, ?, 'scheduled', 'auto', ?)",
                        [$team['id'], $date, $row['start_time'], $row['duration_minutes'], Db::now()]
                    );
                    $created += $stmt->rowCount();
                }
            }
        }
        return $created;
    }

    /** Un cop al dia (en la primera visita) prepara les fitxes dels pròxims dies. */
    public static function ensureUpcoming(): void
    {
        $today = date('Y-m-d');
        if (SettingsService::get('sessions_prepared_on') === $today) {
            return;
        }
        self::ensureSessions($today, date('Y-m-d', strtotime('+' . self::HORIZON_DAYS . ' days')));
        SettingsService::set('sessions_prepared_on', $today);
    }

    /**
     * Després de canviar un horari o un festiu: esborra les fitxes automàtiques futures sense cap marca i les torna a crear.
     * Les fitxes amb assistència ja marcada o creades a mà es conserven.
     */
    public static function rebuild(?int $teamId = null): void
    {
        $sql = "DELETE s FROM attendance_sessions s WHERE s.source = 'auto' AND s.session_date >= CURDATE()
                AND NOT EXISTS (SELECT 1 FROM attendance_records r WHERE r.session_id = s.id)";
        $params = [];
        if ($teamId !== null) {
            $sql .= ' AND s.team_id = ?';
            $params[] = $teamId;
        }
        Db::run($sql, $params);
        self::ensureSessions(date('Y-m-d'), date('Y-m-d', strtotime('+' . self::HORIZON_DAYS . ' days')), $teamId);
        SettingsService::set('sessions_prepared_on', date('Y-m-d'));
    }

    /** Si una categoria canvia d'horari, es reconstrueixen els equips que l'hereten. */
    public static function rebuildCategory(int $categoryId): void
    {
        foreach (Db::column('SELECT id FROM teams WHERE category_id = ?', [$categoryId]) as $tid) {
            self::rebuild((int) $tid);
        }
    }
}
