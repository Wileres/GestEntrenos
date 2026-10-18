<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Request;
use App\Helpers\Response;
use App\Models\Exercise;
use App\Models\Training;
use App\Services\AuditService;
use App\Services\FileService;
use App\Services\SettingsService;

final class FileController extends Controller
{
    public function exercise(Request $r, array $p): void
    {
        $u = $this->user();
        $f = Exercise::file($this->id($p)) ?? Response::notFound();
        if ((int) $f['is_active'] !== 1 && $u['role'] !== 'admin') {
            Response::notFound();
        }
        $this->send('exercises', $f, 'exercise_file');
    }

    public function training(Request $r, array $p): void
    {
        $f = Training::file($this->id($p)) ?? Response::notFound();
        Training::findVisible((int) $f['training_id'], $this->user()) ?? Response::notFound();
        $this->send('trainings', $f, 'training_file');
    }

    private function send(string $sub, array $f, string $entity): never
    {
        $path = FileService::path($sub, $f['stored_name']) ?? Response::notFound();
        AuditService::log('file_downloaded', $entity, (int) $f['id']);
        FileService::stream($path, (string) $f['mime_type'], (string) $f['original_name']);
    }

    /** El logotip és públic (es mostra a la pantalla d'accés). */
    public function logo(Request $r, array $p): void
    {
        $path = SettingsService::logoPath() ?? Response::notFound();
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($path));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: public, max-age=86400');
        readfile($path);
        exit;
    }
}
