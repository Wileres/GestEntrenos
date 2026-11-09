<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Db;
use App\Models\Player;
use App\Models\Team;

/**
 * Importació de jugadors des d'un full de càlcul (.xlsx) o un CSV.
 * Columnes: «Nom i cognoms», «Data de neixament» (DD/MM/AAAA) i «Equip». Sense llibreries externes (ZipArchive + XML).
 */
final class PlayerImport
{
    public const MAX_ROWS = 5000;
    private const MAX_XML_BYTES = 30 * 1024 * 1024;

    /**
     * @return array{rows:array<int,array{line:int,name:string,birth:?string,team:string}>,errors:array<int,array{line:int,message:string}>}
     */
    public static function parseFile(string $path, string $originalName): array
    {
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $table = match ($ext) {
            'xlsx' => self::readXlsx($path),
            'csv', 'txt' => self::readCsv($path),
            default => throw new \InvalidArgumentException('Format no admès. Puja un fitxer .xlsx o .csv.'),
        };
        return self::interpret($table);
    }

    /** @param array<int,array<int,string>> $table */
    public static function interpret(array $table): array
    {
        $table = array_filter($table, fn ($r) => array_filter($r, fn ($c) => trim((string) $c) !== '') !== []);
        if (!$table) {
            throw new \InvalidArgumentException('El fitxer no conté cap fila.');
        }
        $col = ['name' => 0, 'birth' => 1, 'team' => 2];
        $start = 0;
        $found = [];
        $firstKey = array_key_first($table);
        foreach ($table[$firstKey] as $i => $h) {
            $n = Team::normalize((string) $h);
            if (str_contains($n, 'naix') || str_contains($n, 'naci') || str_contains($n, 'birth')) {
                $found['birth'] = $i;
            } elseif ($n === 'equip' || $n === 'equipo' || $n === 'team') {
                $found['team'] = $i;
            } elseif (str_starts_with($n, 'nom') || str_starts_with($n, 'nombre') || str_contains($n, 'cognoms') || $n === 'name' || str_contains($n, 'jugador')) {
                $found['name'] ??= $i;
            }
        }
        if (count($found) >= 2) {
            $col = $found + $col;
            $start = 1;
        }
        if ($start === 1) {
            unset($table[$firstKey]);
        }
        if (count($table) > self::MAX_ROWS) {
            throw new \InvalidArgumentException('El fitxer té massa files (màxim ' . self::MAX_ROWS . ').');
        }
        $rows = [];
        $errors = [];
        $seen = [];
        foreach ($table as $idx => $r) {
            $line = $idx + 1;
            $name = trim((string) preg_replace('/\s+/u', ' ', (string) ($r[$col['name']] ?? '')));
            $birthRaw = trim((string) ($r[$col['birth']] ?? ''));
            $team = trim((string) preg_replace('/\s+/u', ' ', (string) ($r[$col['team']] ?? '')));
            if ($name === '') {
                $errors[] = ['line' => $line, 'message' => 'Falta el nom i cognoms.'];
                continue;
            }
            if (mb_strlen($name) > 190 || mb_strlen($team) > 100) {
                $errors[] = ['line' => $line, 'message' => 'El nom o l\'equip és massa llarg.'];
                continue;
            }
            if ($team === '') {
                $errors[] = ['line' => $line, 'message' => "Falta l'equip de «{$name}»."];
                continue;
            }
            $birth = null;
            if ($birthRaw !== '') {
                $birth = self::parseDate($birthRaw);
                if ($birth === null) {
                    $errors[] = ['line' => $line, 'message' => "Data de neixament no vàlida per a «{$name}»: «{$birthRaw}» (cal DD/MM/AAAA)."];
                    continue;
                }
            }
            $key = Team::normalize($name) . '|' . $birth;
            if (isset($seen[$key])) {
                $errors[] = ['line' => $line, 'message' => "«{$name}» està repetit al fitxer (línia {$seen[$key]})."];
                continue;
            }
            $seen[$key] = $line;
            $rows[] = ['line' => $line, 'name' => $name, 'birth' => $birth, 'team' => $team];
        }
        return ['rows' => $rows, 'errors' => $errors];
    }

    /** Accepta DD/MM/AAAA (també amb - o .), AAAA-MM-DD i el número de sèrie d'Excel. Retorna AAAA-MM-DD o null. */
    public static function parseDate(string $v): ?string
    {
        $v = trim($v);
        if (preg_match('#^(\d{1,2})[/.\-](\d{1,2})[/.\-](\d{4})$#', $v, $m)) {
            [$d, $mo, $y] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } elseif (preg_match('#^(\d{4})-(\d{1,2})-(\d{1,2})(?:[ T].*)?$#', $v, $m)) {
            [$y, $mo, $d] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } elseif (preg_match('#^\d{4,6}(\.\d+)?$#', $v)) {
            $serial = (int) floor((float) $v);
            if ($serial < 1 || $serial > 80000) {
                return null;
            }
            return (new \DateTimeImmutable('1899-12-30'))->modify("+{$serial} days")->format('Y-m-d');
        } else {
            return null;
        }
        if (!checkdate($mo, $d, $y) || $y < 1950 || $y > (int) date('Y')) {
            return null;
        }
        return sprintf('%04d-%02d-%02d', $y, $mo, $d);
    }

    // ---------------------------------------------------------------- lectors

    /** @return array<int,array<int,string>> */
    public static function readCsv(string $path): array
    {
        $raw = (string) file_get_contents($path);
        if (str_starts_with($raw, "\xEF\xBB\xBF")) {
            $raw = substr($raw, 3);
        }
        if (!mb_check_encoding($raw, 'UTF-8')) {
            $raw = mb_convert_encoding($raw, 'UTF-8', 'Windows-1252');
        }
        $firstLine = strtok($raw, "\r\n") ?: '';
        $delim = ';';
        $best = -1;
        foreach ([';', ',', "\t"] as $d) {
            $c = substr_count($firstLine, $d);
            if ($c > $best) {
                [$best, $delim] = [$c, $d];
            }
        }
        $table = [];
        $h = fopen('php://temp', 'r+');
        fwrite($h, $raw);
        rewind($h);
        while (($row = fgetcsv($h, 0, $delim, '"', '')) !== false) {
            $table[] = array_map(fn ($c) => (string) $c, $row);
            if (count($table) > self::MAX_ROWS + 5) {
                break;
            }
        }
        fclose($h);
        return $table;
    }

    /** @return array<int,array<int,string>> */
    public static function readXlsx(string $path): array
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('El servidor no té l\'extensió zip de PHP; desa el full com a CSV i torna-ho a provar.');
        }
        $zip = new \ZipArchive();
        if ($zip->open($path, \ZipArchive::RDONLY) !== true) {
            throw new \InvalidArgumentException('No es pot llegir el fitxer Excel. Comprova que sigui un .xlsx vàlid.');
        }
        try {
            $read = function (string $name) use ($zip): ?string {
                $st = $zip->statName($name);
                if ($st === false) {
                    return null;
                }
                if ($st['size'] > self::MAX_XML_BYTES) {
                    throw new \InvalidArgumentException('El fitxer Excel és massa gran.');
                }
                $c = $zip->getFromName($name);
                return $c === false ? null : $c;
            };
            $sheetPath = self::firstSheetPath($read) ?? 'xl/worksheets/sheet1.xml';
            $sheetXml = $read($sheetPath) ?? throw new \InvalidArgumentException('No s\'ha trobat cap full dins el fitxer Excel.');
            $shared = [];
            if (($ss = $read('xl/sharedStrings.xml')) !== null) {
                $x = simplexml_load_string($ss, \SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
                foreach ($x ? $x->si : [] as $si) {
                    $shared[] = self::richText($si);
                }
            }
            $sheet = simplexml_load_string($sheetXml, \SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
            if (!$sheet) {
                throw new \InvalidArgumentException('El full de càlcul no es pot interpretar.');
            }
        } finally {
            $zip->close();
        }
        $table = [];
        $n = 0;
        foreach ($sheet->sheetData->row ?? [] as $row) {
            $r = (int) $row['r'] ?: $n + 1;
            $n = $r;
            $cells = [];
            $next = 0;
            foreach ($row->c as $c) {
                $idx = isset($c['r']) ? self::columnIndex((string) $c['r']) : $next;
                $next = $idx + 1;
                $t = (string) $c['t'];
                $val = match ($t) {
                    's' => $shared[(int) $c->v] ?? '',
                    'inlineStr' => self::richText($c->is),
                    default => (string) $c->v,
                };
                $cells[$idx] = $val;
            }
            if ($cells) {
                $max = max(array_keys($cells));
                $line = [];
                for ($i = 0; $i <= $max; $i++) {
                    $line[$i] = $cells[$i] ?? '';
                }
                $table[$r - 1] = $line;
            }
            if ($r > self::MAX_ROWS + 5) {
                break;
            }
        }
        // Manté els números de línia reals de l'Excel; les files buides es perden però interpret() les ignora.
        return self::withLineNumbers($table);
    }

    /** Converteix en llista seqüencial però conserva la numeració original mitjançant files buides. */
    private static function withLineNumbers(array $table): array
    {
        if (!$table) {
            return [];
        }
        $out = [];
        for ($i = 0, $max = max(array_keys($table)); $i <= $max; $i++) {
            $out[$i] = $table[$i] ?? [];
        }
        return $out;
    }

    private static function firstSheetPath(callable $read): ?string
    {
        $wb = $read('xl/workbook.xml');
        $rels = $read('xl/_rels/workbook.xml.rels');
        if ($wb === null || $rels === null) {
            return null;
        }
        $w = simplexml_load_string($wb, \SimpleXMLElement::class, LIBXML_NONET);
        $r = simplexml_load_string($rels, \SimpleXMLElement::class, LIBXML_NONET);
        if (!$w || !$r) {
            return null;
        }
        $first = $w->sheets->sheet[0] ?? null;
        if ($first === null) {
            return null;
        }
        $rid = (string) $first->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
        foreach ($r->Relationship as $rel) {
            if ((string) $rel['Id'] === $rid) {
                $target = (string) $rel['Target'];
                return str_starts_with($target, '/') ? ltrim($target, '/') : 'xl/' . $target;
            }
        }
        return null;
    }

    private static function richText(?\SimpleXMLElement $el): string
    {
        if ($el === null) {
            return '';
        }
        if (isset($el->t)) {
            return (string) $el->t;
        }
        $s = '';
        foreach ($el->r ?? [] as $run) {
            $s .= (string) $run->t;
        }
        return $s;
    }

    private static function columnIndex(string $ref): int
    {
        preg_match('/^[A-Z]+/', strtoupper($ref), $m);
        $n = 0;
        foreach (str_split($m[0] ?? 'A') as $ch) {
            $n = $n * 26 + (ord($ch) - 64);
        }
        return $n - 1;
    }

    // ---------------------------------------------------------------- previsualització i aplicació

    /** Resum del que passaria en confirmar la importació (sense escriure res). */
    public static function plan(array $rows): array
    {
        $teams = [];
        $new = $updated = $same = 0;
        foreach ($rows as $r) {
            $key = Team::normalize($r['team']);
            if (!isset($teams[$key])) {
                $existing = Db::one('SELECT t.id, c.name AS category FROM teams t LEFT JOIN categories c ON c.id = t.category_id WHERE t.name = ?', [$r['team']]);
                $catId = $existing ? null : Team::guessCategory($r['team']);
                $teams[$key] = [
                    'name' => $r['team'], 'exists' => (bool) $existing, 'players' => 0,
                    'category' => $existing ? $existing['category'] : ($catId ? Db::value('SELECT name FROM categories WHERE id = ?', [$catId]) : null),
                ];
            }
            $teams[$key]['players']++;
            $p = Player::findByNameAndBirth($r['name'], $r['birth']);
            if (!$p) {
                $new++;
            } else {
                $t = Db::value('SELECT name FROM teams WHERE id = ?', [$p['team_id']]);
                (Team::normalize((string) $t) === Team::normalize($r['team'])) ? $same++ : $updated++;
            }
        }
        return ['teams' => array_values($teams), 'new' => $new, 'updated' => $updated, 'same' => $same];
    }

    /** @return array{created_teams:int,new:int,updated:int,same:int} */
    public static function apply(array $rows): array
    {
        return Db::transaction(function () use ($rows) {
            $res = ['created_teams' => 0, 'new' => 0, 'updated' => 0, 'same' => 0];
            $teamIds = [];
            foreach ($rows as $r) {
                $key = Team::normalize($r['team']);
                if (!isset($teamIds[$key])) {
                    $t = Team::findOrCreate($r['team']);
                    $teamIds[$key] = $t['id'];
                    $res['created_teams'] += $t['created'] ? 1 : 0;
                }
                $tid = $teamIds[$key];
                $p = Player::findByNameAndBirth($r['name'], $r['birth']);
                if (!$p) {
                    Player::save(null, ['full_name' => $r['name'], 'birth_date' => $r['birth'], 'team_id' => $tid, 'is_active' => true]);
                    $res['new']++;
                } elseif ((int) $p['team_id'] !== $tid) {
                    Db::update('players', ['team_id' => $tid, 'is_active' => 1, 'updated_at' => Db::now()], 'id = ?', [$p['id']]);
                    $res['updated']++;
                } else {
                    $res['same']++;
                }
            }
            return $res;
        });
    }
}
