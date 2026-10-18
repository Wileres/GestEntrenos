<!doctype html>
<html lang="ca"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head>
<body style="margin:0;padding:0;background:#eef3fb;font-family:Arial,Helvetica,sans-serif;color:#1b2a41;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eef3fb;padding:24px 12px;">
<tr><td align="center">
  <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:8px;overflow:hidden;">
    <tr><td style="background:#0b3d91;padding:20px 24px;color:#ffffff;">
      <?php if (!empty($hasLogo)): ?><img src="cid:logo-club" alt="" width="48" height="48" style="vertical-align:middle;margin-right:12px;border-radius:4px;background:#fff;"><?php endif; ?>
      <span style="font-size:20px;font-weight:bold;vertical-align:middle;"><?= htmlspecialchars($club, ENT_QUOTES, 'UTF-8') ?></span>
    </td></tr>
    <tr><td style="padding:24px;font-size:16px;line-height:1.5;"><?= $body ?></td></tr>
    <tr><td style="padding:16px 24px;background:#f5f8fd;font-size:12px;color:#5a6b85;">Aquest missatge s'ha enviat automàticament des d'Entrenaments JSPM. No hi responguis si no s'indica el contrari.</td></tr>
  </table>
</td></tr></table>
</body></html>
