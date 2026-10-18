<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Helpers\Env;
use App\Helpers\Request;
use App\Services\Installer;

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'self'; style-src 'self'; form-action 'self'; frame-ancestors 'none'");
header('Cache-Control: no-store');
header('Content-Type: text/html; charset=utf-8');

$h = fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$css = Request::basePath() . '/assets/css/app.css';

if (Installer::isInstalled()) {
    http_response_code(403);
    echo '<!doctype html><html lang="ca"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><link rel="stylesheet" href="' . $h($css) . '">'
        . '<body class="auth-body"><main class="auth-card"><h1 class="auth-title">Instal·lador bloquejat</h1>'
        . '<p>L\'aplicació ja està instal·lada. Per seguretat, elimina el fitxer <strong>public/install.php</strong> del servidor.</p>'
        . '<p class="center"><a class="btn btn-primary" href="' . $h(Request::basePath() . '/') . '">Ves a l\'aplicació</a></p></main></body></html>';
    exit;
}

session_name('jspm_install');
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => Request::isHttps()]);
session_start();
$_SESSION['csrf'] ??= bin2hex(random_bytes(32));

if (!is_file(BASE_PATH . '/.env')) {
    Env::load(BASE_PATH . '/.env.example');
}
$https = Request::isHttps();
$scheme = $https ? 'https' : 'http';
$defaultUrl = $scheme . '://' . preg_replace('/[^A-Za-z0-9.:-]/', '', (string) ($_SERVER['HTTP_HOST'] ?? 'localhost')) . Request::basePath();

$v = [
    'app_url' => $defaultUrl,
    'db_host' => Env::get('DB_HOST', '127.0.0.1'), 'db_port' => Env::get('DB_PORT', '3306'), 'db_name' => Env::get('DB_NAME', ''),
    'db_user' => Env::get('DB_USER', ''), 'db_pass' => '', 'admin_user' => Env::get('INITIAL_ADMIN_USERNAME', 'Gestor'), 'admin_pass' => '',
];
$errors = [];
$done = false;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!hash_equals($_SESSION['csrf'], (string) ($_POST['csrf'] ?? ''))) {
        http_response_code(419);
        $errors[] = 'El formulari ha caducat. Recarrega la pàgina.';
    } else {
        foreach ($v as $k => $_) {
            $v[$k] = is_string($_POST[$k] ?? null) ? trim($_POST[$k]) : '';
        }
        $v['db_pass'] = (string) ($_POST['db_pass'] ?? '');
        $v['admin_pass'] = (string) ($_POST['admin_pass'] ?? '');
        if ($v['admin_pass'] === '') {
            $v['admin_pass'] = (string) Env::get('INITIAL_ADMIN_PASSWORD', '');
        }
        $errors = Installer::run($v, $https);
        $done = $errors === [];
    }
}
$checks = Installer::checkRequirements();
$ready = Installer::requirementsMet();
?>
<!doctype html>
<html lang="ca"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex"><title>Instal·lació · Entrenaments JSPM</title><link rel="stylesheet" href="<?= $h($css) ?>"></head>
<body class="auth-body"><main class="auth-card auth-wide">
<h1 class="auth-title">Instal·lació d'Entrenaments JSPM</h1>
<?php if ($done): ?>
    <div class="alert alert-success">Instal·lació completada. L'usuari administrador haurà de canviar la contrasenya en el primer accés.</div>
    <p>L'instal·lador ha quedat bloquejat. <strong>Elimina el fitxer public/install.php del servidor.</strong></p>
    <p class="center"><a class="btn btn-primary" href="<?= $h(Request::basePath() . '/acces') ?>">Ves a l'accés</a></p>
<?php else: ?>
    <h2>1. Requisits</h2>
    <ul class="list"><?php foreach ($checks as $c): ?>
        <li><span class="badge badge-<?= $c['ok'] ? 'success' : 'danger' ?>"><?= $c['ok'] ? 'OK' : 'Falla' ?></span> <?= $h($c['label']) ?> <small class="muted"><?= $h($c['detail']) ?></small></li>
    <?php endforeach; ?></ul>
    <?php if (!$https): ?><div class="alert alert-warning">Estàs accedint sense HTTPS. S'instal·larà amb FORCE_HTTPS=false; activa'l en producció.</div><?php endif; ?>
    <?php foreach ($errors as $e): ?><div class="alert alert-error" role="alert"><?= $h($e) ?></div><?php endforeach; ?>
    <?php if ($ready): ?>
    <h2>2. Configuració</h2>
    <form method="post" class="stack">
        <input type="hidden" name="csrf" value="<?= $h($_SESSION['csrf']) ?>">
        <?php
        $fields = [
            'app_url' => ['URL de l\'aplicació', 'text'], 'db_host' => ['Servidor MySQL', 'text'], 'db_port' => ['Port MySQL', 'text'],
            'db_name' => ['Base de dades (ha d\'existir)', 'text'], 'db_user' => ['Usuari de la base de dades', 'text'],
            'db_pass' => ['Contrasenya de la base de dades', 'password'], 'admin_user' => ['Usuari administrador', 'text'],
            'admin_pass' => ['Contrasenya inicial de l\'administrador (buit = la del fitxer .env)', 'password'],
        ];
        foreach ($fields as $name => [$label, $type]): ?>
            <div class="field"><label for="<?= $name ?>"><?= $h($label) ?></label>
            <input id="<?= $name ?>" name="<?= $name ?>" type="<?= $type ?>" value="<?= $type === 'password' ? '' : $h($v[$name]) ?>" autocomplete="off"></div>
        <?php endforeach; ?>
        <button class="btn btn-primary btn-block" type="submit">Instal·la</button>
    </form>
    <?php endif; ?>
<?php endif; ?>
</main></body></html>
