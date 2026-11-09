<?php

declare(strict_types=1);

namespace App\Models;

use App\Helpers\Db;

/** Horaris d'entrenament setmanals (un per dia de la setmana) d'una categoria o d'un equip. */
final class Schedule
{
    public const WEEKDAYS = [1 => 'Dilluns', 2 => 'Dimarts', 3 => 'Dimecres', 4 => 'Dijous', 5 => 'Divendres', 6 => 'Dissabte', 7 => 'Diumenge'];
    private const TABLES = ['team' => ['team_schedules', 'team_id'], 'category' => ['category_schedules', 'category_id']];

    /** @return array<int,array{weekday:int,start_time:string,duration_minutes:int}> */
    public static function of(string $owner, int $id): array
    {
        [$table, $col] = self::TABLES[$owner];
        return Db::all("SELECT weekday, start_time, duration_minutes FROM $table WHERE $col = ? ORDER BY weekday, start_time", [$id]);
    }

    /** Horari efectiu d'un equip: el propi si en té; si no, el de la seva categoria. */
    public static function effectiveForTeam(array $team): array
    {
        $own = self::of('team', (int) $team['id']);
        if ($own) {
            return $own;
        }
        return $team['category_id'] ? self::of('category', (int) $team['category_id']) : [];
    }

    public static function replace(string $owner, int $id, array $rows): void
    {
        [$table, $col] = self::TABLES[$owner];
        Db::transaction(function () use ($table, $col, $id, $rows) {
            Db::run("DELETE FROM $table WHERE $col = ?", [$id]);
            foreach ($rows as $r) {
                Db::insert($table, [$col => $id, 'weekday' => $r['weekday'], 'start_time' => $r['start_time'], 'duration_minutes' => $r['duration_minutes']]);
            }
        });
    }

    /**
     * Llegeix els camps del formulari sch[dia][on|time|dur].
     *
     * @return array{rows:array,errors:array<string,string>,form:array}
     */
    public static function parseForm(mixed $input): array
    {
        $rows = [];
        $errors = [];
        $form = [];
        $input = is_array($input) ? $input : [];
        foreach (self::WEEKDAYS as $d => $label) {
            $in = is_array($input[$d] ?? null) ? $input[$d] : [];
            $on = !empty($in['on']);
            $time = is_string($in['time'] ?? null) ? trim($in['time']) : '';
            $dur = is_string($in['dur'] ?? null) ? trim($in['dur']) : '';
            $form[$d] = ['on' => $on, 'time' => $time, 'dur' => $dur];
            if (!$on) {
                continue;
            }
            if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time)) {
                $errors["sch_$d"] = "$label: l'hora no és vàlida.";
                continue;
            }
            if (!ctype_digit($dur) || (int) $dur < 15 || (int) $dur > 300) {
                $errors["sch_$d"] = "$label: la durada ha d'estar entre 15 i 300 minuts.";
                continue;
            }
            $rows[] = ['weekday' => $d, 'start_time' => $time . ':00', 'duration_minutes' => (int) $dur];
        }
        return ['rows' => $rows, 'errors' => $errors, 'form' => $form];
    }

    /** Converteix files de la BD al format del formulari. */
    public static function toForm(array $rows): array
    {
        $form = [];
        foreach ($rows as $r) {
            $form[(int) $r['weekday']] = ['on' => true, 'time' => substr((string) $r['start_time'], 0, 5), 'dur' => (string) $r['duration_minutes']];
        }
        return $form;
    }

    /** Resum curt: "Dl 18:00, Dc 18:00". */
    public static function summary(array $rows): string
    {
        $short = [1 => 'Dl', 2 => 'Dt', 3 => 'Dc', 4 => 'Dj', 5 => 'Dv', 6 => 'Ds', 7 => 'Dg'];
        return implode(', ', array_map(fn ($r) => $short[(int) $r['weekday']] . ' ' . substr((string) $r['start_time'], 0, 5), $rows));
    }
}
