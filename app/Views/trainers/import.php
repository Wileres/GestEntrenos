<nav class="breadcrumb"><a href="<?= e(url('/entrenadors')) ?>">Entrenadors</a> / Importa</nav>
<h1>Importa entrenadors</h1>
<?php if (!$plan): ?>
<section class="card stack narrow">
    <p>Carrega un llistat amb <strong>nom i cognoms</strong> i <strong>correu electrònic</strong> (fitxer <strong>.xlsx</strong>, <strong>.csv</strong> o <strong>.txt</strong>), o enganxa'l directament:</p>
    <table class="table table-compact"><thead><tr><th>Nom i cognoms</th><th>Correu electrònic</th></tr></thead>
        <tbody><tr><td>Joan Pons Vila</td><td>joan.pons@exemple.cat</td></tr></tbody></table>
    <ul class="help-list">
        <li>Les columnes poden anar en qualsevol ordre i la primera fila pot ser la capçalera.</li>
        <li>La primera paraula és el <strong>nom</strong> i la resta els <strong>cognoms</strong>; es poden corregir després a la fitxa de cada entrenador.</li>
        <li>Els entrenadors queden <strong>pendents d'equip</strong>: no reben cap correu ni poden entrar fins que els n'assignis un.</li>
        <li>Els correus que ja existeixen s'ignoren. Abans d'importar veuràs un resum.</li>
    </ul>
    <form method="post" action="<?= e(url('/entrenadors/importa')) ?>" enctype="multipart/form-data" class="stack" data-loading>
        <?= csrf_field() ?>
        <div class="field<?= field_error('file') ? ' has-error' : '' ?>">
            <label for="f-file">Fitxer (màxim <?= (int) $maxMb ?> MB)</label>
            <input type="file" id="f-file" name="file" accept=".xlsx,.csv,.txt">
            <?php if (field_error('file')): ?><p class="field-error"><?= e(field_error('file')) ?></p><?php endif; ?>
        </div>
        <?= input_field('text', 'O enganxa el llistat (una persona per línia)', ['type' => 'textarea', 'rows' => 8, 'attrs' => ['placeholder' => "Joan Pons Vila; joan.pons@exemple.cat\nMarta Sala Roca; marta.sala@exemple.cat"]]) ?>
        <div class="form-actions"><button class="btn btn-primary" type="submit"><?= icon('upload') ?>Carrega i revisa</button><a class="btn btn-secondary" href="<?= e(url('/entrenadors/plantilla')) ?>"><?= icon('download') ?>Descarrega la plantilla</a></div>
    </form>
    <?php if (!empty($skipped)): ?><h3>Files ignorades</h3><ul class="list"><?php foreach (array_slice($skipped, 0, 30) as $er): ?><li><span class="badge badge-danger">Fila <?= (int) $er['line'] ?></span> <?= e($er['message']) ?></li><?php endforeach; ?></ul><?php endif; ?>
</section>
<?php else: ?>
<section class="card stack">
    <h2>Resum de la importació</h2>
    <div class="grid grid-4">
        <div class="card stat"><span class="stat-n"><?= count($plan['new']) ?></span><span>Entrenadors nous</span></div>
        <div class="card stat"><span class="stat-n"><?= count($plan['exists']) ?></span><span>Ja existents (s'ignoren)</span></div>
        <div class="card stat"><span class="stat-n"><?= count($errors) ?></span><span>Files amb errors</span></div>
    </div>
    <?php if ($errors): ?>
        <h3>Files que s'ignoraran</h3>
        <ul class="list"><?php foreach (array_slice($errors, 0, 50) as $er): ?><li><span class="badge badge-danger">Fila <?= (int) $er['line'] ?></span> <?= e($er['message']) ?></li><?php endforeach; ?></ul>
        <?php if (count($errors) > 50): ?><p class="muted">… i <?= count($errors) - 50 ?> més.</p><?php endif; ?>
    <?php endif; ?>
    <h3>Entrenadors que es crearan</h3>
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Nom</th><th>Cognoms</th><th>Correu electrònic</th></tr></thead>
        <tbody>
        <?php foreach (array_slice($plan['new'], 0, 100) as $s): ?>
            <tr><td data-label="Nom"><?= e($s['first_name']) ?></td><td data-label="Cognoms"><?= e($s['last_name']) ?: '—' ?></td><td data-label="Correu"><?= e($s['email']) ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$plan['new']): ?><tr><td colspan="3" class="muted">No hi ha cap entrenador nou.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
    <?php if (count($plan['new']) > 100): ?><p class="muted">… i <?= count($plan['new']) - 100 ?> més.</p><?php endif; ?>
    <?php if ($plan['exists']): ?><p class="muted">Ja existents: <?= e(implode(', ', array_slice(array_column($plan['exists'], 'email'), 0, 20))) ?><?= count($plan['exists']) > 20 ? '…' : '' ?></p><?php endif; ?>
    <form method="post" action="<?= e(url('/entrenadors/importa/confirma')) ?>" class="form-actions" data-loading>
        <?= csrf_field() ?>
        <?php if ($plan['new']): ?><button class="btn btn-primary" type="submit"><?= icon('check') ?>Confirma la importació</button><?php endif; ?>
        <a class="btn btn-secondary" href="<?= e(url('/entrenadors/importa')) ?>">Cancel·la</a>
    </form>
</section>
<?php endif; ?>
