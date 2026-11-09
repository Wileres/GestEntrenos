<?php

declare(strict_types=1);

/**
 * Prepara les fitxes d'assistència dels pròxims dies a partir dels horaris i els festius.
 * Opcional: l'aplicació ja ho fa sola un cop al dia en la primera visita. Es pot programar amb cron:
 *   0 5 * * *  php /ruta/al/projecte/bin/generate-sessions.php
 */
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Services\AttendanceService;

if (PHP_SAPI !== 'cli') {
    exit('Només des de la línia d\'ordres.');
}
$days = isset($argv[1]) && ctype_digit($argv[1]) ? (int) $argv[1] : AttendanceService::HORIZON_DAYS;
$n = AttendanceService::ensureSessions(date('Y-m-d'), date('Y-m-d', strtotime("+$days days")));
echo "Fitxes creades: $n\n";
