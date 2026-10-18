<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Helpers\Request;
use App\Helpers\Response;
use App\Services\AuthService;

final class AdminMiddleware
{
    public static function handle(Request $request): void
    {
        AuthMiddleware::handle($request);
        if (!AuthService::isAdmin()) {
            Response::forbidden();
        }
    }
}
