<?php $user = current_user(); ?>
<h1>El meu perfil</h1>
<div class="grid grid-2">
    <section class="card">
        <h2>Dades personals</h2>
        <dl class="details">
            <dt>Nom</dt><dd><?= e(trim($user['first_name'] . ' ' . $user['last_name'])) ?></dd>
            <?php if (!empty($user['username'])): ?><dt>Usuari</dt><dd><?= e($user['username']) ?></dd><?php endif; ?>
            <dt>Correu electrònic</dt><dd><?= e($user['email'] ?? '—') ?></dd>
            <dt>Telèfon</dt><dd><?= e($user['phone'] ?: '—') ?></dd>
            <dt>Rol</dt><dd><?= $user['role'] === 'admin' ? 'Administrador' : 'Entrenador' ?></dd>
        </dl>
        <a class="btn btn-secondary" href="<?= e(url('/compte/contrasenya')) ?>">Canvia la contrasenya</a>
    </section>
    <?php if ($user['role'] !== 'admin'): ?>
    <section class="card">
        <h2>Les meves categories</h2>
        <?php if ($categories): ?><ul class="tags"><?php foreach ($categories as $c): ?><li><?= e($c['name']) ?></li><?php endforeach; ?></ul>
        <?php else: ?><p class="muted">Encara no tens categories assignades.</p><?php endif; ?>
    </section>
    <?php endif; ?>
</div>
