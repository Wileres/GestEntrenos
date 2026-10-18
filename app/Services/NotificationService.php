<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Db;
use App\Models\Training;

final class NotificationService
{
    public static function sendActivation(array $user, string $token, string $type = 'activation'): bool
    {
        return MailService::send(
            $type,
            (string) $user['email'],
            $user['first_name'] . ' ' . ($user['last_name'] ?? ''),
            'Activa el teu compte · ' . SettingsService::clubName(),
            'activation',
            ['first_name' => $user['first_name'], 'email' => $user['email'], 'link' => absolute_url('/activar/' . $token)]
        );
    }

    public static function sendReset(array $user, string $token): bool
    {
        return MailService::send(
            'password_reset',
            (string) $user['email'],
            $user['first_name'] . ' ' . ($user['last_name'] ?? ''),
            'Recuperació de contrasenya · ' . SettingsService::clubName(),
            'reset',
            ['first_name' => $user['first_name'], 'link' => absolute_url('/contrasenya/restablir/' . $token), 'minutes' => \App\Helpers\Config::get('auth.reset_minutes')]
        );
    }

    /** Entrenadors actius amb correu i contrasenya que tenen assignada alguna categoria o equip de l'entrenament (sense duplicats). */
    public static function recipients(int $trainingId): array
    {
        return Db::all(
            "SELECT DISTINCT u.id, u.first_name, u.last_name, u.email
             FROM users u
             JOIN user_roles ur ON ur.user_id = u.id JOIN roles r ON r.id = ur.role_id AND r.name = 'trainer'
             WHERE u.is_active = 1 AND u.email IS NOT NULL AND u.password_hash IS NOT NULL
               AND (EXISTS (SELECT 1 FROM user_categories uc JOIN training_categories tc ON tc.category_id = uc.category_id
                            WHERE uc.user_id = u.id AND tc.training_id = ?)
                 OR EXISTS (SELECT 1 FROM user_teams ut JOIN training_teams tt ON tt.team_id = ut.team_id
                            WHERE ut.user_id = u.id AND tt.training_id = ?))
             ORDER BY u.id",
            [$trainingId, $trainingId]
        );
    }

    /** @return array{sent:int,failed:int} */
    public static function notifyTraining(int $trainingId, string $kind, string $changes = ''): array
    {
        $training = Training::find($trainingId);
        if ($training === null) {
            return ['sent' => 0, 'failed' => 0];
        }
        $categories = array_column(Training::categories($trainingId), 'name');
        $teams = array_map(fn ($t) => $t['name'], Training::teams($trainingId));
        [$type, $subject] = match ($kind) {
            'published' => ['training_published', 'Nou entrenament: ' . $training['title']],
            'cancelled' => ['training_cancelled', 'Entrenament cancel·lat: ' . $training['title']],
            default => ['training_updated', 'Entrenament modificat: ' . $training['title']],
        };
        $result = ['sent' => 0, 'failed' => 0];
        foreach (self::recipients($trainingId) as $u) {
            $ok = MailService::send($type, $u['email'], $u['first_name'] . ' ' . $u['last_name'], $subject, 'training', [
                'first_name' => $u['first_name'], 'kind' => $kind, 'training' => $training,
                'categories' => $categories, 'teams' => $teams, 'changes' => $changes,
                'link' => absolute_url('/entrenaments/' . $trainingId),
            ]);
            $result[$ok ? 'sent' : 'failed']++;
        }
        return $result;
    }
}
