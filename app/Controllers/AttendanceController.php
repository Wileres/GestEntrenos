<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Request;
use App\Helpers\Response;
use App\Models\Attendance;
use App\Models\Player;
use App\Models\Team;
use App\Services\AttendanceService;
use App\Services\AuditService;
use App\Validators\Validator;

/** Fitxes d'assistència: l'entrenador passa llista a l'inici de l'entrenament. */
final class AttendanceController extends Controller
{
    public function index(Request $r, array $p): void
    {
        $u = $this->user();
        $uid = $u['role'] === 'admin' ? null : (int) $u['id'];
        AttendanceService::ensureUpcoming();
        $today = date('Y-m-d');
        $this->render('attendance/index', [
            'title' => 'Passa llista',
            'today' => Attendance::sessions($today, $today, $uid),
            'upcoming' => Attendance::sessions(date('Y-m-d', strtotime('+1 day')), date('Y-m-d', strtotime('+14 days')), $uid),
            'recent' => Attendance::sessions(date('Y-m-d', strtotime('-14 days')), date('Y-m-d', strtotime('-1 day')), $uid, null, 'DESC'),
        ]);
    }

    private function session(array $p): array
    {
        $s = Attendance::find($this->id($p)) ?? Response::notFound();
        if (!Attendance::mayManage($this->user(), $s)) {
            Response::notFound();
        }
        return $s;
    }

    public function show(Request $r, array $p): void
    {
        $s = $this->session($p);
        $this->render('attendance/show', [
            'title' => 'Fitxa · ' . $s['team_name'], 's' => $s,
            'players' => Player::forTeam((int) $s['team_id']), 'records' => Attendance::records((int) $s['id']),
            'trainings' => Attendance::trainingsFor((int) $s['team_id'], $s['session_date']),
            'editable' => $s['status'] === 'scheduled' && $s['session_date'] <= date('Y-m-d'),
            'isAdmin' => $this->user()['role'] === 'admin',
        ]);
    }

    public function save(Request $r, array $p): void
    {
        $s = $this->session($p);
        $to = '/assistencia/' . $s['id'];
        if ($s['status'] !== 'scheduled') {
            $this->done('Aquest entrenament està cancel·lat.', $to, 'error');
        }
        if ($s['session_date'] > date('Y-m-d')) {
            $this->done('Encara no es pot passar llista: l\'entrenament és en el futur.', $to, 'error');
        }
        $players = Player::forTeam((int) $s['team_id']);
        $sent = $r->post['status'] ?? [];
        $sent = is_array($sent) ? $sent : [];
        $allPresent = $r->input('action') === 'all_present';
        $changes = [];
        $current = Attendance::records((int) $s['id']);
        foreach ($players as $pl) {
            $pid = (int) $pl['id'];
            $val = $sent[$pid] ?? '';
            if (is_string($val) && isset(Attendance::STATUS[$val])) {
                $changes[$pid] = $val;
            } elseif ($val === '' && isset($current[$pid]) && !$allPresent) {
                $changes[$pid] = null;
            } elseif ($allPresent && !isset($current[$pid])) {
                $changes[$pid] = 'present';
            }
        }
        Attendance::saveRecords((int) $s['id'], $changes, (int) $this->user()['id']);
        AuditService::log('attendance_saved', 'attendance_session', (int) $s['id']);
        $this->done('Assistència desada.', $to);
    }

    /** Cancel·la o restaura un entrenament (administrador). */
    public function cancel(Request $r, array $p): void
    {
        $s = Attendance::find($this->id($p)) ?? Response::notFound();
        $cancel = $s['status'] === 'scheduled';
        Attendance::setCancelled((int) $s['id'], $cancel);
        AuditService::log($cancel ? 'attendance_session_cancelled' : 'attendance_session_restored', 'attendance_session', (int) $s['id']);
        $this->done($cancel ? 'Entrenament cancel·lat.' : 'Entrenament restaurat.', '/assistencia/' . $s['id']);
    }

    /** Afegeix un entrenament extra al calendari (administrador). */
    public function addExtra(Request $r, array $p): void
    {
        $v = (new Validator($r->post))->required('date', 'Data')->date('date', 'Data')->time('start_time', 'Hora')->intRange('duration', 'Durada', 15, 300, true);
        $team = Team::find((int) $r->str('team_id'));
        if ($team === null) {
            $v->error('team_id', 'Cal triar un equip.');
        }
        $back = '/calendari?month=' . substr($r->str('date') ?: date('Y-m'), 0, 7);
        if ($v->fails()) {
            $this->done(implode(' ', $v->errors()), $back, 'error');
        }
        $created = Attendance::addManual((int) $team['id'], $r->str('date'), $r->str('start_time') . ':00', (int) $r->str('duration'));
        $this->done($created ? 'Entrenament afegit al calendari.' : 'Aquest equip ja té un entrenament a aquesta hora.', $back, $created ? 'success' : 'error');
    }
}
