<?php $catOpts = []; foreach ($categories as $c) { $catOpts[$c['id']] = $c['name']; } ?>
<div class="page-head"><h1>Entrenaments</h1>
    <?php if (is_admin()): ?><a class="btn btn-primary" href="<?= e(url('/entrenaments/nou')) ?>">Nou entrenament</a><?php endif; ?></div>
<form method="get" class="filters card">
    <?= select_field('when', 'Període', ['upcoming' => 'Pròxims', 'past' => 'Passats', 'all' => 'Tots'], ['value' => $filters['when']]) ?>
    <?= select_field('category', 'Equip', $catOpts, ['value' => $filters['category'], 'blank' => 'Totes']) ?>
    <?php if (is_admin()): ?><?= select_field('status', 'Estat', \App\Helpers\Labels::TRAINING_STATUS, ['value' => $filters['status'], 'blank' => 'Tots']) ?><?php endif; ?>
    <div class="filters-actions"><button class="btn btn-primary" type="submit">Filtra</button> <a class="btn btn-secondary" href="<?= e(url('/entrenaments')) ?>">Neteja</a></div>
</form>
<div class="table-wrap"><table class="table">
    <thead><tr><th>Data</th><th>Títol</th><th>Equips</th><th>Durada</th><th>Estat</th></tr></thead>
    <tbody>
    <?php foreach ($trainings as $t): ?>
        <tr>
            <td data-label="Data"><?= e(fmt_date($t['session_date'])) ?> <?= e(fmt_time($t['start_time'])) ?></td>
            <td data-label="Títol"><a href="<?= e(url('/entrenaments/' . $t['id'])) ?>"><?= e($t['title']) ?></a></td>
            <td data-label="Equips"><?= e($t['categories']) ?></td>
            <td data-label="Durada"><?= (int) $t['duration_minutes'] ?> min</td>
            <td data-label="Estat"><?= status_badge($t['status']) ?></td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$trainings): ?><tr><td colspan="5" class="muted">No hi ha entrenaments.</td></tr><?php endif; ?>
    </tbody>
</table></div>
<?php \App\Helpers\View::component('pagination', ['pager' => $pager]); ?>
