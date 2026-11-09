<div class="page-head"><h1>Equips</h1>
    <div class="form-actions"><a class="btn btn-secondary" href="<?= e(url('/jugadors/importa')) ?>"><?= icon('upload') ?>Importa jugadors</a><a class="btn btn-primary" href="<?= e(url('/equips/nou')) ?>"><?= icon('plus') ?>Nou equip</a></div>
</div>
<?php if (!$teams): ?><div class="card"><p class="muted">Encara no hi ha equips. Crea'n un o <a href="<?= e(url('/jugadors/importa')) ?>">importa els jugadors</a> des d'un Excel i es crearan sols.</p></div><?php else: ?>
<div class="table-wrap"><table class="table">
    <thead><tr><th>Equip</th><th>Categoria</th><th>Jugadors</th><th>Entrenadors</th><th>Horari</th><th>Estat</th><th>Accions</th></tr></thead>
    <tbody>
    <?php foreach ($teams as $t): ?>
        <tr>
            <td data-label="Equip"><a href="<?= e(url('/equips/' . $t['id'])) ?>"><strong><?= e($t['name']) ?></strong></a></td>
            <td data-label="Categoria"><?= $t['category_name'] ? e($t['category_name']) : '<span class="badge badge-warning">Sense categoria</span>' ?></td>
            <td data-label="Jugadors"><a href="<?= e(url('/jugadors', ['team' => $t['id']])) ?>"><?= (int) $t['players_count'] ?></a></td>
            <td data-label="Entrenadors"><?= $t['trainers'] ? e($t['trainers']) : '<span class="muted">—</span>' ?></td>
            <td data-label="Horari"><?= $t['schedule'] !== '' ? e($t['schedule']) . ((int) $t['own_schedule'] ? '' : ' <small class="muted">(de la categoria)</small>') : '<span class="badge badge-warning">Sense horari</span>' ?></td>
            <td data-label="Estat"><?= active_badge($t['is_active']) ?></td>
            <td data-label="Accions" class="actions">
                <a class="btn btn-sm btn-secondary" href="<?= e(url('/equips/' . $t['id'] . '/editar')) ?>"><?= icon('edit') ?>Edita</a>
                <a class="btn btn-sm btn-secondary" href="<?= e(url('/assistencia/equip/' . $t['id'])) ?>"><?= icon('list') ?>Assistència</a>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table></div>
<?php endif; ?>
