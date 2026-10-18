<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\HttpException;
use App\Helpers\Request;
use App\Middleware\AdminMiddleware;
use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Middleware\GuestMiddleware;

final class Router
{
    private const MIDDLEWARE = [
        'auth' => AuthMiddleware::class,
        'admin' => AdminMiddleware::class,
        'guest' => GuestMiddleware::class,
    ];

    /** @var array<int,array{0:string,1:string,2:array,3:array}> */
    private array $routes = [];

    public function get(string $pattern, array $handler, array $middleware = []): void
    {
        $this->routes[] = ['GET', $pattern, $handler, $middleware];
    }

    public function post(string $pattern, array $handler, array $middleware = []): void
    {
        $this->routes[] = ['POST', $pattern, $handler, $middleware];
    }

    public function dispatch(Request $request): void
    {
        foreach ($this->routes as [$method, $pattern, $handler, $middleware]) {
            if ($method !== $request->method) {
                continue;
            }
            $regex = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>[A-Za-z0-9_-]+)', $pattern) . '$#';
            if (!preg_match($regex, $request->path, $m)) {
                continue;
            }
            $params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);

            if ($request->method === 'POST') {
                CsrfMiddleware::handle($request);
            }
            foreach ($middleware as $name) {
                (self::MIDDLEWARE[$name])::handle($request);
            }
            [$class, $action] = $handler;
            (new $class())->$action($request, $params);
            return;
        }
        throw new HttpException(404);
    }
}
