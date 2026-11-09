<?php $fmt = fn (array $dates) => implode(', ', array_map(fn ($d) => date('d/m', strtotime($d)), $dates)); ?>
<div class="page-head"><h1>Faltes de la setmana</h1><a class="btn btn-secondary" href="<?= e(url('/calendari')) ?>"><?= icon('calendar') ?>Calendari</a></div>
<section class="card">
    <div class="month-nav">
        <a class="btn btn-secondary btn-sm" href="<?= e(url('/assistencia/resum', ['week' => $prev])) ?>" aria-label="Setmana anterior"><?= icon('arrow-left') ?></a>
        <h2><?= e(fmt_date($mon)) ?> – <?= e(fmt_date($sun)) ?><?= $isCurrent ? ' <small class="muted">(aquesta setmana)</small>' : '' ?></h2>
        <a class="btn btn-secondary btn-sm" href="<?= e(url('/assistencia/resum', ['week' => $next])) ?>" aria-label="Setmana següent"><?= icon('chevron-right') ?></a>
    </div>
    <div class="grid grid-4">
        <div class="card stat"><span class="stat-n"><?= (int) $absent ?></span><span>Faltes</span></div>
        <div class="card stat"><span class="stat-n"><?= (int) $late ?></span><span>Retards</span></div>
        <div class="card stat"><span class="stat-n"><?= count($teams) ?></span><span>Equips amb incidències</span></div>
        <div class="card stat"><span class="stat-n"><?= count($unmarked) ?></span><span>Fitxes sense passar llista</span></div>
    </div>
</section>
<?php if (!$teams): ?><div class="card"><p class="muted">Cap jugador ha faltat ni ha arribat tard aquesta setmana.</p></div><?php endif; ?>
<?php foreach ($teams as $tid => $t): ?>
<section class="card">
    <h2><a href="<?= e(url('/assistencia/equip/' . $tid)) ?>"><?= e($t['name']) ?></a> <span class="count"><?= count($t['players']) ?></span></h2>
    <ul class="list">
    <?php foreach ($t['players'] as $pl): ?>
        <li><strong><?= e($pl['name']) ?></strong>
            <?php if (!empty($pl['absent'])): ?><span class="badge badge-danger">Falta <?= e($fmt($pl['absent'])) ?></span><?php endif; ?>
            <?php if (!empty($pl['late'])): ?><span class="badge badge-warning">Tard <?= e($fmt($pl['late'])) ?></span><?php endif; ?></li>
    <?php endforeach; ?>
    </ul>
</section>
<?php endforeach; ?>
<?php if ($unmarked): ?>
<section class="card">
    <h2>Entrenaments sense passar llista</h2>
    <ul class="list"><?php foreach ($unmarked as $u): ?>
        <li><a href="<?= e(url('/assistencia/' . $u['id'])) ?>"><?= e($u['team_name']) ?></a> <small class="muted"><?= e(fmt_date($u['session_date'])) ?> <?= e(fmt_time($u['start_time'])) ?></small></li>
    <?php endforeach; ?></ul>
</section>
<?php endif; ?>
