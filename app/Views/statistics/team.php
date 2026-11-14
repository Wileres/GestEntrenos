<?php
$totals = ['late' => 0, 'absent' => 0, 'present' => 0];
foreach ($rows as $x) { foreach ($totals as $k => $_) { $totals[$k] += (int) $x[$k]; } }
$all = array_sum($totals);
$avg = $all ? (int) round(($totals['present'] + $totals['late']) * 100 / $all) : null;
$q = fn (string $ordre) => url('/estadistiques/equip/' . $team['id'], ['from' => $from, 'to' => $to, 'ordre' => $ordre]);
?>
<nav class="breadcrumb"><a href="<?= e(url('/estadistiques')) ?>">Estadístiques</a> / <?= e($team['name']) ?></nav>
<h1>Estadístiques · <?= e($team['name']) ?></h1>
<form method="get" action="<?= e(url('/estadistiques/equip/' . $team['id'])) ?>" class="card filters">
    <?= input_field('from', 'Des del', ['type' => 'date', 'value' => $from]) ?>
    <?= input_field('to', 'Fins al', ['type' => 'date', 'value' => $to]) ?>
    <input type="hidden" name="ordre" value="<?= e($sort) ?>">
    <div class="filters-actions"><button class="btn btn-primary" type="submit"><?= icon('filter') ?>Aplica</button></div>
</form>
<div class="grid grid-4">
    <div class="card stat"><span class="stat-n"><?= (int) $totals['absent'] ?></span><span>Faltes</span></div>
    <div class="card stat"><span class="stat-n"><?= (int) $totals['late'] ?></span><span>Retards</span></div>
    <div class="card stat"><span class="stat-n"><?= (int) $sessions ?></span><span>Entrenaments amb llista</span></div>
    <div class="card stat"><span class="stat-n"><?= $avg === null ? '—' : $avg . ' %' ?></span><span>Assistència de l'equip</span></div>
</div>
<p class="muted">Ordena per:
    <?php foreach (['faltes' => 'Faltes', 'tard' => 'Retards', 'nom' => 'Nom'] as $k => $label): ?>
        <?= $sort === $k ? '<strong>' . e($label) . '</strong>' : '<a href="' . e($q($k)) . '">' . e($label) . '</a>' ?><?= $k !== 'nom' ? ' · ' : '' ?>
    <?php endforeach; ?></p>
<?php if (!$rows): ?><div class="card"><p class="muted">Aquest equip no té jugadors actius.</p></div><?php else: ?>
<div class="table-wrap"><table class="table">
    <thead><tr><th>Jugador</th><th>Hi és</th><th>Retards</th><th>Faltes</th><th>Assistència</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $x): $tot = (int) $x['present'] + (int) $x['late'] + (int) $x['absent']; $pct = $tot ? (int) round(((int) $x['present'] + (int) $x['late']) * 100 / $tot) : null; ?>
        <tr>
            <td data-label="Jugador"><a href="<?= e(url('/estadistiques/jugador/' . $x['id'], ['from' => $from, 'to' => $to])) ?>"><strong><?= e($x['full_name']) ?></strong></a></td>
            <td data-label="Hi és"><?= (int) $x['present'] ?></td>
            <td data-label="Retards"><?= (int) $x['late'] > 0 ? '<span class="badge badge-warning">' . (int) $x['late'] . '</span>' : '0' ?></td>
            <td data-label="Faltes"><?= (int) $x['absent'] > 0 ? '<span class="badge badge-danger">' . (int) $x['absent'] . '</span>' : '0' ?></td>
            <td data-label="Assistència"><?= $pct === null ? '<span class="muted">—</span>' : '<span class="meter meter-' . (int) (round($pct / 10) * 10) . '" role="img" aria-label="' . $pct . ' %"><span></span></span> ' . $pct . ' %' ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table></div>
<?php endif; ?>
