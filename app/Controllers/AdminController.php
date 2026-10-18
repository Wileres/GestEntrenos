<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Db;
use App\Helpers\Labels;
use App\Helpers\Paginator;
use App\Helpers\Request;
use App\Services\AuditService;
use App\Services\FileService;
use App\Services\MailService;
use App\Services\SettingsService;
use App\Validators\Validator;

final class AdminController extends Controller
{
    public function mailForm(Request $r, array $p): void
    {
        $smtp = SettingsService::smtp();
        $this->render('admin/mail', [
            'title' => 'Configuració del correu', 'passwordMask' => SettingsService::maskedPassword($smtp),
            'old' => ['host' => $smtp['host'], 'port' => $smtp['port'], 'encryption' => $smtp['encryption'], 'username' => $smtp['username'],
                'from_email' => $smtp['from_email'], 'from_name' => $smtp['from_name'], 'reply_to' => $smtp['reply_to'], 'enabled' => $smtp['enabled']],
        ]);
    }

    public function mailSave(Request $r, array $p): void
    {
        $d = [];
        foreach (['host', 'port', 'encryption', 'username', 'from_email', 'from_name', 'reply_to'] as $k) {
            $d[$k] = $r->str($k);
        }
        $d['enabled'] = $r->input('enabled') ? '1' : '';
        $v = (new Validator($d))->maxLen('host', 'Servidor SMTP', 190)->intRange('port', 'Port', 1, 65535, true)
            ->inList('encryption', 'Tipus de xifrat', array_keys(Labels::ENCRYPTION))->maxLen('username', 'Usuari SMTP', 190)
            ->email('from_email', 'Correu del remitent', $d['enabled'] === '1')->maxLen('from_name', 'Nom del remitent', 120)
            ->email('reply_to', 'Adreça de resposta', false);
        if ($d['enabled'] === '1' && $d['host'] === '') {
            $v->error('host', 'El servidor SMTP és obligatori per activar els correus.');
        }
        $newPass = (string) $r->input('smtp_password', '');
        if (strlen($newPass) > 255) {
            $v->error('smtp_password', 'La contrasenya SMTP és massa llarga.');
        }
        if ($v->fails()) {
            $this->invalid('admin/mail', ['title' => 'Configuració del correu', 'passwordMask' => SettingsService::maskedPassword(SettingsService::smtp())], $v->errors(), $d);
            return;
        }
        SettingsService::saveSmtp(['port' => (int) $d['port'], 'enabled' => $d['enabled'] === '1'] + $d, $newPass);
        AuditService::log('smtp_settings_changed', 'settings', null, 'ok', $newPass !== '' ? 'Contrasenya SMTP actualitzada' : null);
        $this->done('Configuració del correu desada.', '/admin/correu');
    }

    public function mailTest(Request $r, array $p): void
    {
        $to = mb_strtolower($r->str('test_email'));
        $v = (new Validator(['test_email' => $to]))->email('test_email', 'Correu de prova');
        if ($v->fails()) {
            $this->done($v->errors()['test_email'], '/admin/correu', 'error');
        }
        $ok = MailService::send('test', $to, '', 'Correu de prova · ' . SettingsService::clubName(), 'test', [], true);
        AuditService::log('smtp_test', 'settings', null, $ok ? 'ok' : 'error');
        $this->done($ok ? 'Correu de prova enviat correctament.' : 'No s\'ha pogut enviar el correu de prova. Consulta l\'historial de correus per veure l\'error.', '/admin/correu', $ok ? 'success' : 'error');
    }

    public function logoSave(Request $r, array $p): void
    {
        $files = FileService::normalize($r->files['logo'] ?? null);
        if (!$files) {
            $this->done('Selecciona una imatge (PNG, JPG o WEBP).', '/admin/logotip', 'error');
        }
        [$checked, $err] = FileService::validate($files[0], ['png', 'jpg', 'jpeg', 'webp']);
        if ($err === null && $files[0]['size'] > 2 * 1048576) {
            $err = 'El logotip no pot superar els 2 MB.';
        }
        if ($err === null) {
            $dim = getimagesize($files[0]['tmp_name']);
            if ($dim === false || $dim[0] > 2000 || $dim[1] > 2000) {
                $err = 'El logotip no pot superar els 2000 px de costat.';
            }
        }
        if ($err !== null) {
            $this->done($err, '/admin/logotip', 'error');
        }
        $ext = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'][$checked['mime']];
        $name = 'logo-' . bin2hex(random_bytes(8)) . '.' . $ext;
        $dir = FileService::directory('logo');
        $target = $dir . '/' . $name;
        $moved = FileService::$testing ? copy($files[0]['tmp_name'], $target) : move_uploaded_file($files[0]['tmp_name'], $target);
        if (!$moved) {
            $this->done('No s\'ha pogut desar el logotip.', '/admin/logotip', 'error');
        }
        @chmod($target, 0640);
        $oldPath = SettingsService::logoPath();
        SettingsService::set('logo_file', $name);
        if ($oldPath !== null) {
            @unlink($oldPath);
        }
        AuditService::log('logo_changed', 'settings');
        $this->done('Logotip actualitzat.', '/admin/logotip');
    }

    public function logoForm(Request $r, array $p): void
    {
        $this->render('admin/logo', ['title' => 'Logotip del club', 'hasLogo' => SettingsService::logoPath() !== null]);
    }

    public function audit(Request $r, array $p): void
    {
        $action = $r->q('action');
        $where = $action !== '' ? 'WHERE a.action = ?' : '';
        $params = $action !== '' ? [$action] : [];
        $pg = Paginator::make((int) Db::value("SELECT COUNT(*) FROM audit_logs a $where", $params), $r->q('page'), 30);
        $rows = Db::all(
            "SELECT a.*, CONCAT(u.first_name, ' ', u.last_name) AS user_name FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id
             $where ORDER BY a.id DESC LIMIT {$pg->perPage} OFFSET {$pg->offset}",
            $params
        );
        $actions = Db::column('SELECT DISTINCT action FROM audit_logs ORDER BY action');
        $this->render('admin/audit', ['title' => 'Registre d\'activitat', 'rows' => $rows, 'pager' => $pg, 'action' => $action, 'actions' => $actions]);
    }

    public function emails(Request $r, array $p): void
    {
        $status = $r->q('status');
        $ok = in_array($status, ['sent', 'failed', 'skipped'], true);
        $where = $ok ? 'WHERE status = ?' : '';
        $params = $ok ? [$status] : [];
        $pg = Paginator::make((int) Db::value("SELECT COUNT(*) FROM email_logs $where", $params), $r->q('page'), 30);
        $rows = Db::all("SELECT * FROM email_logs $where ORDER BY id DESC LIMIT {$pg->perPage} OFFSET {$pg->offset}", $params);
        $this->render('admin/emails', ['title' => 'Historial de correus', 'rows' => $rows, 'pager' => $pg, 'status' => $ok ? $status : '']);
    }
}
