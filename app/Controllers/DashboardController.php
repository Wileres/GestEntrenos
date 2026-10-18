<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Db;
use App\Helpers\Request;
use App\Models\Exercise;
use App\Models\Training;
use App\Models\User;

final class DashboardController extends Controller
{
    public function index(Request $r, array $p): void
    {
        $u = $this->user();
        if ($u['role'] === 'admin') {
            $this->render('dashboard/admin', [
                'title' => 'Panell principal',
                'stats' => [
                    'Entrenadors actius' => [User::countActiveTrainers(), '/entrenadors'],
                    'Categories' => [(int) Db::value('SELECT COUNT(*) FROM categories'), '/categories'],
                    'Exercicis' => [(int) Db::value('SELECT COUNT(*) FROM exercises'), '/exercicis'],
                ],
                'upcoming' => Training::upcomingFor($u),
                'latest' => Training::latest(),
                'trainers' => User::latestTrainers(),
                'failedMails' => Db::all("SELECT recipient, type, error_message, created_at FROM email_logs WHERE status = 'failed' ORDER BY id DESC LIMIT 5"),
                'failedCount' => (int) Db::value("SELECT COUNT(*) FROM email_logs WHERE status = 'failed' AND created_at >= ?", [date('Y-m-d H:i:s', time() - 7 * 86400)]),
                'activity' => Db::all(
                    'SELECT a.action, a.entity, a.result, a.created_at, u.first_name FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id ORDER BY a.id DESC LIMIT 8'
                ),
            ]);
            return;
        }
        $this->render('dashboard/trainer', [
            'title' => 'Panell principal',
            'upcoming' => Training::upcomingFor($u),
            'categories' => User::categories((int) $u['id']),
            'exercises' => Exercise::latest(),
            'notifications' => Db::all(
                "SELECT subject, created_at FROM email_logs WHERE recipient = ? AND status = 'sent' AND type LIKE 'training_%' ORDER BY id DESC LIMIT 5",
                [(string) $u['email']]
            ),
        ]);
    }
}
