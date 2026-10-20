<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Db;
use App\Models\Training;

final class NotificationService
{
    public static function sendActivation(array $user, string $token, string $type = 'activation', string $role = 'entrenador'): bool
    {
        return MailService::send(
            $type,
            (string) $user['email'],
            $user['first_name'] . ' ' . ($user['last_name'] ?? ''),
            'Activa el teu compte · ' . SettingsService::clubName(),
            'activation',
            ['first_name' => $user['first_name'], 'email' => $user['email'], 'role' => $role, 'link' => absolute_url('/activar/' . $token)]
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

    /** Entrenadors actius amb correu i contrasenya que tenen assignada alguna categoria (equip) de l'entrenament (sense duplicats). */
    public static function recipients(int $trainingId): array
    {
        return Db::all(
            "SELECT DISTINCT u.id, u.first_name, u.last_name, u.email, u.notify_email
             FROM users u
             JOIN user_roles ur ON ur.user_id = u.id JOIN roles r ON r.id = ur.role_id AND r.name = 'trainer'
             WHERE u.is_active = 1 AND u.email IS NOT NULL AND u.password_hash IS NOT NULL
               AND EXISTS (SELECT 1 FROM user_categories uc JOIN training_categories tc ON tc.category_id = uc.category_id
                           WHERE uc.user_id = u.id AND tc.training_id = ?)
             ORDER BY u.id",
            [$trainingId]
        );
    }

    /** Entrenadors actius amb alguna de les categories (equips) de l'exercici, sense duplicats. */
    public static function exerciseRecipients(int $exerciseId): array
    {
        return Db::all(
            "SELECT DISTINCT u.id, u.first_name, u.last_name, u.email, u.notify_email
             FROM users u
             JOIN user_roles ur ON ur.user_id = u.id JOIN roles r ON r.id = ur.role_id AND r.name = 'trainer'
             WHERE u.is_active = 1 AND u.password_hash IS NOT NULL
               AND EXISTS (SELECT 1 FROM user_categories uc JOIN exercise_categories ec ON ec.category_id = uc.category_id
                           WHERE uc.user_id = u.id AND ec.exercise_id = ?)
             ORDER BY u.id",
            [$exerciseId]
        );
    }

    /** @return array{sent:int,failed:int} */
    public static function notifyNewExercise(int $exerciseId): array
    {
        $ex = \App\Models\Exercise::find($exerciseId);
        $result = ['sent' => 0, 'failed' => 0];
        if ($ex === null || (int) $ex['is_active'] !== 1) {
            return $result;
        }
        $teams = array_column(\App\Models\Exercise::extraCategories($exerciseId), 'name');
        foreach (self::exerciseRecipients($exerciseId) as $u) {
            Notifier::notify((int) $u['id'], 'Nou exercici', $ex['name'] . ' (' . implode(', ', $teams) . ')', '/exercicis/' . $exerciseId);
            if ((int) $u['notify_email'] !== 1 || empty($u['email'])) {
                continue;
            }
            $ok = MailService::send('exercise_new', $u['email'], $u['first_name'] . ' ' . $u['last_name'], 'Nou exercici: ' . $ex['name'], 'exercise', [
                'first_name' => $u['first_name'], 'exercise' => $ex, 'teams' => $teams, 'link' => absolute_url('/exercicis/' . $exerciseId),
            ]);
            $result[$ok ? 'sent' : 'failed']++;
        }
        return $result;
    }

    /** @return array{sent:int,failed:int} */
    public static function notifyTraining(int $trainingId, string $kind, string $changes = ''): array
    {
        $training = Training::find($trainingId);
        if ($training === null) {
            return ['sent' => 0, 'failed' => 0];
        }
        $categories = array_column(Training::categories($trainingId), 'name');
        [$type, $subject] = match ($kind) {
            'published' => ['training_published', 'Nou entrenament: ' . $training['title']],
            'cancelled' => ['training_cancelled', 'Entrenament cancel·lat: ' . $training['title']],
            default => ['training_updated', 'Entrenament modificat: ' . $training['title']],
        };
        $result = ['sent' => 0, 'failed' => 0];
        $heading = match ($kind) {
            'published' => 'Nou entrenament', 'cancelled' => 'Entrenament cancel·lat', default => 'Entrenament modificat',
        };
        $summary = fmt_date($training['session_date']) . ' ' . fmt_time($training['start_time']) . ' · ' . $training['title'];
        foreach (self::recipients($trainingId) as $u) {
            Notifier::notify((int) $u['id'], $heading, $summary . ($changes !== '' ? ' (' . $changes . ')' : ''), '/entrenaments/' . $trainingId);
            if ((int) $u['notify_email'] !== 1) {
                continue;
            }
            $ok = MailService::send($type, $u['email'], $u['first_name'] . ' ' . $u['last_name'], $subject, 'training', [
                'first_name' => $u['first_name'], 'kind' => $kind, 'training' => $training,
                'categories' => $categories, 'changes' => $changes,
                'link' => absolute_url('/entrenaments/' . $trainingId),
            ]);
            $result[$ok ? 'sent' : 'failed']++;
        }
        return $result;
    }
}
