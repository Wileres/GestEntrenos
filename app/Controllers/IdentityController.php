<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Request;
use App\Services\AuditService;
use App\Services\FileService;
use App\Services\IconService;
use App\Services\SettingsService;
use App\Validators\Validator;

/** Logotip del club, icones (favicon, Apple, PWA) i ajustos de l'aplicació instal·lable. */
final class IdentityController extends Controller
{
    private const BACK = '/admin/identitat';

    public function index(Request $r, array $p): void
    {
        $icons = [];
        foreach (IconService::ICONS as $name => [$size, $label, $usage]) {
            $icons[$name] = ['size' => $size, 'label' => $label, 'usage' => $usage, 'custom' => IconService::isCustom($name)];
        }
        $pwa = SettingsService::pwa();
        $this->render('admin/identity', [
            'title' => 'Logotip i icones', 'hasLogo' => SettingsService::logoPath() !== null, 'icons' => $icons,
            'hasGd' => IconService::hasGd(), 'version' => IconService::version(),
            'old' => ['pwa_name' => $pwa['name'], 'pwa_short_name' => $pwa['short'], 'pwa_theme_color' => $pwa['theme'], 'pwa_bg_color' => $pwa['bg']],
        ]);
    }

    public function logoSave(Request $r, array $p): void
    {
        $files = FileService::normalize($r->files['logo'] ?? null);
        if (!$files) {
            $this->done('Selecciona una imatge (PNG, JPG o WEBP).', self::BACK, 'error');
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
            $this->done($err, self::BACK, 'error');
        }
        $ext = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'][$checked['mime']];
        $name = 'logo-' . bin2hex(random_bytes(8)) . '.' . $ext;
        $target = FileService::directory('logo') . '/' . $name;
        $moved = FileService::$testing ? copy($files[0]['tmp_name'], $target) : move_uploaded_file($files[0]['tmp_name'], $target);
        if (!$moved) {
            $this->done('No s\'ha pogut desar el logotip.', self::BACK, 'error');
        }
        @chmod($target, 0640);
        $oldPath = SettingsService::logoPath();
        SettingsService::set('logo_file', $name);
        if ($oldPath !== null) {
            @unlink($oldPath);
        }
        AuditService::log('logo_changed', 'settings');
        $msg = 'Logotip actualitzat.';
        if (IconService::hasGd() && IconService::generateFromLogo(false) === null) {
            $msg .= ' Les icones que no eren personalitzades s\'han regenerat amb el nou logotip.';
        }
        $this->done($msg, self::BACK);
    }

    public function generate(Request $r, array $p): void
    {
        $err = IconService::generateFromLogo(true);
        if ($err !== null) {
            $this->done($err, self::BACK, 'error');
        }
        AuditService::log('icons_generated', 'settings');
        $this->done('Totes les icones s\'han generat a partir del logotip.', self::BACK);
    }

    public function iconUpload(Request $r, array $p): void
    {
        $name = (string) $p['name'];
        if (!IconService::isValidName($name)) {
            $this->done('Icona desconeguda.', self::BACK, 'error');
        }
        $files = FileService::normalize($r->files['icon'] ?? null);
        if (!$files) {
            $this->done('Selecciona un fitxer PNG.', self::BACK, 'error');
        }
        $err = IconService::saveUpload($name, $files[0]);
        if ($err !== null) {
            $this->done($err, self::BACK, 'error');
        }
        AuditService::log('icon_uploaded', 'settings', null, 'ok', $name);
        $this->done('Icona desada.', self::BACK);
    }

    public function iconReset(Request $r, array $p): void
    {
        $name = (string) $p['name'];
        if (!IconService::isValidName($name)) {
            $this->done('Icona desconeguda.', self::BACK, 'error');
        }
        IconService::reset($name);
        AuditService::log('icon_reset', 'settings', null, 'ok', $name);
        $this->done('S\'ha restaurat la icona per defecte.', self::BACK);
    }

    public function pwaSave(Request $r, array $p): void
    {
        $d = [];
        foreach (['pwa_name', 'pwa_short_name', 'pwa_theme_color', 'pwa_bg_color'] as $k) {
            $d[$k] = $r->str($k);
        }
        $v = (new Validator($d))->required('pwa_name', 'Nom de l\'aplicació')->maxLen('pwa_name', 'Nom de l\'aplicació', 45)
            ->required('pwa_short_name', 'Nom curt')->maxLen('pwa_short_name', 'Nom curt', 12);
        foreach (['pwa_theme_color' => 'Color del tema', 'pwa_bg_color' => 'Color de fons'] as $k => $label) {
            if (!preg_match('/^#[0-9a-fA-F]{6}$/', $d[$k])) {
                $v->error($k, "El camp «{$label}» ha de ser un color hexadecimal (#rrggbb).");
            }
        }
        if ($v->fails()) {
            $icons = [];
            foreach (IconService::ICONS as $name => [$size, $label, $usage]) {
                $icons[$name] = ['size' => $size, 'label' => $label, 'usage' => $usage, 'custom' => IconService::isCustom($name)];
            }
            $this->invalid('admin/identity', [
                'title' => 'Logotip i icones', 'hasLogo' => SettingsService::logoPath() !== null, 'icons' => $icons,
                'hasGd' => IconService::hasGd(), 'version' => IconService::version(),
            ], $v->errors(), $d);
            return;
        }
        SettingsService::set('pwa_name', $d['pwa_name']);
        SettingsService::set('pwa_short_name', $d['pwa_short_name']);
        SettingsService::set('pwa_theme_color', strtolower($d['pwa_theme_color']));
        SettingsService::set('pwa_bg_color', strtolower($d['pwa_bg_color']));
        IconService::bumpVersion();
        AuditService::log('pwa_settings_changed', 'settings');
        $this->done('Ajustos de l\'aplicació desats. Per aplicar-los a la pantalla d\'inici pot caldre tornar a instal·lar l\'aplicació.', self::BACK);
    }
}
