<?php

declare(strict_types=1);

namespace App\Models;

use App\Helpers\Db;

final class Holiday
{
    public static function between(string $from, string $to): array
    {
        return Db::all('SELECT id, holiday_date, name FROM holidays WHERE holiday_date BETWEEN ? AND ? ORDER BY holiday_date', [$from, $to]);
    }

    /** @return array<string,string> data => nom */
    public static function map(string $from, string $to): array
    {
        $out = [];
        foreach (self::between($from, $to) as $h) {
            $out[$h['holiday_date']] = $h['name'];
        }
        return $out;
    }

    public static function upcoming(int $limit = 60): array
    {
        return Db::all('SELECT id, holiday_date, name FROM holidays WHERE holiday_date >= CURDATE() ORDER BY holiday_date LIMIT ' . $limit);
    }

    public static function add(string $date, string $name): void
    {
        Db::run('INSERT INTO holidays (holiday_date, name) VALUES (?, ?) ON DUPLICATE KEY UPDATE name = VALUES(name)', [$date, $name]);
    }

    public static function find(int $id): ?array
    {
        return Db::one('SELECT id, holiday_date, name FROM holidays WHERE id = ?', [$id]);
    }

    public static function delete(int $id): void
    {
        Db::run('DELETE FROM holidays WHERE id = ?', [$id]);
    }
}
