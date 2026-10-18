<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Config;
use App\Helpers\Db;
use App\Helpers\Request;
use App\Helpers\Response;
use App\Helpers\Session;
use App\Models\User;
use App\Services\AuditService;
use App\Services\AuthService;
use App\Services\NotificationService;
use App\Services\TokenService;
use App\Validators\Validator;

final class AuthController extends Controller
{
    public function showLogin(Request $r, array $p): void
    {
        if (Session::get('_expired')) {
            Session::forget('_expired');
            Session::flash('warning', 'La sessió ha caducat per inactivitat. Torna a iniciar-la.');
        }
        $this->render('auth/login', ['title' => 'Accés'], 200, 'auth');
    }

    public function login(Request $r, array $p): void
    {
        $res = AuthService::attempt($r->str('login'), (string) $r->input('password', ''), $r->ip());
        if (!$res['ok']) {
            $this->render('auth/login', ['title' => 'Accés', 'loginError' => $res['error'], 'old' => ['login' => $r->str('login')]], 422, 'auth');
            return;
        }
        Response::redirect('/');
    }

    public function logout(Request $r, array $p): void
    {
        AuthService::logout();
        Session::start();
        Session::flash('success', 'Has tancat la sessió correctament.');
        Response::redirect('/acces');
    }

    public function showForgot(Request $r, array $p): void
    {
        $this->render('auth/forgot', ['title' => 'Has oblidat la contrasenya?'], 200, 'auth');
    }

    public function forgot(Request $r, array $p): void
    {
        $email = mb_strtolower($r->str('email'));
        $v = (new Validator($r->post))->email('email', 'Correu electrònic');
        if ($v->fails()) {
            $this->invalid('auth/forgot', ['title' => 'Has oblidat la contrasenya?'], $v->errors(), ['email' => $email]);
            return;
        }
        $key = 'reset:' . $email;
        $since = date('Y-m-d H:i:s', time() - (int) Config::get('auth.lock_minutes') * 60);
        $recent = (int) Db::value('SELECT COUNT(*) FROM login_attempts WHERE identifier = ? AND created_at >= ?', [$key, $since]);
        if ($recent < 3) {
            Db::insert('login_attempts', ['identifier' => $key, 'ip_address' => $r->ip(), 'created_at' => Db::now()]);
            $user = User::findByEmail($email);
            if ($user !== null) {
                $token = TokenService::createReset((int) $user['id']);
                NotificationService::sendReset($user, $token);
                AuditService::log('password_reset_requested', 'user', (int) $user['id'], 'ok', null, (int) $user['id']);
            }
        }
        $this->done('Si el correu existeix al sistema, rebràs un missatge amb les instruccions per restablir la contrasenya.', '/acces', 'info');
    }

    public function showReset(Request $r, array $p): void
    {
        $token = (string) $p['token'];
        if (TokenService::findReset($token) === null) {
            $this->done('L\'enllaç no és vàlid o ha caducat. Sol·licita\'n un de nou.', '/contrasenya/oblidada', 'error');
        }
        $this->render('auth/set_password', [
            'title' => 'Nova contrasenya', 'heading' => 'Restableix la contrasenya',
            'action' => url('/contrasenya/restablir/' . $token),
        ], 200, 'auth');
    }

    public function reset(Request $r, array $p): void
    {
        $token = (string) $p['token'];
        $data = ['title' => 'Nova contrasenya', 'heading' => 'Restableix la contrasenya', 'action' => url('/contrasenya/restablir/' . $token)];
        $v = (new Validator($r->post))->password('password');
        if ($v->fails()) {
            $this->invalid('auth/set_password', $data, $v->errors(), []);
            return;
        }
        $userId = TokenService::consumeReset($token, (string) $r->input('password'));
        if ($userId === null) {
            $this->done('L\'enllaç no és vàlid o ha caducat. Sol·licita\'n un de nou.', '/contrasenya/oblidada', 'error');
        }
        AuditService::log('password_reset_done', 'user', $userId, 'ok', null, $userId);
        $this->done('Contrasenya actualitzada. Ja pots iniciar la sessió.', '/acces');
    }

    public function showActivate(Request $r, array $p): void
    {
        $token = (string) $p['token'];
        $row = TokenService::findActivation($token);
        if ($row === null) {
            $this->render('errors/error', [
                'title' => 'Enllaç no vàlid', 'status' => 410,
                'message' => 'Aquest enllaç d\'activació ja s\'ha utilitzat o ha estat invalidat. Contacta amb l\'administrador del club.',
            ], 410, 'auth');
            return;
        }
        $this->render('auth/set_password', [
            'title' => 'Activa el teu compte', 'heading' => 'Hola, ' . $row['first_name'] . '! Defineix la teva contrasenya',
            'action' => url('/activar/' . $token),
        ], 200, 'auth');
    }

    public function activate(Request $r, array $p): void
    {
        $token = (string) $p['token'];
        $data = ['title' => 'Activa el teu compte', 'heading' => 'Defineix la teva contrasenya', 'action' => url('/activar/' . $token)];
        $v = (new Validator($r->post))->password('password');
        if ($v->fails()) {
            $this->invalid('auth/set_password', $data, $v->errors(), []);
            return;
        }
        $userId = TokenService::consumeActivation($token, (string) $r->input('password'));
        if ($userId === null) {
            $this->done('L\'enllaç d\'activació no és vàlid o ja s\'ha utilitzat.', '/acces', 'error');
        }
        AuditService::log('account_activated', 'user', $userId, 'ok', null, $userId);
        $this->done('Compte activat! Ja pots iniciar la sessió.', '/acces');
    }

    public function profile(Request $r, array $p): void
    {
        $u = $this->user();
        $this->render('account/profile', [
            'title' => 'El meu perfil', 'categories' => User::categories((int) $u['id']),
        ]);
    }

    public function showChangePassword(Request $r, array $p): void
    {
        $this->render('account/password', ['title' => 'Canvia la contrasenya', 'forced' => (int) $this->user()['must_change_password'] === 1]);
    }

    public function changePassword(Request $r, array $p): void
    {
        $u = $this->user();
        $data = ['title' => 'Canvia la contrasenya', 'forced' => (int) $u['must_change_password'] === 1];
        $v = (new Validator($r->post))->password('password');
        $current = (string) $r->input('current_password', '');
        if (!AuthService::verifyPassword((int) $u['id'], $current)) {
            $v->error('current_password', 'La contrasenya actual no és correcta.');
        } elseif (hash_equals($current, (string) $r->input('password', ''))) {
            $v->error('password', 'La nova contrasenya ha de ser diferent de l\'actual.');
        }
        if ($v->fails()) {
            AuditService::log('password_change_failed', 'user', (int) $u['id'], 'error');
            $this->invalid('account/password', $data, $v->errors(), []);
            return;
        }
        AuthService::setPassword((int) $u['id'], (string) $r->input('password'));
        AuthService::login((int) $u['id']);
        AuditService::log('password_changed', 'user', (int) $u['id']);
        $this->done('Contrasenya canviada correctament.', '/');
    }
}
