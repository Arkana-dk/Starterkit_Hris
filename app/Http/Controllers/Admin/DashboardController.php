<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\JobLevel;
use App\Models\Position;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $employees = Employee::with('user:id,name,email,role')
            ->whereHas('user', fn ($query) => $query->where('role', 'employee'))
            ->orderByDesc('created_at')
            ->take(50)
            ->get([
                'id',
                'user_id',
                'employee_code',
                'employment_status',
                'employment_type',
                'join_date',
                'company_id',
                'branch_id',
                'department_id',
                'position_id',
                'job_level_id',
                'manager_id',
                'work_email',
                'work_phone',
                'office_location',
            ]);

        $employeeQuick = [
            'employees' => $employees->map(fn ($employee) => [
                'id' => $employee->id,
                'employee_code' => $employee->employee_code,
                'employment_status' => $employee->employment_status,
                'employment_type' => $employee->employment_type,
                'join_date' => optional($employee->join_date)->toDateString(),
                'company_id' => $employee->company_id,
                'branch_id' => $employee->branch_id,
                'department_id' => $employee->department_id,
                'position_id' => $employee->position_id,
                'job_level_id' => $employee->job_level_id,
                'manager_id' => $employee->manager_id,
                'work_email' => $employee->work_email,
                'work_phone' => $employee->work_phone,
                'office_location' => $employee->office_location,
                'user' => [
                    'name' => $employee->user?->name,
                    'email' => $employee->user?->email,
                    'role' => $employee->user?->role,
                ],
            ])->values(),
            'companies' => Company::orderBy('name')->get(['id', 'name']),
            'branches' => Branch::orderBy('name')->get(['id', 'name', 'company_id']),
            'departments' => Department::orderBy('name')->get(['id', 'name', 'branch_id']),
            'positions' => Position::orderBy('title')->get(['id', 'title', 'department_id']),
            'jobLevels' => JobLevel::orderBy('rank')->get(['id', 'name']),
            'managers' => Employee::with('user:id,name')
                ->whereHas('user', fn ($query) => $query->where('role', 'employee'))
                ->orderBy('employee_code')
                ->get(['id', 'user_id', 'employee_code'])
                ->map(fn ($employee) => [
                    'id' => $employee->id,
                    'employee_code' => $employee->employee_code,
                    'name' => $employee->user?->name,
                ])
                ->values(),
        ];

        return Inertia::render('admin/dashboard', [
            'employeeQuick' => $employeeQuick,
        ]);
    }
}
