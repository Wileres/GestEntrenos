<nav class="breadcrumb"><a href="<?= e(url('/exercicis')) ?>">Exercicis</a> / <?= e($e['name']) ?></nav>
<div class="page-head"><h1><?= e($e['name']) ?></h1>
<?php if (is_admin()): ?><div class="row">
    <a class="btn btn-secondary" href="<?= e(url('/exercicis/' . $e['id'] . '/editar')) ?>">Edita</a>
    <?= post_form_open('/exercicis/' . $e['id'] . '/eliminar', 'Vols eliminar definitivament aquest exercici?') ?><button class="btn btn-danger" type="submit">Elimina</button></form>
</div><?php endif; ?></div>
<div class="grid grid-2">
    <section class="card">
        <h2>Explicació</h2>
        <div class="prose"><?= nl2br(e($e['description'])) ?></div>
    </section>
    <section class="card">
        <h2>Detalls</h2>
        <dl class="details">
            <dt>Equips</dt><dd><?= e(implode(', ', array_column($extra, 'name')) ?: '—') ?></dd>
            <?php if ($e['objective']): ?><dt>Objectiu</dt><dd><?= e($e['objective']) ?></dd><?php endif; ?>
            <?php if ($e['duration_minutes']): ?><dt>Durada</dt><dd><?= (int) $e['duration_minutes'] ?> min</dd><?php endif; ?>
            <?php if ($e['players']): ?><dt>Jugadors</dt><dd><?= e($e['players']) ?></dd><?php endif; ?>
            <?php if ($e['material']): ?><dt>Material</dt><dd><?= e($e['material']) ?></dd><?php endif; ?>
            <?php if ($e['difficulty']): ?><dt>Dificultat</dt><dd><?= e(\App\Helpers\Labels::difficulty($e['difficulty'])) ?></dd><?php endif; ?>
            <dt>Estat</dt><dd><?= active_badge($e['is_active']) ?></dd>
            <dt>Creat per</dt><dd><?= e($e['creator_name']) ?> · <?= e(fmt_date($e['created_at'])) ?></dd>
            <dt>Modificat</dt><dd><?= e(fmt_datetime($e['updated_at'])) ?></dd>
        </dl>
        <?php if ($files): ?><h3>Fitxers adjunts</h3>
        <ul class="list"><?php foreach ($files as $f): ?>
            <li><a href="<?= e(url('/fitxers/exercici/' . $f['id'])) ?>"><?= e($f['original_name']) ?></a> <small class="muted"><?= e(human_size((int) $f['size_bytes'])) ?></small></li>
        <?php endforeach; ?></ul><?php endif; ?>
    </section>
</div>
