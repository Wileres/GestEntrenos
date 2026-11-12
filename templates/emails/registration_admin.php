<?php $h = fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); ?>
<h1 style="font-size:22px;margin:0 0 12px;color:#0b3d91;">Nou entrenador pendent d'aprovar</h1>
<p>Hola, <?= $h($first_name) ?>. <strong><?= $h($trainer_name) ?></strong> (<?= $h($trainer_email) ?>) s'ha registrat com a entrenador.</p>
<p>El compte queda pendent fins que li assignis un equip i l'activis.</p>
<p style="text-align:center;margin:24px 0;"><a href="<?= $h($link) ?>" style="background:#0b3d91;color:#ffffff;text-decoration:none;padding:12px 24px;border-radius:6px;font-weight:bold;display:inline-block;">Revisa i activa el compte</a></p>
