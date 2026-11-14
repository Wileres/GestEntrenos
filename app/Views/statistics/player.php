<?php
$wd = [1 => 'Dilluns', 2 => 'Dimarts', 3 => 'Dimecres', 4 => 'Dijous', 5 => 'Divendres', 6 => 'Dissabte', 7 => 'Diumenge'];
$months = [1 => 'gener', 2 => 'febrer', 3 => 'març', 4 => 'abril', 5 => 'maig', 6 => 'juny', 7 => 'juliol', 8 => 'agost', 9 => 'setembre', 10 => 'octubre', 11 => 'novembre', 12 => 'desembre'];
$pct = $stats['percent'];
?>
<nav class="breadcrumb"><a href="<?= e(url('/estadistiques')) ?>">Estadístiques</a> / <a href="<?= e(url('/estadistiques/equip/' . $player['team_id'], ['from' => $from, 'to' => $to])) ?>"><?= e($player['team_name']) ?></a> / <?= e($player['full_name']) ?></nav>
<h1><?= e($player['full_name']) ?></h1>
<p class="muted"><?= e($player['team_name']) ?></p>
<form method="get" action="<?= e(url('/estadistiques/jugador/' . $player['id'])) ?>" class="card filters">
    <?= input_field('from', 'Des del', ['type' => 'date', 'value' => $from]) ?>
    <?= input_field('to', 'Fins al', ['type' => 'date', 'value' => $to]) ?>
    <div class="filters-actions"><button class="btn btn-primary" type="submit"><?= icon('filter') ?>Aplica</button></div>
</form>
<div class="grid grid-4">
    <div class="card stat"><span class="stat-n"><?= (int) $stats['absent'] ?></span><span>Vegades que no ha vingut</span></div>
    <div class="card stat"><span class="stat-n"><?= (int) $stats['late'] ?></span><span>Vegades que ha arribat tard</span></div>
    <div class="card stat"><span class="stat-n"><?= (int) $stats['present'] ?></span><span>Vegades que hi ha estat</span></div>
    <div class="card stat"><span class="stat-n"><?= $pct === null ? '—' : $pct . ' %' ?></span><span>Assistència</span></div>
</div>
<?php if (!$stats['total']): ?>
    <div class="card"><p class="muted">En aquest període encara no hi ha cap assistència marcada per a aquest jugador.</p></div>
<?php else: ?>
<div class="grid grid-2">
    <section class="card">
        <h2>Per mesos</h2>
        <div class="table-wrap"><table class="table table-compact">
            <thead><tr><th>Mes</th><th>Hi és</th><th>Retards</th><th>Faltes</th></tr></thead>
            <tbody>
            <?php foreach ($stats['months'] as $m => $c): [$y, $mo] = explode('-', $m); ?>
                <tr>
                    <td data-label="Mes"><?= e($months[(int) $mo] . ' ' . $y) ?></td>
                    <td data-label="Hi és"><?= (int) $c['present'] ?></td>
                    <td data-label="Retards"><?= (int) $c['late'] > 0 ? '<span class="badge badge-warning">' . (int) $c['late'] . '</span>' : '0' ?></td>
                    <td data-label="Faltes"><?= (int) $c['absent'] > 0 ? '<span class="badge badge-danger">' . (int) $c['absent'] . '</span>' : '0' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    </section>
    <section class="card">
        <h2>Retards i faltes</h2>
        <?php if (!$stats['incidents']): ?><p class="muted">Cap retard ni cap falta en aquest període.</p><?php else: ?>
        <ul class="list">
        <?php foreach ($stats['incidents'] as $i): ?>
            <li><?= e($wd[(int) date('N', strtotime($i['session_date']))] . ' ' . fmt_date($i['session_date'])) ?> <small class="muted"><?= e(fmt_time($i['start_time'])) ?> · <?= e($i['team_name']) ?></small>
                <?= $i['status'] === 'absent' ? '<span class="badge badge-danger">No ha vingut</span>' : '<span class="badge badge-warning">Ha arribat tard</span>' ?></li>
        <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </section>
</div>
<?php endif; ?>
