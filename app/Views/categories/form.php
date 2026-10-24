<nav class="breadcrumb"><a href="<?= e(url('/categories')) ?>">Categories</a> / <?= e($title) ?></nav>
<h1><?= e($title) ?></h1>
<form method="post" action="<?= e(url($category ? '/categories/' . $category['id'] : '/categories')) ?>" class="card stack narrow" data-loading>
    <?= csrf_field() ?>
    <?= input_field('name', 'Nom', ['required' => true, 'attrs' => ['maxlength' => 80]]) ?>
    <?= input_field('sort_order', 'Ordre', ['type' => 'number', 'required' => true, 'attrs' => ['min' => 0, 'max' => 9999]]) ?>
    <label class="check"><input type="checkbox" name="is_active" value="1"<?= checked((string) old('is_active', '1') === '1') ?>> Categoria activa</label>
    <div class="form-actions"><button class="btn btn-primary" type="submit"><?= icon('save') ?>Desa</button> <a class="btn btn-secondary" href="<?= e(url('/categories')) ?>">Cancel·la</a></div>
</form>
