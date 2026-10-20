<nav class="breadcrumb"><a href="<?= e(url('/administradors')) ?>">Administradors</a> / <?= e($title) ?></nav>
<h1><?= e($title) ?></h1>
<form method="post" action="<?= e(url($admin ? '/administradors/' . $admin['id'] : '/administradors')) ?>" class="card stack" data-loading>
    <?= csrf_field() ?>
    <div class="grid grid-2">
        <?= input_field('first_name', 'Nom', ['required' => true, 'attrs' => ['maxlength' => 80, 'autocomplete' => 'off']]) ?>
        <?= input_field('last_name', 'Cognoms', ['required' => true, 'attrs' => ['maxlength' => 120, 'autocomplete' => 'off']]) ?>
        <?= input_field('email', 'Correu electrònic (serveix per iniciar sessió)', ['type' => 'email', 'required' => true, 'attrs' => ['maxlength' => 190, 'autocomplete' => 'off']]) ?>
        <?= input_field('phone', 'Telèfon', ['type' => 'tel', 'attrs' => ['maxlength' => 30]]) ?>
    </div>
    <?php if (!$admin || (int) $admin['id'] !== ($me ?? 0)): ?>
        <label class="check"><input type="checkbox" name="is_active" value="1"<?= checked((string) old('is_active', '1') === '1') ?>> Compte actiu</label>
        <?php if (field_error('is_active')): ?><p class="field-error"><?= e(field_error('is_active')) ?></p><?php endif; ?>
    <?php endif; ?>
    <div class="form-actions"><button class="btn btn-primary" type="submit"><?= $admin ? 'Desa els canvis' : 'Crea l\'administrador i envia l\'activació' ?></button>
        <a class="btn btn-secondary" href="<?= e(url('/administradors')) ?>">Cancel·la</a></div>
</form>
<?php if ($admin && !(int) $admin['has_password']): ?>
<section class="card"><h2>Activació del compte</h2>
    <p><?= !empty($pendingActivation) ? 'Hi ha un enllaç d\'activació pendent d\'utilitzar.' : 'No hi ha cap enllaç d\'activació vàlid.' ?></p>
    <div class="row">
        <?= post_form_open('/administradors/' . $admin['id'] . '/activacio', 'Es generarà un enllaç nou i els anteriors deixaran de funcionar.') ?>
            <input type="hidden" name="do" value="resend"><button class="btn btn-secondary" type="submit">Reenvia l'activació</button></form>
        <?php if (!empty($pendingActivation)): ?>
        <?= post_form_open('/administradors/' . $admin['id'] . '/activacio', 'L\'enllaç enviat deixarà de funcionar.') ?>
            <input type="hidden" name="do" value="revoke"><button class="btn btn-danger" type="submit">Invalida els enllaços</button></form>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>
