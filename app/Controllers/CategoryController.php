<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Request;
use App\Helpers\Response;
use App\Models\Category;
use App\Models\Schedule;
use App\Services\AttendanceService;
use App\Services\AuditService;
use App\Validators\Validator;

final class CategoryController extends Controller
{
    public function index(Request $r, array $p): void
    {
        $rows = array_map(fn ($c) => $c + ['in_use' => Category::inUse((int) $c['id']), 'schedule' => Schedule::summary(Schedule::of('category', (int) $c['id']))], Category::all());
        $this->render('categories/index', ['title' => 'Categories', 'categories' => $rows]);
    }

    public function create(Request $r, array $p): void
    {
        $this->render('categories/form', ['title' => 'Nova categoria', 'category' => null, 'old' => ['is_active' => '1', 'sort_order' => '0', 'sch' => []]]);
    }

    public function edit(Request $r, array $p): void
    {
        $c = Category::find($this->id($p)) ?? Response::notFound();
        $this->render('categories/form', ['title' => 'Edita la categoria', 'category' => $c, 'old' => $c + ['sch' => Schedule::toForm(Schedule::of('category', (int) $c['id']))]]);
    }

    public function store(Request $r, array $p): void
    {
        $this->save($r, null);
    }

    public function update(Request $r, array $p): void
    {
        $id = $this->id($p);
        Category::find($id) ?? Response::notFound();
        $this->save($r, $id);
    }

    private function save(Request $r, ?int $id): void
    {
        $name = $r->str('name');
        $v = (new Validator($r->post))->required('name', 'Nom')->maxLen('name', 'Nom', 80)->intRange('sort_order', 'Ordre', 0, 9999, true);
        if (!$v->fails() && Category::nameTaken($name, $id)) {
            $v->error('name', 'Ja existeix una categoria amb aquest nom.');
        }
        $sch = Schedule::parseForm($r->input('sch'));
        $old = ['name' => $name, 'sort_order' => $r->str('sort_order'), 'is_active' => $r->input('is_active') ? '1' : '0', 'sch' => $sch['form']];
        $errors = $v->errors() + $sch['errors'];
        if ($errors) {
            $this->invalid('categories/form', ['title' => $id ? 'Edita la categoria' : 'Nova categoria', 'category' => $id ? Category::find($id) : null], $errors, $old);
            return;
        }
        $id = Category::save($id, ['name' => $name, 'sort_order' => $r->str('sort_order'), 'is_active' => $old['is_active'] === '1']);
        Schedule::replace('category', $id, $sch['rows']);
        AttendanceService::rebuildCategory($id);
        AuditService::log('category_saved', 'category', $id);
        $this->done('Categoria desada correctament.', '/categories');
    }

    public function delete(Request $r, array $p): void
    {
        $id = $this->id($p);
        Category::find($id) ?? Response::notFound();
        if (Category::inUse($id)) {
            $this->done('No es pot eliminar: la categoria té equips. Pots desactivar-la.', '/categories', 'error');
        }
        Category::delete($id);
        AuditService::log('category_deleted', 'category', $id);
        $this->done('Categoria eliminada.', '/categories');
    }
}
