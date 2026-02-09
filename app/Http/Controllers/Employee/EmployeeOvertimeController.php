<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\OvertimeRequest;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class EmployeeOvertimeController extends Controller
{
    public function index(Request $request)
    {
        $employee = Employee::with('company:id,name')
            ->where('user_id', $request->user()->id)
            ->first();

        $requests = $employee
            ? OvertimeRequest::where('employee_id', $employee->id)
                ->orderByDesc('work_date')
                ->paginate(10)
                ->withQueryString()
            : null;

        return Inertia::render('employee/overtime/index', [
            'employee' => [
                'name' => $request->user()->name,
                'employee_code' => $employee?->employee_code ?? '-',
                'company' => $employee?->company?->name,
            ],
            'employeeProfileMissing' => $employee === null,
            'requests' => $requests
                ? $this->paginationPayload($requests)
                : $this->emptyPagination(),
        ]);
    }

    public function store(Request $request)
    {
        $employee = Employee::where('user_id', $request->user()->id)->first();
        if (!$employee) {
            return back()->withErrors([
                'employee' => 'Profil karyawan belum terhubung. Hubungi admin HR untuk melengkapi data karyawan Anda.',
            ]);
        }

        $data = $request->validate([
            'work_date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $start = Carbon::parse($data['work_date'].' '.$data['start_time']);
        $end = Carbon::parse($data['work_date'].' '.$data['end_time']);

        if ($end->lessThanOrEqualTo($start)) {
            $end->addDay();
        }

        $totalHours = round($start->diffInMinutes($end) / 60, 2);

        DB::transaction(function () use ($employee, $data, $totalHours) {
            OvertimeRequest::create([
                'employee_id' => $employee->id,
                'work_date' => $data['work_date'],
                'start_time' => $data['start_time'],
                'end_time' => $data['end_time'],
                'total_hours' => $totalHours,
                'reason' => $data['reason'] ?? null,
                'status' => 'pending',
                'requested_at' => now(),
            ]);
        });

        return back();
    }

    private function paginationPayload(LengthAwarePaginator $paginator): array
    {
        return [
            'data' => $paginator->items(),
            'links' => $paginator->linkCollection()
                ->map(fn ($link) => [
                    'url' => $link['url'],
                    'label' => $link['label'],
                    'active' => $link['active'],
                ])
                ->values()
                ->all(),
            'meta' => [
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'total' => $paginator->total(),
            ],
        ];
    }

    private function emptyPagination(): array
    {
        return [
            'data' => [],
            'links' => [],
            'meta' => [
                'from' => null,
                'to' => null,
                'total' => 0,
            ],
        ];
    }
}
