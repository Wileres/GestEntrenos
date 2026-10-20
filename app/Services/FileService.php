<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Config;

final class FileService
{
    /** Només per a proves: permet moure fitxers que no provenen d'una pujada HTTP. */
    public static bool $testing = false;

    private const MIME_BY_EXT = [
        'pdf' => ['application/pdf'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'webp' => ['image/webp'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream'],
        'mp4' => ['video/mp4'],
    ];

    private const BLOCKED_SEGMENT = '/^(php\d?|phtml|phar|pht|exe|dll|bat|cmd|sh|js|html?|cgi|pl|py|asp|aspx|jsp|jar|com|msi|vbs|svg|htaccess|pdf|jpe?g|png|webp|docx?|xlsx?|mp4)$/i';

    public static function allowedExtensions(): array
    {
        $exts = array_keys(self::MIME_BY_EXT);
        return Config::get('upload.allow_mp4') ? $exts : array_values(array_diff($exts, ['mp4']));
    }

    public static function uploadError(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'El fitxer supera la mida màxima permesa.',
            UPLOAD_ERR_PARTIAL => 'El fitxer s\'ha pujat només parcialment.',
            default => 'No s\'ha pogut pujar el fitxer.',
        };
    }

    /**
     * Valida un fitxer pujat. Retorna [extensió, mime] o un missatge d'error.
     *
     * @return array{0:?array{ext:string,mime:string},1:?string}
     */
    public static function validate(array $file, ?array $onlyExtensions = null): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return [null, self::uploadError((int) ($file['error'] ?? 0))];
        }
        $name = (string) ($file['name'] ?? '');
        $size = (int) ($file['size'] ?? 0);
        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($size <= 0 || $size > (int) Config::get('upload.max_bytes')) {
            $mb = (int) round((int) Config::get('upload.max_bytes') / 1048576);
            return [null, "El fitxer «{$name}» supera la mida màxima de {$mb} MB o és buit."];
        }
        if (!self::$testing && !is_uploaded_file($tmp)) {
            return [null, 'Fitxer no vàlid.'];
        }
        if (preg_match('/[\x00-\x1F\/\\\\]/', $name)) {
            return [null, 'El nom del fitxer no és vàlid.'];
        }
        $parts = explode('.', $name);
        if (count($parts) < 2) {
            return [null, "El fitxer «{$name}» no té extensió."];
        }
        $ext = strtolower((string) array_pop($parts));
        foreach ($parts as $i => $segment) {
            if ($i > 0 && preg_match(self::BLOCKED_SEGMENT, $segment)) {
                return [null, "El fitxer «{$name}» té una doble extensió i es rebutja."];
            }
        }
        $allowed = $onlyExtensions ?? self::allowedExtensions();
        if (!in_array($ext, $allowed, true) || !isset(self::MIME_BY_EXT[$ext])) {
            return [null, "El tipus de fitxer «.{$ext}» no està permès."];
        }
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($tmp);
        if (!in_array($mime, self::MIME_BY_EXT[$ext], true)) {
            return [null, "El contingut del fitxer «{$name}» no coincideix amb la seva extensió."];
        }
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true) && @getimagesize($tmp) === false) {
            return [null, "La imatge «{$name}» no és vàlida."];
        }
        if (in_array($ext, ['docx', 'xlsx'], true) && !self::looksLikeOffice($tmp, $ext)) {
            return [null, "El document «{$name}» no és vàlid."];
        }
        return [['ext' => $ext, 'mime' => $mime], null];
    }

    private static function looksLikeOffice(string $path, string $ext): bool
    {
        if (!class_exists(\ZipArchive::class)) {
            return str_starts_with((string) file_get_contents($path, false, null, 0, 4), "PK\x03\x04");
        }
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            return false;
        }
        $ok = $zip->locateName('[Content_Types].xml') !== false
            && $zip->locateName($ext === 'docx' ? 'word/document.xml' : 'xl/workbook.xml') !== false;
        $zip->close();
        return $ok;
    }

    /** Converteix $_FILES['x'] múltiple en una llista de fitxers, ometent els camps buits. */
    public static function normalize(?array $files): array
    {
        if ($files === null || !isset($files['name'])) {
            return [];
        }
        if (!is_array($files['name'])) {
            return ($files['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE ? [] : [$files];
        }
        $out = [];
        foreach ($files['name'] as $i => $name) {
            if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $out[] = [
                'name' => $name, 'type' => $files['type'][$i] ?? '', 'tmp_name' => $files['tmp_name'][$i] ?? '',
                'error' => $files['error'][$i], 'size' => $files['size'][$i] ?? 0,
            ];
        }
        return $out;
    }

    public static function directory(string $sub): string
    {
        $dir = BASE_PATH . '/storage/uploads/' . $sub;
        if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new \RuntimeException('No es pot crear el directori de pujades.');
        }
        return $dir;
    }

    /**
     * Desa un fitxer ja validat amb un nom aleatori i sense extensió.
     *
     * @return array{stored_name:string,original_name:string,mime_type:string,size_bytes:int}
     */
    public static function store(array $file, array $checked, string $sub): array
    {
        $stored = bin2hex(random_bytes(20));
        $target = self::directory($sub) . '/' . $stored;
        $moved = self::$testing ? copy($file['tmp_name'], $target) : move_uploaded_file($file['tmp_name'], $target);
        if (!$moved) {
            throw new \RuntimeException('No s\'ha pogut desar el fitxer.');
        }
        @chmod($target, 0640);
        return [
            'stored_name' => $stored,
            'original_name' => self::cleanName((string) $file['name']),
            'mime_type' => $checked['mime'],
            'size_bytes' => (int) $file['size'],
        ];
    }

    public static function cleanName(string $name): string
    {
        $name = preg_replace('/[\x00-\x1F\x7F"\\\\\/]/u', '', $name) ?? '';
        return mb_substr($name, 0, 255);
    }

    public static function path(string $sub, string $stored): ?string
    {
        if (!preg_match('/^[a-f0-9]{40}$/', $stored)) {
            return null;
        }
        $path = BASE_PATH . '/storage/uploads/' . $sub . '/' . $stored;
        return is_file($path) ? $path : null;
    }

    public static function delete(string $sub, string $stored): void
    {
        $path = self::path($sub, $stored);
        if ($path !== null) {
            @unlink($path);
        }
    }

    /** Tipus que el navegador pot mostrar directament sense risc (no executen codi). */
    public static function isViewable(string $mime): bool
    {
        return in_array($mime, ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'], true);
    }

    public static function stream(string $path, string $mime, string $downloadName, bool $inline = false): never
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        $ascii = preg_replace('/[^A-Za-z0-9._-]/', '_', $downloadName) ?: 'fitxer';
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($path));
        $inline = $inline && self::isViewable($mime);
        header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . "; filename=\"{$ascii}\"; filename*=UTF-8''" . rawurlencode($downloadName));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store');
        $h = fopen($path, 'rb');
        while (!feof($h)) {
            echo fread($h, 65536);
            flush();
        }
        fclose($h);
        exit;
    }
}
