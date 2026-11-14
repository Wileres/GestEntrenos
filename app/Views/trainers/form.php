<nav class="breadcrumb" aria-label="Migues de pa"><a href="<?= e(url('/entrenadors')) ?>">Entrenadors</a> / <?= e($title) ?></nav>
<h1><?= e($title) ?></h1>
<?php if ($trainer && (int) $trainer['pending_approval']): ?>
    <?php if ((int) $trainer['has_password']): ?>
<div class="alert alert-warning" role="status">Aquest entrenador s'ha registrat ell mateix i està pendent d'aprovació. Assigna-li almenys un equip, marca «Compte actiu» i desa: rebrà un correu d'avís.</div>
    <?php else: ?>
<div class="alert alert-warning" role="status">Aquest entrenador s'ha importat i està pendent d'equip. Assigna-li almenys un equip i desa: rebrà un correu per definir la contrasenya i el compte quedarà activat.</div>
    <?php endif; ?>
<?php endif; ?>
<form method="post" action="<?= e(url($trainer ? '/entrenadors/' . $trainer['id'] : '/entrenadors')) ?>" class="stack" data-loading>
    <?= csrf_field() ?>
    <section class="card">
        <div class="grid grid-2">
            <?= input_field('first_name', 'Nom', ['required' => true, 'attrs' => ['maxlength' => 80, 'autocomplete' => 'off']]) ?>
            <?= input_field('last_name', 'Cognoms', ['required' => true, 'attrs' => ['maxlength' => 120, 'autocomplete' => 'off']]) ?>
            <?= input_field('email', 'Correu electrònic', ['type' => 'email', 'required' => true, 'attrs' => ['maxlength' => 190, 'autocomplete' => 'off']]) ?>
            <?= input_field('phone', 'Telèfon', ['type' => 'tel', 'attrs' => ['maxlength' => 30]]) ?>
        </div>
        <?= input_field('notes', 'Observacions', ['type' => 'textarea', 'rows' => 3]) ?>
        <label class="check"><input type="checkbox" name="is_active" value="1"<?= checked(old('is_active', '1') === '1' || old('is_active') === 1) ?>> Compte actiu</label>
    </section>
    <section class="card">
        <fieldset><legend>Equips assignats</legend>
            <?php if (field_error('team_ids')): ?><p class="field-error"><?= e(field_error('team_ids')) ?></p><?php endif; ?>
            <div class="checks">
            <?php foreach ($teams as $c): ?>
                <label class="check"><input type="checkbox" name="team_ids[]" value="<?= (int) $c['id'] ?>"<?= checked(in_old('team_ids', $c['id'])) ?>> <?= e($c['name']) ?><?= (int) $c['is_active'] ? '' : ' (inactiu)' ?></label>
            <?php endforeach; ?>
            </div>
        </fieldset>
    </section>
    <div class="form-actions">
        <button class="btn btn-primary" type="submit"><?= $trainer ? 'Desa els canvis' : 'Crea l\'entrenador i envia l\'activació' ?></button>
        <a class="btn btn-secondary" href="<?= e(url('/entrenadors')) ?>">Cancel·la</a>
    </div>
</form>
<?php if ($trainer): ?>
<section class="card">
    <h2>Eliminar l'entrenador</h2>
    <p class="muted">S'esborra el compte i les seves assignacions. Si només vols que no pugui entrar, desactiva'l.</p>
    <?= post_form_open('/entrenadors/' . $trainer['id'] . '/eliminar', 'Vols eliminar definitivament aquest entrenador? Aquesta acció no es pot desfer.') ?>
        <button class="btn btn-danger" type="submit"><?= icon('trash') ?>Elimina l'entrenador</button></form>
</section>
<section class="card">
    <h2>Activació del compte</h2>
    <?php if ((int) $trainer['has_password']): ?>
        <p>Aquest entrenador ja ha activat el compte<?= $trainer['last_login_at'] ? ' (darrer accés: ' . e(fmt_datetime($trainer['last_login_at'])) . ')' : '' ?>.</p>
    <?php else: ?>
        <p><?= $pendingActivation ? 'Hi ha un enllaç d\'activació pendent d\'utilitzar.' : 'No hi ha cap enllaç d\'activació vàlid.' ?></p>
        <div class="row">
            <?= post_form_open('/entrenadors/' . $trainer['id'] . '/activacio', 'Es generarà un enllaç nou i els anteriors deixaran de funcionar.') ?>
                <input type="hidden" name="do" value="resend"><button class="btn btn-secondary" type="submit">Reenvia l'activació (enllaç nou)</button></form>
            <?php if ($pendingActivation): ?>
            <?= post_form_open('/entrenadors/' . $trainer['id'] . '/activacio', 'L\'enllaç enviat deixarà de funcionar.') ?>
                <input type="hidden" name="do" value="revoke"><button class="btn btn-danger" type="submit">Invalida els enllaços</button></form>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>
<?php endif; ?>
