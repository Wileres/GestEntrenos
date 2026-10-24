<?php
$names = []; foreach ($catalog as $c) { $names[(int) $c['id']] = $c['name']; }
foreach ($rows as $r) { if (!isset($names[$r['exercise_id']])) { $names[$r['exercise_id']] = \App\Helpers\Db::value('SELECT name FROM exercises WHERE id = ?', [$r['exercise_id']]) ?? '?'; } }
$catalogOpts = []; foreach ($catalog as $c) { $catalogOpts[$c['id']] = $c['name'] . ' (' . $c['category_name'] . ')'; }
$last = count($rows) - 1;
?>
<nav class="breadcrumb"><a href="<?= e(url('/entrenaments')) ?>">Entrenaments</a> / <?= e($title) ?></nav>
<h1><?= e($title) ?></h1>
<form method="post" action="<?= e(url($training ? '/entrenaments/' . $training['id'] : '/entrenaments')) ?>" enctype="multipart/form-data" class="stack" data-loading>
    <?= csrf_field() ?>
    <button type="submit" name="action" value="save" class="sr-only" tabindex="-1" aria-hidden="true">Desa</button>
    <section class="card">
        <?= input_field('title', 'Títol', ['required' => true, 'attrs' => ['maxlength' => 150]]) ?>
        <?= input_field('description', 'Descripció', ['type' => 'textarea', 'rows' => 3]) ?>
        <div class="grid grid-4">
            <?= input_field('session_date', 'Data', ['type' => 'date', 'required' => true]) ?>
            <?= input_field('start_time', 'Hora d\'inici', ['type' => 'time', 'required' => true]) ?>
            <?= input_field('duration_minutes', 'Durada (min)', ['type' => 'number', 'required' => true, 'attrs' => ['min' => 1, 'max' => 600]]) ?>
            <?= input_field('season', 'Temporada', ['required' => true, 'attrs' => ['maxlength' => 9, 'placeholder' => '2026-2027']]) ?>
        </div>
        <?= input_field('notes', 'Observacions', ['type' => 'textarea', 'rows' => 3]) ?>
    </section>
    <section class="card">
        <?php if (field_error('category_ids')): ?><p class="field-error"><?= e(field_error('category_ids')) ?></p><?php endif; ?>
        <fieldset><legend>Equips</legend><div class="checks"><?php foreach ($categories as $c): ?>
            <label class="check"><input type="checkbox" name="category_ids[]" value="<?= (int) $c['id'] ?>"<?= checked(in_old('category_ids', $c['id'])) ?>> <?= e($c['name']) ?></label>
        <?php endforeach; ?></div></fieldset>
    </section>
    <section class="card" id="exercicis">
        <h2>Exercicis de l'entrenament</h2>
        <?php if (field_error('exercises')): ?><p class="field-error"><?= e(field_error('exercises')) ?></p><?php endif; ?>
        <?php if (!$rows): ?><p class="muted">Encara no hi ha exercicis. Afegeix-ne des del catàleg.</p><?php endif; ?>
        <ol class="exercise-rows">
        <?php foreach ($rows as $i => $row): ?>
            <li class="exercise-row">
                <input type="hidden" name="ex[<?= $i ?>][exercise_id]" value="<?= (int) $row['exercise_id'] ?>">
                <strong class="exercise-name"><?= e($names[$row['exercise_id']]) ?></strong>
                <div class="field"><label for="ex-d-<?= $i ?>">Durada (min)</label>
                    <input type="number" id="ex-d-<?= $i ?>" name="ex[<?= $i ?>][duration]" min="1" max="600" value="<?= e($row['duration']) ?>"></div>
                <div class="field"><label for="ex-n-<?= $i ?>">Observacions</label>
                    <input type="text" id="ex-n-<?= $i ?>" name="ex[<?= $i ?>][notes]" maxlength="2000" value="<?= e($row['notes']) ?>"></div>
                <div class="row-actions">
                    <button class="btn btn-sm btn-secondary" type="submit" name="action" value="up:<?= $i ?>" formnovalidate<?= $i === 0 ? ' disabled' : '' ?> aria-label="Puja l'exercici <?= e($names[$row['exercise_id']]) ?>">▲ Puja</button>
                    <button class="btn btn-sm btn-secondary" type="submit" name="action" value="down:<?= $i ?>" formnovalidate<?= $i === $last ? ' disabled' : '' ?> aria-label="Baixa l'exercici <?= e($names[$row['exercise_id']]) ?>">▼ Baixa</button>
                    <button class="btn btn-sm btn-danger" type="submit" name="action" value="remove:<?= $i ?>" formnovalidate aria-label="Treu l'exercici <?= e($names[$row['exercise_id']]) ?>">Treu</button>
                </div>
            </li>
        <?php endforeach; ?>
        </ol>
        <div class="row add-exercise">
            <?= select_field('add_exercise', 'Afegeix un exercici del catàleg', $catalogOpts, ['blank' => 'Selecciona un exercici', 'value' => '']) ?>
            <button class="btn btn-secondary" type="submit" name="action" value="add" formnovalidate><?= icon('plus') ?>Afegeix</button>
        </div>
    </section>
    <section class="card">
        <h2>Fitxers adjunts</h2>
        <?php foreach ($files as $f): ?>
            <p class="row"><a href="<?= e(url('/fitxers/entrenament/' . $f['id'])) ?>"><?= e($f['original_name']) ?></a> <small class="muted"><?= e(human_size((int) $f['size_bytes'])) ?></small>
            <button class="btn btn-sm btn-danger" type="submit" form="del-file-<?= (int) $f['id'] ?>">Elimina</button></p>
        <?php endforeach; ?>
        <div class="field<?= field_error('files') ? ' has-error' : '' ?>"><label for="f-files">Afegeix fitxers</label>
            <input type="file" id="f-files" name="files[]" multiple accept=".pdf,.jpg,.jpeg,.png,.webp,.docx,.xlsx<?= \App\Helpers\Config::get('upload.allow_mp4') ? ',.mp4' : '' ?>">
            <?php if (field_error('files')): ?><p class="field-error"><?= e(field_error('files')) ?></p><?php endif; ?></div>
        <?php if ($training && $training['status'] === 'published'): ?>
            <label class="check"><input type="checkbox" name="notify_changes" value="1"<?= checked(old('notify_changes') === '1') ?>> Notifica per correu les modificacions als entrenadors (els canvis de data o hora sempre es notifiquen)</label>
        <?php endif; ?>
    </section>
    <div class="form-actions"><button class="btn btn-primary" type="submit" name="action" value="save"><?= icon('save') ?>Desa l'entrenament</button>
        <a class="btn btn-secondary" href="<?= e(url('/entrenaments')) ?>">Cancel·la</a></div>
</form>
<?php foreach ($files as $f): ?>
    <form id="del-file-<?= (int) $f['id'] ?>" method="post" action="<?= e(url('/fitxers/entrenament/' . $f['id'] . '/eliminar')) ?>" data-confirm="Vols eliminar aquest fitxer?"><?= csrf_field() ?></form>
<?php endforeach; ?>
