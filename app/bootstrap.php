<?php

declare(strict_types=1);

use App\Helpers\Config;
use App\Helpers\Env;
use App\Services\ErrorHandler;

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/vendor/autoload.php';

Env::load(BASE_PATH . '/.env');
date_default_timezone_set((string) Config::get('timezone', 'Europe/Madrid'));
mb_internal_encoding('UTF-8');
