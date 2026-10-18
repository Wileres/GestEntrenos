<div class="page-head"><h1>Categories</h1><a class="btn btn-primary" href="<?= e(url('/categories/nova')) ?>">Nova categoria</a></div>
<div class="table-wrap"><table class="table">
    <thead><tr><th>Ordre</th><th>Nom</th><th>Estat</th><th>Accions</th></tr></thead>
    <tbody>
    <?php foreach ($categories as $c): ?>
        <tr>
            <td data-label="Ordre"><?= (int) $c['sort_order'] ?></td>
            <td data-label="Nom"><?= e($c['name']) ?></td>
            <td data-label="Estat"><?= active_badge($c['is_active']) ?></td>
            <td data-label="Accions" class="actions">
                <a class="btn btn-sm btn-secondary" href="<?= e(url('/categories/' . $c['id'] . '/editar')) ?>">Edita</a>
                <?php if (!$c['in_use']): ?>
                    <?= post_form_open('/categories/' . $c['id'] . '/eliminar', 'Vols eliminar la categoria «' . $c['name'] . '»?') ?><button class="btn btn-sm btn-danger" type="submit">Elimina</button></form>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table></div>
<p class="muted">Les categories amb entrenadors, equips, exercicis o entrenaments relacionats no es poden eliminar; es poden desactivar.</p>
