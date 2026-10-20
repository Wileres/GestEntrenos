<?php
/**
 * Llista d'adjunts. Paràmetres: $files, $kind ('exercici'|'entrenament'), $compact (bool, opcional).
 * Els PDF i les imatges s'obren al navegador (nova pestanya); la resta es descarrega.
 */
$compact = $compact ?? false;
?>
<ul class="files<?= $compact ? ' files-compact' : '' ?>">
<?php foreach ($files as $f):
    $href = url('/fitxers/' . $kind . '/' . $f['id']);
    $viewable = \App\Services\FileService::isViewable((string) $f['mime_type']);
    $isImage = str_starts_with((string) $f['mime_type'], 'image/');
    $ext = strtoupper(pathinfo((string) $f['original_name'], PATHINFO_EXTENSION)) ?: 'FITXER';
    $type = $isImage ? 'img' : strtolower($ext);
    ?>
    <li class="file">
        <a class="file-open" href="<?= e($viewable ? $href . '?veure=1' : $href) ?>"<?= $viewable ? ' target="_blank" rel="noopener"' : '' ?>>
            <?php if ($isImage && !$compact): ?>
                <img class="file-thumb" src="<?= e($href . '?veure=1') ?>" alt="" loading="lazy" width="56" height="56">
            <?php else: ?>
                <span class="file-type file-type-<?= e($type) ?>" aria-hidden="true"><?= e($ext) ?></span>
            <?php endif; ?>
            <span class="file-info">
                <span class="file-name"><?= e($f['original_name']) ?></span>
                <span class="file-meta"><?= $viewable ? 'Obre' : 'Descarrega' ?> · <?= e(human_size((int) $f['size_bytes'])) ?></span>
            </span>
        </a>
        <?php if ($viewable && !$compact): ?>
            <a class="file-download" href="<?= e($href) ?>" download aria-label="Descarrega <?= e($f['original_name']) ?>">Descarrega</a>
        <?php endif; ?>
    </li>
<?php endforeach; ?>
</ul>
