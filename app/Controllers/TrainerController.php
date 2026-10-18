<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Db;
use App\Helpers\Request;
use App\Helpers\Response;
use App\Models\Category;
use App\Models\User;
use App\Services\AuditService;
use App\Services\NotificationService;
use App\Services\TokenService;
use App\Validators\Validator;

final class TrainerController extends Controller
{
    public function index(Request $r, array $p): void
    {
        [$rows, $pg] = User::searchTrainers($r->q('q'), $r->q('status'), (int) $r->q('page'));
        $this->render('trainers/index', ['title' => 'Entrenadors', 'trainers' => $rows, 'pager' => $pg, 'q' => $r->q('q'), 'status' => $r->q('status')]);
    }

    private function formData(?array $trainer): array
    {
        return [
            'title' => $trainer ? 'Edita l\'entrenador' : 'Nou entrenador', 'trainer' => $trainer,
            'categories' => Category::all(),
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
        $this->render('trainers/form', $this->formData($t) + ['old' => $t + ['category_ids' => User::categoryIds($id)]]);
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
        $catIds = $this->onlyKnown($r->intList('category_ids'), array_column(Category::all(), 'id'));
        if ($v->fails()) {
            $t = $id ? User::findTrainer($id) : null;
            $this->invalid('trainers/form', $this->formData($t), $v->errors(), $d + ['category_ids' => $catIds]);
            return;
        }

        $isNew = $id === null;
        $oldCats = $isNew ? [] : User::categoryIds($id);
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
        if (array_diff($catIds, $oldCats) || array_diff($oldCats, $catIds)) {
            AuditService::log('trainer_categories_assigned', 'user', $id, 'ok', 'Categories: ' . implode(',', $catIds));
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

    public function toggle(Request $r, array $p): void
    {
        $id = $this->id($p);
        $t = User::findTrainer($id) ?? Response::notFound();
        $new = (int) $t['is_active'] !== 1;
        User::setActive($id, $new);
        AuditService::log($new ? 'trainer_activated' : 'trainer_deactivated', 'user', $id);
        $this->done($new ? 'Entrenador activat.' : 'Entrenador desactivat.', '/entrenadors');
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
