<?php if (!$items): ?><p class="muted"><?= e($empty ?? '') ?></p><?php else: ?>
<ul class="list">
    <?php foreach ($items as $t): ?>
        <li>
            <a href="<?= e(url('/entrenaments/' . $t['id'])) ?>"><?= e($t['title']) ?></a>
            <small class="muted"><?= e(fmt_date($t['session_date'])) ?> · <?= e(fmt_time($t['start_time'])) ?></small>
            <?= status_badge($t['status']) ?>
        </li>
    <?php endforeach; ?>
</ul>
<?php endif; ?>
