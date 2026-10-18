<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Helpers\Config;
use App\Helpers\Request;
use App\Helpers\Response;
use App\Helpers\Session;
use App\Services\ErrorHandler;
use App\Services\Router;

ErrorHandler::register();

if (!is_file(BASE_PATH . '/storage/installed.lock')) {
    header('Location: ' . Request::basePath() . '/install.php');
    exit;
}

if (Config::get('force_https') && Config::get('env') === 'production' && !Request::isHttps()) {
    $host = preg_replace('/[^A-Za-z0-9.:-]/', '', (string) ($_SERVER['HTTP_HOST'] ?? ''));
    header('Location: https://' . $host . ($_SERVER['REQUEST_URI'] ?? '/'), true, 301);
    exit;
}

Response::securityHeaders();
Response::noCache();
Session::start();

$router = new Router();
(require BASE_PATH . '/routes/web.php')($router);
$router->dispatch(Request::capture());
