<?php $h = fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); ?>
<h1 style="font-size:22px;margin:0 0 12px;color:#0b3d91;">Nou exercici</h1>
<p>Hola, <?= $h($first_name) ?>. S'ha afegit un exercici nou als teus equips.</p>
<table role="presentation" cellpadding="6" cellspacing="0" style="border-collapse:collapse;width:100%;font-size:15px;">
  <tr><td style="color:#5a6b85;width:120px;">Exercici</td><td><strong><?= $h($exercise['name']) ?></strong></td></tr>
  <?php if ($teams): ?><tr><td style="color:#5a6b85;">Equips</td><td><?= $h(implode(', ', $teams)) ?></td></tr><?php endif; ?>
  <?php if (!empty($exercise['objective'])): ?><tr><td style="color:#5a6b85;">Objectiu</td><td><?= $h($exercise['objective']) ?></td></tr><?php endif; ?>
</table>
<p style="text-align:center;margin:24px 0;"><a href="<?= $h($link) ?>" style="background:#0b3d91;color:#ffffff;text-decoration:none;padding:12px 24px;border-radius:6px;font-weight:bold;display:inline-block;">Veure l'exercici</a></p>
