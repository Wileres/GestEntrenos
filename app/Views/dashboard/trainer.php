<?php $user = current_user(); ?>
<h1>Hola, <?= e($user['first_name']) ?>!</h1>
<div class="grid grid-2">
    <section class="card"><h2>Pròxims entrenaments</h2>
        <?php \App\Helpers\View::component('training_list', ['items' => $upcoming, 'empty' => 'No tens entrenaments programats.']); ?>
        <p><a href="<?= e(url('/entrenaments')) ?>">Tots els meus entrenaments</a></p>
    </section>
    <section class="card"><h2>Les meves categories i equips</h2>
        <?php if ($categories || $teams): ?>
            <ul class="tags"><?php foreach ($categories as $c): ?><li><?= e($c['name']) ?></li><?php endforeach; ?>
            <?php foreach ($teams as $t): ?><li><?= e($t['name']) ?> <small>(<?= e($t['season']) ?>)</small></li><?php endforeach; ?></ul>
        <?php else: ?><p class="muted">Encara no tens categories ni equips assignats.</p><?php endif; ?>
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
