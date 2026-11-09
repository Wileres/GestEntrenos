<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Request;
use App\Helpers\Response;
use App\Models\Attendance;
use App\Models\Team;

/** Resums d'assistència per al gestor. */
final class AttendanceReportController extends Controller
{
    /** Jugadors que han faltat (o arribat tard) en una setmana, agrupats per equip. */
    public function week(Request $r, array $p): void
    {
        $ref = preg_match('/^\d{4}-\d{2}-\d{2}$/', $r->q('week')) && strtotime($r->q('week')) ? $r->q('week') : date('Y-m-d');
        $mon = date('Y-m-d', strtotime('monday this week', strtotime($ref)));
        $sun = date('Y-m-d', strtotime($mon . ' +6 days'));
        $teams = [];
        $absent = $late = 0;
        foreach (Attendance::incidents($mon, $sun) as $i) {
            $t = &$teams[$i['team_id']];
            $t['name'] = $i['team_name'];
            $t['players'][$i['player_id']]['name'] = $i['full_name'];
            $t['players'][$i['player_id']][$i['status']][] = $i['session_date'];
            unset($t);
            $i['status'] === 'absent' ? $absent++ : $late++;
        }
        uasort($teams, fn ($a, $b) => strcmp($a['name'], $b['name']));
        $this->render('attendance/week', [
            'title' => 'Faltes de la setmana', 'mon' => $mon, 'sun' => $sun, 'teams' => $teams, 'absent' => $absent, 'late' => $late,
            'unmarked' => Attendance::unmarked($mon, $sun),
            'prev' => date('Y-m-d', strtotime($mon . ' -7 days')), 'next' => date('Y-m-d', strtotime($mon . ' +7 days')),
            'isCurrent' => $mon === date('Y-m-d', strtotime('monday this week')),
        ]);
    }

    /** Assistència d'un equip per jugador en un període (per defecte, la temporada en curs). */
    public function team(Request $r, array $p): void
    {
        $team = Team::find($this->id($p)) ?? Response::notFound();
        $y = (int) date('Y') - ((int) date('n') >= 8 ? 0 : 1);
        $from = preg_match('/^\d{4}-\d{2}-\d{2}$/', $r->q('from')) ? $r->q('from') : $y . '-09-01';
        $to = preg_match('/^\d{4}-\d{2}-\d{2}$/', $r->q('to')) ? $r->q('to') : date('Y-m-d');
        $stats = Attendance::teamStats((int) $team['id'], $from, $to);
        $this->render('attendance/team', ['title' => 'Assistència · ' . $team['name'], 'team' => $team, 'from' => $from, 'to' => $to, 'stats' => $stats]);
    }
}
