<?php

namespace App\Http\Controllers\Employees;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\EmployeeProfile;
use App\Models\JobLevel;
use App\Models\Position;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

class EmployeeController extends Controller
{
    /**
     * Display a listing of employees.
     */
    public function index(Request $request)
    {
        $actor = $request->user();
        $filters = [
            'search' => $request->string('search')->toString(),
            'status' => $request->string('status')->toString(),
            'department' => $request->string('department')->toString(),
        ];

        $query = Employee::query()
            ->with([
                'user:id,name,email',
                'department:id,name',
                'position:id,title',
                'jobLevel:id,name',
                'branch:id,name',
                'manager.user:id,name',
            ]);

        if ($filters['search'] !== '') {
            $search = $filters['search'];
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('employee_code', 'like', "%{$search}%")
                    ->orWhere('work_email', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        if ($filters['status'] !== '') {
            $query->where('employment_status', $filters['status']);
        }

        if ($filters['department'] !== '') {
            $query->where('department_id', $filters['department']);
        }

        $employees = $query
            ->orderBy('employee_code')
            ->paginate(12)
            ->withQueryString();

        $stats = [
            'total' => Employee::count(),
            'active' => Employee::where('employment_status', 'active')->count(),
            'contract' => Employee::where('employment_status', 'contract')->count(),
            'probation' => Employee::where('employment_status', 'probation')->count(),
        ];

        return Inertia::render('employees/index', [
            'employees' => $employees,
            'filters' => $filters,
            'stats' => $stats,
            'departments' => Department::orderBy('name')->get(['id', 'name']),
            'employeeQuick' => $this->quickDialogData($actor),
        ]);
    }

    /**
     * Show the form for creating a new employee.
     */
    public function create()
    {
        return Inertia::render('employees/create', $this->formOptions());
    }

    /**
     * Store a newly created employee.
     */
    public function store(Request $request)
    {
        $actor = $request->user();
        $validated = $request->validate($this->rules(null, $actor));

        if ($actor->role !== 'superadmin') {
            $validated['role'] = 'employee';
        }

        $employee = DB::transaction(function () use ($validated, $request) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'role' => $validated['role'],
                'password' => Hash::make($validated['password']),
                'email_verified_at' => now(),
            ]);

            $employee = Employee::create([
                ...$this->employeePayload($validated),
                'user_id' => $user->id,
            ]);

            EmployeeProfile::updateOrCreate(
                ['employee_id' => $employee->id],
                $this->profilePayload($validated),
            );

            $this->storeDocuments($request, $employee);

            return $employee;
        });

        if ($this->shouldRedirectBack($request)) {
            return redirect()->back();
        }

        return redirect()->route('employees.show', $employee);
    }

    /**
     * Display the specified employee.
     */
    public function show(Employee $employee)
    {
        $this->ensureRoleManagement($employee);
        $employee->load([
            'user',
            'company',
            'department',
            'position',
            'jobLevel',
            'branch',
            'manager.user',
            'profile',
            'documents',
            'contracts',
        ]);

        $attendanceLogs = $employee->attendanceLogs()
            ->with([
                'shift:id,name,start_time,end_time',
                'workLocation:id,name',
                'photos:id,attendance_log_id,type,file_path',
                'approvedBy:id,name',
            ])
            ->orderByDesc('work_date')
            ->orderByDesc('check_in_at')
            ->take(20)
            ->get();

        return Inertia::render('employees/show', [
            'employee' => $employee,
            'attendanceLogs' => $attendanceLogs,
        ]);
    }

    /**
     * Show the form for editing the specified employee.
     */
    public function edit(Employee $employee)
    {
        $this->ensureRoleManagement($employee);
        $employee->load([
            'user',
            'company',
            'department',
            'position',
            'jobLevel',
            'branch',
            'manager.user',
            'profile',
            'documents',
        ]);

        return Inertia::render('employees/edit', [
            ...$this->formOptions(),
            'employee' => $employee,
        ]);
    }

    /**
     * Update the specified employee.
     */
    public function update(Request $request, Employee $employee)
    {
        $actor = $request->user();
        $this->ensureRoleManagement($employee);
        $validated = $request->validate($this->rules($employee, $actor));

        if ($actor->role !== 'superadmin') {
            $validated['role'] = 'employee';
        }

        DB::transaction(function () use ($validated, $employee, $request) {
            $userData = [
                'name' => $validated['name'],
                'email' => $validated['email'],
                'role' => $validated['role'],
            ];

            if (!empty($validated['password'])) {
                $userData['password'] = Hash::make($validated['password']);
            }

            $employee->user->update($userData);
            $employee->update($this->employeePayload($validated, $employee));

            EmployeeProfile::updateOrCreate(
                ['employee_id' => $employee->id],
                $this->profilePayload($validated, $employee->profile),
            );

            $this->storeDocuments($request, $employee);
        });

        if ($this->shouldRedirectBack($request)) {
            return redirect()->back();
        }

        return redirect()->route('employees.show', $employee);
    }

    /**
     * Deactivate the specified employee.
     */
    public function destroy(Request $request, Employee $employee)
    {
        $this->ensureRoleManagement($employee);

        DB::transaction(function () use ($employee) {
            $employee->update([
                'employment_status' => 'terminated',
                'resign_date' => now()->toDateString(),
                'is_active' => false,
            ]);
        });

        if ($this->shouldRedirectBack($request)) {
            return redirect()->back();
        }

        return redirect()->route('employees.index');
    }

    private function shouldRedirectBack(Request $request): bool
    {
        return $request->query('from') === 'dashboard';
    }

    private function formOptions(): array
    {
        return [
            'companies' => Company::orderBy('name')->get(['id', 'name']),
            'branches' => Branch::orderBy('name')->get(['id', 'name', 'company_id']),
            'departments' => Department::orderBy('name')->get(['id', 'name', 'branch_id']),
            'positions' => Position::orderBy('title')->get(['id', 'title', 'department_id']),
            'jobLevels' => JobLevel::orderBy('rank')->get(['id', 'name']),
            'managers' => Employee::with('user:id,name')
                ->orderBy('employee_code')
                ->get(['id', 'user_id', 'employee_code']),
        ];
    }

    private function quickDialogData(User $actor): array
    {
        $employeesQuery = Employee::with('user:id,name,email,role')
            ->orderByDesc('created_at');

        if ($actor->role !== 'superadmin') {
            $employeesQuery->whereHas('user', fn ($query) => $query->where('role', 'employee'));
        }

        $employees = $employeesQuery
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

        $managerQuery = Employee::with('user:id,name')
            ->orderBy('employee_code');

        if ($actor->role !== 'superadmin') {
            $managerQuery->whereHas('user', fn ($query) => $query->where('role', 'employee'));
        }

        return [
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
            'managers' => $managerQuery
                ->get(['id', 'user_id', 'employee_code'])
                ->map(fn ($employee) => [
                    'id' => $employee->id,
                    'employee_code' => $employee->employee_code,
                    'name' => $employee->user?->name,
                ])
                ->values(),
        ];
    }
    private function rules(?Employee $employee = null, ?User $actor = null): array
    {
        $userId = $employee?->user_id;
        $allowedRoles = $this->allowedRoles($actor ?? auth()->user());

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($userId),
            ],
            'role' => ['required', Rule::in($allowedRoles)],
            'password' => [
                $employee ? 'nullable' : 'required',
                Password::default(),
            ],
            'employee_code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('employees', 'employee_code')->ignore($employee),
            ],
            'company_id' => ['required', 'exists:companies,id'],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'position_id' => ['nullable', 'exists:positions,id'],
            'job_level_id' => ['nullable', 'exists:job_levels,id'],
            'manager_id' => ['nullable', 'exists:employees,id'],
            'employment_status' => [
                'required',
                Rule::in(['active', 'probation', 'contract', 'resign', 'terminated']),
            ],
            'employment_type' => [
                'required',
                Rule::in(['permanent', 'contract', 'internship', 'daily', 'freelance']),
            ],
            'join_date' => ['required', 'date'],
            'confirmation_date' => ['nullable', 'date'],
            'resign_date' => ['nullable', 'date'],
            'work_email' => ['nullable', 'email'],
            'work_phone' => ['nullable', 'string', 'max:30'],
            'office_location' => ['nullable', 'string', 'max:255'],
            'nik' => ['nullable', 'string', 'max:32'],
            'kk_number' => ['nullable', 'string', 'max:32'],
            'npwp' => ['nullable', 'string', 'max:32'],
            'bpjs_kes' => ['nullable', 'string', 'max:32'],
            'bpjs_tk' => ['nullable', 'string', 'max:32'],
            'gender' => ['nullable', 'string', 'max:20'],
            'birth_place' => ['nullable', 'string', 'max:100'],
            'birth_date' => ['nullable', 'date'],
            'marital_status' => ['nullable', 'string', 'max:20'],
            'religion' => ['nullable', 'string', 'max:50'],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'emergency_contact_name' => ['nullable', 'string', 'max:100'],
            'emergency_contact_relation' => ['nullable', 'string', 'max:50'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:30'],
            'bank_name' => ['nullable', 'string', 'max:100'],
            'bank_account_name' => ['nullable', 'string', 'max:100'],
            'bank_account_number' => ['nullable', 'string', 'max:50'],
            'documents' => ['nullable', 'array'],
            'documents.*.type' => ['nullable', 'string', 'max:100'],
            'documents.*.number' => ['nullable', 'string', 'max:100'],
            'documents.*.issued_at' => ['nullable', 'date'],
            'documents.*.expires_at' => ['nullable', 'date'],
            'documents.*.file' => ['nullable', 'file', 'max:5120'],
        ];
    }

    private function allowedRoles(?User $actor = null): array
    {
        $role = $actor?->role ?? 'employee';

        if ($role === 'superadmin') {
            return ['superadmin', 'admin', 'employee'];
        }

        return ['employee'];
    }

    private function ensureRoleManagement(Employee $employee): void
    {
        $actorRole = auth()->user()?->role ?? 'employee';

        if ($actorRole !== 'superadmin' && $employee->user && $employee->user->role !== 'employee') {
            abort(403, 'Unauthorized access.');
        }
    }

    private function employeePayload(array $validated, ?Employee $employee = null): array
    {
        $fields = [
            'company_id',
            'branch_id',
            'department_id',
            'position_id',
            'job_level_id',
            'manager_id',
            'employee_code',
            'employment_status',
            'employment_type',
            'join_date',
            'confirmation_date',
            'resign_date',
            'work_email',
            'work_phone',
            'office_location',
        ];

        $payload = $employee ? $employee->only($fields) : [];

        foreach ($fields as $field) {
            if (array_key_exists($field, $validated)) {
                $payload[$field] = $validated[$field];
            }
        }

        if (
            $employee
            && array_key_exists('manager_id', $payload)
            && (int) $payload['manager_id'] === (int) $employee->id
        ) {
            $payload['manager_id'] = null;
        }

        return $payload;
    }

    private function profilePayload(array $validated, ?EmployeeProfile $profile = null): array
    {
        $fields = [
            'nik',
            'kk_number',
            'npwp',
            'bpjs_kes',
            'bpjs_tk',
            'gender',
            'birth_place',
            'birth_date',
            'marital_status',
            'religion',
            'address_line1',
            'address_line2',
            'city',
            'province',
            'postal_code',
            'emergency_contact_name',
            'emergency_contact_relation',
            'emergency_contact_phone',
            'bank_name',
            'bank_account_name',
            'bank_account_number',
        ];

        $payload = $profile ? $profile->only($fields) : [];

        foreach ($fields as $field) {
            if (array_key_exists($field, $validated)) {
                $payload[$field] = $validated[$field];
            }
        }

        return $payload;
    }

    private function storeDocuments(Request $request, Employee $employee): void
    {
        $documents = $request->input('documents', []);

        foreach ($documents as $index => $document) {
            $file = $request->file("documents.{$index}.file");

            $hasData = $file
                || !empty($document['type'])
                || !empty($document['number'])
                || !empty($document['issued_at'])
                || !empty($document['expires_at']);

            if (!$hasData) {
                continue;
            }

            $path = $file
                ? $file->store("employees/{$employee->id}/documents", 'public')
                : null;

            EmployeeDocument::create([
                'employee_id' => $employee->id,
                'type' => $document['type'] ?? 'Dokumen',
                'number' => $document['number'] ?? null,
                'issued_at' => $document['issued_at'] ?? null,
                'expires_at' => $document['expires_at'] ?? null,
                'file_path' => $path,
                'notes' => $document['notes'] ?? null,
            ]);
        }
    }
}
