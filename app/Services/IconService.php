<?php

declare(strict_types=1);

namespace App\Services;

/** Gestió de les icones de l'aplicació (favicon, Apple, PWA i maskable). */
final class IconService
{
    /** nom => [mida, descripció, ús] */
    public const ICONS = [
        'favicon-16' => [16, 'Favicon 16×16', 'Pestanya del navegador'],
        'favicon-32' => [32, 'Favicon 32×32', 'Pestanya del navegador i drecera'],
        'apple-touch-icon' => [180, 'Icona Apple 180×180', 'Pantalla d\'inici de l\'iPhone i l\'iPad'],
        'icon-192' => [192, 'Icona PWA 192×192', 'Android i escriptori'],
        'icon-512' => [512, 'Icona PWA 512×512', 'Pantalla de benvinguda i instal·lació'],
        'icon-maskable-512' => [512, 'Icona maskable 512×512', 'Android (s\'adapta a formes rodones o quadrades)'],
    ];

    public static function hasGd(): bool
    {
        return extension_loaded('gd');
    }

    public static function dir(): string
    {
        return FileService::directory('icons');
    }

    public static function isValidName(string $name): bool
    {
        return isset(self::ICONS[$name]);
    }

    public static function customPath(string $name): string
    {
        return self::dir() . '/' . $name . '.png';
    }

    public static function isCustom(string $name): bool
    {
        return self::isValidName($name) && is_file(self::customPath($name));
    }

    /** Ruta del fitxer que s'ha de servir: la personalitzada o la icona per defecte. */
    public static function path(string $name): ?string
    {
        if (!self::isValidName($name)) {
            return null;
        }
        $custom = self::customPath($name);
        return is_file($custom) ? $custom : BASE_PATH . '/public/assets/img/icons/' . $name . '.png';
    }

    public static function version(): string
    {
        return SettingsService::get('icons_version', '1');
    }

    public static function bumpVersion(): void
    {
        SettingsService::set('icons_version', (string) time());
    }

    public static function url(string $name): string
    {
        return url('/icones/' . $name, ['v' => self::version()]);
    }

    /** Genera una icona quadrada a partir d'una imatge. $bg = color de fons (#rrggbb) o null per transparent. */
    public static function render(\GdImage $src, int $size, ?string $bg, float $scale): string
    {
        $canvas = imagecreatetruecolor($size, $size);
        imagesavealpha($canvas, true);
        imagealphablending($canvas, false);
        if ($bg !== null) {
            [$r, $g, $b] = sscanf($bg, '#%02x%02x%02x');
            imagefill($canvas, 0, 0, imagecolorallocate($canvas, $r, $g, $b));
        } else {
            imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));
        }
        imagealphablending($canvas, true);
        $sw = imagesx($src);
        $sh = imagesy($src);
        $ratio = min($size * $scale / $sw, $size * $scale / $sh);
        $dw = max(1, (int) round($sw * $ratio));
        $dh = max(1, (int) round($sh * $ratio));
        imagecopyresampled($canvas, $src, intdiv($size - $dw, 2), intdiv($size - $dh, 2), 0, 0, $dw, $dh, $sw, $sh);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        ob_start();
        imagepng($canvas, null, 9);
        return (string) ob_get_clean();
    }

    /** Genera totes les icones (o només les no personalitzades) a partir del logotip. Retorna un error o null. */
    public static function generateFromLogo(bool $overwriteCustom): ?string
    {
        if (!self::hasGd()) {
            return 'L\'extensió GD de PHP no està disponible. Puja les icones una per una.';
        }
        $logo = SettingsService::logoPath();
        if ($logo === null) {
            return 'Cal pujar primer el logotip del club.';
        }
        $src = @imagecreatefromstring((string) file_get_contents($logo));
        if ($src === false) {
            return 'No s\'ha pogut llegir el logotip.';
        }
        $pwa = SettingsService::pwa();
        foreach (self::ICONS as $name => [$size]) {
            if (!$overwriteCustom && self::isCustom($name)) {
                continue;
            }
            [$bg, $scale] = match ($name) {
                'apple-touch-icon' => [$pwa['bg'], 0.86],
                'icon-maskable-512' => [$pwa['theme'], 0.58],
                'favicon-16', 'favicon-32' => [null, 1.0],
                default => [null, 0.92],
            };
            file_put_contents(self::customPath($name), self::render($src, $size, $bg, $scale));
            @chmod(self::customPath($name), 0640);
        }
        self::bumpVersion();
        return null;
    }

    /** Desa una icona pujada manualment. Retorna un error o null. */
    public static function saveUpload(string $name, array $file): ?string
    {
        if (!self::isValidName($name)) {
            return 'Icona desconeguda.';
        }
        [$checked, $err] = FileService::validate($file, ['png']);
        if ($err !== null) {
            return $err;
        }
        $info = @getimagesize($file['tmp_name']);
        if ($info === false || $info[0] !== $info[1]) {
            return 'La icona ha de ser una imatge PNG quadrada.';
        }
        $size = self::ICONS[$name][0];
        $data = (string) file_get_contents($file['tmp_name']);
        if ($info[0] !== $size) {
            if (!self::hasGd()) {
                return "La icona ha de fer exactament {$size}×{$size} px.";
            }
            $src = @imagecreatefromstring($data);
            if ($src === false) {
                return 'No s\'ha pogut llegir la imatge.';
            }
            $data = self::render($src, $size, null, 1.0);
        }
        file_put_contents(self::customPath($name), $data);
        @chmod(self::customPath($name), 0640);
        self::bumpVersion();
        return null;
    }

    public static function reset(string $name): void
    {
        if (self::isCustom($name)) {
            @unlink(self::customPath($name));
            self::bumpVersion();
        }
    }
}
