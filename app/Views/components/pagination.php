<?php
/** @var \App\Helpers\Paginator $pager */
if ($pager->pages <= 1) {
    return;
}
$query = $_GET;
$link = function (int $page) use ($query): string {
    $query['page'] = $page;
    return '?' . http_build_query($query);
};
$from = max(1, $pager->page - 2);
$to = min($pager->pages, $pager->page + 2);
?>
<nav class="pagination" aria-label="Paginació">
    <?php if ($pager->page > 1): ?><a href="<?= e($link($pager->page - 1)) ?>" rel="prev">« Anterior</a><?php endif; ?>
    <?php for ($i = $from; $i <= $to; $i++): ?>
        <?php if ($i === $pager->page): ?><span class="current" aria-current="page"><?= $i ?></span>
        <?php else: ?><a href="<?= e($link($i)) ?>"><?= $i ?></a><?php endif; ?>
    <?php endfor; ?>
    <?php if ($pager->page < $pager->pages): ?><a href="<?= e($link($pager->page + 1)) ?>" rel="next">Següent »</a><?php endif; ?>
    <span class="muted"><?= (int) $pager->total ?> resultats</span>
</nav>
