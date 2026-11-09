<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Config;
use App\Helpers\Request;
use App\Helpers\Response;
use App\Helpers\Session;
use App\Models\Category;
use App\Models\Player;
use App\Models\Team;
use App\Services\AuditService;
use App\Services\PlayerImport;
use App\Validators\Validator;

/** Gestió de jugadors i importació des d'Excel/CSV (administrador). */
final class PlayerController extends Controller
{
    public function index(Request $r, array $p): void
    {
        $f = ['q' => $r->q('q'), 'team' => $r->q('team'), 'category' => $r->q('category'), 'status' => $r->q('status')];
        [$rows, $pg] = Player::search($f, (int) $r->q('page'));
        $this->render('players/index', ['title' => 'Jugadors', 'players' => $rows, 'pager' => $pg, 'filters' => $f, 'teams' => Team::all(), 'categories' => Category::all()]);
    }

    private function formData(?array $player): array
    {
        return ['title' => $player ? 'Edita el jugador' : 'Nou jugador', 'player' => $player, 'teams' => Team::all()];
    }

    public function create(Request $r, array $p): void
    {
        $this->render('players/form', $this->formData(null) + ['old' => ['is_active' => '1', 'team_id' => $r->q('team')]]);
    }

    public function edit(Request $r, array $p): void
    {
        $pl = Player::find($this->id($p)) ?? Response::notFound();
        $this->render('players/form', $this->formData($pl) + ['old' => ['birth_date' => $pl['birth_date'] ? fmt_date($pl['birth_date']) : ''] + $pl]);
    }

    public function store(Request $r, array $p): void
    {
        $this->save($r, null);
    }

    public function update(Request $r, array $p): void
    {
        $id = $this->id($p);
        Player::find($id) ?? Response::notFound();
        $this->save($r, $id);
    }

    private function save(Request $r, ?int $id): void
    {
        $name = $r->str('full_name');
        $birthRaw = $r->str('birth_date');
        $v = (new Validator($r->post))->required('full_name', 'Nom i cognoms')->maxLen('full_name', 'Nom i cognoms', 190);
        $teamId = (int) $r->str('team_id');
        if (Team::find($teamId) === null) {
            $v->error('team_id', 'Cal triar un equip.');
        }
        $birth = null;
        if ($birthRaw !== '') {
            $birth = PlayerImport::parseDate($birthRaw);
            if ($birth === null || !preg_match('#^\d{1,2}/\d{1,2}/\d{4}$#', $birthRaw)) {
                $v->error('birth_date', 'La data de neixament ha de tenir el format DD/MM/AAAA.');
            }
        }
        $old = ['full_name' => $name, 'birth_date' => $birthRaw, 'team_id' => (string) $teamId, 'is_active' => $r->input('is_active') ? '1' : '0'];
        if ($v->fails()) {
            $this->invalid('players/form', $this->formData($id ? Player::find($id) : null), $v->errors(), $old);
            return;
        }
        $id = Player::save($id, ['full_name' => $name, 'birth_date' => $birth, 'team_id' => $teamId, 'is_active' => $old['is_active'] === '1']);
        AuditService::log('player_saved', 'player', $id);
        $this->done('Jugador desat correctament.', '/jugadors');
    }

    public function delete(Request $r, array $p): void
    {
        $id = $this->id($p);
        Player::find($id) ?? Response::notFound();
        Player::delete($id);
        AuditService::log('player_deleted', 'player', $id);
        $this->done('Jugador eliminat (també el seu historial d\'assistència).', '/jugadors');
    }

    // ------------------------------------------------------------ importació

    public function importForm(Request $r, array $p): void
    {
        $this->render('players/import', ['title' => 'Importa jugadors', 'plan' => null, 'maxMb' => (int) (Config::get('upload.max_bytes', 10485760) / 1048576)]);
    }

    public function importPreview(Request $r, array $p): void
    {
        $file = $r->files['file'] ?? null;
        $max = (int) Config::get('upload.max_bytes', 10485760);
        $fail = fn (string $m) => $this->invalid('players/import', ['title' => 'Importa jugadors', 'plan' => null, 'maxMb' => (int) (Config::get('upload.max_bytes', 10485760) / 1048576)], ['file' => $m], []);
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) $file['tmp_name'])) {
            $fail('Cal triar un fitxer .xlsx o .csv.');
            return;
        }
        if ((int) $file['size'] > $max) {
            $fail('El fitxer supera la mida màxima permesa.');
            return;
        }
        try {
            $parsed = PlayerImport::parseFile((string) $file['tmp_name'], (string) $file['name']);
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            $fail($e->getMessage());
            return;
        } catch (\Throwable) {
            $fail('No s\'ha pogut llegir el fitxer. Comprova el format.');
            return;
        }
        if (!$parsed['rows']) {
            $this->invalid('players/import', ['title' => 'Importa jugadors', 'plan' => null, 'maxMb' => (int) (Config::get('upload.max_bytes', 10485760) / 1048576)], ['file' => 'No s\'ha trobat cap jugador vàlid al fitxer.'], []);
            return;
        }
        Session::set('player_import', $parsed['rows']);
        $this->render('players/import', [
            'title' => 'Importa jugadors', 'plan' => PlayerImport::plan($parsed['rows']), 'errors' => $parsed['errors'],
            'sample' => array_slice($parsed['rows'], 0, 30), 'total' => count($parsed['rows']), 'maxMb' => (int) (Config::get('upload.max_bytes', 10485760) / 1048576),
        ]);
    }

    public function importConfirm(Request $r, array $p): void
    {
        $rows = Session::get('player_import');
        if (!is_array($rows) || !$rows) {
            $this->done('La importació ha caducat. Torna a pujar el fitxer.', '/jugadors/importa', 'error');
        }
        Session::forget('player_import');
        $res = PlayerImport::apply($rows);
        AuditService::log('players_imported', 'player', null, 'ok', json_encode($res));
        $this->done(sprintf(
            'Importació acabada: %d jugadors nous, %d canvis d\'equip, %d ja existents i %d equips creats.',
            $res['new'], $res['updated'], $res['same'], $res['created_teams']
        ), '/jugadors');
    }

    public function template(Request $r, array $p): void
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="plantilla-jugadors.csv"');
        echo "\xEF\xBB\xBF" . "Nom i cognoms;Data de neixament;Equip\r\nJoan Pons Vila;15/03/2015;Benjamí A\r\nMarta Sala Roca;02/11/2013;Aleví B\r\n";
        exit;
    }
}
