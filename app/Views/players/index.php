<?php $teamOpts = []; foreach ($teams as $t) { $teamOpts[$t['id']] = $t['name']; } $catOpts = []; foreach ($categories as $c) { $catOpts[$c['id']] = $c['name']; } ?>
<div class="page-head"><h1>Jugadors</h1>
    <div class="form-actions"><a class="btn btn-secondary" href="<?= e(url('/jugadors/importa')) ?>"><?= icon('upload') ?>Importa</a><a class="btn btn-primary" href="<?= e(url('/jugadors/nou')) ?>"><?= icon('plus') ?>Nou jugador</a></div>
</div>
<form method="get" action="<?= e(url('/jugadors')) ?>" class="card filters">
    <?= input_field('q', 'Cerca per nom', ['value' => $filters['q'], 'attrs' => ['maxlength' => 100]]) ?>
    <?= select_field('category', 'Categoria', $catOpts, ['value' => $filters['category'], 'blank' => 'Totes']) ?>
    <?= select_field('team', 'Equip', $teamOpts, ['value' => $filters['team'], 'blank' => 'Tots']) ?>
    <?= select_field('status', 'Estat', ['active' => 'Actius', 'inactive' => 'Inactius'], ['value' => $filters['status'], 'blank' => 'Tots']) ?>
    <div class="filters-actions"><button class="btn btn-primary" type="submit"><?= icon('search') ?>Filtra</button><a class="btn btn-secondary" href="<?= e(url('/jugadors')) ?>">Neteja</a></div>
</form>
<?php if (!$players): ?><div class="card"><p class="muted">No s'ha trobat cap jugador.</p></div><?php else: ?>
<p class="muted"><?= (int) $pager->total ?> jugadors</p>
<div class="table-wrap"><table class="table">
    <thead><tr><th>Nom i cognoms</th><th>Data de neixament</th><th>Equip</th><th>Estat</th><th>Accions</th></tr></thead>
    <tbody>
    <?php foreach ($players as $x): ?>
        <tr>
            <td data-label="Nom"><strong><?= e($x['full_name']) ?></strong></td>
            <td data-label="Neixament"><?= $x['birth_date'] ? e(fmt_date($x['birth_date'])) : '<span class="muted">—</span>' ?></td>
            <td data-label="Equip"><a href="<?= e(url('/equips/' . $x['team_id'])) ?>"><?= e($x['team_name']) ?></a></td>
            <td data-label="Estat"><?= active_badge($x['is_active']) ?></td>
            <td data-label="Accions" class="actions">
                <a class="btn btn-sm btn-secondary" href="<?= e(url('/jugadors/' . $x['id'] . '/editar')) ?>"><?= icon('edit') ?>Edita</a>
                <?= post_form_open('/jugadors/' . $x['id'] . '/eliminar', 'Vols eliminar el jugador «' . $x['full_name'] . '» i el seu historial d\'assistència?') ?><button class="btn btn-sm btn-danger" type="submit"><?= icon('trash') ?>Elimina</button></form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table></div>
<?php \App\Helpers\View::component('pagination', ['pager' => $pager]); endif; ?>
