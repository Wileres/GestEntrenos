<h1 class="auth-title">Recupera la contrasenya</h1>
<p>Introdueix el teu correu electrònic i t'enviarem un enllaç per definir una contrasenya nova.</p>
<form method="post" action="<?= e(url('/contrasenya/oblidada')) ?>" class="stack" data-loading>
    <?= csrf_field() ?>
    <?= input_field('email', 'Correu electrònic', ['type' => 'email', 'required' => true, 'attrs' => ['autocomplete' => 'email', 'maxlength' => 190]]) ?>
    <button class="btn btn-primary btn-block" type="submit">Envia l'enllaç</button>
</form>
<p class="center"><a href="<?= e(url('/acces')) ?>">Torna a l'accés</a></p>
