<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Helpers\Request;
use App\Helpers\Response;
use App\Services\AuthService;

final class GuestMiddleware
{
    public static function handle(Request $request): void
    {
        if (AuthService::user() !== null) {
            Response::redirect('/');
        }
    }
}
