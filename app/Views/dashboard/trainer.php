<?php $user = current_user(); ?>
<h1>Hola, <?= e($user['first_name']) ?>!</h1>
<div class="grid grid-2">
    <section class="card"><h2>Pròxims entrenaments</h2>
        <?php \App\Helpers\View::component('training_list', ['items' => $upcoming, 'empty' => 'No tens entrenaments programats.']); ?>
        <p><a href="<?= e(url('/entrenaments')) ?>">Tots els meus entrenaments</a></p>
    </section>
    <section class="card"><h2>Els meus equips</h2>
        <?php if ($categories): ?>
            <div class="team-buttons">
            <?php foreach ($categories as $c): ?><a class="btn btn-primary" href="<?= e(url('/equips/' . $c['id'])) ?>"><?= e($c['name']) ?></a><?php endforeach; ?>
            </div>
            <p class="muted">Fes clic en un equip per veure'n els exercicis i els entrenaments.</p>
        <?php else: ?><p class="muted">Encara no tens equips assignats.</p><?php endif; ?>
    </section>
    <section class="card"><h2>Últims exercicis</h2>
        <?php if ($exercises): ?><ul class="list"><?php foreach ($exercises as $e): ?>
            <li><a href="<?= e(url('/exercicis/' . $e['id'])) ?>"><?= e($e['name']) ?></a> <small class="muted"><?= e($e['category_name']) ?></small></li>
        <?php endforeach; ?></ul><?php else: ?><p class="muted">Encara no hi ha exercicis.</p><?php endif; ?>
        <p><a class="btn btn-secondary" href="<?= e(url('/exercicis')) ?>">Catàleg d'exercicis</a></p>
    </section>
    <section class="card"><h2>Notificacions recents</h2>
        <?php if ($notifications): ?><ul class="list"><?php foreach ($notifications as $n): ?>
            <li><?= e($n['subject']) ?> <small class="muted"><?= e(fmt_datetime($n['created_at'])) ?></small></li>
        <?php endforeach; ?></ul><?php else: ?><p class="muted">No tens notificacions.</p><?php endif; ?>
    </section>
</div>
