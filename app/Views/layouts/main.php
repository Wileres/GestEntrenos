<?php
$user = current_user();
$admin = is_admin();
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$rel = substr($path, strlen(\App\Helpers\Request::basePath())) ?: '/';
$active = fn (string $p) => ($p === '/' ? $rel === '/' : str_starts_with($rel, $p)) ? ' aria-current="page"' : '';
$logo = \App\Services\SettingsService::logoPath() !== null;
$club = \App\Services\SettingsService::clubName();
?>
<!doctype html>
<html lang="ca">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($title ?? 'Entrenaments') ?> · Entrenaments JSPM</title>
<?php \App\Helpers\View::component('head_pwa'); ?>
    <link rel="preload" href="<?= e(asset('fonts/inter-latin.woff2')) ?>" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
    <script src="<?= e(asset('js/app.js')) ?>" defer></script>
</head>
<body>
<a class="skip-link" href="#contingut">Salta al contingut</a>
<header class="app-header">
    <div class="container header-inner">
        <button class="menu-btn" type="button" aria-expanded="false" aria-controls="menu-principal"><span class="burger" aria-hidden="true"></span><span class="sr-only">Menú</span></button>
        <a class="brand" href="<?= e(url('/')) ?>">
            <?php if ($logo): ?><img src="<?= e(url('/logotip')) ?>" alt="" class="brand-logo" width="36" height="36"><?php endif; ?>
            <span>Entrenaments JSPM</span>
        </a>
        <button type="button" class="btn btn-sm btn-install" data-install hidden><?= icon('smartphone') ?><span>Instal·la</span></button>
    </div>
</header>
<div class="drawer-backdrop" data-drawer-close hidden></div>
<nav id="menu-principal" class="drawer" aria-label="Navegació principal">
    <div class="drawer-head">
        <span class="drawer-user"><?= icon('user') ?><span><?= e(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))) ?><small><?= $admin ? 'Gestor' : 'Entrenador' ?></small></span></span>
        <button type="button" class="drawer-close" data-drawer-close aria-label="Tanca el menú"><?= icon('x') ?></button>
    </div>
    <div class="drawer-links">
        <a href="<?= e(url('/')) ?>"<?= $active('/') ?>><?= icon('home') ?>Inici</a>
        <a href="<?= e(url('/assistencia')) ?>"<?= $active('/assistencia') ?>><?= icon('check') ?>Passa llista</a>
        <a href="<?= e(url('/entrenaments')) ?>"<?= $active('/entrenaments') ?>><?= icon('calendar') ?>Entrenaments</a>
        <a href="<?= e(url('/exercicis')) ?>"<?= $active('/exercicis') ?>><?= icon('ball') ?>Exercicis</a>
        <?php if ($user && !$admin): $unread = \App\Services\Notifier::unreadCount((int) $user['id']); ?>
            <a href="<?= e(url('/notificacions')) ?>"<?= $active('/notificacions') ?>><?= icon('bell') ?>Notificacions<?= $unread ? ' <span class="badge badge-danger" aria-label="' . $unread . ' sense llegir">' . $unread . '</span>' : '' ?></a>
        <?php endif; ?>
        <?php if ($admin): ?>
            <p class="drawer-title">Seguiment</p>
            <a href="<?= e(url('/assistencia/resum')) ?>"<?= $active('/assistencia/resum') ?>><?= icon('list') ?>Faltes de la setmana</a>
            <a href="<?= e(url('/calendari')) ?>"<?= $active('/calendari') ?>><?= icon('calendar') ?>Calendari i festius</a>
            <p class="drawer-title">Gestió</p>
            <a href="<?= e(url('/equips')) ?>"<?= $active('/equips') ?>><?= icon('shield') ?>Equips</a>
            <a href="<?= e(url('/jugadors')) ?>"<?= $active('/jugadors') ?>><?= icon('user') ?>Jugadors</a>
            <a href="<?= e(url('/entrenadors')) ?>"<?= $active('/entrenadors') ?>><?= icon('users') ?>Entrenadors</a>
            <a href="<?= e(url('/categories')) ?>"<?= $active('/categories') ?>><?= icon('tag') ?>Categories</a>
            <p class="drawer-title">Administració</p>
            <a href="<?= e(url('/administradors')) ?>"<?= $active('/administradors') ?>><?= icon('shield') ?>Administradors</a>
            <a href="<?= e(url('/admin/correu')) ?>"<?= $active('/admin/correu') ?>><?= icon('mail') ?>Configuració del correu</a>
            <a href="<?= e(url('/admin/identitat')) ?>"<?= $active('/admin/identitat') ?>><?= icon('image') ?>Logotip i icones</a>
            <a href="<?= e(url('/admin/correus')) ?>"<?= $active('/admin/correus') ?>><?= icon('history') ?>Historial de correus</a>
            <a href="<?= e(url('/admin/registre')) ?>"<?= $active('/admin/registre') ?>><?= icon('list') ?>Registre d'activitat</a>
        <?php endif; ?>
        <p class="drawer-title">El meu compte</p>
        <a href="<?= e(url('/compte')) ?>"<?= $active('/compte') ?>><?= icon('user') ?>El meu perfil</a>
        <a href="<?= e(url('/compte/contrasenya')) ?>"><?= icon('key') ?>Canvia la contrasenya</a>
        <form method="post" action="<?= e(url('/sortir')) ?>"><?= csrf_field() ?><button type="submit" class="link-button"><?= icon('logout') ?>Tanca la sessió</button></form>
    </div>
</nav>
<main id="contingut" class="container main" tabindex="-1">
    <?php \App\Helpers\View::component('alerts'); ?>
    <?= $content ?>
</main>
<footer class="app-footer container">
    <small><?= e($club) ?> · Entrenaments JSPM</small>
</footer>
<?php \App\Helpers\View::component('install_ui'); ?>
<dialog id="confirm-dialog" class="modal" aria-labelledby="confirm-title">
    <form method="dialog">
        <h2 id="confirm-title">Confirmes l'acció?</h2>
        <p id="confirm-text"></p>
        <div class="modal-actions">
            <button value="cancel" class="btn btn-secondary"><?= icon('x') ?>Cancel·la</button>
            <button value="ok" class="btn btn-danger">Confirma</button>
        </div>
    </form>
</dialog>
</body>
</html>
