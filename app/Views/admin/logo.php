<h1>Logotip del club</h1>
<section class="card stack narrow">
    <?php if ($hasLogo): ?><p><img src="<?= e(url('/logotip')) ?>?v=<?= time() ?>" alt="Logotip actual" class="logo-preview" width="160" height="160"></p>
    <?php else: ?><p class="muted">Encara no hi ha cap logotip.</p><?php endif; ?>
    <form method="post" action="<?= e(url('/admin/logotip')) ?>" enctype="multipart/form-data" class="stack" data-loading>
        <?= csrf_field() ?>
        <div class="field"><label for="f-logo">Nou logotip (PNG, JPG o WEBP, màxim 2 MB i 2000 px)</label>
            <input type="file" id="f-logo" name="logo" accept=".png,.jpg,.jpeg,.webp" required></div>
        <button class="btn btn-primary" type="submit">Puja el logotip</button>
    </form>
</section>
