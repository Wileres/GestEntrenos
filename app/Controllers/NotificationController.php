<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Request;
use App\Models\User;
use App\Services\AuditService;
use App\Services\Notifier;

final class NotificationController extends Controller
{
    public function index(Request $r, array $p): void
    {
        $u = $this->user();
        $items = Notifier::recent((int) $u['id']);
        $notifyEmail = (int) \App\Helpers\Db::value('SELECT notify_email FROM users WHERE id = ?', [$u['id']]) === 1;
        $this->render('notifications/index', [
            'title' => 'Notificacions', 'items' => $items, 'notifyEmail' => $notifyEmail,
            'vapidKey' => Notifier::publicKey(), 'subscribed' => Notifier::hasSubscription((int) $u['id']),
        ]);
        Notifier::markAllRead((int) $u['id']);
    }

    public function preferences(Request $r, array $p): void
    {
        $u = $this->user();
        User::setNotifyEmail((int) $u['id'], (bool) $r->input('notify_email'));
        AuditService::log('notify_preferences_changed', 'user', (int) $u['id']);
        $this->done('Preferències desades.', '/notificacions');
    }

    /** Alta d'una subscripció Web Push (JSON enviat per JavaScript). */
    public function subscribe(Request $r, array $p): void
    {
        $data = json_decode((string) file_get_contents('php://input'), true);
        $ok = is_array($data) && is_string($data['endpoint'] ?? null) && is_array($data['keys'] ?? null)
            && Notifier::subscribe((int) $this->user()['id'], $data['endpoint'], (string) ($data['keys']['p256dh'] ?? ''), (string) ($data['keys']['auth'] ?? ''));
        $this->json(['ok' => $ok], $ok ? 200 : 422);
    }

    public function unsubscribe(Request $r, array $p): void
    {
        $data = json_decode((string) file_get_contents('php://input'), true);
        if (is_array($data) && is_string($data['endpoint'] ?? null)) {
            Notifier::unsubscribe((int) $this->user()['id'], $data['endpoint']);
        }
        $this->json(['ok' => true]);
    }

    private function json(array $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data);
        exit;
    }
}
