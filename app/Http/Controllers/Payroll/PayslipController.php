<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Models\Payslip;
use App\Models\PayslipItem;
use App\Models\SalaryComponent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class PayslipController extends Controller
{
    public function index()
    {
        $payslips = Payslip::with([
            'employee.user:id,name',
            'payrollPeriod:id,name,start_date,end_date',
        ])
            ->orderByDesc('created_at')
            ->paginate(12);

        $stats = [
            'total' => Payslip::count(),
            'draft' => Payslip::where('status', 'draft')->count(),
            'final' => Payslip::where('status', 'final')->count(),
            'paid' => Payslip::where('status', 'paid')->count(),
        ];

        return Inertia::render('payroll/payslips/index', [
            'payslips' => $payslips,
            'stats' => $stats,
        ]);
    }

    public function create()
    {
        return Inertia::render('payroll/payslips/form', [
            'mode' => 'create',
            'payslip' => null,
            'employees' => Employee::with('user:id,name')
                ->orderBy('employee_code')
                ->get(['id', 'employee_code', 'user_id']),
            'periods' => PayrollPeriod::orderByDesc('start_date')
                ->get(['id', 'name', 'start_date', 'end_date']),
            'components' => SalaryComponent::orderBy('name')
                ->get(['id', 'name', 'type', 'default_amount']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validatePayload($request);

        $payslip = DB::transaction(function () use ($validated) {
            [$gross, $deductions] = $this->calculateTotals($validated['items']);

            $payslip = Payslip::create([
                'employee_id' => $validated['employee_id'],
                'payroll_period_id' => $validated['payroll_period_id'],
                'gross_salary' => $gross,
                'total_deductions' => $deductions,
                'net_salary' => $gross - $deductions,
                'status' => $validated['status'],
                'issued_at' => $validated['status'] === 'draft' ? null : now(),
            ]);

            $this->syncItems($payslip, $validated['items']);

            return $payslip;
        });

        return redirect()->route('payslips.edit', $payslip);
    }

    public function edit(Payslip $payslip)
    {
        $payslip->load(['items', 'employee.user', 'payrollPeriod']);

        return Inertia::render('payroll/payslips/form', [
            'mode' => 'edit',
            'payslip' => $payslip,
            'employees' => Employee::with('user:id,name')
                ->orderBy('employee_code')
                ->get(['id', 'employee_code', 'user_id']),
            'periods' => PayrollPeriod::orderByDesc('start_date')
                ->get(['id', 'name', 'start_date', 'end_date']),
            'components' => SalaryComponent::orderBy('name')
                ->get(['id', 'name', 'type', 'default_amount']),
        ]);
    }

    public function update(Request $request, Payslip $payslip)
    {
        $validated = $this->validatePayload($request, $payslip);

        DB::transaction(function () use ($validated, $payslip) {
            [$gross, $deductions] = $this->calculateTotals($validated['items']);

            $payslip->update([
                'employee_id' => $validated['employee_id'],
                'payroll_period_id' => $validated['payroll_period_id'],
                'gross_salary' => $gross,
                'total_deductions' => $deductions,
                'net_salary' => $gross - $deductions,
                'status' => $validated['status'],
                'issued_at' => $validated['status'] === 'draft' ? null : now(),
            ]);

            $payslip->items()->delete();
            $this->syncItems($payslip, $validated['items']);
        });

        return redirect()->route('payslips.edit', $payslip);
    }

    private function validatePayload(Request $request, ?Payslip $payslip = null): array
    {
        $employeeId = $request->input('employee_id');
        $periodId = $request->input('payroll_period_id');

        return $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'payroll_period_id' => [
                'required',
                'exists:payroll_periods,id',
                Rule::unique('payslips')
                    ->where(fn ($query) => $query->where('employee_id', $employeeId))
                    ->ignore($payslip?->id),
            ],
            'status' => ['required', Rule::in(['draft', 'final', 'paid'])],
            'items' => ['required', 'array', 'min:1'],
            'items.*.component_id' => ['required', 'exists:salary_components,id'],
            'items.*.amount' => ['required', 'numeric', 'min:0'],
            'items.*.notes' => ['nullable', 'string'],
        ]);
    }

    private function calculateTotals(array $items): array
    {
        $componentIds = collect($items)
            ->pluck('component_id')
            ->filter()
            ->all();

        $components = SalaryComponent::whereIn('id', $componentIds)
            ->get(['id', 'type'])
            ->keyBy('id');

        $gross = 0;
        $deductions = 0;

        foreach ($items as $item) {
            $amount = (float) ($item['amount'] ?? 0);
            $type = $components[$item['component_id']]->type ?? 'earning';

            if ($type === 'deduction') {
                $deductions += $amount;
            } else {
                $gross += $amount;
            }
        }

        return [$gross, $deductions];
    }

    private function syncItems(Payslip $payslip, array $items): void
    {
        foreach ($items as $item) {
            PayslipItem::create([
                'payslip_id' => $payslip->id,
                'salary_component_id' => $item['component_id'],
                'amount' => $item['amount'],
                'notes' => $item['notes'] ?? null,
            ]);
        }
    }
}
