<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Crypto;
use App\Helpers\Db;

final class SettingsService
{
    public static function get(string $key, string $default = ''): string
    {
        $v = Db::value('SELECT setting_value FROM application_settings WHERE setting_key = ?', [$key]);
        return $v === null ? $default : (string) $v;
    }

    public static function set(string $key, string $value): void
    {
        Db::run(
            'INSERT INTO application_settings (setting_key, setting_value, updated_at) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = VALUES(updated_at)',
            [$key, $value, Db::now()]
        );
    }

    public static function clubName(): string
    {
        return self::get('club_name', 'Joventut Sant Pere Màrtir');
    }

    public static function logoPath(): ?string
    {
        $file = self::get('logo_file');
        if ($file === '' || !preg_match('/^logo-[a-f0-9]{16}\.(png|jpg|webp)$/', $file)) {
            return null;
        }
        $path = BASE_PATH . '/storage/uploads/logo/' . $file;
        return is_file($path) ? $path : null;
    }

    /** @return array{name:string,short:string,theme:string,bg:string} */
    public static function pwa(): array
    {
        return [
            'name' => self::get('pwa_name', 'Entrenaments JSPM'),
            'short' => self::get('pwa_short_name', 'JSPM'),
            'theme' => self::get('pwa_theme_color', '#0b3d91'),
            'bg' => self::get('pwa_bg_color', '#ffffff'),
        ];
    }

    public static function smtp(): array
    {
        return Db::one('SELECT * FROM smtp_settings WHERE id = 1') ?? [
            'host' => '', 'port' => 587, 'encryption' => 'tls', 'username' => '', 'password_enc' => null,
            'from_email' => '', 'from_name' => '', 'reply_to' => '', 'enabled' => 0,
        ];
    }

    /** Desa la configuració SMTP. La contrasenya només es canvia si se'n proporciona una de nova. */
    public static function saveSmtp(array $d, ?string $newPassword): void
    {
        $data = [
            'host' => $d['host'], 'port' => $d['port'], 'encryption' => $d['encryption'], 'username' => $d['username'],
            'from_email' => $d['from_email'], 'from_name' => $d['from_name'], 'reply_to' => $d['reply_to'],
            'enabled' => $d['enabled'] ? 1 : 0, 'updated_at' => Db::now(),
        ];
        if ($newPassword !== null && $newPassword !== '') {
            $data['password_enc'] = Crypto::encrypt($newPassword);
        }
        Db::update('smtp_settings', $data, 'id = 1');
    }

    public static function maskedPassword(array $smtp): string
    {
        return empty($smtp['password_enc']) ? '' : '••••••••';
    }
}
