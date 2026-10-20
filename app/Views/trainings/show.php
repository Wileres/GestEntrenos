<nav class="breadcrumb"><a href="<?= e(url('/entrenaments')) ?>">Entrenaments</a> / <?= e($t['title']) ?></nav>
<div class="page-head"><h1><?= e($t['title']) ?> <?= status_badge($t['status']) ?></h1>
<?php if (is_admin()): ?><div class="row">
    <a class="btn btn-secondary" href="<?= e(url('/entrenaments/' . $t['id'] . '/editar')) ?>">Edita</a>
    <?php foreach ($transitions as $s): ?>
        <?= post_form_open('/entrenaments/' . $t['id'] . '/estat', $s === 'cancelled' ? 'Vols cancel·lar aquest entrenament? Es notificarà als entrenadors si estava publicat.' : '') ?>
            <input type="hidden" name="status" value="<?= e($s) ?>">
            <button class="btn <?= $s === 'cancelled' ? 'btn-danger' : 'btn-primary' ?>" type="submit"><?= e(['published' => 'Publica', 'cancelled' => 'Cancel·la', 'finished' => 'Marca com a finalitzat', 'draft' => 'Torna a esborrany'][$s]) ?></button></form>
    <?php endforeach; ?>
    <?= post_form_open('/entrenaments/' . $t['id'] . '/eliminar', 'Vols eliminar definitivament aquest entrenament?') ?><button class="btn btn-danger" type="submit">Elimina</button></form>
</div><?php endif; ?></div>
<?php if ($files): ?>
<section class="card card-files" aria-labelledby="h-adjunts">
    <h2 id="h-adjunts">Fitxers adjunts <span class="count"><?= count($files) ?></span></h2>
    <?php \App\Helpers\View::component('attachments', ['files' => $files, 'kind' => 'entrenament']); ?>
</section>
<?php endif; ?>
<div class="grid grid-2">
    <section class="card"><h2>Detalls</h2>
        <dl class="details">
            <dt>Data</dt><dd><?= e(fmt_date($t['session_date'])) ?></dd>
            <dt>Hora d'inici</dt><dd><?= e(fmt_time($t['start_time'])) ?></dd>
            <dt>Durada</dt><dd><?= (int) $t['duration_minutes'] ?> min</dd>
            <dt>Temporada</dt><dd><?= e($t['season']) ?></dd>
            <dt>Equips</dt><dd><?= e(implode(', ', array_column($categories, 'name')) ?: '—') ?></dd>
        </dl>
        <?php if ($t['description']): ?><h3>Descripció</h3><div class="prose"><?= nl2br(e($t['description'])) ?></div><?php endif; ?>
        <?php if ($t['notes']): ?><h3>Observacions</h3><div class="prose"><?= nl2br(e($t['notes'])) ?></div><?php endif; ?>
</section>
    <section class="card"><h2>Exercicis</h2>
        <?php if ($exercises):
            $clock = null;
            if ($t['start_time'] && preg_match('/^(\d{1,2}):(\d{2})/', (string) $t['start_time'], $m)) { $clock = (int) $m[1] * 60 + (int) $m[2]; }
            ?><ol class="timeline"><?php foreach ($exercises as $x):
                $dur = (int) $x['duration_minutes'];
                $at = $clock !== null ? sprintf('%02d:%02d', intdiv($clock, 60) % 24, $clock % 60) : null;
                if ($clock !== null) { $clock += $dur; }
                $xf = $exerciseFiles[(int) $x['exercise_id']] ?? [];
                ?>
            <li class="timeline-item">
                <span class="timeline-time"><?= $at !== null ? e($at) : '' ?></span>
                <div class="timeline-body">
                    <p class="timeline-title"><a href="<?= e(url('/exercicis/' . $x['exercise_id'])) ?>"><?= e($x['name']) ?></a><?= $dur ? ' <span class="timeline-dur">' . $dur . ' min</span>' : '' ?></p>
                    <?= $x['notes'] ? '<p class="muted">' . e($x['notes']) . '</p>' : '' ?>
                    <?php if ($xf): \App\Helpers\View::component('attachments', ['files' => $xf, 'kind' => 'exercici', 'compact' => true]); endif; ?>
                </div>
            </li>
        <?php endforeach; ?></ol><?php else: ?><p class="muted">Aquest entrenament encara no té exercicis.</p><?php endif; ?>
    </section>
</div>
