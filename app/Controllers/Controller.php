<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\HttpException;
use App\Helpers\Response;
use App\Helpers\Session;
use App\Helpers\View;
use App\Services\AuthService;

abstract class Controller
{
    protected function render(string $template, array $data = [], int $status = 200, string $layout = 'main'): void
    {
        View::render($template, $data, $layout, $status);
    }

    /** Torna a mostrar un formulari amb els errors i les dades introduïdes. */
    protected function invalid(string $template, array $data, array $errors, array $old): void
    {
        $layout = \App\Services\AuthService::user() === null ? 'auth' : 'main';
        $this->render($template, $data + ['errors' => $errors, 'old' => $old], 422, $layout);
    }

    protected function done(string $message, string $to, string $type = 'success'): never
    {
        Session::flash($type, $message);
        Response::redirect($to);
    }

    protected function id(array $params, string $key = 'id'): int
    {
        $v = $params[$key] ?? '';
        if (!ctype_digit((string) $v) || (int) $v < 1) {
            throw new HttpException(404);
        }
        return (int) $v;
    }

    protected function user(): array
    {
        return AuthService::user() ?? throw new HttpException(403);
    }

    /** Filtra una llista d'identificadors deixant només els existents al conjunt permès. */
    protected function onlyKnown(array $ids, array $known): array
    {
        return array_values(array_intersect($ids, array_map('intval', $known)));
    }
}
