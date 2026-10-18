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
            <dt>Categoria principal</dt><dd><?= e($e['category_name']) ?></dd>
            <?php if ($extra): ?><dt>Altres categories</dt><dd><?= e(implode(', ', array_column($extra, 'name'))) ?></dd><?php endif; ?>
            <dt>Objectiu</dt><dd><?= e($e['objective'] ?: '—') ?></dd>
            <dt>Durada</dt><dd><?= $e['duration_minutes'] ? (int) $e['duration_minutes'] . ' min' : '—' ?></dd>
            <dt>Jugadors</dt><dd><?= e($e['players'] ?: '—') ?></dd>
            <dt>Material</dt><dd><?= e($e['material'] ?: '—') ?></dd>
            <dt>Dificultat</dt><dd><?= e(\App\Helpers\Labels::difficulty($e['difficulty']) ?: '—') ?></dd>
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
