<?php

declare(strict_types=1);

namespace App\Helpers;

final class Labels
{
    public const TRAINING_STATUS = [
        'draft' => 'Esborrany',
        'published' => 'Publicat',
        'cancelled' => 'Cancel·lat',
        'finished' => 'Finalitzat',
    ];

    public const DIFFICULTY = [
        'easy' => 'Fàcil',
        'medium' => 'Mitjana',
        'hard' => 'Difícil',
    ];

    public const ENCRYPTION = [
        'tls' => 'STARTTLS (TLS)',
        'ssl' => 'SSL/TLS implícit',
        'none' => 'Sense xifrat',
    ];

    public static function status(string $s): string
    {
        return self::TRAINING_STATUS[$s] ?? $s;
    }

    public static function difficulty(?string $d): string
    {
        return $d === null ? '' : (self::DIFFICULTY[$d] ?? $d);
    }

    /** Temporada actual en format 2026-2027 (la temporada comença al setembre). */
    public static function currentSeason(): string
    {
        $y = (int) date('Y');
        $start = (int) date('n') >= 8 ? $y : $y - 1;
        return $start . '-' . ($start + 1);
    }
}
