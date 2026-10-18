<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Labels;
use App\Helpers\Request;
use App\Helpers\Response;
use App\Models\Category;
use App\Models\Team;
use App\Services\AuditService;
use App\Validators\Validator;

final class TeamController extends Controller
{
    public function index(Request $r, array $p): void
    {
        $season = $r->q('season');
        $this->render('teams/index', [
            'title' => 'Equips', 'teams' => Team::all($season), 'season' => $season,
        ]);
    }

    public function create(Request $r, array $p): void
    {
        $this->render('teams/form', [
            'title' => 'Nou equip', 'team' => null, 'categories' => Category::active(),
            'old' => ['season' => Labels::currentSeason(), 'is_active' => '1'],
        ]);
    }

    public function edit(Request $r, array $p): void
    {
        $t = Team::find($this->id($p)) ?? Response::notFound();
        $this->render('teams/form', ['title' => 'Edita l\'equip', 'team' => $t, 'categories' => Category::all(), 'old' => $t]);
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
        $cats = array_column(Category::all(), 'id');
        $d = [
            'category_id' => $r->str('category_id'), 'name' => $r->str('name'), 'season' => $r->str('season'),
            'description' => $r->str('description'), 'is_active' => $r->input('is_active') ? '1' : '0',
        ];
        $v = (new Validator($d))->inList('category_id', 'Categoria', $cats)->required('name', 'Nom')->maxLen('name', 'Nom', 80)
            ->season('season', 'Temporada')->maxLen('description', 'Descripció', 2000);
        if (!$v->fails() && Team::duplicate((int) $d['category_id'], $d['name'], $d['season'], $id)) {
            $v->error('name', 'Ja existeix un equip amb aquest nom, categoria i temporada.');
        }
        if ($v->fails()) {
            $this->invalid('teams/form', [
                'title' => $id ? 'Edita l\'equip' : 'Nou equip', 'team' => $id ? Team::find($id) : null, 'categories' => Category::all(),
            ], $v->errors(), $d);
            return;
        }
        $id = Team::save($id, ['is_active' => $d['is_active'] === '1'] + $d);
        AuditService::log('team_saved', 'team', $id);
        $this->done('Equip desat correctament.', '/equips');
    }

    public function delete(Request $r, array $p): void
    {
        $id = $this->id($p);
        Team::find($id) ?? Response::notFound();
        if (Team::inUse($id)) {
            $this->done('No es pot eliminar: l\'equip està assignat a entrenadors o entrenaments. Pots desactivar-lo.', '/equips', 'error');
        }
        Team::delete($id);
        AuditService::log('team_deleted', 'team', $id);
        $this->done('Equip eliminat.', '/equips');
    }
}
