<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Crypto;
use PDO;

/** Lògica de la instal·lació, compartida pel instal·lador web i per bin/install.php. */
final class Installer
{
    public const REQUIRED_EXTENSIONS = ['pdo', 'pdo_mysql', 'mbstring', 'fileinfo', 'sodium', 'openssl', 'session'];

    public static function lockFile(): string
    {
        return BASE_PATH . '/storage/installed.lock';
    }

    public static function isInstalled(): bool
    {
        return is_file(self::lockFile());
    }

    /** @return array<int,array{label:string,ok:bool,detail:string}> */
    public static function checkRequirements(): array
    {
        $checks = [['label' => 'PHP 8.4 o superior', 'ok' => version_compare(PHP_VERSION, '8.4.0', '>='), 'detail' => 'Versió actual: ' . PHP_VERSION]];
        foreach (self::REQUIRED_EXTENSIONS as $ext) {
            $checks[] = ['label' => 'Extensió ' . $ext, 'ok' => extension_loaded($ext), 'detail' => ''];
        }
        $checks[] = ['label' => 'PHPMailer instal·lat (composer install)', 'ok' => class_exists(\PHPMailer\PHPMailer\PHPMailer::class), 'detail' => ''];
        foreach (['storage', 'storage/logs', 'storage/uploads'] as $dir) {
            $path = BASE_PATH . '/' . $dir;
            if (!is_dir($path)) {
                @mkdir($path, 0750, true);
            }
            $checks[] = ['label' => "Escriptura a {$dir}/", 'ok' => is_dir($path) && is_writable($path), 'detail' => ''];
        }
        $envOk = is_file(BASE_PATH . '/.env') ? is_writable(BASE_PATH . '/.env') : is_writable(BASE_PATH);
        $checks[] = ['label' => 'Es pot escriure el fitxer .env', 'ok' => $envOk, 'detail' => ''];
        return $checks;
    }

    public static function requirementsMet(): bool
    {
        foreach (self::checkRequirements() as $c) {
            if (!$c['ok']) {
                return false;
            }
        }
        return true;
    }

    /**
     * Executa la instal·lació. Retorna una llista d'errors (buida si tot ha anat bé).
     *
     * @param array{app_url:string,db_host:string,db_port:string,db_name:string,db_user:string,db_pass:string,admin_user:string,admin_pass:string} $c
     * @return string[]
     */
    public static function run(array $c, bool $https): array
    {
        if (self::isInstalled()) {
            return ['La instal·lació ja s\'ha completat.'];
        }
        if (!self::requirementsMet()) {
            return ['No es compleixen tots els requisits del sistema.'];
        }
        $errors = [];
        if (!preg_match('#^https?://[^\s/]+(/\S*)?$#', $c['app_url'])) {
            $errors[] = 'L\'URL de l\'aplicació no és vàlida (ha de començar per https://).';
        }
        if ($c['db_name'] === '' || $c['db_user'] === '' || !ctype_digit($c['db_port'])) {
            $errors[] = 'Les dades de connexió a la base de dades són incompletes.';
        }
        if ($c['admin_user'] === '' || strlen($c['admin_pass']) < 8) {
            $errors[] = 'L\'usuari administrador i una contrasenya inicial (mínim 8 caràcters) són obligatoris.';
        }
        if ($errors) {
            return $errors;
        }

        try {
            $pdo = new PDO(
                sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $c['db_host'], (int) $c['db_port'], $c['db_name']),
                $c['db_user'],
                $c['db_pass'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
        } catch (\PDOException) {
            return ['No s\'ha pogut connectar amb la base de dades. Comprova el servidor, la base de dades, l\'usuari i la contrasenya.'];
        }

        $version = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
        $isMaria = stripos($version, 'mariadb') !== false;
        if (!$isMaria && version_compare($version, '8.0.0', '<')) {
            return ['Cal MySQL 8 o MariaDB. Versió detectada: ' . $version];
        }

        try {
            self::writeEnv($c, $https);
            $files = glob(BASE_PATH . '/database/migrations/*.sql') ?: [];
            sort($files);
            foreach ([...$files, BASE_PATH . '/database/seeds/001_initial_data.sql'] as $file) {
                self::runSqlFile($pdo, $file);
            }
            self::createAdmin($pdo, $c['admin_user'], $c['admin_pass']);
            file_put_contents(self::lockFile(), 'Instal·lat el ' . date('Y-m-d H:i:s') . "\n");
            @chmod(self::lockFile(), 0640);
        } catch (\Throwable $e) {
            ErrorHandler::log($e);
            return ['Error durant la instal·lació. Consulta storage/logs per veure els detalls.'];
        }
        return [];
    }

    public static function runSqlFile(PDO $pdo, string $file): void
    {
        $sql = (string) file_get_contents($file);
        $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
        foreach (preg_split('/;\s*(?:\r?\n|$)/', $sql) ?: [] as $stmt) {
            if (trim($stmt) !== '') {
                $pdo->exec($stmt);
            }
        }
    }

    public static function createAdmin(PDO $pdo, string $username, string $password): void
    {
        $exists = $pdo->prepare('SELECT id FROM users WHERE username = ?');
        $exists->execute([mb_strtolower($username)]);
        if ($exists->fetchColumn()) {
            return;
        }
        $now = date('Y-m-d H:i:s');
        $pdo->beginTransaction();
        $pdo->prepare(
            'INSERT INTO users (username, first_name, last_name, email, password_hash, is_active, must_change_password, created_at, updated_at)
             VALUES (?, ?, ?, NULL, ?, 1, 1, ?, ?)'
        )->execute([mb_strtolower($username), $username, '', password_hash($password, PASSWORD_DEFAULT), $now, $now]);
        $id = (int) $pdo->lastInsertId();
        $pdo->prepare('INSERT INTO user_roles (user_id, role_id) VALUES (?, 1)')->execute([$id]);
        $pdo->commit();
    }

    /** Genera el fitxer .env a partir de .env.example, amb clau nova i sense la contrasenya inicial. */
    private static function writeEnv(array $c, bool $https): void
    {
        $existing = is_file(BASE_PATH . '/.env') ? (string) file_get_contents(BASE_PATH . '/.env') : (string) file_get_contents(BASE_PATH . '/.env.example');
        preg_match('/^APP_KEY=(.+)$/m', $existing, $m);
        $key = isset($m[1]) && base64_decode(trim($m[1]), true) !== false && strlen((string) base64_decode(trim($m[1]), true)) === 32
            ? trim($m[1]) : Crypto::generateKey();

        $values = [
            'APP_URL' => rtrim($c['app_url'], '/'),
            'DB_HOST' => $c['db_host'], 'DB_PORT' => $c['db_port'], 'DB_NAME' => $c['db_name'], 'DB_USER' => $c['db_user'],
            'DB_PASS' => $c['db_pass'], 'APP_KEY' => $key, 'INITIAL_ADMIN_USERNAME' => $c['admin_user'], 'INITIAL_ADMIN_PASSWORD' => '',
            'FORCE_HTTPS' => $https ? 'true' : 'false',
        ];
        $content = $existing;
        foreach ($values as $k => $v) {
            $line = $k . '=' . (preg_match('/[\s#"\'\\\\]/', $v) ? '"' . addcslashes($v, '"\\') . '"' : $v);
            $content = preg_match("/^{$k}=.*$/m", $content)
                ? preg_replace("/^{$k}=.*$/m", str_replace(['\\', '$'], ['\\\\', '\\$'], $line), $content)
                : rtrim($content) . "\n" . $line . "\n";
        }
        if (file_put_contents(BASE_PATH . '/.env', $content) === false) {
            throw new \RuntimeException('No es pot escriure .env');
        }
        @chmod(BASE_PATH . '/.env', 0640);
    }
}
