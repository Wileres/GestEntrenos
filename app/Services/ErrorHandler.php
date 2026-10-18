<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Config;
use App\Helpers\HttpException;
use App\Helpers\Response;
use App\Helpers\View;

final class ErrorHandler
{
    private const MESSAGES = [
        403 => ['Accés no permès', 'No tens permís per accedir a aquesta pàgina.'],
        404 => ['Pàgina no trobada', 'La pàgina que busques no existeix o s\'ha mogut.'],
        419 => ['Sessió o formulari caducat', 'El formulari ha caducat. Torna enrere, recarrega la pàgina i prova-ho de nou.'],
        500 => ['Error intern', 'S\'ha produït un error inesperat. Torna-ho a provar més tard.'],
    ];

    public static function register(): void
    {
        set_exception_handler([self::class, 'handle']);
        set_error_handler(static function (int $no, string $str, string $file, int $line): bool {
            if (!(error_reporting() & $no)) {
                return false;
            }
            throw new \ErrorException($str, 0, $no, $file, $line);
        });
        ini_set('display_errors', '0');
    }

    public static function handle(\Throwable $e): void
    {
        $status = $e instanceof HttpException ? $e->status : 500;
        if ($status >= 500) {
            self::log($e);
        }
        if (!headers_sent()) {
            Response::noCache();
        }
        [$title, $message] = self::MESSAGES[$status] ?? self::MESSAGES[500];
        try {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            View::render('errors/error', compact('title', 'message', 'status'), 'auth', $status);
        } catch (\Throwable) {
            echo '<!doctype html><meta charset="utf-8"><title>Error</title><p>' . htmlspecialchars($message) . '</p>';
        }
    }

    public static function log(\Throwable $e): void
    {
        $frames = [];
        foreach (array_slice($e->getTrace(), 0, 12) as $f) {
            $frames[] = ($f['file'] ?? '?') . ':' . ($f['line'] ?? '?') . ' ' . ($f['class'] ?? '') . ($f['type'] ?? '') . ($f['function'] ?? '');
        }
        $entry = sprintf(
            "[%s] %s: %s in %s:%d\n  %s\n",
            date('Y-m-d H:i:s'),
            $e::class,
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            implode("\n  ", $frames)
        );
        $dir = BASE_PATH . '/storage/logs';
        if (is_dir($dir) || @mkdir($dir, 0750, true)) {
            @file_put_contents($dir . '/app-' . date('Y-m') . '.log', $entry, FILE_APPEND | LOCK_EX);
        }
        if (Config::get('debug')) {
            error_log($entry);
        }
    }
}
