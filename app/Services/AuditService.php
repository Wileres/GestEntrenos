<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Db;

final class AuditService
{
    public static function log(
        string $action,
        ?string $entity = null,
        ?int $entityId = null,
        string $result = 'ok',
        ?string $details = null,
        ?int $userId = null
    ): void {
        try {
            $userId ??= AuthService::user()['id'] ?? null;
            if ($details !== null) {
                $details = preg_replace('/[A-Fa-f0-9]{32,}/', '[ocult]', $details);
                $details = mb_substr($details, 0, 500);
            }
            Db::insert('audit_logs', [
                'user_id' => $userId,
                'action' => mb_substr($action, 0, 60),
                'entity' => $entity,
                'entity_id' => $entityId,
                'result' => $result === 'ok' ? 'ok' : 'error',
                'details' => $details,
                'ip_address' => substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
                'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
                'created_at' => Db::now(),
            ]);
        } catch (\Throwable $e) {
            ErrorHandler::log($e);
        }
    }
}
