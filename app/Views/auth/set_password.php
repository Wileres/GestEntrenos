<h1 class="auth-title"><?= e($heading) ?></h1>
<p>Tria una contrasenya de com a mínim 10 caràcters, amb lletres i números.</p>
<form method="post" action="<?= e($action) ?>" class="stack" data-loading>
    <?= csrf_field() ?>
    <?= input_field('password', 'Contrasenya nova', ['type' => 'password', 'required' => true, 'attrs' => ['autocomplete' => 'new-password', 'minlength' => 10, 'maxlength' => 128]]) ?>
    <?= input_field('password_confirmation', 'Repeteix la contrasenya', ['type' => 'password', 'required' => true, 'attrs' => ['autocomplete' => 'new-password', 'maxlength' => 128]]) ?>
    <button class="btn btn-primary btn-block" type="submit">Desa la contrasenya</button>
</form>
