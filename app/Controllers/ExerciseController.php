<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Db;
use App\Helpers\Labels;
use App\Helpers\Request;
use App\Helpers\Response;
use App\Models\Team;
use App\Models\Exercise;
use App\Services\AuditService;
use App\Services\FileService;
use App\Services\NotificationService;
use App\Validators\Validator;

final class ExerciseController extends Controller
{
    public function index(Request $r, array $p): void
    {
        $filters = [
            'name' => $r->q('name'), 'team' => $r->q('team'), 'objective' => $r->q('objective'), 'material' => $r->q('material'),
            'difficulty' => $r->q('difficulty'), 'players' => $r->q('players'), 'status' => $r->q('status'), 'page' => $r->q('page'),
        ];
        [$rows, $pg] = Exercise::search($filters, (int) $filters['page'], is_admin());
        $this->render('exercises/index', ['title' => 'Exercicis', 'exercises' => $rows, 'pager' => $pg, 'filters' => $filters, 'teams' => Team::all()]);
    }

    public function show(Request $r, array $p): void
    {
        $e = Exercise::find($this->id($p)) ?? Response::notFound();
        if ((int) $e['is_active'] !== 1 && !is_admin()) {
            Response::notFound();
        }
        $this->render('exercises/show', [
            'title' => $e['name'], 'e' => $e, 'extra' => Exercise::teams((int) $e['id']), 'files' => Exercise::files((int) $e['id']),
        ]);
    }

    private function formData(?array $e): array
    {
        return [
            'title' => $e ? 'Edita l\'exercici' : 'Nou exercici', 'exercise' => $e, 'teams' => Team::all(),
            'files' => $e ? Exercise::files((int) $e['id']) : [],
        ];
    }

    public function create(Request $r, array $p): void
    {
        $this->render('exercises/form', $this->formData(null) + ['old' => ['is_active' => '1']]);
    }

    public function edit(Request $r, array $p): void
    {
        $id = $this->id($p);
        $e = Exercise::find($id) ?? Response::notFound();
        $this->render('exercises/form', $this->formData($e) + ['old' => $e + ['team_ids' => Exercise::teamIds($id)]]);
    }

    public function store(Request $r, array $p): void
    {
        $this->save($r, null);
    }

    public function update(Request $r, array $p): void
    {
        $id = $this->id($p);
        Exercise::find($id) ?? Response::notFound();
        $this->save($r, $id);
    }

    private function save(Request $r, ?int $id): void
    {
        $catIds = array_column(Team::all(), 'id');
        $d = [];
        foreach (['name', 'description', 'objective', 'duration_minutes', 'players', 'material', 'difficulty'] as $k) {
            $d[$k] = $r->str($k);
        }
        $d['is_active'] = $r->input('is_active') ? '1' : '0';
        $v = (new Validator($d))->required('name', 'Nom')->maxLen('name', 'Nom', 150)
            ->required('description', 'Explicació')->maxLen('description', 'Explicació', 20000)
            ->maxLen('objective', 'Objectiu', 255)->maxLen('material', 'Material', 255)
            ->intRange('duration_minutes', 'Durada', 1, 600)->intRange('players', 'Nombre de jugadors', 1, 200)
            ->inList('difficulty', 'Dificultat', array_keys(Labels::DIFFICULTY), false);
        $extra = $this->onlyKnown($r->intList('team_ids'), $catIds);
        if (!$extra) {
            $v->error('team_ids', 'Cal seleccionar almenys un equip.');
        }

        $valid = [];
        foreach (FileService::normalize($r->files['files'] ?? null) as $f) {
            [$checked, $err] = FileService::validate($f);
            if ($err !== null) {
                $v->error('files', $err);
                break;
            }
            $valid[] = [$f, $checked];
        }
        if ($v->fails()) {
            $e = $id ? Exercise::find($id) : null;
            $this->invalid('exercises/form', $this->formData($e), $v->errors(), $d + ['team_ids' => $extra]);
            return;
        }

        $d['team_ids'] = $extra;
        $d['is_active'] = $d['is_active'] === '1';
        $isNew = $id === null;
        $wasActive = $isNew ? false : (int) (Exercise::find($id)['is_active'] ?? 0) === 1;
        $id = Exercise::save($id, $d, (int) $this->user()['id']);
        foreach ($valid as [$f, $checked]) {
            Exercise::addFile($id, FileService::store($f, $checked, 'exercises'));
        }
        AuditService::log($isNew ? 'exercise_created' : 'exercise_updated', 'exercise', $id);
        $msg = 'Exercici desat correctament.';
        if ($d['is_active'] && !$wasActive) {
            $res = NotificationService::notifyNewExercise($id);
            $msg .= " S'ha avisat els entrenadors dels equips afectats (correus enviats: {$res['sent']}" . ($res['failed'] ? ", errors: {$res['failed']}" : '') . ').';
        }
        $this->done($msg, '/exercicis/' . $id);
    }

    public function deleteFile(Request $r, array $p): void
    {
        $file = Exercise::file($this->id($p)) ?? Response::notFound();
        Db::run('DELETE FROM exercise_files WHERE id = ?', [$file['id']]);
        FileService::delete('exercises', $file['stored_name']);
        AuditService::log('exercise_file_deleted', 'exercise', (int) $file['exercise_id']);
        $this->done('Fitxer eliminat.', '/exercicis/' . $file['exercise_id'] . '/editar');
    }

    public function delete(Request $r, array $p): void
    {
        $id = $this->id($p);
        Exercise::find($id) ?? Response::notFound();
        if (Exercise::inTraining($id)) {
            $this->done('No es pot eliminar: l\'exercici forma part d\'algun entrenament. Pots desactivar-lo.', '/exercicis/' . $id, 'error');
        }
        $stored = Db::column('SELECT stored_name FROM exercise_files WHERE exercise_id = ?', [$id]);
        Exercise::delete($id);
        foreach ($stored as $s) {
            FileService::delete('exercises', $s);
        }
        AuditService::log('exercise_deleted', 'exercise', $id);
        $this->done('Exercici eliminat.', '/exercicis');
    }
}
