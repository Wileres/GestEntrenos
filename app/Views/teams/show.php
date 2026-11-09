<nav class="breadcrumb"><a href="<?= e(url('/')) ?>">Inici</a> / <?= e($team['name']) ?></nav>
<div class="page-head"><h1><?= e($team['name']) ?></h1>
<?php if (is_admin()): ?><div class="form-actions"><a class="btn btn-secondary" href="<?= e(url('/equips/' . $team['id'] . '/editar')) ?>"><?= icon('edit') ?>Edita</a><a class="btn btn-secondary" href="<?= e(url('/assistencia/equip/' . $team['id'])) ?>"><?= icon('list') ?>Assistència</a></div><?php endif; ?></div>
<p class="muted"><?= $team['category_name'] ? 'Categoria ' . e($team['category_name']) . ' · ' : '' ?><?= $schedule ? 'Entrena: ' . e(\App\Models\Schedule::summary($schedule)) : 'Sense horari definit' ?><?= $trainers ? ' · Entrenadors: ' . e(implode(', ', array_map(fn ($t) => $t['first_name'] . ' ' . $t['last_name'], $trainers))) : '' ?></p>
<section class="card">
    <h2>Pròxims entrenaments (assistència)</h2>
    <?php \App\Helpers\View::component('session_list', ['items' => $sessions, 'empty' => 'No hi ha entrenaments al calendari els pròxims dies.']); ?>
</section>
<section class="card">
    <h2>Jugadors <span class="count"><?= count($players) ?></span></h2>
    <?php if ($players): ?><ul class="list"><?php foreach ($players as $pl): ?>
        <li><?= e($pl['full_name']) ?><?= $pl['birth_date'] ? ' <small class="muted">' . e(fmt_date($pl['birth_date'])) . '</small>' : '' ?></li>
    <?php endforeach; ?></ul><?php else: ?><p class="muted">Aquest equip encara no té jugadors.</p><?php endif; ?>
</section>
<section class="card">
    <h2>Exercicis</h2>
    <?php if ($exercises): ?>
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Nom</th><th>Objectiu</th><th>Durada</th><th>Jugadors</th><th>Dificultat</th></tr></thead>
        <tbody>
        <?php foreach ($exercises as $x): ?>
            <tr>
                <td data-label="Nom"><a href="<?= e(url('/exercicis/' . $x['id'])) ?>"><?= e($x['name']) ?></a></td>
                <td data-label="Objectiu"><?= e($x['objective']) ?></td>
                <td data-label="Durada"><?= $x['duration_minutes'] ? (int) $x['duration_minutes'] . ' min' : '' ?></td>
                <td data-label="Jugadors"><?= e($x['players']) ?></td>
                <td data-label="Dificultat"><?= e(\App\Helpers\Labels::difficulty($x['difficulty'])) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php \App\Helpers\View::component('pagination', ['pager' => $pager]); ?>
    <?php else: ?><p class="muted">Aquest equip encara no té exercicis.</p><?php endif; ?>
</section>
<?php if ($upcoming || $past): ?>
<section class="card">
    <h2>Entrenaments</h2>
    <?php if ($upcoming): ?><h3>Pròxims</h3><?php \App\Helpers\View::component('training_list', ['items' => $upcoming]); ?><?php endif; ?>
    <?php if ($past): ?><h3>Anteriors</h3><?php \App\Helpers\View::component('training_list', ['items' => $past]); ?><?php endif; ?>
</section>
<?php endif; ?>
