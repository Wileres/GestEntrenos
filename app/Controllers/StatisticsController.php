<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Request;
use App\Helpers\Response;
use App\Models\Attendance;
use App\Models\Player;
use App\Models\Team;

/** Estadístiques d'assistència (retards i faltes) per al gestor i per als entrenadors dels seus equips. */
final class StatisticsController extends Controller
{
    /** @return array{0:string,1:string} període (des de, fins a) validat; per defecte, la temporada en curs. */
    private function period(Request $r): array
    {
        $ok = fn (string $d) => preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) === 1 && strtotime($d) !== false;
        $from = $ok($r->q('from')) ? $r->q('from') : Attendance::seasonStart();
        $to = $ok($r->q('to')) ? $r->q('to') : date('Y-m-d');
        return $from <= $to ? [$from, $to] : [$to, $from];
    }

    public function index(Request $r, array $p): void
    {
        $u = $this->user();
        $teams = Attendance::teamsFor($u);
        if (count($teams) === 1 && $r->q('from') === '' && $r->q('to') === '') {
            Response::redirect('/estadistiques/equip/' . $teams[0]['id']);
        }
        [$from, $to] = $this->period($r);
        $this->render('statistics/index', [
            'title' => 'Estadístiques', 'from' => $from, 'to' => $to,
            'rows' => Attendance::overview(array_map('intval', array_column($teams, 'id')), $from, $to),
        ]);
    }

    public function team(Request $r, array $p): void
    {
        $u = $this->user();
        $id = $this->id($p);
        $team = Team::find($id);
        if ($team === null || !Attendance::mayViewTeam($u, $id)) {
            Response::notFound();
        }
        [$from, $to] = $this->period($r);
        $stats = Attendance::teamStats($id, $from, $to);
        $sort = in_array($r->q('ordre'), ['nom', 'faltes', 'tard'], true) ? $r->q('ordre') : 'faltes';
        $rows = $stats['rows'];
        usort($rows, fn ($a, $b) => match ($sort) {
            'nom' => strcmp($a['full_name'], $b['full_name']),
            'tard' => [(int) $b['late'], (int) $b['absent'], $a['full_name']] <=> [(int) $a['late'], (int) $a['absent'], $b['full_name']],
            default => [(int) $b['absent'], (int) $b['late'], $a['full_name']] <=> [(int) $a['absent'], (int) $a['late'], $b['full_name']],
        });
        $this->render('statistics/team', [
            'title' => 'Estadístiques · ' . $team['name'], 'team' => $team, 'from' => $from, 'to' => $to, 'sort' => $sort,
            'sessions' => $stats['sessions'], 'rows' => $rows, 'multiple' => count(Attendance::teamsFor($u)) > 1,
        ]);
    }

    public function player(Request $r, array $p): void
    {
        $u = $this->user();
        $player = Player::find($this->id($p));
        // Un entrenador només veu els jugadors dels seus equips; per als altres, la pàgina «no existeix».
        if ($player === null || !Attendance::mayViewTeam($u, (int) $player['team_id'])) {
            Response::notFound();
        }
        [$from, $to] = $this->period($r);
        $this->render('statistics/player', [
            'title' => 'Estadístiques · ' . $player['full_name'], 'player' => $player, 'from' => $from, 'to' => $to,
            'stats' => Attendance::playerStats((int) $player['id'], $from, $to),
        ]);
    }
}
