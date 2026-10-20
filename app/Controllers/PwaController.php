<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Request;
use App\Helpers\Response;
use App\Services\IconService;
use App\Services\SettingsService;

/** Recursos públics de la PWA: manifest, service worker, icones i pàgina sense connexió. */
final class PwaController extends Controller
{
    public function manifest(Request $r, array $p): void
    {
        $pwa = SettingsService::pwa();
        $v = '?v=' . IconService::version();
        $manifest = [
            'id' => './',
            'name' => $pwa['name'],
            'short_name' => $pwa['short'],
            'description' => 'Gestió d\'entrenaments, exercicis i equips del ' . SettingsService::clubName() . '.',
            'lang' => 'ca',
            'start_url' => './?source=pwa',
            'scope' => './',
            'display' => 'standalone',
            'orientation' => 'any',
            'theme_color' => $pwa['theme'],
            'background_color' => $pwa['bg'],
            'icons' => [
                ['src' => 'icones/icon-192' . $v, 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => 'icones/icon-512' . $v, 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => 'icones/icon-maskable-512' . $v, 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
        ];
        header('Content-Type: application/manifest+json; charset=utf-8');
        header('Cache-Control: no-cache');
        echo json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }

    public function serviceWorker(Request $r, array $p): void
    {
        $file = BASE_PATH . '/app/Views/pwa/service-worker.js';
        $precache = [
            url('/offline'), asset('css/app.css'), asset('js/app.js'),
            IconService::url('icon-192'), IconService::url('favicon-32'),
        ];
        $version = substr(hash('sha256', implode('|', [
            filemtime($file), @filemtime(BASE_PATH . '/public/assets/css/app.css'), @filemtime(BASE_PATH . '/public/assets/js/app.js'), IconService::version(),
        ])), 0, 12);
        $js = str_replace(
            ['__VERSION__', '__PRECACHE__'],
            [$version, json_encode($precache, JSON_UNESCAPED_SLASHES)],
            (string) file_get_contents($file)
        );
        header('Content-Type: text/javascript; charset=utf-8');
        header('Cache-Control: no-cache');
        header('Service-Worker-Allowed: ' . (Request::basePath() ?: '') . '/');
        echo $js;
    }

    public function offline(Request $r, array $p): void
    {
        header('Content-Type: text/html; charset=utf-8');
        echo \App\Helpers\View::capture('pwa/offline');
    }

    public function icon(Request $r, array $p): void
    {
        $path = IconService::path((string) ($p['name'] ?? '')) ?? Response::notFound();
        $this->sendPng($path);
    }

    public function favicon(Request $r, array $p): void
    {
        $this->sendPng(IconService::path('favicon-32'));
    }

    private function sendPng(string $path): never
    {
        header('Content-Type: image/png');
        header('Content-Length: ' . filesize($path));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: public, max-age=604800');
        readfile($path);
        exit;
    }
}
