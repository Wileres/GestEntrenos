<nav class="breadcrumb"><a href="<?= e(url('/')) ?>">Inici</a> / <?= e($team['name']) ?></nav>
<h1><?= e($team['name']) ?></h1>
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
