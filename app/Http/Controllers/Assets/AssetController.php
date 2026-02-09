<?php

namespace App\Http\Controllers\Assets;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Company;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class AssetController extends Controller
{
    public function index(Request $request)
    {
        $filters = [
            'search' => $request->string('search')->toString(),
            'status' => $request->string('status')->toString(),
        ];

        $query = Asset::with([
            'company:id,name',
            'assignedEmployee.user:id,name',
        ]);

        if ($filters['search'] !== '') {
            $search = $filters['search'];
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('serial_number', 'like', "%{$search}%");
            });
        }

        if ($filters['status'] !== '') {
            $query->where('status', $filters['status']);
        }

        $assets = $query
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        $stats = [
            'total' => Asset::count(),
            'assigned' => Asset::where('status', 'assigned')->count(),
            'available' => Asset::where('status', 'available')->count(),
            'maintenance' => Asset::where('status', 'maintenance')->count(),
        ];

        return Inertia::render('assets/index', [
            'assets' => $assets,
            'filters' => $filters,
            'stats' => $stats,
        ]);
    }

    public function create()
    {
        return Inertia::render('assets/form', [
            'mode' => 'create',
            'asset' => null,
            'companies' => Company::orderBy('name')->get(['id', 'name']),
            'employees' => Employee::with('user:id,name')
                ->orderBy('employee_code')
                ->get(['id', 'employee_code', 'user_id']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->rules());

        DB::transaction(function () use ($validated) {
            Asset::create($validated);
        });

        return redirect()->route('assets.index');
    }

    public function edit(Asset $asset)
    {
        $asset->load(['company', 'assignedEmployee.user']);

        return Inertia::render('assets/form', [
            'mode' => 'edit',
            'asset' => $asset,
            'companies' => Company::orderBy('name')->get(['id', 'name']),
            'employees' => Employee::with('user:id,name')
                ->orderBy('employee_code')
                ->get(['id', 'employee_code', 'user_id']),
        ]);
    }

    public function update(Request $request, Asset $asset)
    {
        $validated = $request->validate($this->rules($asset));

        DB::transaction(function () use ($asset, $validated) {
            $asset->update($validated);
        });

        return redirect()->route('assets.index');
    }

    public function destroy(Asset $asset)
    {
        $asset->delete();

        return redirect()->route('assets.index');
    }

    private function rules(?Asset $asset = null): array
    {
        $id = $asset?->id;

        $companyId = request('company_id');

        return [
            'company_id' => ['required', 'exists:companies,id'],
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('assets', 'code')
                    ->ignore($id)
                    ->where(fn ($query) => $query->where('company_id', $companyId)),
            ],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
            'brand' => ['nullable', 'string', 'max:100'],
            'model' => ['nullable', 'string', 'max:100'],
            'serial_number' => ['nullable', 'string', 'max:100'],
            'purchase_date' => ['nullable', 'date'],
            'purchase_price' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', Rule::in(['available', 'assigned', 'maintenance', 'retired'])],
            'assigned_employee_id' => ['nullable', 'exists:employees,id'],
            'assigned_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
