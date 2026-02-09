<?php

namespace App\Http\Controllers\Documents;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class EmployeeDocumentController extends Controller
{
    public function index(Request $request)
    {
        $filters = [
            'search' => $request->string('search')->toString(),
            'status' => $request->string('status')->toString(),
            'type' => $request->string('type')->toString(),
        ];

        $query = EmployeeDocument::with(['employee.user:id,name,email']);

        if ($filters['search'] !== '') {
            $search = $filters['search'];
            $query->where(function ($documentQuery) use ($search) {
                $documentQuery
                    ->where('type', 'like', "%{$search}%")
                    ->orWhere('number', 'like', "%{$search}%")
                    ->orWhereHas('employee', function ($employeeQuery) use ($search) {
                        $employeeQuery
                            ->where('employee_code', 'like', "%{$search}%")
                            ->orWhereHas('user', function ($userQuery) use ($search) {
                                $userQuery
                                    ->where('name', 'like', "%{$search}%")
                                    ->orWhere('email', 'like', "%{$search}%");
                            });
                    });
            });
        }

        if ($filters['type'] !== '') {
            $query->where('type', $filters['type']);
        }

        if ($filters['status'] !== '') {
            $today = Carbon::today();
            $expiringLimit = $today->copy()->addDays(30);

            if ($filters['status'] === 'expired') {
                $query->whereNotNull('expires_at')
                    ->whereDate('expires_at', '<', $today);
            } elseif ($filters['status'] === 'expiring') {
                $query->whereNotNull('expires_at')
                    ->whereBetween('expires_at', [$today, $expiringLimit]);
            } elseif ($filters['status'] === 'valid') {
                $query->where(function ($validQuery) use ($expiringLimit) {
                    $validQuery
                        ->whereNull('expires_at')
                        ->orWhereDate('expires_at', '>', $expiringLimit);
                });
            }
        }

        $documents = $query
            ->orderByDesc('created_at')
            ->paginate(12)
            ->withQueryString();

        $today = Carbon::today();
        $expiringLimit = $today->copy()->addDays(30);

        $stats = [
            'total' => EmployeeDocument::count(),
            'expired' => EmployeeDocument::whereNotNull('expires_at')
                ->whereDate('expires_at', '<', $today)
                ->count(),
            'expiring' => EmployeeDocument::whereNotNull('expires_at')
                ->whereBetween('expires_at', [$today, $expiringLimit])
                ->count(),
        ];

        return Inertia::render('documents/index', [
            'documents' => $documents,
            'filters' => $filters,
            'stats' => $stats,
        ]);
    }

    public function create()
    {
        return Inertia::render('documents/form', [
            'mode' => 'create',
            'document' => null,
            'employees' => $this->employeeOptions(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->rules());

        DB::transaction(function () use ($request, $validated) {
            $payload = $validated;
            $payload['file_path'] = $request->file('file')
                ? $request->file('file')->store(
                    "employees/{$validated['employee_id']}/documents",
                    'public',
                )
                : null;

            EmployeeDocument::create($payload);
        });

        return redirect()->route('documents.index');
    }

    public function edit(EmployeeDocument $document)
    {
        $document->load('employee.user');

        return Inertia::render('documents/form', [
            'mode' => 'edit',
            'document' => $document,
            'employees' => $this->employeeOptions(),
        ]);
    }

    public function update(Request $request, EmployeeDocument $document)
    {
        $validated = $request->validate($this->rules());

        DB::transaction(function () use ($request, $validated, $document) {
            $payload = $validated;

            if ($request->hasFile('file')) {
                $payload['file_path'] = $request->file('file')->store(
                    "employees/{$validated['employee_id']}/documents",
                    'public',
                );
            }

            $document->update($payload);
        });

        return redirect()->route('documents.index');
    }

    public function destroy(EmployeeDocument $document)
    {
        $document->delete();

        return redirect()->route('documents.index');
    }

    private function rules(): array
    {
        return [
            'employee_id' => ['required', 'exists:employees,id'],
            'type' => ['required', 'string', 'max:100'],
            'number' => ['nullable', 'string', 'max:100'],
            'issued_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
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
