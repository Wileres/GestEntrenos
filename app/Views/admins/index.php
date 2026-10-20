<div class="page-head"><h1>Administradors</h1><a class="btn btn-primary" href="<?= e(url('/administradors/nou')) ?>">Nou administrador</a></div>
<div class="table-wrap"><table class="table">
    <thead><tr><th>Nom</th><th>Correu / usuari</th><th>Darrer accés</th><th>Estat</th><th>Accions</th></tr></thead>
    <tbody>
    <?php foreach ($admins as $a): ?>
        <tr>
            <td data-label="Nom"><a href="<?= e(url('/administradors/' . $a['id'] . '/editar')) ?>"><?= e(trim($a['first_name'] . ' ' . $a['last_name'])) ?></a><?= (int) $a['id'] === $me ? ' <span class="badge badge-info">Tu</span>' : '' ?></td>
            <td data-label="Correu"><?= e($a['email'] ?: $a['username']) ?></td>
            <td data-label="Darrer accés"><?= e(fmt_datetime($a['last_login_at'])) ?: '—' ?></td>
            <td data-label="Estat"><?= active_badge($a['is_active']) ?><?= (int) $a['has_password'] ? '' : ' <span class="badge badge-warning">Pendent d\'activar</span>' ?></td>
            <td data-label="Accions" class="actions">
                <a class="btn btn-sm btn-secondary" href="<?= e(url('/administradors/' . $a['id'] . '/editar')) ?>">Edita</a>
                <?php if ((int) $a['id'] !== $me): ?>
                    <?= post_form_open('/administradors/' . $a['id'] . '/estat', (int) $a['is_active'] ? 'Vols desactivar aquest administrador? No podrà iniciar sessió.' : '') ?>
                        <button class="btn btn-sm btn-secondary" type="submit"><?= (int) $a['is_active'] ? 'Desactiva' : 'Activa' ?></button></form>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table></div>
<p class="muted">Els administradors tenen accés complet. Reben un correu per definir la seva contrasenya.</p>
