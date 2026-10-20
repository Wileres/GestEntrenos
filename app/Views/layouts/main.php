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
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
    <script src="<?= e(asset('js/app.js')) ?>" defer></script>
</head>
<body>
<a class="skip-link" href="#contingut">Salta al contingut</a>
<header class="app-header">
    <div class="container header-inner">
        <a class="brand" href="<?= e(url('/')) ?>">
            <?php if ($logo): ?><img src="<?= e(url('/logotip')) ?>" alt="" class="brand-logo" width="40" height="40"><?php endif; ?>
            <span>Entrenaments JSPM</span>
        </a>
        <button type="button" class="btn btn-sm btn-install" data-install hidden>Instal·la l'aplicació</button>
        <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="menu-principal">Menú</button>
        <nav id="menu-principal" class="main-nav" aria-label="Navegació principal">
            <a href="<?= e(url('/')) ?>"<?= $active('/') ?>>Inici</a>
            <a href="<?= e(url('/entrenaments')) ?>"<?= $active('/entrenaments') ?>>Entrenaments</a>
            <a href="<?= e(url('/exercicis')) ?>"<?= $active('/exercicis') ?>>Exercicis</a>
            <?php if ($user && !$admin): $unread = \App\Services\Notifier::unreadCount((int) $user['id']); ?>
                <a href="<?= e(url('/notificacions')) ?>"<?= $active('/notificacions') ?>>Notificacions<?= $unread ? ' <span class="badge badge-danger" aria-label="' . $unread . ' sense llegir">' . $unread . '</span>' : '' ?></a>
            <?php endif; ?>
            <?php if ($admin): ?>
                <a href="<?= e(url('/entrenadors')) ?>"<?= $active('/entrenadors') ?>>Entrenadors</a>
                <a href="<?= e(url('/categories')) ?>"<?= $active('/categories') ?>>Categories</a>
                <details class="nav-menu">
                    <summary>Administració</summary>
                    <div class="nav-menu-list">
                        <a href="<?= e(url('/administradors')) ?>">Administradors</a>
                        <a href="<?= e(url('/admin/correu')) ?>">Configuració del correu</a>
                        <a href="<?= e(url('/admin/identitat')) ?>">Logotip i icones</a>
                        <a href="<?= e(url('/admin/correus')) ?>">Historial de correus</a>
                        <a href="<?= e(url('/admin/registre')) ?>">Registre d'activitat</a>
                    </div>
                </details>
            <?php endif; ?>
            <details class="nav-menu nav-user">
                <summary><?= e($user['first_name'] ?? '') ?></summary>
                <div class="nav-menu-list">
                    <a href="<?= e(url('/compte')) ?>">El meu perfil</a>
                    <a href="<?= e(url('/compte/contrasenya')) ?>">Canvia la contrasenya</a>
                    <form method="post" action="<?= e(url('/sortir')) ?>"><?= csrf_field() ?><button type="submit" class="link-button">Tanca la sessió</button></form>
                </div>
            </details>
        </nav>
    </div>
</header>
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
            <button value="cancel" class="btn btn-secondary">Cancel·la</button>
            <button value="ok" class="btn btn-danger">Confirma</button>
        </div>
    </form>
</dialog>
</body>
</html>
