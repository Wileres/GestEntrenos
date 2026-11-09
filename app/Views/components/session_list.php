<?php /** Llista de fitxes d'assistència. Variables: $items, $empty. */
$months = ['gen.', 'febr.', 'març', 'abr.', 'maig', 'juny', 'jul.', 'ag.', 'set.', 'oct.', 'nov.', 'des.'];
$days = [1 => 'dl.', 2 => 'dt.', 3 => 'dc.', 4 => 'dj.', 5 => 'dv.', 6 => 'ds.', 7 => 'dg.'];
if (!$items): ?><p class="muted"><?= e($empty ?? '') ?></p><?php else: ?>
<ul class="sessions">
    <?php foreach ($items as $s): $ts = strtotime((string) $s['session_date']); $future = $s['session_date'] > date('Y-m-d'); ?>
        <li>
            <a class="session" href="<?= e(url('/assistencia/' . $s['id'])) ?>">
                <span class="session-date" aria-hidden="true"><b><?= date('j', $ts) ?></b><?= e($months[(int) date('n', $ts) - 1]) ?></span>
                <span class="session-info">
                    <span class="session-title"><?= e($s['team_name']) ?></span>
                    <span class="session-meta"><?= e($days[(int) date('N', $ts)]) ?> <?= e(fmt_time($s['start_time'])) ?> · <?= (int) $s['duration_minutes'] ?> min</span>
                </span>
                <?php if ($s['status'] === 'cancelled'): ?><span class="badge badge-danger">Cancel·lat</span>
                <?php elseif ((int) $s['marked'] > 0): ?><span class="badge badge-success"><?= (int) $s['marked'] ?>/<?= (int) $s['players'] ?> marcats</span>
                <?php elseif ($future): ?><span class="badge badge-neutral">Pendent</span>
                <?php else: ?><span class="badge badge-warning">Passa llista</span><?php endif; ?>
            </a>
        </li>
    <?php endforeach; ?>
</ul>
<?php endif; ?>
