<?php

namespace App\Http\Controllers\Analytics;

use App\Http\Controllers\Controller;
use App\Models\AttendanceLog;
use App\Models\Department;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Models\Payslip;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class AnalyticsController extends Controller
{
    public function index()
    {
        $headcountTotal = Employee::count();
        $activeHeadcount = Employee::whereIn('employment_status', ['active', 'probation', 'contract'])
            ->count();

        $headcountTrend = collect(range(5, 0))
            ->map(function ($monthsAgo) {
                $month = Carbon::now()->startOfMonth()->subMonths($monthsAgo);
                $endOfMonth = $month->copy()->endOfMonth();
                $count = Employee::whereDate('join_date', '<=', $endOfMonth)
                    ->whereNotIn('employment_status', ['resign', 'terminated'])
                    ->count();

                return [
                    'month' => $month->format('M'),
                    'total' => $count,
                ];
            })
            ->values();

        $departmentBreakdown = Department::withCount('employees')
            ->orderByDesc('employees_count')
            ->take(6)
            ->get(['id', 'name'])
            ->map(fn ($department) => [
                'name' => $department->name,
                'total' => $department->employees_count,
            ])
            ->values();

        $attendanceWindowStart = Carbon::now()->subDays(29)->startOfDay();
        $attendanceCounts = AttendanceLog::select('status', DB::raw('count(*) as total'))
            ->whereDate('work_date', '>=', $attendanceWindowStart)
            ->groupBy('status')
            ->pluck('total', 'status');

        $attendanceSummary = collect([
            'present' => 'Hadir',
            'late' => 'Terlambat',
            'absent' => 'Alpha',
            'on_leave' => 'Izin',
            'sick' => 'Sakit',
            'permission' => 'Permission',
        ])
            ->map(fn ($label, $status) => [
                'name' => $label,
                'value' => (int) ($attendanceCounts[$status] ?? 0),
            ])
            ->values();

        $attendanceTotal = $attendanceSummary->sum('value');
        $attendancePresent = (int) ($attendanceCounts['present'] ?? 0) + (int) ($attendanceCounts['late'] ?? 0);
        $attendanceRate = $attendanceTotal > 0
            ? round(($attendancePresent / $attendanceTotal) * 100, 1)
            : 0.0;

        $attendanceTrend = collect(range(6, 0))
            ->map(function ($daysAgo) {
                $date = Carbon::now()->subDays($daysAgo);

                return [
                    'date' => $date->format('d M'),
                    'present' => AttendanceLog::whereDate('work_date', $date)
                        ->whereIn('status', ['present', 'late'])
                        ->count(),
                    'late' => AttendanceLog::whereDate('work_date', $date)
                        ->where('status', 'late')
                        ->count(),
                ];
            })
            ->values();

        $latestPeriod = PayrollPeriod::orderByDesc('end_date')->first();
        $latestPayroll = null;

        if ($latestPeriod) {
            $latestPayroll = [
                'name' => $latestPeriod->name,
                'range' => sprintf(
                    '%s - %s',
                    $latestPeriod->start_date?->format('d M Y'),
                    $latestPeriod->end_date?->format('d M Y'),
                ),
                'gross' => (float) Payslip::where('payroll_period_id', $latestPeriod->id)->sum('gross_salary'),
                'deductions' => (float) Payslip::where('payroll_period_id', $latestPeriod->id)->sum('total_deductions'),
                'net' => (float) Payslip::where('payroll_period_id', $latestPeriod->id)->sum('net_salary'),
                'count' => Payslip::where('payroll_period_id', $latestPeriod->id)->count(),
            ];
        }

        $payrollTrend = PayrollPeriod::orderByDesc('start_date')
            ->take(6)
            ->get(['id', 'name', 'start_date'])
            ->reverse()
            ->map(function ($period) {
                $net = (float) Payslip::where('payroll_period_id', $period->id)->sum('net_salary');

                return [
                    'period' => $period->name ?? $period->start_date?->format('M Y'),
                    'net' => $net,
                ];
            })
            ->values();

        return Inertia::render('analytics/index', [
            'headcount' => [
                'total' => $headcountTotal,
                'active' => $activeHeadcount,
                'trend' => $headcountTrend,
                'departments' => $departmentBreakdown,
            ],
            'attendance' => [
                'summary' => $attendanceSummary,
                'trend' => $attendanceTrend,
                'rate' => $attendanceRate,
            ],
            'payroll' => [
                'latest' => $latestPayroll,
                'trend' => $payrollTrend,
            ],
        ]);
    }
}
