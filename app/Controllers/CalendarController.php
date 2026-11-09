<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Request;
use App\Helpers\Response;
use App\Models\Attendance;
use App\Models\Holiday;
use App\Services\AttendanceService;
use App\Services\AuditService;
use App\Validators\Validator;

/** Calendari d'entrenaments i dies festius (administrador). */
final class CalendarController extends Controller
{
    public const MONTHS = ['', 'Gener', 'Febrer', 'Març', 'Abril', 'Maig', 'Juny', 'Juliol', 'Agost', 'Setembre', 'Octubre', 'Novembre', 'Desembre'];

    public function index(Request $r, array $p): void
    {
        $month = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $r->q('month')) ? $r->q('month') : date('Y-m');
        $first = $month . '-01';
        $last = date('Y-m-t', strtotime($first));
        if ($last >= date('Y-m-d') && $first <= date('Y-m-d', strtotime('+1 year'))) {
            AttendanceService::ensureSessions($first, $last);
        }
        $days = [];
        foreach (Holiday::map($first, $last) as $date => $name) {
            $days[$date]['holiday'] = $name;
        }
        foreach (Attendance::sessions($first, $last) as $s) {
            $days[$s['session_date']]['sessions'][] = $s;
        }
        ksort($days);
        $this->render('calendar/index', [
            'title' => 'Calendari', 'month' => $month, 'days' => $days,
            'prev' => date('Y-m', strtotime($first . ' -1 month')), 'next' => date('Y-m', strtotime($first . ' +1 month')),
            'label' => self::MONTHS[(int) substr($month, 5, 2)] . ' ' . substr($month, 0, 4),
            'holidays' => Holiday::upcoming(),
            'teams' => \App\Models\Team::active(),
        ]);
    }

    public function holidayAdd(Request $r, array $p): void
    {
        $v = (new Validator($r->post))->required('date_from', 'Des del dia')->date('date_from', 'Des del dia')
            ->required('name', 'Motiu')->maxLen('name', 'Motiu', 120);
        $to = $r->str('date_to');
        if ($to !== '') {
            $v->date('date_to', 'Fins al dia');
        }
        $from = $r->str('date_from');
        if (!$v->fails() && $to !== '' && $to < $from) {
            $v->error('date_to', 'La data final no pot ser anterior a la inicial.');
        }
        if (!$v->fails() && $to !== '' && (strtotime($to) - strtotime($from)) > 120 * 86400) {
            $v->error('date_to', 'El període no pot superar els 120 dies.');
        }
        if ($v->fails()) {
            $this->done(implode(' ', $v->errors()), '/calendari?month=' . substr($from ?: date('Y-m'), 0, 7), 'error');
        }
        $n = 0;
        for ($d = new \DateTimeImmutable($from); $d->format('Y-m-d') <= ($to ?: $from); $d = $d->modify('+1 day')) {
            Holiday::add($d->format('Y-m-d'), $r->str('name'));
            $n++;
        }
        AttendanceService::rebuild();
        AuditService::log('holiday_added', 'holiday', null, 'ok', "$from $to");
        $this->done($n === 1 ? 'Dia festiu afegit.' : "S'han afegit $n dies festius.", '/calendari?month=' . substr($from, 0, 7));
    }

    public function holidayDelete(Request $r, array $p): void
    {
        $id = $this->id($p);
        $h = Holiday::find($id) ?? Response::notFound();
        Holiday::delete($id);
        AttendanceService::rebuild();
        AuditService::log('holiday_deleted', 'holiday', $id);
        $this->done('Dia festiu eliminat.', '/calendari?month=' . substr($h['holiday_date'], 0, 7));
    }
}
