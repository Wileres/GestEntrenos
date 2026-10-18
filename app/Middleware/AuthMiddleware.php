<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Helpers\Request;
use App\Helpers\Response;
use App\Helpers\Session;
use App\Services\AuthService;

final class AuthMiddleware
{
    private const ALLOWED_WHEN_PASSWORD_CHANGE = ['/compte/contrasenya', '/sortir'];

    public static function handle(Request $request): void
    {
        $user = AuthService::user();
        if ($user === null) {
            if (Session::get('_expired')) {
                Session::flash('warning', 'La sessió ha caducat per inactivitat. Torna a iniciar-la.');
                Session::forget('_expired');
            }
            Response::redirect('/acces');
        }
        if ((int) $user['must_change_password'] === 1 && !in_array($request->path, self::ALLOWED_WHEN_PASSWORD_CHANGE, true)) {
            Response::redirect('/compte/contrasenya');
        }
    }
}
