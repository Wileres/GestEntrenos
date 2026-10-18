<nav class="breadcrumb"><a href="<?= e(url('/entrenaments')) ?>">Entrenaments</a> / <?= e($t['title']) ?></nav>
<div class="page-head"><h1><?= e($t['title']) ?> <?= status_badge($t['status']) ?></h1>
<?php if (is_admin()): ?><div class="row">
    <a class="btn btn-secondary" href="<?= e(url('/entrenaments/' . $t['id'] . '/editar')) ?>">Edita</a>
    <?php foreach ($transitions as $s): ?>
        <?= post_form_open('/entrenaments/' . $t['id'] . '/estat', $s === 'cancelled' ? 'Vols cancel·lar aquest entrenament? Es notificarà als entrenadors si estava publicat.' : '') ?>
            <input type="hidden" name="status" value="<?= e($s) ?>">
            <button class="btn <?= $s === 'cancelled' ? 'btn-danger' : 'btn-primary' ?>" type="submit"><?= e(['published' => 'Publica', 'cancelled' => 'Cancel·la', 'finished' => 'Marca com a finalitzat', 'draft' => 'Torna a esborrany'][$s]) ?></button></form>
    <?php endforeach; ?>
    <?= post_form_open('/entrenaments/' . $t['id'] . '/eliminar', 'Vols eliminar definitivament aquest entrenament?') ?><button class="btn btn-danger" type="submit">Elimina</button></form>
</div><?php endif; ?></div>
<div class="grid grid-2">
    <section class="card"><h2>Detalls</h2>
        <dl class="details">
            <dt>Data</dt><dd><?= e(fmt_date($t['session_date'])) ?></dd>
            <dt>Hora d'inici</dt><dd><?= e(fmt_time($t['start_time'])) ?></dd>
            <dt>Durada</dt><dd><?= (int) $t['duration_minutes'] ?> min</dd>
            <dt>Temporada</dt><dd><?= e($t['season']) ?></dd>
            <dt>Equips</dt><dd><?= e(implode(', ', array_column($categories, 'name')) ?: '—') ?></dd>
        </dl>
        <?php if ($t['description']): ?><h3>Descripció</h3><div class="prose"><?= nl2br(e($t['description'])) ?></div><?php endif; ?>
        <?php if ($t['notes']): ?><h3>Observacions</h3><div class="prose"><?= nl2br(e($t['notes'])) ?></div><?php endif; ?>
        <?php if ($files): ?><h3>Fitxers adjunts</h3><ul class="list"><?php foreach ($files as $f): ?>
            <li><a href="<?= e(url('/fitxers/entrenament/' . $f['id'])) ?>"><?= e($f['original_name']) ?></a> <small class="muted"><?= e(human_size((int) $f['size_bytes'])) ?></small></li>
        <?php endforeach; ?></ul><?php endif; ?>
    </section>
    <section class="card"><h2>Exercicis</h2>
        <?php if ($exercises): ?><ol class="exercise-list"><?php foreach ($exercises as $x): ?>
            <li><a href="<?= e(url('/exercicis/' . $x['exercise_id'])) ?>"><?= e($x['name']) ?></a>
                <?= $x['duration_minutes'] ? '<span class="muted">· ' . (int) $x['duration_minutes'] . ' min</span>' : '' ?>
                <?= $x['notes'] ? '<p class="muted">' . e($x['notes']) . '</p>' : '' ?></li>
        <?php endforeach; ?></ol><?php else: ?><p class="muted">Aquest entrenament encara no té exercicis.</p><?php endif; ?>
    </section>
</div>
