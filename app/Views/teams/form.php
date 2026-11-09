<?php $catOpts = []; foreach ($categories as $c) { $catOpts[$c['id']] = $c['name']; } ?>
<nav class="breadcrumb"><a href="<?= e(url('/equips')) ?>">Equips</a> / <?= e($title) ?></nav>
<h1><?= e($title) ?></h1>
<form method="post" action="<?= e(url($team ? '/equips/' . $team['id'] : '/equips')) ?>" class="stack" data-loading>
    <?= csrf_field() ?>
    <section class="card stack">
        <h2>Dades de l'equip</h2>
        <?= input_field('name', 'Nom de l\'equip', ['required' => true, 'attrs' => ['maxlength' => 100], 'help' => 'Per exemple: Benjamí A, Aleví B…']) ?>
        <?= select_field('category_id', 'Categoria', $catOpts, ['blank' => '— Sense categoria —', 'help' => 'Cada equip pertany a una categoria.']) ?>
        <label class="check"><input type="checkbox" name="is_active" value="1"<?= checked((string) old('is_active', '1') === '1') ?>> Equip actiu</label>
    </section>
    <section class="card">
        <fieldset><legend>Entrenadors responsables</legend>
            <p class="help">Un entrenador pot portar diversos equips i un equip pot tenir més d'un entrenador.</p>
            <div class="checks">
            <?php foreach ($trainersAll as $u): ?>
                <label class="check"><input type="checkbox" name="trainer_ids[]" value="<?= (int) $u['id'] ?>"<?= checked(in_old('trainer_ids', $u['id'])) ?>> <?= e($u['first_name'] . ' ' . $u['last_name']) ?><?= (int) $u['is_active'] ? '' : ' (inactiu)' ?></label>
            <?php endforeach; ?>
            <?php if (!$trainersAll): ?><p class="muted">Encara no hi ha entrenadors.</p><?php endif; ?>
            </div>
        </fieldset>
    </section>
    <section class="card stack">
        <h2>Horari d'entrenament</h2>
        <?php if (!empty($categorySchedule)): ?><p class="muted">Per defecte l'equip segueix l'horari de la seva categoria: <strong><?= e($categorySchedule) ?></strong>.</p>
        <?php else: ?><p class="muted">La categoria no té horari definit; defineix-ne un aquí o a la categoria.</p><?php endif; ?>
        <label class="check"><input type="checkbox" name="own_schedule" value="1"<?= checked((string) old('own_schedule', '') === '1') ?>> Aquest equip té un horari propi (substitueix el de la categoria)</label>
        <?php \App\Helpers\View::component('schedule_fields'); ?>
    </section>
    <div class="form-actions"><button class="btn btn-primary" type="submit"><?= icon('save') ?>Desa</button> <a class="btn btn-secondary" href="<?= e(url('/equips')) ?>">Cancel·la</a></div>
</form>
<?php if ($team): ?>
<form method="post" action="<?= e(url('/equips/' . $team['id'] . '/eliminar')) ?>" class="card" data-confirm="Vols eliminar l'equip «<?= e($team['name']) ?>»?">
    <?= csrf_field() ?>
    <h2>Eliminar l'equip</h2>
    <p class="muted">Només es pot eliminar un equip sense jugadors, entrenadors, exercicis ni entrenaments. Si no, desactiva'l.</p>
    <button class="btn btn-danger" type="submit"><?= icon('trash') ?>Elimina l'equip</button>
</form>
<?php endif; ?>
