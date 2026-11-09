<nav class="breadcrumb"><a href="<?= e(url('/equips')) ?>">Equips</a> / <?= e($team['name']) ?></nav>
<h1>Assistència · <?= e($team['name']) ?></h1>
<form method="get" action="<?= e(url('/assistencia/equip/' . $team['id'])) ?>" class="card filters">
    <?= input_field('from', 'Des del', ['type' => 'date', 'value' => $from]) ?>
    <?= input_field('to', 'Fins al', ['type' => 'date', 'value' => $to]) ?>
    <div class="filters-actions"><button class="btn btn-primary" type="submit"><?= icon('filter') ?>Aplica</button></div>
</form>
<p class="muted"><?= (int) $stats['sessions'] ?> entrenaments amb llista passada en aquest període.</p>
<?php if (!$stats['rows']): ?><div class="card"><p class="muted">Aquest equip no té jugadors actius.</p></div><?php else: ?>
<div class="table-wrap"><table class="table">
    <thead><tr><th>Jugador</th><th>Hi és</th><th>Tard</th><th>Faltes</th><th>Assistència</th></tr></thead>
    <tbody>
    <?php foreach ($stats['rows'] as $x): $tot = (int) $x['present'] + (int) $x['late'] + (int) $x['absent']; $pct = $tot ? (int) round(((int) $x['present'] + (int) $x['late']) * 100 / $tot) : null; ?>
        <tr>
            <td data-label="Jugador"><strong><?= e($x['full_name']) ?></strong></td>
            <td data-label="Hi és"><?= (int) $x['present'] ?></td>
            <td data-label="Tard"><?= (int) $x['late'] ?></td>
            <td data-label="Faltes"><?= (int) $x['absent'] > 0 ? '<span class="badge badge-danger">' . (int) $x['absent'] . '</span>' : '0' ?></td>
            <td data-label="Assistència"><?= $pct === null ? '<span class="muted">—</span>' : '<span class="meter meter-' . (int) (round($pct / 10) * 10) . '" role="img" aria-label="' . $pct . ' %"><span></span></span> ' . $pct . ' %' ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table></div>
<?php endif; ?>
