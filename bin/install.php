<?php

declare(strict_types=1);

/**
 * Instal·lació per línia d'ordres. Requereix un .env amb DB_* i INITIAL_ADMIN_*.
 * Ús: php bin/install.php
 */
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Helpers\Env;
use App\Services\Installer;

if (PHP_SAPI !== 'cli') {
    exit('Només des de la línia d\'ordres.');
}
if (!is_file(BASE_PATH . '/.env')) {
    fwrite(STDERR, "Copia .env.example a .env i omple les dades DB_* abans d'instal·lar.\n");
    exit(1);
}
$cfg = [
    'app_url' => (string) Env::get('APP_URL', 'http://localhost'),
    'db_host' => (string) Env::get('DB_HOST', '127.0.0.1'), 'db_port' => (string) Env::get('DB_PORT', '3306'),
    'db_name' => (string) Env::get('DB_NAME', ''), 'db_user' => (string) Env::get('DB_USER', ''), 'db_pass' => (string) Env::get('DB_PASS', ''),
    'admin_user' => (string) Env::get('INITIAL_ADMIN_USERNAME', 'Gestor'), 'admin_pass' => (string) Env::get('INITIAL_ADMIN_PASSWORD', ''),
];
$errors = Installer::run($cfg, str_starts_with($cfg['app_url'], 'https://'));
foreach ($errors as $e) {
    fwrite(STDERR, "ERROR: $e\n");
}
echo $errors ? '' : "Instal·lació completada. Elimina public/install.php.\n";
exit($errors ? 1 : 0);
