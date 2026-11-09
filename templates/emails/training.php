<?php
$h = fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$heads = ['published' => 'Nou entrenament publicat', 'updated' => 'Entrenament modificat', 'cancelled' => 'Entrenament cancel·lat'];
?>
<h1 style="font-size:22px;margin:0 0 12px;color:<?= $kind === 'cancelled' ? '#b00020' : '#0b3d91' ?>;"><?= $h($heads[$kind] ?? $heads['updated']) ?></h1>
<p>Hola, <?= $h($first_name) ?>.</p>
<?php if ($changes !== ''): ?><p><strong>Canvis:</strong> <?= $h($changes) ?></p><?php endif; ?>
<table role="presentation" cellpadding="6" cellspacing="0" style="border-collapse:collapse;width:100%;font-size:15px;">
  <tr><td style="color:#5a6b85;width:120px;">Títol</td><td><strong><?= $h($training['title']) ?></strong></td></tr>
  <tr><td style="color:#5a6b85;">Data</td><td><?= $h(date('d/m/Y', strtotime($training['session_date']))) ?></td></tr>
  <tr><td style="color:#5a6b85;">Hora</td><td><?= $h(substr($training['start_time'], 0, 5)) ?></td></tr>
  <?php if ($teams): ?><tr><td style="color:#5a6b85;">Equips</td><td><?= $h(implode(', ', $teams)) ?></td></tr><?php endif; ?>
</table>
<?php if (!empty($training['description'])): ?><p><?= nl2br($h($training['description'])) ?></p><?php endif; ?>
<?php if ($kind !== 'cancelled'): ?>
<p style="text-align:center;margin:24px 0;"><a href="<?= $h($link) ?>" style="background:#0b3d91;color:#ffffff;text-decoration:none;padding:12px 24px;border-radius:6px;font-weight:bold;display:inline-block;">Veure l'entrenament</a></p>
<?php endif; ?>
