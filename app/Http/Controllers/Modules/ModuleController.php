<?php

namespace App\Http\Controllers\Modules;

use App\Http\Controllers\Controller;
use App\Models\AttendanceLog;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Department;
use App\Models\EmployeeContract;
use App\Models\EmployeeDocument;
use App\Models\JobLevel;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Position;
use App\Models\Shift;
use App\Models\WorkLocation;
use App\Models\WorkSchedule;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;

class ModuleController extends Controller
{
    private array $superadminOnly = [
        'organization',
        'payroll-periods',
        'salary-components',
        'payslips',
        'audit-logs',
    ];

    private array $modules = [
        'organization' => [
            'title' => 'Organization',
            'summary' => 'Struktur perusahaan dari cabang, departemen, hingga jabatan.',
            'highlights' => [
                'Multi-branch & department hierarchy.',
                'Job level & job position mapping.',
                'Lokasi kerja & geofence.',
            ],
        ],
        'contracts' => [
            'title' => 'Contracts',
            'summary' => 'Manajemen kontrak kerja dan status hubungan kerja karyawan.',
            'highlights' => [
                'Tipe kontrak & masa berlaku.',
                'Riwayat kontrak per karyawan.',
                'Dokumen kontrak & status.',
            ],
        ],
        'documents' => [
            'title' => 'Documents',
            'summary' => 'Repositori dokumen karyawan (KTP, NPWP, sertifikat, dll).',
            'highlights' => [
                'Masa berlaku dokumen.',
                'Checklist dokumen wajib.',
                'Akses cepat per karyawan.',
            ],
        ],
        'assets' => [
            'title' => 'Assets',
            'summary' => 'Pelacakan aset perusahaan yang dipinjamkan ke karyawan.',
            'highlights' => [
                'Inventaris per departemen.',
                'Status pinjam & pengembalian.',
                'Riwayat aset per karyawan.',
            ],
            'placeholder' => true,
        ],
        'attendance' => [
            'title' => 'Attendance Logs',
            'summary' => 'Pantau absensi berbasis GPS + selfie dengan validasi lokasi.',
            'highlights' => [
                'Check-in/out dengan geofence & selfie.',
                'Deteksi keterlambatan & overtime.',
                'Approval koreksi absensi.',
            ],
        ],
        'shifts' => [
            'title' => 'Shifts',
            'summary' => 'Manajemen shift kerja dan jam operasional.',
            'highlights' => [
                'Shift harian, malam, dan rotasi.',
                'Break time & grace period.',
                'Aktif/non-aktif per shift.',
            ],
        ],
        'schedules' => [
            'title' => 'Work Schedules',
            'summary' => 'Penjadwalan kerja per karyawan atau tim.',
            'highlights' => [
                'Jadwal harian & kalender kerja.',
                'Status off/holiday terintegrasi.',
                'Integrasi lokasi kerja.',
            ],
        ],
        'overtime' => [
            'title' => 'Overtime',
            'summary' => 'Kontrol lembur dan approval dengan SLA.',
            'highlights' => [
                'Pengajuan lembur terstruktur.',
                'Perhitungan lembur otomatis.',
                'Approval multi-level.',
            ],
            'placeholder' => true,
        ],
        'leave-requests' => [
            'title' => 'Leave Requests',
            'summary' => 'Pengajuan cuti dengan approval multi-level.',
            'highlights' => [
                'Jenis cuti & kuota tahunan.',
                'Approval flow bertingkat.',
                'Lampiran bukti & SLA.',
            ],
        ],
        'leave-types' => [
            'title' => 'Leave Types',
            'summary' => 'Konfigurasi jenis cuti, kuota, dan aturan cuti.',
            'highlights' => [
                'Kuota tahunan & carry-over.',
                'Cuti berbayar vs tidak.',
                'Persyaratan lampiran.',
            ],
        ],
        'payroll-periods' => [
            'title' => 'Payroll Periods',
            'summary' => 'Kelola periode payroll dan komponen gaji secara aman.',
            'highlights' => [
                'Periode gaji dengan lock & audit.',
                'Komponen gaji & potongan.',
                'Payslip otomatis & export bank.',
            ],
            'placeholder' => true,
        ],
        'salary-components' => [
            'title' => 'Salary Components',
            'summary' => 'Komponen pendapatan dan potongan gaji.',
            'highlights' => [
                'Allowance, insentif, tunjangan.',
                'Potongan BPJS & pajak.',
                'Konfigurasi per job level.',
            ],
            'placeholder' => true,
        ],
        'payslips' => [
            'title' => 'Payslips',
            'summary' => 'Distribusi slip gaji dan riwayat payroll.',
            'highlights' => [
                'Slip gaji digital.',
                'Riwayat payroll per karyawan.',
                'Pengiriman otomatis.',
            ],
            'placeholder' => true,
        ],
        'kpi-okr' => [
            'title' => 'KPI / OKR',
            'summary' => 'Manajemen KPI dan OKR perusahaan.',
            'highlights' => [
                'Target per unit & individu.',
                'Tracking pencapaian.',
                'Review periodik.',
            ],
            'placeholder' => true,
        ],
        'appraisals' => [
            'title' => 'Appraisals',
            'summary' => 'Penilaian kinerja berbasis siklus.',
            'highlights' => [
                'Skema penilaian multi-rater.',
                'Kalibrasi nilai.',
                'Riwayat appraisal.',
            ],
            'placeholder' => true,
        ],
        'training' => [
            'title' => 'Training',
            'summary' => 'Manajemen pelatihan dan pengembangan karyawan.',
            'highlights' => [
                'Katalog training.',
                'Sertifikasi & modul.',
                'Tracking peserta.',
            ],
            'placeholder' => true,
        ],
        'job-posts' => [
            'title' => 'Job Posts',
            'summary' => 'Publikasi lowongan kerja & pipeline rekrutmen.',
            'highlights' => [
                'Job posting multi-channel.',
                'Hiring pipeline.',
                'Talent pool.',
            ],
            'placeholder' => true,
        ],
        'candidates' => [
            'title' => 'Candidates',
            'summary' => 'Manajemen kandidat dan tahapan seleksi.',
            'highlights' => [
                'Tracking kandidat.',
                'Catatan interview.',
                'Status lamaran.',
            ],
            'placeholder' => true,
        ],
        'interviews' => [
            'title' => 'Interviews',
            'summary' => 'Jadwal interview dan feedback evaluator.',
            'highlights' => [
                'Jadwal interview terpusat.',
                'Form feedback.',
                'Decision log.',
            ],
            'placeholder' => true,
        ],
        'analytics' => [
            'title' => 'Analytics',
            'summary' => 'Dashboard laporan HR & insight bisnis.',
            'highlights' => [
                'Turnover & headcount.',
                'Attendance & leave trends.',
                'Cost per employee.',
            ],
            'placeholder' => true,
        ],
        'audit-logs' => [
            'title' => 'Audit Logs',
            'summary' => 'Jejak aktivitas penting untuk keamanan dan compliance.',
            'highlights' => [
                'Log perubahan data kritikal.',
                'Export laporan audit.',
                'Alert untuk akses sensitif.',
            ],
            'placeholder' => true,
        ],
    ];

    public function show(Request $request, string $slug)
    {
        $module = $this->modules[$slug] ?? null;

        if (!$module) {
            abort(404);
        }

        if (in_array($slug, $this->superadminOnly, true) && $request->user()->role !== 'superadmin') {
            abort(403, 'Unauthorized access.');
        }

        $payload = [
            'slug' => $slug,
            'module' => $module,
        ];

        if (!empty($module['placeholder'])) {
            return Inertia::render('modules/show', [
                ...$payload,
                'placeholder' => true,
                'stats' => [],
                'sections' => [],
            ]);
        }

        [$stats, $sections] = $this->moduleData($slug);

        return Inertia::render('modules/show', [
            ...$payload,
            'placeholder' => false,
            'stats' => $stats,
            'sections' => $sections,
        ]);
    }

    private function moduleData(string $slug): array
    {
        return match ($slug) {
            'organization' => $this->organizationData(),
            'contracts' => $this->contractData(),
            'documents' => $this->documentData(),
            'attendance' => $this->attendanceData(),
            'shifts' => $this->shiftData(),
            'schedules' => $this->scheduleData(),
            'leave-types' => $this->leaveTypeData(),
            'leave-requests' => $this->leaveRequestData(),
            default => [[], []],
        };
    }

    private function organizationData(): array
    {
        $companies = Company::withCount('branches')
            ->orderBy('name')
            ->get(['id', 'name', 'industry', 'city', 'is_active']);

        $branches = Branch::with('company:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'company_id', 'city', 'is_head_office', 'is_active']);

        $departments = Department::with(['company:id,name', 'branch:id,name'])
            ->orderBy('name')
            ->get(['id', 'name', 'company_id', 'branch_id', 'is_active']);

        $jobLevels = JobLevel::with('company:id,name')
            ->orderBy('rank')
            ->get(['id', 'name', 'rank', 'company_id']);

        $positions = Position::with(['department:id,name', 'jobLevel:id,name'])
            ->orderBy('title')
            ->get(['id', 'title', 'department_id', 'job_level_id', 'is_active']);

        $locations = WorkLocation::with(['company:id,name', 'branch:id,name'])
            ->orderBy('name')
            ->get(['id', 'name', 'company_id', 'branch_id', 'radius_meters', 'is_active']);

        $stats = [
            [
                'label' => 'Perusahaan',
                'value' => Company::count(),
                'description' => 'Total perusahaan terdaftar',
            ],
            [
                'label' => 'Cabang',
                'value' => Branch::count(),
                'description' => 'Cabang aktif & nonaktif',
            ],
            [
                'label' => 'Departemen',
                'value' => Department::count(),
                'description' => 'Unit organisasi',
            ],
            [
                'label' => 'Posisi',
                'value' => Position::count(),
                'description' => 'Jabatan aktif',
            ],
            [
                'label' => 'Job Level',
                'value' => JobLevel::count(),
                'description' => 'Level jabatan',
            ],
            [
                'label' => 'Work Location',
                'value' => WorkLocation::count(),
                'description' => 'Lokasi & geofence',
            ],
        ];

        $sections = [
            [
                'title' => 'Perusahaan',
                'manage_url' => '/modules/organization/companies',
                'columns' => [
                    ['key' => 'name', 'label' => 'Nama'],
                    ['key' => 'industry', 'label' => 'Industri'],
                    ['key' => 'city', 'label' => 'Kota'],
                    ['key' => 'branches', 'label' => 'Cabang'],
                    ['key' => 'status', 'label' => 'Status'],
                ],
                'rows' => $companies->map(fn ($company) => [
                    'name' => $company->name,
                    'industry' => $company->industry ?? '-',
                    'city' => $company->city ?? '-',
                    'branches' => $company->branches_count,
                    'status' => $company->is_active ? 'Aktif' : 'Nonaktif',
                ])->all(),
                'empty' => 'Belum ada data perusahaan.',
            ],
            [
                'title' => 'Cabang',
                'manage_url' => '/modules/organization/branches',
                'columns' => [
                    ['key' => 'name', 'label' => 'Nama'],
                    ['key' => 'company', 'label' => 'Perusahaan'],
                    ['key' => 'city', 'label' => 'Kota'],
                    ['key' => 'head_office', 'label' => 'Head Office'],
                    ['key' => 'status', 'label' => 'Status'],
                ],
                'rows' => $branches->map(fn ($branch) => [
                    'name' => $branch->name,
                    'company' => $branch->company?->name ?? '-',
                    'city' => $branch->city ?? '-',
                    'head_office' => $branch->is_head_office ? 'Ya' : '-',
                    'status' => $branch->is_active ? 'Aktif' : 'Nonaktif',
                ])->all(),
                'empty' => 'Belum ada data cabang.',
            ],
            [
                'title' => 'Departemen',
                'manage_url' => '/modules/organization/departments',
                'columns' => [
                    ['key' => 'name', 'label' => 'Nama'],
                    ['key' => 'company', 'label' => 'Perusahaan'],
                    ['key' => 'branch', 'label' => 'Cabang'],
                    ['key' => 'status', 'label' => 'Status'],
                ],
                'rows' => $departments->map(fn ($department) => [
                    'name' => $department->name,
                    'company' => $department->company?->name ?? '-',
                    'branch' => $department->branch?->name ?? '-',
                    'status' => $department->is_active ? 'Aktif' : 'Nonaktif',
                ])->all(),
                'empty' => 'Belum ada data departemen.',
            ],
            [
                'title' => 'Job Level',
                'manage_url' => '/modules/organization/job-levels',
                'columns' => [
                    ['key' => 'name', 'label' => 'Nama'],
                    ['key' => 'company', 'label' => 'Perusahaan'],
                    ['key' => 'rank', 'label' => 'Rank'],
                ],
                'rows' => $jobLevels->map(fn ($level) => [
                    'name' => $level->name,
                    'company' => $level->company?->name ?? '-',
                    'rank' => $level->rank,
                ])->all(),
                'empty' => 'Belum ada data job level.',
            ],
            [
                'title' => 'Posisi',
                'manage_url' => '/modules/organization/positions',
                'columns' => [
                    ['key' => 'title', 'label' => 'Jabatan'],
                    ['key' => 'department', 'label' => 'Departemen'],
                    ['key' => 'job_level', 'label' => 'Level'],
                    ['key' => 'status', 'label' => 'Status'],
                ],
                'rows' => $positions->map(fn ($position) => [
                    'title' => $position->title,
                    'department' => $position->department?->name ?? '-',
                    'job_level' => $position->jobLevel?->name ?? '-',
                    'status' => $position->is_active ? 'Aktif' : 'Nonaktif',
                ])->all(),
                'empty' => 'Belum ada data posisi.',
            ],
            [
                'title' => 'Lokasi Kerja',
                'manage_url' => '/modules/organization/work-locations',
                'columns' => [
                    ['key' => 'name', 'label' => 'Nama'],
                    ['key' => 'company', 'label' => 'Perusahaan'],
                    ['key' => 'branch', 'label' => 'Cabang'],
                    ['key' => 'radius', 'label' => 'Radius'],
                    ['key' => 'status', 'label' => 'Status'],
                ],
                'rows' => $locations->map(fn ($location) => [
                    'name' => $location->name,
                    'company' => $location->company?->name ?? '-',
                    'branch' => $location->branch?->name ?? '-',
                    'radius' => "{$location->radius_meters} m",
                    'status' => $location->is_active ? 'Aktif' : 'Nonaktif',
                ])->all(),
                'empty' => 'Belum ada data lokasi kerja.',
            ],
        ];

        return [$stats, $sections];
    }

    private function shiftData(): array
    {
        $shifts = Shift::with('company:id,name')
            ->orderBy('name')
            ->take(20)
            ->get(['id', 'name', 'company_id', 'start_time', 'end_time', 'break_minutes', 'grace_minutes', 'is_overnight', 'is_active']);

        $stats = [
            [
                'label' => 'Shift Aktif',
                'value' => Shift::where('is_active', true)->count(),
                'description' => 'Shift aktif',
            ],
            [
                'label' => 'Total Shift',
                'value' => Shift::count(),
                'description' => 'Termasuk nonaktif',
            ],
        ];

        $sections = [
            [
                'title' => 'Daftar Shift',
                'columns' => [
                    ['key' => 'name', 'label' => 'Nama'],
                    ['key' => 'company', 'label' => 'Perusahaan'],
                    ['key' => 'hours', 'label' => 'Jam Kerja'],
                    ['key' => 'break', 'label' => 'Break'],
                    ['key' => 'grace', 'label' => 'Grace'],
                    ['key' => 'status', 'label' => 'Status'],
                ],
                'rows' => $shifts->map(fn ($shift) => [
                    'name' => $shift->name,
                    'company' => $shift->company?->name ?? '-',
                    'hours' => sprintf(
                        '%s - %s',
                        $this->formatTime($shift->start_time),
                        $this->formatTime($shift->end_time),
                    ),
                    'break' => "{$shift->break_minutes} m",
                    'grace' => "{$shift->grace_minutes} m",
                    'status' => $shift->is_active ? 'Aktif' : 'Nonaktif',
                ])->all(),
                'empty' => 'Belum ada data shift.',
            ],
        ];

        return [$stats, $sections];
    }

    private function scheduleData(): array
    {
        $schedules = WorkSchedule::with([
            'employee.user:id,name',
            'shift:id,name,start_time,end_time',
            'workLocation:id,name',
        ])
            ->orderByDesc('work_date')
            ->take(20)
            ->get(['id', 'employee_id', 'shift_id', 'work_location_id', 'work_date', 'status']);

        $stats = [
            [
                'label' => 'Jadwal Terbaru',
                'value' => WorkSchedule::count(),
                'description' => 'Total jadwal',
            ],
        ];

        $sections = [
            [
                'title' => 'Work Schedules',
                'columns' => [
                    ['key' => 'employee', 'label' => 'Karyawan'],
                    ['key' => 'date', 'label' => 'Tanggal'],
                    ['key' => 'shift', 'label' => 'Shift'],
                    ['key' => 'location', 'label' => 'Lokasi'],
                    ['key' => 'status', 'label' => 'Status'],
                ],
                'rows' => $schedules->map(fn ($schedule) => [
                    'employee' => $schedule->employee?->user?->name ?? '-',
                    'date' => $this->formatDate($schedule->work_date),
                    'shift' => $schedule->shift
                        ? sprintf(
                            '%s (%s-%s)',
                            $schedule->shift->name,
                            $this->formatTime($schedule->shift->start_time),
                            $this->formatTime($schedule->shift->end_time),
                        )
                        : '-',
                    'location' => $schedule->workLocation?->name ?? '-',
                    'status' => ucfirst($schedule->status),
                ])->all(),
                'empty' => 'Belum ada data jadwal kerja.',
            ],
        ];

        return [$stats, $sections];
    }

    private function attendanceData(): array
    {
        $logs = AttendanceLog::with([
            'employee.user:id,name',
            'shift:id,name,start_time,end_time',
        ])
            ->orderByDesc('work_date')
            ->take(20)
            ->get([
                'id',
                'employee_id',
                'shift_id',
                'work_date',
                'check_in_at',
                'check_out_at',
                'status',
                'late_minutes',
                'overtime_minutes',
            ]);

        $stats = [
            [
                'label' => 'Total Log',
                'value' => AttendanceLog::count(),
                'description' => 'Riwayat absensi',
            ],
            [
                'label' => 'Terlambat',
                'value' => AttendanceLog::where('status', 'late')->count(),
                'description' => 'Status late',
            ],
        ];

        $sections = [
            [
                'title' => 'Attendance Logs',
                'columns' => [
                    ['key' => 'employee', 'label' => 'Karyawan'],
                    ['key' => 'date', 'label' => 'Tanggal'],
                    ['key' => 'shift', 'label' => 'Shift'],
                    ['key' => 'check_in', 'label' => 'Check In'],
                    ['key' => 'check_out', 'label' => 'Check Out'],
                    ['key' => 'status', 'label' => 'Status'],
                    ['key' => 'late', 'label' => 'Late'],
                    ['key' => 'overtime', 'label' => 'OT'],
                ],
                'rows' => $logs->map(fn ($log) => [
                    'employee' => $log->employee?->user?->name ?? '-',
                    'date' => $this->formatDate($log->work_date),
                    'shift' => $log->shift?->name ?? '-',
                    'check_in' => $this->formatDateTime($log->check_in_at),
                    'check_out' => $this->formatDateTime($log->check_out_at),
                    'status' => ucfirst($log->status),
                    'late' => $log->late_minutes ? "{$log->late_minutes} m" : '-',
                    'overtime' => $log->overtime_minutes ? "{$log->overtime_minutes} m" : '-',
                ])->all(),
                'empty' => 'Belum ada data absensi.',
            ],
        ];

        return [$stats, $sections];
    }

    private function leaveTypeData(): array
    {
        $leaveTypes = LeaveType::with('company:id,name')
            ->orderBy('name')
            ->take(20)
            ->get([
                'id',
                'name',
                'company_id',
                'category',
                'default_allocation',
                'paid',
                'requires_approval',
                'is_active',
            ]);

        $stats = [
            [
                'label' => 'Jenis Cuti',
                'value' => LeaveType::count(),
                'description' => 'Total jenis cuti',
            ],
        ];

        $sections = [
            [
                'title' => 'Leave Types',
                'columns' => [
                    ['key' => 'name', 'label' => 'Nama'],
                    ['key' => 'company', 'label' => 'Perusahaan'],
                    ['key' => 'category', 'label' => 'Kategori'],
                    ['key' => 'allocation', 'label' => 'Kuota'],
                    ['key' => 'paid', 'label' => 'Paid'],
                    ['key' => 'approval', 'label' => 'Approval'],
                    ['key' => 'status', 'label' => 'Status'],
                ],
                'rows' => $leaveTypes->map(fn ($type) => [
                    'name' => $type->name,
                    'company' => $type->company?->name ?? '-',
                    'category' => ucfirst($type->category),
                    'allocation' => $type->default_allocation,
                    'paid' => $type->paid ? 'Ya' : 'Tidak',
                    'approval' => $type->requires_approval ? 'Ya' : 'Tidak',
                    'status' => $type->is_active ? 'Aktif' : 'Nonaktif',
                ])->all(),
                'empty' => 'Belum ada data jenis cuti.',
            ],
        ];

        return [$stats, $sections];
    }

    private function leaveRequestData(): array
    {
        $requests = LeaveRequest::with([
            'employee.user:id,name',
            'leaveType:id,name',
        ])
            ->orderByDesc('requested_at')
            ->take(20)
            ->get([
                'id',
                'employee_id',
                'leave_type_id',
                'start_date',
                'end_date',
                'total_days',
                'status',
                'requested_at',
            ]);

        $stats = [
            [
                'label' => 'Pengajuan Cuti',
                'value' => LeaveRequest::count(),
                'description' => 'Total request',
            ],
            [
                'label' => 'Pending',
                'value' => LeaveRequest::where('status', 'pending')->count(),
                'description' => 'Menunggu approval',
            ],
        ];

        $sections = [
            [
                'title' => 'Leave Requests',
                'manage_url' => '/modules/leave-requests',
                'columns' => [
                    ['key' => 'employee', 'label' => 'Karyawan'],
                    ['key' => 'type', 'label' => 'Jenis Cuti'],
                    ['key' => 'period', 'label' => 'Periode'],
                    ['key' => 'days', 'label' => 'Hari'],
                    ['key' => 'status', 'label' => 'Status'],
                ],
                'rows' => $requests->map(fn ($request) => [
                    'employee' => $request->employee?->user?->name ?? '-',
                    'type' => $request->leaveType?->name ?? '-',
                    'period' => sprintf(
                        '%s - %s',
                        $this->formatDate($request->start_date),
                        $this->formatDate($request->end_date),
                    ),
                    'days' => $request->total_days,
                    'status' => ucfirst($request->status),
                ])->all(),
                'empty' => 'Belum ada data cuti.',
            ],
        ];

        return [$stats, $sections];
    }

    private function contractData(): array
    {
        $contracts = EmployeeContract::with('employee.user:id,name')
            ->orderByDesc('start_date')
            ->take(20)
            ->get([
                'id',
                'employee_id',
                'type',
                'start_date',
                'end_date',
                'status',
                'base_salary',
            ]);

        $stats = [
            [
                'label' => 'Kontrak',
                'value' => EmployeeContract::count(),
                'description' => 'Total kontrak',
            ],
            [
                'label' => 'Aktif',
                'value' => EmployeeContract::where('status', 'active')->count(),
                'description' => 'Kontrak aktif',
            ],
        ];

        $sections = [
            [
                'title' => 'Employee Contracts',
                'columns' => [
                    ['key' => 'employee', 'label' => 'Karyawan'],
                    ['key' => 'type', 'label' => 'Tipe'],
                    ['key' => 'start', 'label' => 'Mulai'],
                    ['key' => 'end', 'label' => 'Selesai'],
                    ['key' => 'status', 'label' => 'Status'],
                    ['key' => 'salary', 'label' => 'Gaji Pokok'],
                ],
                'rows' => $contracts->map(fn ($contract) => [
                    'employee' => $contract->employee?->user?->name ?? '-',
                    'type' => ucfirst($contract->type),
                    'start' => $this->formatDate($contract->start_date),
                    'end' => $this->formatDate($contract->end_date),
                    'status' => ucfirst($contract->status),
                    'salary' => $contract->base_salary
                        ? number_format((float) $contract->base_salary, 0, ',', '.')
                        : '-',
                ])->all(),
                'empty' => 'Belum ada data kontrak.',
            ],
        ];

        return [$stats, $sections];
    }

    private function documentData(): array
    {
        $documents = EmployeeDocument::with('employee.user:id,name')
            ->orderByDesc('created_at')
            ->take(20)
            ->get([
                'id',
                'employee_id',
                'type',
                'number',
                'expires_at',
                'file_path',
            ]);

        $stats = [
            [
                'label' => 'Dokumen',
                'value' => EmployeeDocument::count(),
                'description' => 'Total dokumen',
            ],
        ];

        $sections = [
            [
                'title' => 'Employee Documents',
                'columns' => [
                    ['key' => 'employee', 'label' => 'Karyawan'],
                    ['key' => 'type', 'label' => 'Jenis'],
                    ['key' => 'number', 'label' => 'Nomor'],
                    ['key' => 'expires', 'label' => 'Berlaku Sampai'],
                    ['key' => 'file', 'label' => 'File'],
                ],
                'rows' => $documents->map(fn ($document) => [
                    'employee' => $document->employee?->user?->name ?? '-',
                    'type' => $document->type,
                    'number' => $document->number ?? '-',
                    'expires' => $this->formatDate($document->expires_at),
                    'file' => $document->file_path ? 'Ada' : '-',
                ])->all(),
                'empty' => 'Belum ada data dokumen.',
            ],
        ];

        return [$stats, $sections];
    }

    private function formatDate($value): string
    {
        if (!$value) {
            return '-';
        }

        return Carbon::parse($value)->format('d M Y');
    }

    private function formatTime($value): string
    {
        if (!$value) {
            return '-';
        }

        return Carbon::parse($value)->format('H:i');
    }

    private function formatDateTime($value): string
    {
        if (!$value) {
            return '-';
        }

        return Carbon::parse($value)->format('d M Y H:i');
    }
}
