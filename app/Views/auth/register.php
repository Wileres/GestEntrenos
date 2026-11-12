<h1 class="auth-title">Registre d'entrenador</h1>
<p>Omple les dades. Un administrador t'assignarà un equip i activarà el compte; t'avisarem per correu.</p>
<form method="post" action="<?= e(url('/registre')) ?>" class="stack" data-loading>
    <?= csrf_field() ?>
    <?= input_field('first_name', 'Nom', ['required' => true, 'attrs' => ['autocomplete' => 'given-name', 'autofocus' => 'autofocus', 'maxlength' => 80]]) ?>
    <?= input_field('last_name', 'Cognoms', ['required' => true, 'attrs' => ['autocomplete' => 'family-name', 'maxlength' => 120]]) ?>
    <?= input_field('email', 'Correu electrònic', ['type' => 'email', 'required' => true, 'attrs' => ['autocomplete' => 'email', 'maxlength' => 190]]) ?>
    <?= input_field('password', 'Contrasenya', ['type' => 'password', 'required' => true, 'help' => 'Mínim 10 caràcters, amb lletres i números.', 'attrs' => ['autocomplete' => 'new-password', 'minlength' => 10, 'maxlength' => 128]]) ?>
    <?= input_field('password_confirmation', 'Repeteix la contrasenya', ['type' => 'password', 'required' => true, 'attrs' => ['autocomplete' => 'new-password', 'maxlength' => 128]]) ?>
    <?= input_field('captcha', 'Comprovació: ' . $captcha, ['value' => '', 'required' => true, 'attrs' => ['inputmode' => 'numeric', 'autocomplete' => 'off', 'maxlength' => 3]]) ?>
    <div style="position:absolute;left:-10000px" aria-hidden="true"><label>No omplis aquest camp<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
    <button class="btn btn-primary btn-block" type="submit"><?= icon('save') ?>Registra'm</button>
</form>
<p class="center"><a href="<?= e(url('/acces')) ?>">Ja tens compte? Accedeix</a></p>
