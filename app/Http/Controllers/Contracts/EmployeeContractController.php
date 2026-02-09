<?php

namespace App\Http\Controllers\Contracts;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeContract;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class EmployeeContractController extends Controller
{
    public function index(Request $request)
    {
        $filters = [
            'search' => $request->string('search')->toString(),
            'status' => $request->string('status')->toString(),
            'type' => $request->string('type')->toString(),
        ];

        $query = EmployeeContract::with(['employee.user:id,name,email']);

        if ($filters['search'] !== '') {
            $search = $filters['search'];
            $query->whereHas('employee', function ($employeeQuery) use ($search) {
                $employeeQuery
                    ->where('employee_code', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        if ($filters['status'] !== '') {
            $query->where('status', $filters['status']);
        }

        if ($filters['type'] !== '') {
            $query->where('type', $filters['type']);
        }

        $contracts = $query
            ->orderByDesc('start_date')
            ->paginate(12)
            ->withQueryString();

        $stats = [
            'total' => EmployeeContract::count(),
            'active' => EmployeeContract::where('status', 'active')->count(),
            'expired' => EmployeeContract::where('status', 'expired')->count(),
            'terminated' => EmployeeContract::where('status', 'terminated')->count(),
        ];

        return Inertia::render('contracts/index', [
            'contracts' => $contracts,
            'filters' => $filters,
            'stats' => $stats,
        ]);
    }

    public function create()
    {
        return Inertia::render('contracts/form', [
            'mode' => 'create',
            'contract' => null,
            'employees' => $this->employeeOptions(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->rules());

        DB::transaction(function () use ($request, $validated) {
            $payload = $validated;
            $payload['file_path'] = $request->file('file')
                ? $request->file('file')->store('contracts', 'public')
                : null;

            EmployeeContract::create($payload);
        });

        return redirect()->route('contracts.index');
    }

    public function edit(EmployeeContract $contract)
    {
        $contract->load('employee.user');

        return Inertia::render('contracts/form', [
            'mode' => 'edit',
            'contract' => $contract,
            'employees' => $this->employeeOptions(),
        ]);
    }

    public function update(Request $request, EmployeeContract $contract)
    {
        $validated = $request->validate($this->rules($contract));

        DB::transaction(function () use ($request, $validated, $contract) {
            $payload = $validated;

            if ($request->hasFile('file')) {
                $payload['file_path'] = $request->file('file')->store('contracts', 'public');
            }

            $contract->update($payload);
        });

        return redirect()->route('contracts.index');
    }

    public function destroy(EmployeeContract $contract)
    {
        $contract->delete();

        return redirect()->route('contracts.index');
    }

    private function rules(?EmployeeContract $contract = null): array
    {
        return [
            'employee_id' => ['required', 'exists:employees,id'],
            'type' => ['required', Rule::in(['permanent', 'contract', 'internship', 'probation'])],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date'],
            'base_salary' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', Rule::in(['active', 'expired', 'terminated'])],
            'signed_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'file' => ['nullable', 'file', 'max:5120'],
        ];
    }

    private function employeeOptions()
    {
        return Employee::with('user:id,name')
            ->orderBy('employee_code')
            ->get(['id', 'employee_code', 'user_id'])
            ->map(fn ($employee) => [
                'id' => $employee->id,
                'employee_code' => $employee->employee_code,
                'name' => $employee->user?->name,
            ])
            ->values();
    }
}
