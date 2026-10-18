<div class="narrow">
<h1>Canvia la contrasenya</h1>
<?php if (!empty($forced)): ?>
    <div class="alert alert-warning" role="status">Per seguretat, has de canviar la contrasenya inicial abans de continuar.</div>
<?php endif; ?>
<form method="post" action="<?= e(url('/compte/contrasenya')) ?>" class="card stack" data-loading>
    <?= csrf_field() ?>
    <?= input_field('current_password', 'Contrasenya actual', ['type' => 'password', 'required' => true, 'attrs' => ['autocomplete' => 'current-password']]) ?>
    <?= input_field('password', 'Contrasenya nova', ['type' => 'password', 'required' => true, 'help' => 'Mínim 10 caràcters, amb lletres i números.', 'attrs' => ['autocomplete' => 'new-password', 'minlength' => 10, 'maxlength' => 128]]) ?>
    <?= input_field('password_confirmation', 'Repeteix la contrasenya nova', ['type' => 'password', 'required' => true, 'attrs' => ['autocomplete' => 'new-password']]) ?>
    <button class="btn btn-primary" type="submit">Canvia la contrasenya</button>
</form>
</div>
