<?php $wd = [1 => 'Dilluns', 2 => 'Dimarts', 3 => 'Dimecres', 4 => 'Dijous', 5 => 'Divendres', 6 => 'Dissabte', 7 => 'Diumenge']; ?>
<nav class="breadcrumb"><a href="<?= e(url('/assistencia')) ?>">Passa llista</a> / <?= e($s['team_name']) ?></nav>
<div class="page-head">
    <div><h1><?= e($s['team_name']) ?></h1>
        <p class="muted"><?= e($wd[(int) date('N', strtotime($s['session_date']))]) ?> <?= e(fmt_date($s['session_date'])) ?> · <?= e(fmt_time($s['start_time'])) ?> · <?= (int) $s['duration_minutes'] ?> min</p></div>
    <?php if ($isAdmin): ?><?= post_form_open('/assistencia/' . $s['id'] . '/cancela', $s['status'] === 'scheduled' ? 'Vols cancel·lar aquest entrenament?' : '') ?><button class="btn btn-secondary" type="submit"><?= $s['status'] === 'scheduled' ? icon('x') . 'Cancel·la l\'entrenament' : icon('refresh') . 'Restaura l\'entrenament' ?></button></form><?php endif; ?>
</div>
<?php if ($s['status'] === 'cancelled'): ?><div class="alert alert-warning" role="status">Aquest entrenament està cancel·lat.</div><?php endif; ?>
<?php if ($s['status'] === 'scheduled' && !$editable): ?><div class="alert alert-info" role="status">Aquest entrenament és en el futur: podràs passar llista el mateix dia.</div><?php endif; ?>
<?php foreach ($trainings as $t): ?><p><a class="btn btn-secondary btn-sm" href="<?= e(url('/entrenaments/' . $t['id'])) ?>"><?= icon('calendar') ?>Entrenament previst: <?= e($t['title']) ?></a></p><?php endforeach; ?>
<?php if (!$players): ?>
    <div class="card"><p class="muted">Aquest equip no té jugadors actius. <?php if ($isAdmin): ?><a href="<?= e(url('/jugadors/nou', ['team' => $s['team_id']])) ?>">Afegeix-ne</a> o <a href="<?= e(url('/jugadors/importa')) ?>">importa'ls</a>.<?php endif; ?></p></div>
<?php else: ?>
<form method="post" action="<?= e(url('/assistencia/' . $s['id'])) ?>" data-loading>
    <?= csrf_field() ?>
    <ul class="roll">
    <?php foreach ($players as $pl): $cur = $records[(int) $pl['id']] ?? ''; $pid = (int) $pl['id']; ?>
        <li class="roll-row">
            <span class="roll-name"><?= e($pl['full_name']) ?></span>
            <fieldset class="seg" <?= $editable ? '' : 'disabled' ?>>
                <legend class="sr-only">Assistència de <?= e($pl['full_name']) ?></legend>
                <?php foreach (['present' => ['Hi és', 'ok'], 'late' => ['Tard', 'late'], 'absent' => ['Falta', 'no']] as $val => [$txt, $cls]): ?>
                    <label class="seg-opt seg-<?= $cls ?>"><input type="radio" name="status[<?= $pid ?>]" value="<?= $val ?>"<?= checked($cur === $val) ?>><span><?= $txt ?></span></label>
                <?php endforeach; ?>
                <label class="seg-clear"><input type="radio" name="status[<?= $pid ?>]" value=""<?= checked($cur === '') ?>><span class="sr-only">Sense marcar</span><span aria-hidden="true">–</span></label>
            </fieldset>
        </li>
    <?php endforeach; ?>
    </ul>
    <?php if ($editable): ?>
    <div class="sticky-actions">
        <button class="btn btn-primary" type="submit"><?= icon('save') ?>Desa l'assistència</button>
        <button class="btn btn-secondary" type="submit" name="action" value="all_present"><?= icon('check') ?>Tots presents</button>
    </div>
    <p class="help">«Tots presents» marca com a present els jugadors que encara no tenen cap marca.</p>
    <?php endif; ?>
</form>
<?php endif; ?>
