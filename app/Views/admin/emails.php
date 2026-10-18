<h1>Historial de correus</h1>
<form method="get" class="filters card">
    <?= select_field('status', 'Estat', ['sent' => 'Enviats', 'failed' => 'Fallits', 'skipped' => 'Omesos'], ['value' => $status, 'blank' => 'Tots']) ?>
    <div class="filters-actions"><button class="btn btn-primary" type="submit">Filtra</button></div>
</form>
<div class="table-wrap"><table class="table">
    <thead><tr><th>Data</th><th>Tipus</th><th>Destinatari</th><th>Estat</th><th>Intents</th><th>Error</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
        <tr>
            <td data-label="Data"><?= e(fmt_datetime($r['created_at'])) ?></td>
            <td data-label="Tipus"><?= e($r['type']) ?></td>
            <td data-label="Destinatari"><?= e($r['recipient']) ?></td>
            <td data-label="Estat"><span class="badge badge-<?= ['sent' => 'success', 'failed' => 'danger', 'skipped' => 'neutral'][$r['status']] ?>"><?= ['sent' => 'Enviat', 'failed' => 'Fallit', 'skipped' => 'Omès'][$r['status']] ?></span></td>
            <td data-label="Intents"><?= (int) $r['attempts'] ?></td>
            <td data-label="Error"><?= e($r['error_message']) ?></td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="6" class="muted">Cap correu registrat.</td></tr><?php endif; ?>
    </tbody>
</table></div>
<?php \App\Helpers\View::component('pagination', ['pager' => $pager]); ?>
