<nav class="breadcrumb"><a href="<?= e(url('/jugadors')) ?>">Jugadors</a> / Importa</nav>
<h1>Importa jugadors</h1>
<?php if (!$plan): ?>
<section class="card stack narrow">
    <p>Puja un full d'Excel (<strong>.xlsx</strong>) o un <strong>.csv</strong> amb tres columnes:</p>
    <table class="table table-compact"><thead><tr><th>Nom i cognoms</th><th>Data de neixament</th><th>Equip</th></tr></thead>
        <tbody><tr><td>Joan Pons Vila</td><td>15/03/2015</td><td>Benjamí A</td></tr></tbody></table>
    <ul class="help-list">
        <li>La primera fila pot ser la capçalera (si no, s'entenen les columnes en aquest ordre).</li>
        <li>Els <strong>equips que no existeixin es creen automàticament</strong>; si el nom comença pel d'una categoria («Benjamí A»), s'hi assignen.</li>
        <li>Un jugador que ja existeix (mateix nom i data) no es duplica: si ha canviat d'equip, s'actualitza.</li>
        <li>Abans d'importar veuràs un resum per confirmar-lo.</li>
    </ul>
    <form method="post" action="<?= e(url('/jugadors/importa')) ?>" enctype="multipart/form-data" class="stack" data-loading>
        <?= csrf_field() ?>
        <div class="field<?= field_error('file') ? ' has-error' : '' ?>">
            <label for="f-file">Fitxer (màxim <?= (int) $maxMb ?> MB)</label>
            <input type="file" id="f-file" name="file" accept=".xlsx,.csv,.txt" required>
            <?php if (field_error('file')): ?><p class="field-error"><?= e(field_error('file')) ?></p><?php endif; ?>
        </div>
        <div class="form-actions"><button class="btn btn-primary" type="submit"><?= icon('upload') ?>Carrega i revisa</button><a class="btn btn-secondary" href="<?= e(url('/jugadors/plantilla')) ?>"><?= icon('download') ?>Descarrega la plantilla</a></div>
    </form>
</section>
<?php else: ?>
<section class="card stack">
    <h2>Resum de la importació</h2>
    <div class="grid grid-4">
        <div class="card stat"><span class="stat-n"><?= (int) $plan['new'] ?></span><span>Jugadors nous</span></div>
        <div class="card stat"><span class="stat-n"><?= (int) $plan['updated'] ?></span><span>Canvis d'equip</span></div>
        <div class="card stat"><span class="stat-n"><?= (int) $plan['same'] ?></span><span>Ja existents</span></div>
        <div class="card stat"><span class="stat-n"><?= count($errors) ?></span><span>Files amb errors</span></div>
    </div>
    <h3>Equips</h3>
    <ul class="list">
    <?php foreach ($plan['teams'] as $t): ?>
        <li><strong><?= e($t['name']) ?></strong> · <?= (int) $t['players'] ?> jugadors ·
            <?= $t['exists'] ? '<span class="badge badge-neutral">Ja existeix</span>' : '<span class="badge badge-success">Es crearà</span>' ?>
            <?= $t['category'] ? '<span class="badge badge-info">' . e($t['category']) . '</span>' : ($t['exists'] ? '' : '<span class="badge badge-warning">Sense categoria (l\'assignaràs després)</span>') ?></li>
    <?php endforeach; ?>
    </ul>
    <?php if ($errors): ?>
        <h3>Files que s'ignoraran</h3>
        <ul class="list"><?php foreach (array_slice($errors, 0, 50) as $er): ?><li><span class="badge badge-danger">Fila <?= (int) $er['line'] ?></span> <?= e($er['message']) ?></li><?php endforeach; ?></ul>
        <?php if (count($errors) > 50): ?><p class="muted">… i <?= count($errors) - 50 ?> més.</p><?php endif; ?>
    <?php endif; ?>
    <h3>Primers jugadors (<?= (int) $total ?> en total)</h3>
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Nom i cognoms</th><th>Data de neixament</th><th>Equip</th></tr></thead>
        <tbody><?php foreach ($sample as $s): ?><tr><td data-label="Nom"><?= e($s['name']) ?></td><td data-label="Neixament"><?= $s['birth'] ? e(fmt_date($s['birth'])) : '—' ?></td><td data-label="Equip"><?= e($s['team']) ?></td></tr><?php endforeach; ?></tbody>
    </table></div>
    <form method="post" action="<?= e(url('/jugadors/importa/confirma')) ?>" class="form-actions" data-loading>
        <?= csrf_field() ?>
        <button class="btn btn-primary" type="submit"><?= icon('check') ?>Confirma la importació</button>
        <a class="btn btn-secondary" href="<?= e(url('/jugadors/importa')) ?>">Cancel·la</a>
    </form>
</section>
<?php endif; ?>
