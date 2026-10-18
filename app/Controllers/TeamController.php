<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Request;
use App\Helpers\Response;
use App\Models\Category;
use App\Models\Exercise;
use App\Models\Training;
use App\Models\User;

/** Pàgina d'un equip (categoria): els seus exercicis i els seus entrenaments publicats. */
final class TeamController extends Controller
{
    public function show(Request $r, array $p): void
    {
        $u = $this->user();
        $id = $this->id($p);
        $team = Category::find($id) ?? Response::notFound();
        if ($u['role'] !== 'admin' && !in_array($id, User::categoryIds((int) $u['id']), true)) {
            Response::notFound();
        }
        [$exercises, $pager] = Exercise::search(['category' => (string) $id, 'page' => $r->q('page')], (int) $r->q('page'), false);
        $today = date('Y-m-d');
        $trainings = Training::publishedForCategory($id);
        $upcoming = array_values(array_filter($trainings, fn ($t) => $t['session_date'] >= $today));
        $past = array_reverse(array_values(array_filter($trainings, fn ($t) => $t['session_date'] < $today)));
        $this->render('teams/show', [
            'title' => $team['name'], 'team' => $team, 'exercises' => $exercises, 'pager' => $pager,
            'upcoming' => $upcoming, 'past' => $past,
        ]);
    }
}
