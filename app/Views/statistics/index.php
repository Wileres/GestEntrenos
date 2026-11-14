<h1>Estadístiques</h1>
<form method="get" action="<?= e(url('/estadistiques')) ?>" class="card filters">
    <?= input_field('from', 'Des del', ['type' => 'date', 'value' => $from]) ?>
    <?= input_field('to', 'Fins al', ['type' => 'date', 'value' => $to]) ?>
    <div class="filters-actions"><button class="btn btn-primary" type="submit"><?= icon('filter') ?>Aplica</button></div>
</form>
<p class="muted">Retards i faltes dels jugadors de cada equip, comptant només els entrenaments on s'ha passat llista.</p>
<?php if (!$rows): ?><div class="card"><p class="muted">No tens cap equip assignat.</p></div><?php else: ?>
<div class="table-wrap"><table class="table">
    <thead><tr><th>Equip</th><th>Jugadors</th><th>Entrenaments</th><th>Retards</th><th>Faltes</th><th>Assistència</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $x): $tot = (int) $x['present'] + (int) $x['late'] + (int) $x['absent']; $pct = $tot ? (int) round(((int) $x['present'] + (int) $x['late']) * 100 / $tot) : null; ?>
        <tr>
            <td data-label="Equip"><a href="<?= e(url('/estadistiques/equip/' . $x['id'], ['from' => $from, 'to' => $to])) ?>"><strong><?= e($x['name']) ?></strong></a></td>
            <td data-label="Jugadors"><?= (int) $x['players'] ?></td>
            <td data-label="Entrenaments"><?= (int) $x['sessions'] ?></td>
            <td data-label="Retards"><?= (int) $x['late'] > 0 ? '<span class="badge badge-warning">' . (int) $x['late'] . '</span>' : '0' ?></td>
            <td data-label="Faltes"><?= (int) $x['absent'] > 0 ? '<span class="badge badge-danger">' . (int) $x['absent'] . '</span>' : '0' ?></td>
            <td data-label="Assistència"><?= $pct === null ? '<span class="muted">—</span>' : '<span class="meter meter-' . (int) (round($pct / 10) * 10) . '" role="img" aria-label="' . $pct . ' %"><span></span></span> ' . $pct . ' %' ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table></div>
<?php endif; ?>
