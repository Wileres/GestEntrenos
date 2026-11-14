<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Db;
use App\Models\User;

/**
 * Importació d'entrenadors des d'un llistat (CSV, XLSX o text enganxat) amb «Nom i cognoms» i correu electrònic.
 * Els entrenadors queden pendents fins que un administrador els assigna un equip.
 */
final class TrainerImport
{
    public const MAX_ROWS = 1000;
    private const EMAIL_RE = '/[^\s;,<>"\']+@[^\s;,<>"\']+\.[^\s;,<>"\']+/u';

    /**
     * @return array{rows:array<int,array{line:int,first_name:string,last_name:string,email:string}>,errors:array<int,array{line:int,message:string}>}
     */
    public static function parseFile(string $path, string $originalName): array
    {
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $table = match ($ext) {
            'xlsx' => PlayerImport::readXlsx($path),
            'csv', 'txt' => PlayerImport::readCsv($path),
            default => throw new \InvalidArgumentException('Format no admès. Puja un fitxer .xlsx, .csv o .txt.'),
        };
        return self::interpret($table);
    }

    public static function parseText(string $text): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'jspm-imp');
        file_put_contents($tmp, $text);
        try {
            return self::interpret(PlayerImport::readCsv($tmp));
        } finally {
            @unlink($tmp);
        }
    }

    /** @param array<int,array<int,string>> $table */
    public static function interpret(array $table): array
    {
        $table = array_filter($table, fn ($r) => array_filter($r, fn ($c) => trim((string) $c) !== '') !== []);
        if (!$table) {
            throw new \InvalidArgumentException('No s\'ha trobat cap fila.');
        }
        // Capçalera: la primera fila si cap cel·la conté un correu.
        $firstKey = array_key_first($table);
        if (!preg_match(self::EMAIL_RE, implode(' ', $table[$firstKey]))) {
            unset($table[$firstKey]);
        }
        if (count($table) > self::MAX_ROWS) {
            throw new \InvalidArgumentException('Hi ha massa files (màxim ' . self::MAX_ROWS . ' per importació).');
        }
        $rows = [];
        $errors = [];
        $seen = [];
        foreach ($table as $idx => $cells) {
            $line = $idx + 1;
            $email = '';
            $names = [];
            foreach ($cells as $cell) {
                $cell = trim((string) preg_replace('/\s+/u', ' ', (string) $cell));
                if ($cell === '') {
                    continue;
                }
                if ($email === '' && preg_match(self::EMAIL_RE, $cell, $m)) {
                    $email = mb_strtolower($m[0]);
                    $cell = trim((string) preg_replace('/[<>()]/', ' ', str_replace($m[0], '', $cell)));
                    $cell = trim((string) preg_replace('/\s+/u', ' ', $cell));
                    if ($cell === '') {
                        continue;
                    }
                }
                $names[] = $cell;
            }
            if ($email === '') {
                $errors[] = ['line' => $line, 'message' => 'No s\'ha trobat cap correu electrònic.'];
                continue;
            }
            if (mb_strlen($email) > 190 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = ['line' => $line, 'message' => "Correu no vàlid: «{$email}»."];
                continue;
            }
            if (!$names) {
                $errors[] = ['line' => $line, 'message' => "Falta el nom de {$email}."];
                continue;
            }
            if (count($names) >= 2) {
                $first = $names[0];
                $last = implode(' ', array_slice($names, 1));
            } else {
                $parts = explode(' ', $names[0], 2);
                [$first, $last] = [$parts[0], $parts[1] ?? ''];
            }
            if (mb_strlen($first) > 80 || mb_strlen($last) > 120) {
                $errors[] = ['line' => $line, 'message' => "El nom de {$email} és massa llarg."];
                continue;
            }
            if (isset($seen[$email])) {
                $errors[] = ['line' => $line, 'message' => "{$email} està repetit al llistat (línia {$seen[$email]})."];
                continue;
            }
            $seen[$email] = $line;
            $rows[] = ['line' => $line, 'first_name' => $first, 'last_name' => $last, 'email' => $email];
        }
        return ['rows' => $rows, 'errors' => $errors];
    }

    /**
     * Separa les files noves de les que ja existeixen (mateix correu).
     *
     * @return array{new:array,exists:array}
     */
    public static function plan(array $rows): array
    {
        $emails = array_column($rows, 'email');
        $taken = $emails ? array_flip(Db::column('SELECT email FROM users WHERE email IN (' . Db::in($emails) . ')', $emails)) : [];
        $plan = ['new' => [], 'exists' => []];
        foreach ($rows as $r) {
            $plan[isset($taken[$r['email']]) ? 'exists' : 'new'][] = $r;
        }
        return $plan;
    }

    /** Crea els entrenadors nous (pendents d'equip, sense contrasenya ni correu). @return array{created:int,skipped:int} */
    public static function apply(array $rows): array
    {
        $created = 0;
        $skipped = 0;
        foreach (self::plan($rows)['new'] as $r) {
            try {
                User::importPending($r['first_name'], $r['last_name'], $r['email']);
                $created++;
            } catch (\PDOException) {
                $skipped++; // correu duplicat per una importació simultània
            }
        }
        return ['created' => $created, 'skipped' => $skipped + count(self::plan($rows)['exists'])];
    }
}
