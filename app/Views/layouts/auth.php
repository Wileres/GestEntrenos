<?php $logo = \App\Services\SettingsService::logoPath() !== null; ?>
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
<body class="auth-body">
<main class="auth-card" id="contingut">
    <div class="auth-brand">
        <?php if ($logo): ?><img src="<?= e(url('/logotip')) ?>" alt="Logotip del club" class="auth-logo" width="96" height="96"><?php endif; ?>
        <p class="auth-club">Joventut Sant Pere Màrtir</p>
        <p class="auth-app">Entrenaments JSPM</p>
    </div>
    <?php \App\Helpers\View::component('alerts'); ?>
    <?= $content ?>
    <p class="center"><button type="button" class="btn btn-sm btn-secondary" data-install hidden>Instal·la l'aplicació</button></p>
</main>
<?php \App\Helpers\View::component('install_ui'); ?>
</body>
</html>
