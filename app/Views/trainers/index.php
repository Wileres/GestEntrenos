<?php $pendingRows = array_filter($trainers, fn ($t) => (int) $t['pending_approval'] === 1); ?>
<div class="page-head"><h1>Entrenadors</h1>
    <div class="form-actions"><a class="btn btn-secondary" href="<?= e(url('/entrenadors/importa')) ?>"><?= icon('upload') ?>Importa</a><a class="btn btn-primary" href="<?= e(url('/entrenadors/nou')) ?>"><?= icon('plus') ?>Nou entrenador</a></div></div>
<form method="get" class="filters card" role="search">
    <?= input_field('q', 'Cerca per nom o correu', ['value' => $q, 'attrs' => ['maxlength' => 100]]) ?>
    <?= select_field('status', 'Estat', ['active' => 'Actius', 'inactive' => 'Inactius', 'pending' => 'Pendents d\'equip o d\'aprovació'], ['value' => $status, 'blank' => 'Tots']) ?>
    <div class="filters-actions"><button class="btn btn-primary" type="submit"><?= icon('filter') ?>Filtra</button> <a class="btn btn-secondary" href="<?= e(url('/entrenadors')) ?>">Neteja</a></div>
</form>
<?php if ($pendingRows): ?>
<form method="post" action="<?= e(url('/entrenadors/assigna')) ?>" id="bulk-assign" class="card stack" data-loading>
    <?= csrf_field() ?>
    <h2>Assigna equips als pendents seleccionats</h2>
    <p class="muted">En assignar un equip, l'entrenador rep un correu per definir la contrasenya i el compte queda activat.</p>
    <label class="check"><input type="checkbox" data-select-all="trainer_ids[]"> Selecciona tots els pendents d'aquesta pàgina</label>
    <fieldset><legend>Equips</legend><div class="checks">
        <?php foreach ($teams as $tm): ?><label class="check"><input type="checkbox" name="team_ids[]" value="<?= (int) $tm['id'] ?>"> <?= e($tm['name']) ?></label><?php endforeach; ?>
    </div></fieldset>
    <div class="form-actions"><button class="btn btn-primary" type="submit"><?= icon('check') ?>Assigna i envia l'activació</button></div>
</form>
<?php endif; ?>
<div class="table-wrap">
<table class="table">
    <thead><tr><th>Nom</th><th>Correu</th><th>Equips</th><th>Estat</th><th>Accions</th></tr></thead>
    <tbody>
    <?php foreach ($trainers as $t): ?>
        <tr>
            <td data-label="Nom"><?php if ((int) $t['pending_approval']): ?><input type="checkbox" form="bulk-assign" name="trainer_ids[]" value="<?= (int) $t['id'] ?>" aria-label="Selecciona <?= e($t['first_name'] . ' ' . $t['last_name']) ?>"> <?php endif; ?><a href="<?= e(url('/entrenadors/' . $t['id'] . '/editar')) ?>"><?= e($t['last_name'] . ', ' . $t['first_name']) ?></a></td>
            <td data-label="Correu"><?= e($t['email']) ?></td>
            <td data-label="Equips"><?= (int) $t['teams_count'] ?></td>
            <td data-label="Estat"><?php if ((int) $t['pending_approval']): ?><span class="badge badge-warning"><?= (int) $t['has_password'] ? 'Pendent d\'aprovar' : 'Pendent d\'assignar equip' ?></span>
                <?php else: ?><?= active_badge($t['is_active']) ?><?= (int) $t['has_password'] ? '' : ' <span class="badge badge-warning">Pendent d\'activar</span>' ?><?php endif; ?></td>
            <td data-label="Accions" class="actions">
                <a class="btn btn-sm btn-secondary" href="<?= e(url('/entrenadors/' . $t['id'] . '/editar')) ?>">Edita</a>
                <?= post_form_open('/entrenadors/' . $t['id'] . '/estat', (int) $t['is_active'] ? 'Vols desactivar aquest entrenador? No podrà iniciar sessió.' : '') ?>
                    <button class="btn btn-sm btn-secondary" type="submit"><?= (int) $t['is_active'] ? 'Desactiva' : 'Activa' ?></button></form>
                <?= post_form_open('/entrenadors/' . $t['id'] . '/eliminar', 'Vols eliminar definitivament l\'entrenador «' . $t['first_name'] . ' ' . $t['last_name'] . '»? Aquesta acció no es pot desfer.') ?>
                    <button class="btn btn-sm btn-danger" type="submit"><?= icon('trash') ?>Elimina</button></form>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$trainers): ?><tr><td colspan="6" class="muted">No s'ha trobat cap entrenador.</td></tr><?php endif; ?>
    </tbody>
</table>
</div>
<?php \App\Helpers\View::component('pagination', ['pager' => $pager]); ?>
