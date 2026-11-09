<?php $teamOpts = []; foreach ($teams as $t) { $teamOpts[$t['id']] = $t['name']; } ?>
<nav class="breadcrumb"><a href="<?= e(url('/jugadors')) ?>">Jugadors</a> / <?= e($title) ?></nav>
<h1><?= e($title) ?></h1>
<form method="post" action="<?= e(url($player ? '/jugadors/' . $player['id'] : '/jugadors')) ?>" class="card stack narrow" data-loading>
    <?= csrf_field() ?>
    <?= input_field('full_name', 'Nom i cognoms', ['required' => true, 'attrs' => ['maxlength' => 190, 'autocomplete' => 'off']]) ?>
    <?= input_field('birth_date', 'Data de neixament', ['help' => 'Format DD/MM/AAAA (per exemple 15/03/2015).', 'attrs' => ['inputmode' => 'numeric', 'placeholder' => 'DD/MM/AAAA', 'maxlength' => 10, 'autocomplete' => 'off']]) ?>
    <?= select_field('team_id', 'Equip', $teamOpts, ['required' => true, 'blank' => '— Tria un equip —']) ?>
    <label class="check"><input type="checkbox" name="is_active" value="1"<?= checked((string) old('is_active', '1') === '1') ?>> Jugador actiu (surt a les fitxes d'assistència)</label>
    <div class="form-actions"><button class="btn btn-primary" type="submit"><?= icon('save') ?>Desa</button> <a class="btn btn-secondary" href="<?= e(url('/jugadors')) ?>">Cancel·la</a></div>
</form>
