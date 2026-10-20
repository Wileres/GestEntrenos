<?php $h = fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); ?>
<h1 style="font-size:22px;margin:0 0 12px;color:#0b3d91;">Benvingut/da, <?= $h($first_name) ?>!</h1>
<p>L'administrador de <strong><?= $h($club) ?></strong> t'ha creat un compte d'<?= $h($role ?? 'entrenador') ?> a Entrenaments JSPM.</p>
<p>El teu usuari per iniciar sessió és el correu electrònic: <strong><?= $h($email) ?></strong></p>
<p>Per començar, defineix la teva contrasenya amb aquest botó:</p>
<p style="text-align:center;margin:24px 0;"><a href="<?= $h($link) ?>" style="background:#0b3d91;color:#ffffff;text-decoration:none;padding:12px 24px;border-radius:6px;font-weight:bold;display:inline-block;">Estableix la contrasenya</a></p>
<p style="font-size:14px;">Si el botó no funciona, copia aquest enllaç al navegador:<br><a href="<?= $h($link) ?>"><?= $h($link) ?></a></p>
<p style="font-size:14px;color:#5a6b85;">L'enllaç només es pot utilitzar una vegada.</p>
