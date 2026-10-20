<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Web Push natiu (RFC 8030 + RFC 8291 aes128gcm + VAPID RFC 8292) amb OpenSSL, sense llibreries externes.
 */
final class WebPush
{
    /** Només per a proves: substitueix l'enviament HTTP. Rep (endpoint, capçaleres, cos) i retorna el codi HTTP. */
    public static ?\Closure $transport = null;

    private const SPKI_PREFIX = '3059301306072a8648ce3d020106082a8648ce3d030107034200';
    private const ALLOWED_HOSTS = ['.googleapis.com', '.mozilla.com', '.mozaws.net', '.push.apple.com', '.windows.com', '.notify.windows.com'];

    public static function b64uEncode(string $bin): string
    {
        return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
    }

    public static function b64uDecode(string $s): string
    {
        return (string) base64_decode(strtr($s, '-_', '+/') . str_repeat('=', (4 - strlen($s) % 4) % 4), true);
    }

    /** L'endpoint ha de ser HTTPS i d'un servei de push conegut (evita peticions a adreces internes). */
    public static function isAllowedEndpoint(string $endpoint): bool
    {
        $p = parse_url($endpoint);
        if (!$p || ($p['scheme'] ?? '') !== 'https' || empty($p['host']) || isset($p['user'])) {
            return false;
        }
        $host = strtolower($p['host']);
        foreach (self::ALLOWED_HOSTS as $suffix) {
            if (str_ends_with($host, $suffix) || $host === ltrim($suffix, '.')) {
                return true;
            }
        }
        return false;
    }

    /** @return array{private:string,public:string} claus en brut (32 i 65 bytes) */
    public static function generateKeyPair(): array
    {
        $key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        $d = openssl_pkey_get_details($key)['ec'];
        return [
            'private' => str_pad($d['d'], 32, "\0", STR_PAD_LEFT),
            'public' => "\x04" . str_pad($d['x'], 32, "\0", STR_PAD_LEFT) . str_pad($d['y'], 32, "\0", STR_PAD_LEFT),
        ];
    }

    private static function pem(string $label, string $der): string
    {
        return "-----BEGIN {$label}-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END {$label}-----\n";
    }

    private static function privateKeyPem(string $d, string $public): string
    {
        $der = hex2bin('30770201010420') . $d . hex2bin('a00a06082a8648ce3d030107a144034200') . $public;
        return self::pem('EC PRIVATE KEY', $der);
    }

    private static function publicKeyPem(string $public): string
    {
        return self::pem('PUBLIC KEY', hex2bin(self::SPKI_PREFIX) . $public);
    }

    /**
     * Xifra el missatge per a un navegador (aes128gcm). Els paràmetres opcionals existeixen per verificar amb el vector de l'RFC 8291.
     */
    public static function encrypt(string $payload, string $uaPublic, string $authSecret, ?array $asKeys = null, ?string $salt = null): string
    {
        $as = $asKeys ?? self::generateKeyPair();
        $salt ??= random_bytes(16);
        $secret = openssl_pkey_derive(self::publicKeyPem($uaPublic), self::privateKeyPem($as['private'], $as['public']), 32);
        if ($secret === false) {
            throw new \RuntimeException('ECDH fallit.');
        }
        $ikm = hash_hkdf('sha256', $secret, 32, "WebPush: info\0" . $uaPublic . $as['public'], $authSecret);
        $cek = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\0", $salt);
        $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\0", $salt);
        $tag = '';
        $cipher = openssl_encrypt($payload . "\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);
        if ($cipher === false) {
            throw new \RuntimeException('Xifrat fallit.');
        }
        return $salt . pack('N', 4096) . chr(65) . $as['public'] . $cipher . $tag;
    }

    public static function vapidAuthorization(string $endpoint, string $subject, string $privateRaw, string $publicRaw): string
    {
        $p = parse_url($endpoint);
        $aud = $p['scheme'] . '://' . $p['host'] . (isset($p['port']) ? ':' . $p['port'] : '');
        $input = self::b64uEncode((string) json_encode(['typ' => 'JWT', 'alg' => 'ES256']))
            . '.' . self::b64uEncode((string) json_encode(['aud' => $aud, 'exp' => time() + 12 * 3600, 'sub' => $subject]));
        openssl_sign($input, $der, self::privateKeyPem($privateRaw, $publicRaw), OPENSSL_ALGO_SHA256);
        return 'vapid t=' . $input . '.' . self::b64uEncode(self::derToRaw($der)) . ', k=' . self::b64uEncode($publicRaw);
    }

    private static function derToRaw(string $der): string
    {
        $o = 2 + (ord($der[1]) & 0x80 ? (ord($der[1]) & 0x7f) : 0);
        $rl = ord($der[$o + 1]);
        $r = substr($der, $o + 2, $rl);
        $sl = ord($der[$o + 3 + $rl]);
        $s = substr($der, $o + 4 + $rl, $sl);
        return str_pad(ltrim($r, "\0"), 32, "\0", STR_PAD_LEFT) . str_pad(ltrim($s, "\0"), 32, "\0", STR_PAD_LEFT);
    }

    /**
     * Envia una notificació. Retorna el codi HTTP (201 = acceptada, 404/410 = subscripció caducada, 0 = error de xarxa).
     *
     * @param array{endpoint:string,p256dh:string,auth:string} $sub
     * @param array{private:string,public:string,subject:string} $vapid
     */
    public static function send(array $sub, string $payload, array $vapid): int
    {
        if (!self::isAllowedEndpoint($sub['endpoint'])) {
            return 400;
        }
        $body = self::encrypt($payload, self::b64uDecode($sub['p256dh']), self::b64uDecode($sub['auth']));
        $headers = [
            'Authorization: ' . self::vapidAuthorization($sub['endpoint'], $vapid['subject'], $vapid['private'], $vapid['public']),
            'Content-Encoding: aes128gcm', 'Content-Type: application/octet-stream', 'TTL: 86400', 'Urgency: normal',
            'Content-Length: ' . strlen($body),
        ];
        if (self::$transport !== null) {
            return (int) (self::$transport)($sub['endpoint'], $headers, $body);
        }
        $ctx = stream_context_create(['http' => ['method' => 'POST', 'header' => implode("\r\n", $headers), 'content' => $body, 'timeout' => 6, 'ignore_errors' => true]]);
        @file_get_contents($sub['endpoint'], false, $ctx);
        foreach ($http_response_header ?? [] as $line) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $m)) {
                return (int) $m[1];
            }
        }
        return 0;
    }
}
