<h1>Configuració del correu</h1>
<form method="post" action="<?= e(url('/admin/correu')) ?>" class="card stack" data-loading autocomplete="off">
    <?= csrf_field() ?>
    <div class="grid grid-2">
        <?= input_field('host', 'Servidor SMTP', ['attrs' => ['maxlength' => 190]]) ?>
        <?= input_field('port', 'Port SMTP', ['type' => 'number', 'required' => true, 'attrs' => ['min' => 1, 'max' => 65535]]) ?>
        <?= select_field('encryption', 'Tipus de xifrat', \App\Helpers\Labels::ENCRYPTION, ['required' => true]) ?>
        <?= input_field('username', 'Usuari SMTP', ['attrs' => ['maxlength' => 190, 'autocomplete' => 'off']]) ?>
        <?= input_field('smtp_password', 'Contrasenya SMTP', ['type' => 'password', 'help' => $passwordMask !== '' ? 'Hi ha una contrasenya desada (' . $passwordMask . '). Deixa el camp buit per conservar-la.' : 'Encara no hi ha cap contrasenya desada.', 'attrs' => ['autocomplete' => 'new-password', 'maxlength' => 255]]) ?>
        <?= input_field('from_email', 'Correu del remitent', ['type' => 'email', 'attrs' => ['maxlength' => 190]]) ?>
        <?= input_field('from_name', 'Nom del remitent', ['attrs' => ['maxlength' => 120]]) ?>
        <?= input_field('reply_to', 'Adreça de resposta', ['type' => 'email', 'attrs' => ['maxlength' => 190]]) ?>
    </div>
    <label class="check"><input type="checkbox" name="enabled" value="1"<?= checked((string) old('enabled') === '1') ?>> Activa l'enviament de correus</label>
    <div class="form-actions"><button class="btn btn-primary" type="submit"><?= icon('save') ?>Desa la configuració</button></div>
</form>
<form method="post" action="<?= e(url('/admin/correu/prova')) ?>" class="card stack" data-loading>
    <?= csrf_field() ?>
    <h2>Correu de prova</h2>
    <p class="muted">Desa primer la configuració. El correu de prova s'envia encara que els correus estiguin desactivats.</p>
    <?= input_field('test_email', 'Envia a', ['type' => 'email', 'required' => true, 'value' => current_user()['email'] ?? '']) ?>
    <div class="form-actions"><button class="btn btn-secondary" type="submit"><?= icon('send') ?>Envia el correu de prova</button></div>
</form>
