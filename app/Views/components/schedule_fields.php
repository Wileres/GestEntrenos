<?php /** Editor d'horari setmanal: una fila per dia (activa, hora d'inici i durada). Camps sch[dia][on|time|dur]. */
$sch = \App\Helpers\View::$old['sch'] ?? [];
?>
<div class="schedule">
<?php foreach (\App\Models\Schedule::WEEKDAYS as $d => $label):
    $row = $sch[$d] ?? ['on' => false, 'time' => '18:00', 'dur' => '90'];
    $time = $row['time'] !== '' ? $row['time'] : '18:00'; $dur = $row['dur'] !== '' ? $row['dur'] : '90'; ?>
    <div class="schedule-row<?= field_error("sch_$d") ? ' has-error' : '' ?>">
        <label class="check schedule-day"><input type="checkbox" name="sch[<?= $d ?>][on]" value="1"<?= checked(!empty($row['on'])) ?>> <span><?= e($label) ?></span></label>
        <label class="schedule-field"><span class="sr-only">Hora d'inici (<?= e($label) ?>)</span><input type="time" name="sch[<?= $d ?>][time]" value="<?= e($time) ?>"></label>
        <label class="schedule-field schedule-dur"><span class="sr-only">Durada en minuts (<?= e($label) ?>)</span><input type="number" name="sch[<?= $d ?>][dur]" value="<?= e($dur) ?>" min="15" max="300" step="5"><small class="muted">min</small></label>
        <?php if (field_error("sch_$d")): ?><p class="field-error"><?= e(field_error("sch_$d")) ?></p><?php endif; ?>
    </div>
<?php endforeach; ?>
</div>
