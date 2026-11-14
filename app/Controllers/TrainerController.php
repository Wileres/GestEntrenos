<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Db;
use App\Helpers\Config;
use App\Helpers\Request;
use App\Helpers\Response;
use App\Helpers\Session;
use App\Models\Team;
use App\Models\User;
use App\Services\AuditService;
use App\Services\NotificationService;
use App\Services\TrainerImport;
use App\Services\TokenService;
use App\Validators\Validator;

final class TrainerController extends Controller
{
    public function index(Request $r, array $p): void
    {
        [$rows, $pg] = User::searchTrainers($r->q('q'), $r->q('status'), (int) $r->q('page'));
        $this->render('trainers/index', ['title' => 'Entrenadors', 'teams' => Team::all(), 'trainers' => $rows, 'pager' => $pg, 'q' => $r->q('q'), 'status' => $r->q('status')]);
    }

    private function formData(?array $trainer): array
    {
        return [
            'title' => $trainer ? 'Edita l\'entrenador' : 'Nou entrenador', 'trainer' => $trainer,
            'teams' => Team::all(),
            'pendingActivation' => $trainer ? TokenService::hasPendingActivation((int) $trainer['id']) : false,
        ];
    }

    public function create(Request $r, array $p): void
    {
        $this->render('trainers/form', $this->formData(null) + ['old' => ['is_active' => '1']]);
    }

    public function edit(Request $r, array $p): void
    {
        $id = $this->id($p);
        $t = User::findTrainer($id) ?? Response::notFound();
        $this->render('trainers/form', $this->formData($t) + ['old' => $t + ['team_ids' => User::teamIds($id)]]);
    }

    public function store(Request $r, array $p): void
    {
        $this->save($r, null);
    }

    public function update(Request $r, array $p): void
    {
        $id = $this->id($p);
        User::findTrainer($id) ?? Response::notFound();
        $this->save($r, $id);
    }

    private function save(Request $r, ?int $id): void
    {
        $d = [
            'first_name' => $r->str('first_name'), 'last_name' => $r->str('last_name'), 'email' => mb_strtolower($r->str('email')),
            'phone' => $r->str('phone'), 'notes' => $r->str('notes'), 'is_active' => $r->input('is_active') ? '1' : '0',
        ];
        $v = (new Validator($d))->required('first_name', 'Nom')->maxLen('first_name', 'Nom', 80)
            ->required('last_name', 'Cognoms')->maxLen('last_name', 'Cognoms', 120)
            ->email('email', 'Correu electrònic')->phone('phone', 'Telèfon')->maxLen('notes', 'Observacions', 2000);
        if (!$v->fails() && User::emailTaken($d['email'], $id)) {
            $v->error('email', 'Ja existeix un usuari amb aquest correu electrònic.');
        }
        $catIds = $this->onlyKnown($r->intList('team_ids'), array_column(Team::all(), 'id'));
        if ($v->fails()) {
            $t = $id ? User::findTrainer($id) : null;
            $this->invalid('trainers/form', $this->formData($t), $v->errors(), $d + ['team_ids' => $catIds]);
            return;
        }

        $isNew = $id === null;
        $existing = $isNew ? null : User::findTrainer($id);
        $pending = $existing !== null && (int) $existing['pending_approval'] === 1;
        $imported = $pending && (int) $existing['has_password'] === 0;
        // Un entrenador importat s'activa en assignar-li un equip; un d'auto-registrat, en marcar «Compte actiu».
        $approving = $pending && ($d['is_active'] === '1' || ($imported && $catIds));
        if ($approving && !$catIds) {
            $this->invalid('trainers/form', $this->formData($existing), ['team_ids' => 'Cal assignar almenys un equip per activar un entrenador pendent.'], $d + ['team_ids' => $catIds]);
            return;
        }
        if ($approving) {
            $d['is_active'] = '1';
        }
        $oldCats = $isNew ? [] : User::teamIds($id);
        $token = null;
        $id = Db::transaction(function () use ($id, $d, $catIds, $isNew, &$token) {
            $id = User::saveTrainer($id, ['is_active' => $d['is_active'] === '1'] + $d);
            User::syncRelations($id, $catIds);
            if ($isNew) {
                $token = TokenService::createActivation($id);
            }
            return $id;
        });
        AuditService::log($isNew ? 'trainer_created' : 'trainer_updated', 'user', $id);
        if ($approving) {
            $sent = $this->approve($id, $d, $imported);
            $this->done($this->approvalMessage($imported, $sent), '/entrenadors', $sent ? 'success' : 'warning');
        }
        if (array_diff($catIds, $oldCats) || array_diff($oldCats, $catIds)) {
            AuditService::log('trainer_teams_assigned', 'user', $id, 'ok', 'Equips: ' . implode(',', $catIds));
        }

        if ($isNew) {
            $sent = NotificationService::sendActivation(['id' => $id] + $d, (string) $token);
            if ($sent) {
                $this->done('Entrenador creat. S\'ha enviat un correu d\'activació.', '/entrenadors');
            }
            $this->done('Entrenador creat, però el correu d\'activació no s\'ha pogut enviar. Revisa la configuració del correu i fes servir «Reenvia l\'activació».', '/entrenadors/' . $id . '/editar', 'warning');
        }
        $this->done('Entrenador desat correctament.', '/entrenadors');
    }

    /** Activa un entrenador pendent: si ja té contrasenya l'avisa; si no (importat), li envia l'enllaç per definir-la. */
    private function approve(int $id, array $trainer, bool $imported): bool
    {
        User::approve($id);
        AuditService::log('trainer_approved', 'user', $id);
        if (!$imported) {
            return NotificationService::sendApproved($trainer);
        }
        return NotificationService::sendActivation(['id' => $id] + $trainer, TokenService::createActivation($id));
    }

    private function approvalMessage(bool $imported, bool $sent): string
    {
        if ($imported) {
            return $sent ? 'Equip assignat. S\'ha enviat un correu a l\'entrenador perquè defineixi la contrasenya.'
                : 'Equip assignat, però el correu no s\'ha pogut enviar. Fes servir «Reenvia l\'activació» a la seva fitxa.';
        }
        return $sent ? 'Entrenador aprovat i activat. S\'ha enviat un correu d\'avís.' : 'Entrenador aprovat i activat, però el correu d\'avís no s\'ha pogut enviar.';
    }

    /** Assigna equips a diversos entrenadors pendents alhora i els envia l'activació. */
    public function bulkAssign(Request $r, array $p): void
    {
        $ids = $r->intList('trainer_ids');
        $teamIds = $this->onlyKnown($r->intList('team_ids'), array_column(Team::all(), 'id'));
        if (!$ids || !$teamIds) {
            $this->done('Selecciona almenys un entrenador pendent i un equip.', '/entrenadors?status=pending', 'error');
        }
        $ok = 0;
        $failed = 0;
        foreach ($ids as $id) {
            $t = User::findTrainer($id);
            if ($t === null || (int) $t['pending_approval'] !== 1) {
                continue;
            }
            User::syncRelations($id, array_values(array_unique([...User::teamIds($id), ...$teamIds])));
            AuditService::log('trainer_teams_assigned', 'user', $id, 'ok', 'Equips: ' . implode(',', $teamIds));
            $this->approve($id, $t, (int) $t['has_password'] === 0) ? $ok++ : $failed++;
        }
        $this->done("Equips assignats a " . ($ok + $failed) . " entrenador(s). Correus enviats: {$ok}" . ($failed ? ", amb error: {$failed} (revisa l'historial de correus)" : '') . '.', '/entrenadors?status=pending', $failed ? 'warning' : 'success');
    }

    // ------------------------------------------------------------ importació

    private function importData(array $extra = []): array
    {
        return $extra + ['title' => 'Importa entrenadors', 'plan' => null, 'maxMb' => (int) (Config::get('upload.max_bytes', 10485760) / 1048576)];
    }

    public function importForm(Request $r, array $p): void
    {
        $this->render('trainers/import', $this->importData());
    }

    public function importPreview(Request $r, array $p): void
    {
        $file = $r->files['file'] ?? null;
        $text = trim((string) $r->input('text', ''));
        $hasFile = is_array($file) && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        $fail = fn (string $field, string $m) => $this->invalid('trainers/import', $this->importData(), [$field => $m], ['text' => $text]);
        try {
            if ($hasFile) {
                if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file((string) $file['tmp_name'])) {
                    $fail('file', 'No s\'ha pogut pujar el fitxer.');
                    return;
                }
                if ((int) $file['size'] > (int) Config::get('upload.max_bytes', 10485760)) {
                    $fail('file', 'El fitxer supera la mida màxima permesa.');
                    return;
                }
                $parsed = TrainerImport::parseFile((string) $file['tmp_name'], (string) $file['name']);
            } elseif ($text !== '') {
                if (strlen($text) > 512 * 1024) {
                    $fail('text', 'El text és massa llarg.');
                    return;
                }
                $parsed = TrainerImport::parseText($text);
            } else {
                $fail('file', 'Puja un fitxer o enganxa el llistat.');
                return;
            }
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            $fail($hasFile ? 'file' : 'text', $e->getMessage());
            return;
        } catch (\Throwable) {
            $fail($hasFile ? 'file' : 'text', 'No s\'ha pogut llegir el llistat. Comprova el format.');
            return;
        }
        if (!$parsed['rows']) {
            $this->invalid('trainers/import', $this->importData(['skipped' => $parsed['errors']]), ['file' => 'No s\'ha trobat cap entrenador vàlid.'], ['text' => $text]);
            return;
        }
        $plan = TrainerImport::plan($parsed['rows']);
        Session::set('trainer_import', $parsed['rows']);
        $this->render('trainers/import', $this->importData(['plan' => $plan, 'errors' => $parsed['errors']]));
    }

    public function importConfirm(Request $r, array $p): void
    {
        $rows = Session::get('trainer_import');
        if (!is_array($rows) || !$rows) {
            $this->done('La importació ha caducat. Torna a carregar el llistat.', '/entrenadors/importa', 'error');
        }
        Session::forget('trainer_import');
        $res = TrainerImport::apply($rows);
        AuditService::log('trainers_imported', 'user', null, 'ok', json_encode($res));
        $this->done(
            "Importació acabada: {$res['created']} entrenadors nous (pendents d'assignar equip) i {$res['skipped']} ignorats.",
            $res['created'] > 0 ? '/entrenadors?status=pending' : '/entrenadors',
            $res['created'] > 0 ? 'success' : 'warning'
        );
    }

    public function template(Request $r, array $p): void
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="plantilla-entrenadors.csv"');
        echo "\xEF\xBB\xBF" . "Nom i cognoms;Correu electrònic\r\nJoan Pons Vila;joan.pons@exemple.cat\r\nMarta Sala Roca;marta.sala@exemple.cat\r\n";
        exit;
    }

    public function toggle(Request $r, array $p): void
    {
        $id = $this->id($p);
        $t = User::findTrainer($id) ?? Response::notFound();
        if ((int) $t['pending_approval'] === 1) {
            $this->done('Aquest entrenador està pendent d\'aprovació: assigna-li un equip i activa el compte des d\'aquí.', '/entrenadors/' . $id . '/editar', 'warning');
        }
        $new = (int) $t['is_active'] !== 1;
        User::setActive($id, $new);
        AuditService::log($new ? 'trainer_activated' : 'trainer_deactivated', 'user', $id);
        $this->done($new ? 'Entrenador activat.' : 'Entrenador desactivat.', '/entrenadors');
    }

    public function delete(Request $r, array $p): void
    {
        $id = $this->id($p);
        $t = User::findTrainer($id) ?? Response::notFound();
        User::deleteTrainer($id);
        AuditService::log('trainer_deleted', 'user', $id, 'ok', trim($t['first_name'] . ' ' . $t['last_name']));
        $this->done('Entrenador eliminat.', '/entrenadors');
    }

    public function activation(Request $r, array $p): void
    {
        $id = $this->id($p);
        $t = User::findTrainer($id) ?? Response::notFound();
        $back = '/entrenadors/' . $id . '/editar';
        if ((int) $t['has_password'] === 1) {
            $this->done('Aquest entrenador ja ha activat el compte.', $back, 'warning');
        }
        if ($r->str('do') === 'revoke') {
            TokenService::revokeActivation($id);
            AuditService::log('activation_revoked', 'user', $id);
            $this->done('Els enllaços d\'activació pendents s\'han invalidat.', $back);
        }
        $token = TokenService::createActivation($id);
        AuditService::log('activation_regenerated', 'user', $id);
        if (NotificationService::sendActivation($t, $token, 'activation_resend')) {
            $this->done('S\'ha generat un enllaç nou i s\'ha enviat per correu. Els enllaços anteriors ja no funcionen.', $back);
        }
        $this->done('S\'ha generat un enllaç nou, però el correu no s\'ha pogut enviar. Consulta l\'historial de correus.', $back, 'warning');
    }
}
