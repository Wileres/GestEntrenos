<div class="page-head"><h1>Equips</h1><a class="btn btn-primary" href="<?= e(url('/equips/nou')) ?>">Nou equip</a></div>
<form method="get" class="filters card">
    <?= input_field('season', 'Temporada', ['value' => $season, 'attrs' => ['placeholder' => '2026-2027', 'maxlength' => 9]]) ?>
    <div class="filters-actions"><button class="btn btn-primary" type="submit">Filtra</button> <a class="btn btn-secondary" href="<?= e(url('/equips')) ?>">Neteja</a></div>
</form>
<div class="table-wrap"><table class="table">
    <thead><tr><th>Equip</th><th>Categoria</th><th>Temporada</th><th>Estat</th><th>Accions</th></tr></thead>
    <tbody>
    <?php foreach ($teams as $t): ?>
        <tr>
            <td data-label="Equip"><?= e($t['name']) ?></td>
            <td data-label="Categoria"><?= e($t['category_name']) ?></td>
            <td data-label="Temporada"><?= e($t['season']) ?></td>
            <td data-label="Estat"><?= active_badge($t['is_active']) ?></td>
            <td data-label="Accions" class="actions">
                <a class="btn btn-sm btn-secondary" href="<?= e(url('/equips/' . $t['id'] . '/editar')) ?>">Edita</a>
                <?= post_form_open('/equips/' . $t['id'] . '/eliminar', 'Vols eliminar l\'equip «' . $t['name'] . '»?') ?><button class="btn btn-sm btn-danger" type="submit">Elimina</button></form>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$teams): ?><tr><td colspan="5" class="muted">No hi ha equips.</td></tr><?php endif; ?>
    </tbody>
</table></div>
