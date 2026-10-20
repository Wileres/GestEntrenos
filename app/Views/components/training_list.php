<?php if (!$items): ?><p class="muted"><?= e($empty ?? '') ?></p><?php else: ?>
<ul class="sessions">
    <?php foreach ($items as $t): $ts = strtotime((string) $t['session_date']); ?>
        <li>
            <a class="session" href="<?= e(url('/entrenaments/' . $t['id'])) ?>">
                <span class="session-date" aria-hidden="true"><b><?= $ts ? date('j', $ts) : '' ?></b><?= $ts ? e(['gen.', 'febr.', 'març', 'abr.', 'maig', 'juny', 'jul.', 'ag.', 'set.', 'oct.', 'nov.', 'des.'][(int) date('n', $ts) - 1]) : '' ?></span>
                <span class="session-info">
                    <span class="session-title"><?= e($t['title']) ?></span>
                    <span class="session-meta"><?= e(fmt_time($t['start_time'])) ?></span>
                </span>
                <?= status_badge($t['status']) ?>
            </a>
        </li>
    <?php endforeach; ?>
</ul>
<?php endif; ?>
