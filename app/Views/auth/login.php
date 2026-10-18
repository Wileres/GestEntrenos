<h1 class="auth-title">Inicia la sessió</h1>
<?php if (!empty($loginError)): ?><div class="alert alert-error" role="alert"><?= e($loginError) ?></div><?php endif; ?>
<form method="post" action="<?= e(url('/acces')) ?>" class="stack" data-loading>
    <?= csrf_field() ?>
    <?= input_field('login', 'Usuari o correu electrònic', ['required' => true, 'attrs' => ['autocomplete' => 'username', 'autofocus' => 'autofocus', 'maxlength' => 190]]) ?>
    <?= input_field('password', 'Contrasenya', ['type' => 'password', 'required' => true, 'attrs' => ['autocomplete' => 'current-password', 'maxlength' => 128]]) ?>
    <button class="btn btn-primary btn-block" type="submit">Entra</button>
</form>
<p class="center"><a href="<?= e(url('/contrasenya/oblidada')) ?>">Has oblidat la contrasenya?</a></p>
