<?php $opts = []; foreach ($categories as $c) { $opts[$c['id']] = $c['name']; } ?>
<nav class="breadcrumb"><a href="<?= e(url('/equips')) ?>">Equips</a> / <?= e($title) ?></nav>
<h1><?= e($title) ?></h1>
<form method="post" action="<?= e(url($team ? '/equips/' . $team['id'] : '/equips')) ?>" class="card stack narrow" data-loading>
    <?= csrf_field() ?>
    <?= select_field('category_id', 'Categoria', $opts, ['required' => true, 'blank' => 'Selecciona una categoria']) ?>
    <?= input_field('name', 'Nom (p. ex. Infantil A)', ['required' => true, 'attrs' => ['maxlength' => 80]]) ?>
    <?= input_field('season', 'Temporada', ['required' => true, 'help' => 'Format 2026-2027', 'attrs' => ['maxlength' => 9, 'placeholder' => '2026-2027']]) ?>
    <?= input_field('description', 'Descripció', ['type' => 'textarea', 'rows' => 3]) ?>
    <label class="check"><input type="checkbox" name="is_active" value="1"<?= checked((string) old('is_active', '1') === '1') ?>> Equip actiu</label>
    <div class="form-actions"><button class="btn btn-primary" type="submit">Desa</button> <a class="btn btn-secondary" href="<?= e(url('/equips')) ?>">Cancel·la</a></div>
</form>
