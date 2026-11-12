<?php $h = fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); ?>
<h1 style="font-size:22px;margin:0 0 12px;color:#0b3d91;">El teu compte ja està activat</h1>
<p>Hola, <?= $h($first_name) ?>. Un administrador ha activat el teu compte i t'ha assignat els teus equips.</p>
<p style="text-align:center;margin:24px 0;"><a href="<?= $h($link) ?>" style="background:#0b3d91;color:#ffffff;text-decoration:none;padding:12px 24px;border-radius:6px;font-weight:bold;display:inline-block;">Entra a l'aplicació</a></p>
