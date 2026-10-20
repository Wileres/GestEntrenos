<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Config;
use App\Helpers\Crypto;
use App\Helpers\Db;

/** Notificacions dins l'aplicació i al navegador (Web Push). */
final class Notifier
{
    /** @return array{private:string,public:string,subject:string} */
    public static function vapid(): array
    {
        $pub = SettingsService::get('vapid_public');
        $privEnc = SettingsService::get('vapid_private');
        $priv = $privEnc !== '' ? Crypto::decrypt($privEnc) : null;
        if ($pub === '' || $priv === null) {
            $k = WebPush::generateKeyPair();
            $pub = WebPush::b64uEncode($k['public']);
            $priv = WebPush::b64uEncode($k['private']);
            SettingsService::set('vapid_public', $pub);
            SettingsService::set('vapid_private', Crypto::encrypt($priv));
        }
        $url = (string) Config::get('url');
        return [
            'private' => WebPush::b64uDecode($priv), 'public' => WebPush::b64uDecode($pub),
            'subject' => str_starts_with($url, 'https://') ? $url : 'mailto:noreply@localhost.invalid',
        ];
    }

    public static function publicKey(): string
    {
        return WebPush::b64uEncode(self::vapid()['public']);
    }

    public static function subscribe(int $userId, string $endpoint, string $p256dh, string $auth): bool
    {
        if (!WebPush::isAllowedEndpoint($endpoint) || strlen($endpoint) > 1000
            || strlen(WebPush::b64uDecode($p256dh)) !== 65 || strlen(WebPush::b64uDecode($auth)) < 16) {
            return false;
        }
        Db::run(
            'INSERT INTO push_subscriptions (user_id, endpoint_hash, endpoint, p256dh, auth, created_at) VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), p256dh = VALUES(p256dh), auth = VALUES(auth)',
            [$userId, hash('sha256', $endpoint), $endpoint, $p256dh, $auth, Db::now()]
        );
        return true;
    }

    public static function unsubscribe(int $userId, string $endpoint): void
    {
        Db::run('DELETE FROM push_subscriptions WHERE user_id = ? AND endpoint_hash = ?', [$userId, hash('sha256', $endpoint)]);
    }

    public static function hasSubscription(int $userId): bool
    {
        return (bool) Db::value('SELECT 1 FROM push_subscriptions WHERE user_id = ? LIMIT 1', [$userId]);
    }

    /** Desa la notificació a l'aplicació i l'envia a tots els navegadors subscrits de l'usuari. */
    public static function notify(int $userId, string $title, string $body, string $path): void
    {
        Db::insert('notifications', [
            'user_id' => $userId, 'title' => mb_substr($title, 0, 190), 'body' => mb_substr($body, 0, 500),
            'url' => $path, 'created_at' => Db::now(),
        ]);
        $subs = Db::all('SELECT id, endpoint, p256dh, auth FROM push_subscriptions WHERE user_id = ?', [$userId]);
        if (!$subs) {
            return;
        }
        $payload = (string) json_encode(['title' => $title, 'body' => $body, 'url' => absolute_url($path)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $vapid = self::vapid();
        foreach ($subs as $s) {
            try {
                $status = WebPush::send($s, $payload, $vapid);
            } catch (\Throwable $e) {
                ErrorHandler::log($e);
                continue;
            }
            if (in_array($status, [404, 410], true)) {
                Db::run('DELETE FROM push_subscriptions WHERE id = ?', [$s['id']]);
            }
        }
    }

    public static function unreadCount(int $userId): int
    {
        return (int) Db::value('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL', [$userId]);
    }

    public static function recent(int $userId, int $limit = 30): array
    {
        return Db::all('SELECT id, title, body, url, created_at, read_at FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT ' . $limit, [$userId]);
    }

    public static function markAllRead(int $userId): void
    {
        Db::run('UPDATE notifications SET read_at = ? WHERE user_id = ? AND read_at IS NULL', [Db::now(), $userId]);
    }
}
