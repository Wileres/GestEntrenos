<?php $h = fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); ?>
<h1 style="font-size:22px;margin:0 0 12px;color:#0b3d91;">Recuperació de contrasenya</h1>
<p>Hola, <?= $h($first_name) ?>. Hem rebut una sol·licitud per restablir la teva contrasenya.</p>
<p style="text-align:center;margin:24px 0;"><a href="<?= $h($link) ?>" style="background:#0b3d91;color:#ffffff;text-decoration:none;padding:12px 24px;border-radius:6px;font-weight:bold;display:inline-block;">Restableix la contrasenya</a></p>
<p style="font-size:14px;">Enllaç alternatiu:<br><a href="<?= $h($link) ?>"><?= $h($link) ?></a></p>
<p style="font-size:14px;color:#5a6b85;">L'enllaç caduca d'aquí a <?= (int) $minutes ?> minuts i només es pot utilitzar una vegada. Si no ho has sol·licitat tu, ignora aquest missatge.</p>
