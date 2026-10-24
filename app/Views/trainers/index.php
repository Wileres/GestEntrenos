<div class="page-head"><h1>Entrenadors</h1><a class="btn btn-primary" href="<?= e(url('/entrenadors/nou')) ?>">Nou entrenador</a></div>
<form method="get" class="filters card" role="search">
    <?= input_field('q', 'Cerca per nom o correu', ['value' => $q, 'attrs' => ['maxlength' => 100]]) ?>
    <?= select_field('status', 'Estat', ['active' => 'Actius', 'inactive' => 'Inactius'], ['value' => $status, 'blank' => 'Tots']) ?>
    <div class="filters-actions"><button class="btn btn-primary" type="submit"><?= icon('filter') ?>Filtra</button> <a class="btn btn-secondary" href="<?= e(url('/entrenadors')) ?>">Neteja</a></div>
</form>
<div class="table-wrap">
<table class="table">
    <thead><tr><th>Nom</th><th>Correu</th><th>Categories</th><th>Estat</th><th>Accions</th></tr></thead>
    <tbody>
    <?php foreach ($trainers as $t): ?>
        <tr>
            <td data-label="Nom"><a href="<?= e(url('/entrenadors/' . $t['id'] . '/editar')) ?>"><?= e($t['last_name'] . ', ' . $t['first_name']) ?></a></td>
            <td data-label="Correu"><?= e($t['email']) ?></td>
            <td data-label="Categories"><?= (int) $t['categories_count'] ?></td>
            <td data-label="Estat"><?= active_badge($t['is_active']) ?><?= (int) $t['has_password'] ? '' : ' <span class="badge badge-warning">Pendent d\'activar</span>' ?></td>
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
