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

    /** Amaga l'avís de correus fallits fins que n'hi hagi de nous. */
    public function dismissMailAlert(Request $r, array $p): void
    {
        SettingsService::set('mail_alert_dismissed_at', Db::now());
        AuditService::log('mail_alert_dismissed', 'settings');
        $this->done('Avís descartat.', '/');
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
