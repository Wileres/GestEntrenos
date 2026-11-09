<?php
$wd = [1 => 'Dilluns', 2 => 'Dimarts', 3 => 'Dimecres', 4 => 'Dijous', 5 => 'Divendres', 6 => 'Dissabte', 7 => 'Diumenge'];
$teamOpts = []; foreach ($teams as $t) { $teamOpts[$t['id']] = $t['name']; }
?>
<div class="page-head"><h1>Calendari</h1></div>
<section class="card">
    <div class="month-nav">
        <a class="btn btn-secondary btn-sm" href="<?= e(url('/calendari', ['month' => $prev])) ?>" aria-label="Mes anterior"><?= icon('arrow-left') ?></a>
        <h2><?= e($label) ?></h2>
        <a class="btn btn-secondary btn-sm" href="<?= e(url('/calendari', ['month' => $next])) ?>" aria-label="Mes següent"><?= icon('chevron-right') ?></a>
    </div>
    <?php if (!$days): ?><p class="muted">No hi ha entrenaments ni festius aquest mes. Defineix els horaris a <a href="<?= e(url('/equips')) ?>">Equips</a> o <a href="<?= e(url('/categories')) ?>">Categories</a>.</p><?php else: ?>
    <ul class="agenda">
    <?php foreach ($days as $date => $day): $ts = strtotime($date); $isHoliday = isset($day['holiday']); ?>
        <li class="agenda-day<?= $isHoliday ? ' is-holiday' : '' ?><?= $date === date('Y-m-d') ? ' is-today' : '' ?>">
            <div class="agenda-date"><b><?= date('j', $ts) ?></b><span><?= e(mb_substr($wd[(int) date('N', $ts)], 0, 3)) ?>.</span></div>
            <div class="agenda-body">
                <?php if ($isHoliday): ?><p class="agenda-holiday"><span class="badge badge-danger">Festiu</span> <?= e($day['holiday']) ?></p><?php endif; ?>
                <?php foreach ($day['sessions'] ?? [] as $s): ?>
                    <a class="agenda-item<?= $s['status'] === 'cancelled' ? ' is-cancelled' : '' ?>" href="<?= e(url('/assistencia/' . $s['id'])) ?>">
                        <span class="agenda-time"><?= e(fmt_time($s['start_time'])) ?></span>
                        <span><?= e($s['team_name']) ?></span>
                        <?php if ($s['status'] === 'cancelled'): ?><span class="badge badge-danger">Cancel·lat</span><?php elseif ((int) $s['marked'] > 0): ?><span class="badge badge-success"><?= (int) $s['marked'] ?>/<?= (int) $s['players'] ?></span><?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </li>
    <?php endforeach; ?>
    </ul>
    <?php endif; ?>
</section>
<div class="grid grid-2">
    <section class="card stack">
        <h2>Afegeix dies festius</h2>
        <p class="muted">Els festius no tenen entrenament: s'esborren les fitxes que encara no s'han marcat.</p>
        <form method="post" action="<?= e(url('/calendari/festius')) ?>" class="stack" data-loading>
            <?= csrf_field() ?>
            <div class="row">
                <?= input_field('date_from', 'Des del dia', ['type' => 'date', 'required' => true, 'value' => '']) ?>
                <?= input_field('date_to', 'Fins al dia (opcional)', ['type' => 'date', 'value' => '', 'help' => 'Per a vacances o ponts.']) ?>
            </div>
            <?= input_field('name', 'Motiu', ['required' => true, 'value' => '', 'attrs' => ['maxlength' => 120, 'placeholder' => 'Nadal, Pasqua, Festa major…']]) ?>
            <button class="btn btn-primary" type="submit"><?= icon('plus') ?>Afegeix</button>
        </form>
        <h3>Pròxims festius</h3>
        <?php if ($holidays): ?><ul class="list"><?php foreach ($holidays as $h): ?>
            <li class="list-row"><span><strong><?= e(fmt_date($h['holiday_date'])) ?></strong> · <?= e($h['name']) ?></span>
                <?= post_form_open('/calendari/festius/' . $h['id'] . '/elimina', 'Vols eliminar aquest dia festiu?') ?><button class="btn btn-sm btn-danger" type="submit" aria-label="Elimina el festiu del <?= e(fmt_date($h['holiday_date'])) ?>"><?= icon('trash') ?></button></form></li>
        <?php endforeach; ?></ul><?php else: ?><p class="muted">No hi ha festius programats.</p><?php endif; ?>
    </section>
    <section class="card stack">
        <h2>Entrenament extra</h2>
        <p class="muted">Afegeix un entrenament puntual fora de l'horari habitual (partit amistós, recuperació…).</p>
        <form method="post" action="<?= e(url('/assistencia/extra')) ?>" class="stack" data-loading>
            <?= csrf_field() ?>
            <?= select_field('team_id', 'Equip', $teamOpts, ['required' => true, 'blank' => '— Tria un equip —', 'value' => '']) ?>
            <div class="row">
                <?= input_field('date', 'Data', ['type' => 'date', 'required' => true, 'value' => '']) ?>
                <?= input_field('start_time', 'Hora', ['type' => 'time', 'required' => true, 'value' => '18:00']) ?>
                <?= input_field('duration', 'Durada (min)', ['type' => 'number', 'required' => true, 'value' => '90', 'attrs' => ['min' => 15, 'max' => 300]]) ?>
            </div>
            <button class="btn btn-primary" type="submit"><?= icon('plus') ?>Afegeix al calendari</button>
        </form>
    </section>
</div>
