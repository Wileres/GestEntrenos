<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Request;
use App\Helpers\Response;
use App\Models\User;
use App\Services\AuditService;
use App\Services\NotificationService;
use App\Services\TokenService;
use App\Validators\Validator;

/** Gestió d'altres usuaris amb rol d'administrador. */
final class AdminUserController extends Controller
{
    public function index(Request $r, array $p): void
    {
        $this->render('admins/index', ['title' => 'Administradors', 'admins' => User::admins(), 'me' => (int) $this->user()['id']]);
    }

    public function create(Request $r, array $p): void
    {
        $this->render('admins/form', ['title' => 'Nou administrador', 'admin' => null, 'old' => ['is_active' => '1']]);
    }

    public function edit(Request $r, array $p): void
    {
        $a = User::findAdmin($this->id($p)) ?? Response::notFound();
        $this->render('admins/form', [
            'title' => 'Edita l\'administrador', 'admin' => $a, 'old' => $a,
            'pendingActivation' => TokenService::hasPendingActivation((int) $a['id']), 'me' => (int) $this->user()['id'],
        ]);
    }

    public function store(Request $r, array $p): void
    {
        $this->save($r, null);
    }

    public function update(Request $r, array $p): void
    {
        $id = $this->id($p);
        User::findAdmin($id) ?? Response::notFound();
        $this->save($r, $id);
    }

    private function save(Request $r, ?int $id): void
    {
        $me = (int) $this->user()['id'];
        $d = [
            'first_name' => $r->str('first_name'), 'last_name' => $r->str('last_name'), 'email' => mb_strtolower($r->str('email')),
            'phone' => $r->str('phone'), 'notes' => '', 'is_active' => $r->input('is_active') ? '1' : '0',
        ];
        if ($id === $me) {
            $d['is_active'] = '1';
        }
        $v = (new Validator($d))->required('first_name', 'Nom')->maxLen('first_name', 'Nom', 80)
            ->required('last_name', 'Cognoms')->maxLen('last_name', 'Cognoms', 120)
            ->email('email', 'Correu electrònic')->phone('phone', 'Telèfon');
        if (!$v->fails() && User::emailTaken($d['email'], $id)) {
            $v->error('email', 'Ja existeix un usuari amb aquest correu electrònic.');
        }
        if ($id !== null && $d['is_active'] === '0' && User::countActiveAdmins() <= 1) {
            $v->error('is_active', 'Ha de quedar com a mínim un administrador actiu.');
        }
        if ($v->fails()) {
            $a = $id ? User::findAdmin($id) : null;
            $this->invalid('admins/form', ['title' => $id ? 'Edita l\'administrador' : 'Nou administrador', 'admin' => $a, 'me' => $me, 'pendingActivation' => $id ? TokenService::hasPendingActivation($id) : false], $v->errors(), $d);
            return;
        }
        $isNew = $id === null;
        $id = User::saveAccount($id, ['is_active' => $d['is_active'] === '1'] + $d, 1);
        AuditService::log($isNew ? 'admin_created' : 'admin_updated', 'user', $id);
        if ($isNew) {
            $token = TokenService::createActivation($id);
            $sent = NotificationService::sendActivation(['id' => $id] + $d, $token, 'activation', 'administrador');
            $this->done(
                $sent ? 'Administrador creat. S\'ha enviat un correu d\'activació.' : 'Administrador creat, però el correu d\'activació no s\'ha pogut enviar. Fes servir «Reenvia l\'activació».',
                $sent ? '/administradors' : '/administradors/' . $id . '/editar',
                $sent ? 'success' : 'warning'
            );
        }
        $this->done('Administrador desat correctament.', '/administradors');
    }

    public function toggle(Request $r, array $p): void
    {
        $id = $this->id($p);
        $a = User::findAdmin($id) ?? Response::notFound();
        if ($id === (int) $this->user()['id']) {
            $this->done('No et pots desactivar a tu mateix.', '/administradors', 'error');
        }
        $new = (int) $a['is_active'] !== 1;
        if (!$new && User::countActiveAdmins() <= 1) {
            $this->done('Ha de quedar com a mínim un administrador actiu.', '/administradors', 'error');
        }
        User::setActive($id, $new);
        AuditService::log($new ? 'admin_activated' : 'admin_deactivated', 'user', $id);
        $this->done($new ? 'Administrador activat.' : 'Administrador desactivat.', '/administradors');
    }

    public function activation(Request $r, array $p): void
    {
        $id = $this->id($p);
        $a = User::findAdmin($id) ?? Response::notFound();
        $back = '/administradors/' . $id . '/editar';
        if ((int) $a['has_password'] === 1) {
            $this->done('Aquest administrador ja ha activat el compte.', $back, 'warning');
        }
        if ($r->str('do') === 'revoke') {
            TokenService::revokeActivation($id);
            AuditService::log('activation_revoked', 'user', $id);
            $this->done('Els enllaços d\'activació pendents s\'han invalidat.', $back);
        }
        $token = TokenService::createActivation($id);
        AuditService::log('activation_regenerated', 'user', $id);
        $ok = NotificationService::sendActivation($a, $token, 'activation_resend', 'administrador');
        $this->done($ok ? 'S\'ha enviat un enllaç nou. Els anteriors ja no funcionen.' : 'S\'ha generat un enllaç nou, però el correu no s\'ha pogut enviar.', $back, $ok ? 'success' : 'warning');
    }
}
