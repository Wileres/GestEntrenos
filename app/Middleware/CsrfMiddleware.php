<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Helpers\Csrf;
use App\Helpers\HttpException;
use App\Helpers\Request;

final class CsrfMiddleware
{
    public static function handle(Request $request): void
    {
        $token = $request->post['_csrf'] ?? ($request->server['HTTP_X_CSRF_TOKEN'] ?? null);
        if (!Csrf::verify(is_string($token) ? $token : null)) {
            throw new HttpException(419);
        }
    }
}
