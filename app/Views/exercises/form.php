<nav class="breadcrumb"><a href="<?= e(url('/exercicis')) ?>">Exercicis</a> / <?= e($title) ?></nav>
<h1><?= e($title) ?></h1>
<form method="post" action="<?= e(url($exercise ? '/exercicis/' . $exercise['id'] : '/exercicis')) ?>" enctype="multipart/form-data" class="stack" data-loading>
    <?= csrf_field() ?>
    <section class="card">
        <?= input_field('name', 'Nom', ['required' => true, 'attrs' => ['maxlength' => 150]]) ?>
        <?= input_field('description', 'Explicació', ['type' => 'textarea', 'rows' => 8, 'required' => true]) ?>
        <div class="grid grid-2">
            <?= select_field('difficulty', 'Dificultat', \App\Helpers\Labels::DIFFICULTY, ['blank' => 'Sense especificar']) ?>
            <?= input_field('objective', 'Objectiu', ['attrs' => ['maxlength' => 255]]) ?>
            <?= input_field('material', 'Material necessari', ['attrs' => ['maxlength' => 255]]) ?>
            <?= input_field('duration_minutes', 'Durada estimada (minuts)', ['type' => 'number', 'attrs' => ['min' => 1, 'max' => 600]]) ?>
            <?= input_field('players', 'Nombre de jugadors', ['type' => 'number', 'attrs' => ['min' => 1, 'max' => 200]]) ?>
        </div>
        <fieldset><legend>Equips <span class="req" aria-hidden="true">*</span></legend>
            <?php if (field_error('team_ids')): ?><p class="field-error"><?= e(field_error('team_ids')) ?></p><?php endif; ?>
            <div class="checks"><?php foreach ($teams as $c): ?>
                <label class="check"><input type="checkbox" name="team_ids[]" value="<?= (int) $c['id'] ?>"<?= checked(in_old('team_ids', $c['id'])) ?>> <?= e($c['name']) ?></label>
            <?php endforeach; ?></div>
        </fieldset>
        <label class="check"><input type="checkbox" name="is_active" value="1"<?= checked((string) old('is_active', '1') === '1') ?>> Exercici actiu (visible per als entrenadors)</label>
    </section>
    <section class="card">
        <h2>Fitxers adjunts</h2>
        <?php foreach ($files as $f): ?>
            <p class="row"><a href="<?= e(url('/fitxers/exercici/' . $f['id'])) ?>"><?= e($f['original_name']) ?></a> <small class="muted"><?= e(human_size((int) $f['size_bytes'])) ?></small>
            <button class="btn btn-sm btn-danger" type="submit" form="del-file-<?= (int) $f['id'] ?>">Elimina</button></p>
        <?php endforeach; ?>
        <div class="field<?= field_error('files') ? ' has-error' : '' ?>">
            <label for="f-files">Afegeix fitxers</label>
            <input type="file" id="f-files" name="files[]" multiple accept=".pdf,.jpg,.jpeg,.png,.webp,.docx,.xlsx<?= \App\Helpers\Config::get('upload.allow_mp4') ? ',.mp4' : '' ?>">
            <p class="help">Permesos: PDF, JPG, PNG, WEBP, DOCX, XLSX<?= \App\Helpers\Config::get('upload.allow_mp4') ? ', MP4' : '' ?>. Màxim <?= (int) (\App\Helpers\Config::get('upload.max_bytes') / 1048576) ?> MB per fitxer.</p>
            <?php if (field_error('files')): ?><p class="field-error"><?= e(field_error('files')) ?></p><?php endif; ?>
        </div>
    </section>
    <div class="form-actions"><button class="btn btn-primary" type="submit"><?= icon('save') ?>Desa l'exercici</button> <a class="btn btn-secondary" href="<?= e(url('/exercicis')) ?>">Cancel·la</a></div>
</form>
<?php foreach ($files as $f): ?>
    <form id="del-file-<?= (int) $f['id'] ?>" method="post" action="<?= e(url('/fitxers/exercici/' . $f['id'] . '/eliminar')) ?>" data-confirm="Vols eliminar aquest fitxer?"><?= csrf_field() ?></form>
<?php endforeach; ?>
