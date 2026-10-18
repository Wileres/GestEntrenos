<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Labels;
use App\Helpers\Request;
use App\Helpers\Response;
use App\Models\Category;
use App\Models\Exercise;
use App\Models\Training;
use App\Services\AuditService;
use App\Services\FileService;
use App\Services\NotificationService;
use App\Validators\Validator;

final class TrainingController extends Controller
{
    private const TRANSITIONS = [
        'draft' => ['published', 'cancelled'],
        'published' => ['draft', 'cancelled', 'finished'],
        'cancelled' => ['draft'],
        'finished' => [],
    ];

    public function index(Request $r, array $p): void
    {
        $u = $this->user();
        $f = ['when' => $r->q('when') ?: 'upcoming', 'status' => $r->q('status'), 'category' => $r->q('category'), 'page' => $r->q('page')];
        [$rows, $pg] = Training::search($u, $f, (int) $f['page']);
        $this->render('trainings/index', ['title' => 'Entrenaments', 'trainings' => $rows, 'pager' => $pg, 'filters' => $f, 'categories' => Category::all()]);
    }

    public function show(Request $r, array $p): void
    {
        $t = Training::findVisible($this->id($p), $this->user()) ?? Response::notFound();
        $id = (int) $t['id'];
        $this->render('trainings/show', [
            'title' => $t['title'], 't' => $t, 'categories' => Training::categories($id),
            'exercises' => Training::exercises($id), 'files' => Training::files($id),
            'transitions' => self::TRANSITIONS[$t['status']],
        ]);
    }

    private function formData(?array $t, array $rows): array
    {
        return [
            'title' => $t ? 'Edita l\'entrenament' : 'Nou entrenament', 'training' => $t,
            'categories' => Category::all(), 'catalog' => Exercise::catalog(),
            'rows' => $rows, 'files' => $t ? Training::files((int) $t['id']) : [],
        ];
    }

    public function create(Request $r, array $p): void
    {
        $this->render('trainings/form', $this->formData(null, []) + ['old' => [
            'season' => Labels::currentSeason(), 'session_date' => date('Y-m-d'), 'start_time' => '18:00', 'duration_minutes' => '90',
        ]]);
    }

    public function edit(Request $r, array $p): void
    {
        $id = $this->id($p);
        $t = Training::find($id) ?? Response::notFound();
        $rows = array_map(fn ($e) => ['exercise_id' => (int) $e['exercise_id'], 'duration' => $e['duration_minutes'], 'notes' => (string) $e['notes']], Training::exercises($id));
        $this->render('trainings/form', $this->formData($t, $rows) + ['old' => $t + [
            'category_ids' => array_column(Training::categories($id), 'id'),
        ]]);
    }

    public function store(Request $r, array $p): void
    {
        $this->save($r, null);
    }

    public function update(Request $r, array $p): void
    {
        $id = $this->id($p);
        Training::find($id) ?? Response::notFound();
        $this->save($r, $id);
    }

    /** Llegeix les files d'exercicis enviades i aplica l'acció d'ordenació sense JavaScript. */
    private function readRows(Request $r, string $action, array $catalogIds): array
    {
        $rows = [];
        foreach ((array) ($r->post['ex'] ?? []) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = (int) ($row['exercise_id'] ?? 0);
            if ($id < 1) {
                continue;
            }
            $dur = trim((string) ($row['duration'] ?? ''));
            $rows[] = ['exercise_id' => $id, 'duration' => $dur, 'notes' => trim((string) ($row['notes'] ?? ''))];
        }
        if ($action === 'add') {
            $add = (int) $r->input('add_exercise', 0);
            if (in_array($add, $catalogIds, true)) {
                $rows[] = ['exercise_id' => $add, 'duration' => '', 'notes' => ''];
            }
        } elseif (preg_match('/^(up|down|remove):(\d+)$/', $action, $m) && isset($rows[(int) $m[2]])) {
            $i = (int) $m[2];
            if ($m[1] === 'remove') {
                array_splice($rows, $i, 1);
            } else {
                $j = $m[1] === 'up' ? $i - 1 : $i + 1;
                if (isset($rows[$j])) {
                    [$rows[$i], $rows[$j]] = [$rows[$j], $rows[$i]];
                }
            }
        }
        return $rows;
    }

    private function save(Request $r, ?int $id): void
    {
        $existing = $id ? Training::find($id) : null;
        $action = $r->str('action') ?: 'save';
        $catalog = Exercise::catalog();
        $allowedEx = array_map('intval', array_column($catalog, 'id'));
        if ($id) {
            $allowedEx = array_merge($allowedEx, array_map('intval', array_column(Training::exercises($id), 'exercise_id')));
        }
        $rows = $this->readRows($r, $action, $allowedEx);

        $d = [];
        foreach (['title', 'description', 'session_date', 'start_time', 'duration_minutes', 'season', 'notes'] as $k) {
            $d[$k] = $r->str($k);
        }
        $catIds = $this->onlyKnown($r->intList('category_ids'), array_column(Category::all(), 'id'));
        $old = $d + ['category_ids' => $catIds, 'notify_changes' => $r->input('notify_changes') ? '1' : ''];

        if ($action !== 'save') {
            $this->render('trainings/form', $this->formData($existing, $rows) + ['old' => $old]);
            return;
        }

        $v = (new Validator($d))->required('title', 'Títol')->maxLen('title', 'Títol', 150)->maxLen('description', 'Descripció', 20000)
            ->date('session_date', 'Data')->time('start_time', 'Hora d\'inici')->intRange('duration_minutes', 'Durada', 1, 600, true)
            ->season('season', 'Temporada')->maxLen('notes', 'Observacions', 20000);
        if (!$catIds) {
            $v->error('category_ids', 'Cal seleccionar almenys un equip.');
        }
        $clean = [];
        foreach ($rows as $i => $row) {
            if (!in_array($row['exercise_id'], $allowedEx, true)) {
                $v->error('exercises', 'Un dels exercicis seleccionats no és vàlid.');
                break;
            }
            if ($row['duration'] !== '' && (!ctype_digit($row['duration']) || (int) $row['duration'] < 1 || (int) $row['duration'] > 600)) {
                $v->error('exercises', 'La durada dels exercicis ha de ser un número entre 1 i 600.');
                break;
            }
            if (mb_strlen($row['notes']) > 2000) {
                $v->error('exercises', 'Les observacions d\'un exercici són massa llargues.');
                break;
            }
            $clean[] = ['exercise_id' => $row['exercise_id'], 'duration' => $row['duration'] === '' ? null : (int) $row['duration'], 'notes' => $row['notes']];
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
            $this->invalid('trainings/form', $this->formData($existing, $rows), $v->errors(), $old);
            return;
        }

        $isNew = $id === null;
        $id = Training::save($id, $d, ['categories' => $catIds, 'exercises' => $clean], (int) $this->user()['id']);
        foreach ($valid as [$f, $checked]) {
            Training::addFile($id, FileService::store($f, $checked, 'trainings'));
        }
        AuditService::log($isNew ? 'training_created' : 'training_updated', 'training', $id);

        $msg = 'Entrenament desat correctament.';
        if ($existing && $existing['status'] === 'published') {
            $changes = [];
            if ($existing['session_date'] !== $d['session_date']) {
                $changes[] = 'Data: ' . fmt_date($existing['session_date']) . ' → ' . fmt_date($d['session_date']);
            }
            if (substr($existing['start_time'], 0, 5) !== $d['start_time']) {
                $changes[] = 'Hora: ' . fmt_time($existing['start_time']) . ' → ' . $d['start_time'];
            }
            if ($changes || $old['notify_changes'] === '1') {
                $res = NotificationService::notifyTraining($id, 'updated', implode('; ', $changes));
                $msg .= " S'ha notificat el canvi a {$res['sent']} entrenador(s)" . ($res['failed'] ? " ({$res['failed']} errors d'enviament)" : '') . '.';
            }
        }
        $this->done($msg, '/entrenaments/' . $id);
    }

    public function status(Request $r, array $p): void
    {
        $id = $this->id($p);
        $t = Training::find($id) ?? Response::notFound();
        $new = $r->str('status');
        if (!in_array($new, self::TRANSITIONS[$t['status']], true)) {
            $this->done('Aquest canvi d\'estat no és possible.', '/entrenaments/' . $id, 'error');
        }
        if ($new === 'published' && !Training::categories($id)) {
            $this->done('Cal assignar almenys un equip abans de publicar.', '/entrenaments/' . $id, 'error');
        }
        Training::setStatus($id, $new);
        AuditService::log('training_' . $new, 'training', $id);
        $msg = 'Estat canviat a «' . Labels::status($new) . '».';
        $notify = $new === 'published' ? 'published' : ($new === 'cancelled' && $t['status'] === 'published' ? 'cancelled' : null);
        if ($notify !== null) {
            $res = NotificationService::notifyTraining($id, $notify);
            $msg .= " Correus enviats: {$res['sent']}" . ($res['failed'] ? ", errors: {$res['failed']}" : '') . '.';
        }
        $this->done($msg, '/entrenaments/' . $id);
    }

    public function deleteFile(Request $r, array $p): void
    {
        $file = Training::file($this->id($p)) ?? Response::notFound();
        \App\Helpers\Db::run('DELETE FROM training_files WHERE id = ?', [$file['id']]);
        FileService::delete('trainings', $file['stored_name']);
        AuditService::log('training_file_deleted', 'training', (int) $file['training_id']);
        $this->done('Fitxer eliminat.', '/entrenaments/' . $file['training_id'] . '/editar');
    }

    public function delete(Request $r, array $p): void
    {
        $id = $this->id($p);
        Training::find($id) ?? Response::notFound();
        foreach (Training::delete($id) as $stored) {
            FileService::delete('trainings', $stored);
        }
        AuditService::log('training_deleted', 'training', $id);
        $this->done('Entrenament eliminat.', '/entrenaments');
    }
}
