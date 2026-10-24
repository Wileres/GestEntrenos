<h1>Logotip i icones</h1>

<section class="card stack">
    <h2>Logotip del club</h2>
    <p class="muted">Es mostra a l'accés, a la capçalera, al panell i als correus.</p>
    <?php if ($hasLogo): ?><p><img src="<?= e(url('/logotip')) ?>?v=<?= e($version) ?>" alt="Logotip actual" class="logo-preview" width="120" height="120"></p>
    <?php else: ?><p class="muted">Encara no hi ha cap logotip.</p><?php endif; ?>
    <form method="post" action="<?= e(url('/admin/identitat/logotip')) ?>" enctype="multipart/form-data" class="stack" data-loading>
        <?= csrf_field() ?>
        <div class="field"><label for="f-logo">Nou logotip (PNG, JPG o WEBP, màxim 2 MB i 2000 px)</label>
            <input type="file" id="f-logo" name="logo" accept=".png,.jpg,.jpeg,.webp" required></div>
        <button class="btn btn-primary" type="submit"><?= icon('upload') ?>Puja el logotip</button>
    </form>
</section>

<section class="card">
    <h2>Icones de l'aplicació</h2>
    <p class="muted">Són les icones del navegador, de la pantalla d'inici del mòbil i de l'aplicació instal·lada. Pots generar-les totes a partir del logotip o pujar-ne cada una a mà (PNG quadrat).</p>
    <?php if ($hasGd && $hasLogo): ?>
        <?= post_form_open('/admin/identitat/generar', 'Es regeneraran totes les icones a partir del logotip, també les que havies personalitzat.', 'stack') ?>
            <button class="btn btn-primary" type="submit">Genera totes les icones a partir del logotip</button></form>
    <?php elseif (!$hasGd): ?><div class="alert alert-warning">L'extensió GD de PHP no està activa: la generació automàtica no és disponible, però pots pujar les icones una per una.</div>
    <?php else: ?><p class="muted">Puja primer el logotip per poder generar les icones automàticament.</p><?php endif; ?>
    <div class="icon-grid">
    <?php foreach ($icons as $name => $i): ?>
        <div class="icon-card">
            <img src="<?= e(url('/icones/' . $name, ['v' => $version])) ?>" alt="Vista prèvia de <?= e($i['label']) ?>" width="<?= min(96, $i['size']) ?>" height="<?= min(96, $i['size']) ?>" class="icon-preview<?= $name === 'icon-maskable-512' ? ' icon-preview-mask' : '' ?>">
            <strong><?= e($i['label']) ?></strong>
            <small class="muted"><?= e($i['usage']) ?></small>
            <span class="badge badge-<?= $i['custom'] ? 'success' : 'neutral' ?>"><?= $i['custom'] ? 'Personalitzada' : 'Per defecte' ?></span>
            <form method="post" action="<?= e(url('/admin/identitat/icona/' . $name)) ?>" enctype="multipart/form-data" class="stack" data-loading>
                <?= csrf_field() ?>
                <div class="field"><label for="icon-<?= e($name) ?>" class="sr-only">Fitxer PNG per a <?= e($i['label']) ?></label>
                    <input type="file" id="icon-<?= e($name) ?>" name="icon" accept=".png" required></div>
                <button class="btn btn-sm btn-secondary" type="submit"><?= icon('upload') ?>Puja</button>
            </form>
            <?php if ($i['custom']): ?>
                <?= post_form_open('/admin/identitat/icona/' . $name . '/restaura', 'Vols tornar a la icona per defecte?') ?><button class="btn btn-sm btn-danger" type="submit"><?= icon('refresh') ?>Restaura</button></form>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
    </div>
</section>

<section class="card">
    <h2>Aplicació instal·lable (PWA)</h2>
    <form method="post" action="<?= e(url('/admin/identitat/pwa')) ?>" class="stack" data-loading>
        <?= csrf_field() ?>
        <div class="grid grid-2">
            <?= input_field('pwa_name', 'Nom de l\'aplicació', ['required' => true, 'attrs' => ['maxlength' => 45]]) ?>
            <?= input_field('pwa_short_name', 'Nom curt (sota la icona)', ['required' => true, 'attrs' => ['maxlength' => 12]]) ?>
            <?= input_field('pwa_theme_color', 'Color del tema (barra del navegador)', ['type' => 'color', 'required' => true]) ?>
            <?= input_field('pwa_bg_color', 'Color de fons de la pantalla de càrrega', ['type' => 'color', 'required' => true]) ?>
        </div>
        <button class="btn btn-primary" type="submit"><?= icon('save') ?>Desa els ajustos</button>
    </form>
</section>
