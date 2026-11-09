<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Request;
use App\Helpers\Response;
use App\Models\Attendance;
use App\Models\Category;
use App\Models\Exercise;
use App\Models\Player;
use App\Models\Schedule;
use App\Models\Team;
use App\Models\Training;
use App\Models\User;
use App\Services\AttendanceService;
use App\Services\AuditService;
use App\Validators\Validator;

/** Equips: pàgina de l'equip (tothom) i gestió (administrador). */
final class TeamController extends Controller
{
    public function show(Request $r, array $p): void
    {
        $u = $this->user();
        $id = $this->id($p);
        $team = Team::find($id) ?? Response::notFound();
        if ($u['role'] !== 'admin' && !in_array($id, User::teamIds((int) $u['id']), true)) {
            Response::notFound();
        }
        [$exercises, $pager] = Exercise::search(['team' => (string) $id, 'page' => $r->q('page')], (int) $r->q('page'), false);
        $today = date('Y-m-d');
        $trainings = Training::publishedForTeam($id);
        $upcoming = array_values(array_filter($trainings, fn ($t) => $t['session_date'] >= $today));
        $past = array_reverse(array_values(array_filter($trainings, fn ($t) => $t['session_date'] < $today)));
        AttendanceService::ensureUpcoming();
        $this->render('teams/show', [
            'title' => $team['name'], 'team' => $team, 'exercises' => $exercises, 'pager' => $pager,
            'upcoming' => $upcoming, 'past' => $past,
            'players' => Player::forTeam($id), 'trainers' => Team::trainers($id),
            'sessions' => Attendance::sessions($today, date('Y-m-d', strtotime('+21 days')), null, $id),
            'schedule' => Schedule::effectiveForTeam($team),
        ]);
    }

    public function index(Request $r, array $p): void
    {
        $this->render('teams/index', ['title' => 'Equips', 'teams' => array_map(
            fn ($t) => $t + ['schedule' => Schedule::summary(Schedule::effectiveForTeam($t))],
            Team::overview()
        )]);
    }

    private function formData(?array $team): array
    {
        return [
            'title' => $team ? 'Edita l\'equip' : 'Nou equip', 'team' => $team,
            'categories' => Category::all(), 'trainersAll' => User::allTrainers(),
            'categorySchedule' => $team && $team['category_id'] ? Schedule::summary(Schedule::of('category', (int) $team['category_id'])) : '',
        ];
    }

    public function create(Request $r, array $p): void
    {
        $this->render('teams/form', $this->formData(null) + ['old' => ['is_active' => '1', 'trainer_ids' => [], 'sch' => []]]);
    }

    public function edit(Request $r, array $p): void
    {
        $id = $this->id($p);
        $t = Team::find($id) ?? Response::notFound();
        $own = Schedule::of('team', $id);
        $this->render('teams/form', $this->formData($t) + ['old' => $t + [
            'trainer_ids' => Team::trainerIds($id), 'own_schedule' => $own ? '1' : '', 'sch' => Schedule::toForm($own),
        ]]);
    }

    public function store(Request $r, array $p): void
    {
        $this->save($r, null);
    }

    public function update(Request $r, array $p): void
    {
        $id = $this->id($p);
        Team::find($id) ?? Response::notFound();
        $this->save($r, $id);
    }

    private function save(Request $r, ?int $id): void
    {
        $name = $r->str('name');
        $v = (new Validator($r->post))->required('name', 'Nom')->maxLen('name', 'Nom', 100);
        if (!$v->fails() && Team::nameTaken($name, $id)) {
            $v->error('name', 'Ja existeix un equip amb aquest nom.');
        }
        $catId = (int) $r->str('category_id');
        if ($catId > 0 && Category::find($catId) === null) {
            $v->error('category_id', 'La categoria no és vàlida.');
        }
        $trainerIds = $this->onlyKnown($r->intList('trainer_ids'), array_column(User::allTrainers(), 'id'));
        $own = (bool) $r->input('own_schedule');
        $sch = Schedule::parseForm($r->input('sch'));
        $errors = $v->errors() + ($own ? $sch['errors'] : []);
        $old = ['name' => $name, 'category_id' => (string) $catId, 'is_active' => $r->input('is_active') ? '1' : '0',
            'trainer_ids' => $trainerIds, 'own_schedule' => $own ? '1' : '', 'sch' => $sch['form']];
        if ($errors) {
            $this->invalid('teams/form', $this->formData($id ? Team::find($id) : null), $errors, $old);
            return;
        }
        $id = Team::save($id, ['name' => $name, 'category_id' => $catId ?: null, 'is_active' => $old['is_active'] === '1']);
        Team::syncTrainers($id, $trainerIds);
        Schedule::replace('team', $id, $own ? $sch['rows'] : []);
        AttendanceService::rebuild($id);
        AuditService::log('team_saved', 'team', $id);
        $this->done('Equip desat correctament.', '/equips');
    }

    public function delete(Request $r, array $p): void
    {
        $id = $this->id($p);
        Team::find($id) ?? Response::notFound();
        if (Team::inUse($id)) {
            $this->done('No es pot eliminar: l\'equip té jugadors, entrenadors, exercicis o entrenaments relacionats. Pots desactivar-lo.', '/equips', 'error');
        }
        Team::delete($id);
        AuditService::log('team_deleted', 'team', $id);
        $this->done('Equip eliminat.', '/equips');
    }
}
