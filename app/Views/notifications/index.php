<h1>Notificacions</h1>
<div class="grid grid-2">
    <section class="card" data-push data-vapid-key="<?= e($vapidKey) ?>" data-csrf="<?= e(\App\Helpers\Csrf::token()) ?>"
             data-subscribe-url="<?= e(url('/push/subscriure')) ?>" data-unsubscribe-url="<?= e(url('/push/cancela')) ?>">
        <h2>Al navegador</h2>
        <p>Rep un avís en aquest dispositiu quan hi hagi un exercici o un entrenament nou als teus equips, encara que l'aplicació estigui tancada.</p>
        <p class="muted" data-push-status><?= $subscribed ? 'Activades en algun dispositiu.' : 'No activades.' ?></p>
        <p class="muted" data-push-unsupported hidden>Aquest navegador no admet notificacions. A l'iPhone/iPad cal instal·lar primer l'aplicació a la pantalla d'inici.</p>
        <button type="button" class="btn btn-primary" data-push-toggle hidden>Activa les notificacions en aquest dispositiu</button>
    </section>
    <section class="card">
        <h2>Per correu</h2>
        <?= post_form_open('/notificacions/preferencies', '', 'stack') ?>
            <label class="check"><input type="checkbox" name="notify_email" value="1"<?= checked($notifyEmail) ?>> Vull rebre correus quan hi hagi novetats als meus equips</label>
            <button class="btn btn-secondary" type="submit">Desa</button>
        </form>
    </section>
</div>
<section class="card">
    <h2>Notificacions recents</h2>
    <?php if ($items): ?><ul class="list">
        <?php foreach ($items as $n): ?>
            <li<?= $n['read_at'] ? '' : ' class="unread"' ?>>
                <?php if ($n['url']): ?><a href="<?= e(url($n['url'])) ?>"><strong><?= e($n['title']) ?></strong></a><?php else: ?><strong><?= e($n['title']) ?></strong><?php endif; ?>
                <?= $n['read_at'] ? '' : '<span class="badge badge-info">Nova</span>' ?><br>
                <?= e($n['body']) ?> <small class="muted">· <?= e(fmt_datetime($n['created_at'])) ?></small>
            </li>
        <?php endforeach; ?></ul>
    <?php else: ?><p class="muted">Encara no tens notificacions.</p><?php endif; ?>
</section>
