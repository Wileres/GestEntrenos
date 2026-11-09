<?php $catOpts = []; foreach ($teams as $c) { $catOpts[$c['id']] = $c['name']; } ?>
<div class="page-head"><h1>Exercicis</h1>
    <?php if (is_admin()): ?><a class="btn btn-primary" href="<?= e(url('/exercicis/nou')) ?>">Nou exercici</a><?php endif; ?></div>
<form method="get" class="filters card" role="search">
    <?= input_field('name', 'Nom', ['value' => $filters['name'], 'attrs' => ['maxlength' => 100]]) ?>
    <?= select_field('team', 'Equip', $catOpts, ['value' => $filters['team'], 'blank' => 'Totes']) ?>
    <?= input_field('objective', 'Objectiu', ['value' => $filters['objective'], 'attrs' => ['maxlength' => 100]]) ?>
    <?= input_field('material', 'Material', ['value' => $filters['material'], 'attrs' => ['maxlength' => 100]]) ?>
    <?= select_field('difficulty', 'Dificultat', \App\Helpers\Labels::DIFFICULTY, ['value' => $filters['difficulty'], 'blank' => 'Totes']) ?>
    <?= input_field('players', 'Jugadors', ['type' => 'number', 'value' => $filters['players'], 'attrs' => ['min' => 1, 'max' => 200]]) ?>
    <?php if (is_admin()): ?><?= select_field('status', 'Estat', ['active' => 'Actius', 'inactive' => 'Inactius'], ['value' => $filters['status'], 'blank' => 'Tots']) ?><?php endif; ?>
    <div class="filters-actions"><button class="btn btn-primary" type="submit"><?= icon('search') ?>Cerca</button> <a class="btn btn-secondary" href="<?= e(url('/exercicis')) ?>">Neteja</a></div>
</form>
<div class="table-wrap"><table class="table">
    <thead><tr><th>Nom</th><th>Equips</th><th>Objectiu</th><th>Durada</th><th>Jugadors</th><th>Dificultat</th></tr></thead>
    <tbody>
    <?php foreach ($exercises as $x): ?>
        <tr>
            <td data-label="Nom"><a href="<?= e(url('/exercicis/' . $x['id'])) ?>"><?= e($x['name']) ?></a><?= (int) $x['is_active'] ? '' : ' <span class="badge badge-neutral">Inactiu</span>' ?></td>
            <td data-label="Equips"><?= e($x['team_name']) ?></td>
            <td data-label="Objectiu"><?= e($x['objective']) ?></td>
            <td data-label="Durada"><?= $x['duration_minutes'] ? (int) $x['duration_minutes'] . ' min' : '' ?></td>
            <td data-label="Jugadors"><?= e($x['players']) ?></td>
            <td data-label="Dificultat"><?= e(\App\Helpers\Labels::difficulty($x['difficulty'])) ?></td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$exercises): ?><tr><td colspan="6" class="muted">No s'ha trobat cap exercici.</td></tr><?php endif; ?>
    </tbody>
</table></div>
<?php \App\Helpers\View::component('pagination', ['pager' => $pager]); ?>
