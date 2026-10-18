<h1>Registre d'activitat</h1>
<form method="get" class="filters card">
    <?= select_field('action', 'Acció', array_combine($actions, $actions), ['value' => $action, 'blank' => 'Totes']) ?>
    <div class="filters-actions"><button class="btn btn-primary" type="submit">Filtra</button></div>
</form>
<div class="table-wrap"><table class="table">
    <thead><tr><th>Data</th><th>Usuari</th><th>Acció</th><th>Entitat</th><th>Resultat</th><th>IP</th><th>Detalls</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
        <tr>
            <td data-label="Data"><?= e(fmt_datetime($r['created_at'])) ?></td>
            <td data-label="Usuari"><?= e($r['user_name'] ?? '—') ?></td>
            <td data-label="Acció"><?= e($r['action']) ?></td>
            <td data-label="Entitat"><?= e(trim(($r['entity'] ?? '') . ' ' . ($r['entity_id'] ?? ''))) ?></td>
            <td data-label="Resultat"><span class="badge badge-<?= $r['result'] === 'ok' ? 'success' : 'danger' ?>"><?= $r['result'] === 'ok' ? 'Correcte' : 'Error' ?></span></td>
            <td data-label="IP"><?= e($r['ip_address']) ?></td>
            <td data-label="Detalls"><?= e($r['details']) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table></div>
<?php \App\Helpers\View::component('pagination', ['pager' => $pager]); ?>
