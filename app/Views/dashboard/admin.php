<?php $logo = \App\Services\SettingsService::logoPath() !== null; ?>
<div class="page-head">
    <div class="brand-hero">
        <?php if ($logo): ?><img src="<?= e(url('/logotip')) ?>" alt="Logotip del club" width="72" height="72"><?php endif; ?>
        <div><h1>Panell principal</h1><p class="muted">Joventut Sant Pere Màrtir</p></div>
    </div>
    <a class="btn btn-primary" href="<?= e(url('/entrenaments/nou')) ?>">Nou entrenament</a>
</div>
<div class="grid grid-4">
    <?php foreach ($stats as $label => [$n, $link]): ?>
        <a class="card stat" href="<?= e(url($link)) ?>"><span class="stat-n"><?= (int) $n ?></span><span><?= e($label) ?></span></a>
    <?php endforeach; ?>
</div>
<?php if ($failedCount > 0): ?>
    <div class="alert alert-warning" role="status">Hi ha <?= (int) $failedCount ?> correu(s) fallit(s) els darrers 7 dies. <a href="<?= e(url('/admin/correus', ['status' => 'failed'])) ?>">Revisa'ls</a>.</div>
<?php endif; ?>
<div class="grid grid-2">
    <section class="card"><h2>Pròxims entrenaments</h2>
        <?php \App\Helpers\View::component('training_list', ['items' => $upcoming, 'empty' => 'No hi ha entrenaments programats.']); ?>
    </section>
    <section class="card"><h2>Últims entrenaments creats</h2>
        <?php \App\Helpers\View::component('training_list', ['items' => $latest, 'empty' => 'Encara no hi ha entrenaments.']); ?>
    </section>
    <section class="card"><h2>Últims entrenadors</h2>
        <?php if ($trainers): ?><ul class="list"><?php foreach ($trainers as $t): ?>
            <li><a href="<?= e(url('/entrenadors/' . $t['id'] . '/editar')) ?>"><?= e($t['first_name'] . ' ' . $t['last_name']) ?></a> <small class="muted"><?= e(fmt_date($t['created_at'])) ?></small></li>
        <?php endforeach; ?></ul><?php else: ?><p class="muted">Encara no hi ha entrenadors.</p><?php endif; ?>
    </section>
    <section class="card"><h2>Correus fallits</h2>
        <?php if ($failedMails): ?><ul class="list"><?php foreach ($failedMails as $m): ?>
            <li><?= e($m['recipient']) ?> <small class="muted"><?= e(fmt_datetime($m['created_at'])) ?></small></li>
        <?php endforeach; ?></ul><?php else: ?><p class="muted">Cap correu fallit.</p><?php endif; ?>
    </section>
</div>
<section class="card"><h2>Activitat recent</h2>
    <?php if ($activity): ?><ul class="list"><?php foreach ($activity as $a): ?>
        <li><strong><?= e($a['action']) ?></strong> <?= e($a['entity'] ?? '') ?> <small class="muted">· <?= e($a['first_name'] ?? 'Sistema') ?> · <?= e(fmt_datetime($a['created_at'])) ?></small></li>
    <?php endforeach; ?></ul>
    <p><a href="<?= e(url('/admin/registre')) ?>">Veure tot el registre</a></p>
    <?php else: ?><p class="muted">Sense activitat.</p><?php endif; ?>
</section>
