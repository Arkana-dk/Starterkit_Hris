<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Candidate;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Interview;
use App\Models\JobPost;
use App\Models\JobLevel;
use App\Models\LeaveType;
use App\Models\PayrollPeriod;
use App\Models\Position;
use App\Models\Appraisal;
use App\Models\AuditLog;
use App\Models\KpiOkr;
use App\Models\SalaryComponent;
use App\Models\Shift;
use App\Models\Training;
use App\Models\WorkLocation;
use App\Models\WorkSchedule;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class MasterDataController extends Controller
{
    public function index(Request $request, string $resource)
    {
        $config = $this->config($resource);
        $this->ensureAccess($request, $config);

        $query = $this->listQuery($resource);
        $items = $query->paginate(12)->withQueryString();

        $items->through(fn ($item) => $this->mapRow($resource, $item, $config['basePath']));

        return Inertia::render('master-data/index', [
            'resource' => $resource,
            'title' => $config['title'],
            'description' => $config['description'] ?? null,
            'columns' => $config['columns'],
            'items' => $items,
            'createUrl' => $config['basePath'].'/create',
            'exportUrl' => $config['basePath'].'/export',
            'importUrl' => $config['basePath'].'/import',
            'templateUrl' => $config['basePath'].'/template',
            'templateColumns' => $this->importColumns($resource),
        ]);
    }

    public function create(Request $request, string $resource)
    {
        $config = $this->config($resource);
        $this->ensureAccess($request, $config);

        return Inertia::render('master-data/form', [
            'resource' => $resource,
            'title' => $config['title'],
            'description' => $config['description'] ?? null,
            'mode' => 'create',
            'fields' => $this->fieldsFor($resource),
            'record' => null,
            'formAction' => $config['basePath'],
        ]);
    }

    public function store(Request $request, string $resource)
    {
        $config = $this->config($resource);
        $this->ensureAccess($request, $config);

        $validated = $request->validate($this->rulesFor($resource));

        DB::transaction(function () use ($resource, $validated) {
            $model = $this->modelFor($resource);
            $model::create($validated);
        });

        return redirect()->to($config['basePath']);
    }

    public function edit(Request $request, string $resource, string $record)
    {
        $config = $this->config($resource);
        $this->ensureAccess($request, $config);

        $model = $this->modelFor($resource);
        $item = $model::findOrFail($record);

        return Inertia::render('master-data/form', [
            'resource' => $resource,
            'title' => $config['title'],
            'description' => $config['description'] ?? null,
            'mode' => 'edit',
            'fields' => $this->fieldsFor($resource),
            'record' => $this->recordData($resource, $item),
            'formAction' => $config['basePath'].'/'.$item->id,
        ]);
    }

    public function update(Request $request, string $resource, string $record)
    {
        $config = $this->config($resource);
        $this->ensureAccess($request, $config);

        $model = $this->modelFor($resource);
        $item = $model::findOrFail($record);

        $validated = $request->validate($this->rulesFor($resource, $item));

        DB::transaction(function () use ($item, $validated) {
            $item->update($validated);
        });

        return redirect()->to($config['basePath']);
    }

    public function destroy(Request $request, string $resource, string $record)
    {
        $config = $this->config($resource);
        $this->ensureAccess($request, $config);

        $model = $this->modelFor($resource);
        $item = $model::findOrFail($record);
        $item->delete();

        return redirect()->to($config['basePath']);
    }

    public function export(Request $request, string $resource)
    {
        $config = $this->config($resource);
        $this->ensureAccess($request, $config);

        $columns = $this->importColumns($resource);
        $rows = $this->listQuery($resource)->get();
        $filename = sprintf('%s-%s.csv', $resource, now()->format('Ymd_His'));

        $callback = function () use ($resource, $columns, $rows) {
            $output = fopen('php://output', 'w');
            fputcsv($output, $columns);

            foreach ($rows as $row) {
                $record = $this->recordData($resource, $row);
                $data = [];

                foreach ($columns as $column) {
                    $value = $record[$column] ?? data_get($row, $column);
                    $data[] = $this->formatValueForExport($resource, $column, $value);
                }

                fputcsv($output, $data);
            }

            fclose($output);
        };

        return response()->streamDownload($callback, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function template(Request $request, string $resource)
    {
        $config = $this->config($resource);
        $this->ensureAccess($request, $config);

        $columns = $this->importColumns($resource);
        $filename = sprintf('%s-template.csv', $resource);

        $callback = function () use ($columns) {
            $output = fopen('php://output', 'w');
            fputcsv($output, $columns);
            fclose($output);
        };

        return response()->streamDownload($callback, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function import(Request $request, string $resource)
    {
        $config = $this->config($resource);
        $this->ensureAccess($request, $config);

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt'],
        ]);

        $file = $request->file('file');
        $handle = fopen($file->getRealPath(), 'r');

        if ($handle === false) {
            return back()->withErrors([
                'file' => 'File tidak dapat dibaca.',
            ]);
        }

        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            return back()->withErrors([
                'file' => 'Header CSV tidak ditemukan.',
            ]);
        }

        $columns = array_map('trim', $header);
        $expected = $this->importColumns($resource);
        $missing = array_diff($expected, $columns);

        if ($missing) {
            fclose($handle);
            return back()->withErrors([
                'file' => 'Kolom wajib belum lengkap: '.implode(', ', $missing),
            ]);
        }

        $rules = $this->rulesFor($resource);
        $model = $this->modelFor($resource);
        $rows = [];
        $line = 1;

        while (($values = fgetcsv($handle)) !== false) {
            $line++;
            $record = [];

            foreach ($columns as $index => $column) {
                if (!in_array($column, $expected, true)) {
                    continue;
                }

                $record[$column] = $values[$index] ?? null;
            }

            $record = $this->normalizeImportRow($resource, $record);

            if ($this->isEmptyRow($record)) {
                continue;
            }

            $validator = Validator::make($record, $rules);
            if ($validator->fails()) {
                fclose($handle);
                $message = collect($validator->errors()->all())->implode(', ');
                return back()->withErrors([
                    'file' => "Baris {$line}: {$message}",
                ]);
            }

            $rows[] = $validator->validated();
        }

        fclose($handle);

        if (!$rows) {
            return back()->withErrors([
                'file' => 'Tidak ada data untuk diimport.',
            ]);
        }

        DB::transaction(function () use ($model, $resource, $rows) {
            foreach ($rows as $row) {
                $unique = $this->uniqueKeysForImport($resource, $row);
                if ($unique) {
                    $model::updateOrCreate($unique, $row);
                    continue;
                }

                $model::create($row);
            }
        });

        return back();
    }

    private function ensureAccess(Request $request, array $config): void
    {
        $role = $request->user()?->role ?? 'employee';

        if (!in_array($role, $config['roles'], true)) {
            abort(403, 'Unauthorized access.');
        }
    }

    private function modelFor(string $resource): string
    {
        return match ($resource) {
            'companies' => Company::class,
            'branches' => Branch::class,
            'departments' => Department::class,
            'job-levels' => JobLevel::class,
            'positions' => Position::class,
            'work-locations' => WorkLocation::class,
            'shifts' => Shift::class,
            'leave-types' => LeaveType::class,
            'salary-components' => SalaryComponent::class,
            'payroll-periods' => PayrollPeriod::class,
            'schedules' => WorkSchedule::class,
            'kpi-okr' => KpiOkr::class,
            'appraisals' => Appraisal::class,
            'training' => Training::class,
            'job-posts' => JobPost::class,
            'candidates' => Candidate::class,
            'interviews' => Interview::class,
            'audit-logs' => AuditLog::class,
            default => abort(404),
        };
    }

    private function config(string $resource): array
    {
        return match ($resource) {
            'companies' => [
                'title' => 'Perusahaan',
                'description' => 'Kelola data perusahaan utama.',
                'columns' => [
                    ['key' => 'name', 'label' => 'Nama'],
                    ['key' => 'industry', 'label' => 'Industri'],
                    ['key' => 'city', 'label' => 'Kota'],
                    ['key' => 'status', 'label' => 'Status'],
                ],
                'roles' => ['superadmin'],
                'basePath' => '/modules/organization/companies',
            ],
            'branches' => [
                'title' => 'Cabang',
                'description' => 'Kelola cabang dan lokasi operasional.',
                'columns' => [
                    ['key' => 'name', 'label' => 'Nama'],
                    ['key' => 'company', 'label' => 'Perusahaan'],
                    ['key' => 'city', 'label' => 'Kota'],
                    ['key' => 'head_office', 'label' => 'Head Office'],
                    ['key' => 'status', 'label' => 'Status'],
                ],
                'roles' => ['superadmin'],
                'basePath' => '/modules/organization/branches',
            ],
            'departments' => [
                'title' => 'Departemen',
                'description' => 'Kelola struktur departemen dan hierarki.',
                'columns' => [
                    ['key' => 'name', 'label' => 'Nama'],
                    ['key' => 'company', 'label' => 'Perusahaan'],
                    ['key' => 'branch', 'label' => 'Cabang'],
                    ['key' => 'parent', 'label' => 'Parent'],
                    ['key' => 'status', 'label' => 'Status'],
                ],
                'roles' => ['superadmin'],
                'basePath' => '/modules/organization/departments',
            ],
            'job-levels' => [
                'title' => 'Job Level',
                'description' => 'Kelola level jabatan dan ranking.',
                'columns' => [
                    ['key' => 'name', 'label' => 'Nama'],
                    ['key' => 'company', 'label' => 'Perusahaan'],
                    ['key' => 'rank', 'label' => 'Rank'],
                ],
                'roles' => ['superadmin'],
                'basePath' => '/modules/organization/job-levels',
            ],
            'positions' => [
                'title' => 'Jabatan',
                'description' => 'Kelola posisi & job title.',
                'columns' => [
                    ['key' => 'title', 'label' => 'Jabatan'],
                    ['key' => 'department', 'label' => 'Departemen'],
                    ['key' => 'job_level', 'label' => 'Level'],
                    ['key' => 'leadership', 'label' => 'Leadership'],
                    ['key' => 'status', 'label' => 'Status'],
                ],
                'roles' => ['superadmin'],
                'basePath' => '/modules/organization/positions',
            ],
            'work-locations' => [
                'title' => 'Work Locations',
                'description' => 'Kelola lokasi kerja dan radius geofence.',
                'columns' => [
                    ['key' => 'name', 'label' => 'Nama'],
                    ['key' => 'company', 'label' => 'Perusahaan'],
                    ['key' => 'branch', 'label' => 'Cabang'],
                    ['key' => 'radius', 'label' => 'Radius'],
                    ['key' => 'status', 'label' => 'Status'],
                ],
                'roles' => ['superadmin'],
                'basePath' => '/modules/organization/work-locations',
            ],
            'shifts' => [
                'title' => 'Shifts',
                'description' => 'Kelola shift kerja & jam operasional.',
                'columns' => [
                    ['key' => 'name', 'label' => 'Nama'],
                    ['key' => 'company', 'label' => 'Perusahaan'],
                    ['key' => 'hours', 'label' => 'Jam Kerja'],
                    ['key' => 'break', 'label' => 'Break'],
                    ['key' => 'status', 'label' => 'Status'],
                ],
                'roles' => ['admin', 'superadmin'],
                'basePath' => '/modules/shifts',
            ],
            'leave-types' => [
                'title' => 'Leave Types',
                'description' => 'Kelola jenis cuti, kuota, dan aturan.',
                'columns' => [
                    ['key' => 'name', 'label' => 'Nama'],
                    ['key' => 'company', 'label' => 'Perusahaan'],
                    ['key' => 'category', 'label' => 'Kategori'],
                    ['key' => 'allocation', 'label' => 'Kuota'],
                    ['key' => 'paid', 'label' => 'Paid'],
                    ['key' => 'status', 'label' => 'Status'],
                ],
                'roles' => ['admin', 'superadmin'],
                'basePath' => '/modules/leave-types',
            ],
            'salary-components' => [
                'title' => 'Salary Components',
                'description' => 'Komponen pendapatan & potongan gaji.',
                'columns' => [
                    ['key' => 'name', 'label' => 'Nama'],
                    ['key' => 'company', 'label' => 'Perusahaan'],
                    ['key' => 'type', 'label' => 'Tipe'],
                    ['key' => 'amount', 'label' => 'Default'],
                    ['key' => 'status', 'label' => 'Status'],
                ],
                'roles' => ['superadmin'],
                'basePath' => '/modules/salary-components',
            ],
            'payroll-periods' => [
                'title' => 'Payroll Periods',
                'description' => 'Kelola periode payroll dan statusnya.',
                'columns' => [
                    ['key' => 'name', 'label' => 'Periode'],
                    ['key' => 'company', 'label' => 'Perusahaan'],
                    ['key' => 'range', 'label' => 'Rentang'],
                    ['key' => 'pay_date', 'label' => 'Pay Date'],
                    ['key' => 'status', 'label' => 'Status'],
                ],
                'roles' => ['superadmin'],
                'basePath' => '/modules/payroll-periods',
            ],
            'schedules' => [
                'title' => 'Work Schedules',
                'description' => 'Kelola jadwal kerja per karyawan.',
                'columns' => [
                    ['key' => 'employee', 'label' => 'Karyawan'],
                    ['key' => 'date', 'label' => 'Tanggal'],
                    ['key' => 'shift', 'label' => 'Shift'],
                    ['key' => 'location', 'label' => 'Lokasi'],
                    ['key' => 'status', 'label' => 'Status'],
                ],
                'roles' => ['admin', 'superadmin'],
                'basePath' => '/modules/schedules',
            ],
            'kpi-okr' => [
                'title' => 'KPI / OKR',
                'description' => 'Kelola target kinerja tim dan individu.',
                'columns' => [
                    ['key' => 'code', 'label' => 'Kode'],
                    ['key' => 'title', 'label' => 'Target'],
                    ['key' => 'owner', 'label' => 'PIC'],
                    ['key' => 'period', 'label' => 'Periode'],
                    ['key' => 'progress', 'label' => 'Progress'],
                    ['key' => 'status', 'label' => 'Status'],
                ],
                'roles' => ['admin', 'superadmin'],
                'basePath' => '/modules/kpi-okr',
            ],
            'appraisals' => [
                'title' => 'Appraisals',
                'description' => 'Kelola siklus penilaian kinerja karyawan.',
                'columns' => [
                    ['key' => 'employee', 'label' => 'Karyawan'],
                    ['key' => 'reviewer', 'label' => 'Reviewer'],
                    ['key' => 'period', 'label' => 'Periode'],
                    ['key' => 'score', 'label' => 'Skor'],
                    ['key' => 'rating', 'label' => 'Rating'],
                    ['key' => 'status', 'label' => 'Status'],
                ],
                'roles' => ['admin', 'superadmin'],
                'basePath' => '/modules/appraisals',
            ],
            'training' => [
                'title' => 'Training',
                'description' => 'Kelola program pelatihan dan kapasitas peserta.',
                'columns' => [
                    ['key' => 'code', 'label' => 'Kode'],
                    ['key' => 'title', 'label' => 'Pelatihan'],
                    ['key' => 'provider', 'label' => 'Provider'],
                    ['key' => 'date', 'label' => 'Tanggal'],
                    ['key' => 'duration', 'label' => 'Durasi'],
                    ['key' => 'status', 'label' => 'Status'],
                ],
                'roles' => ['admin', 'superadmin'],
                'basePath' => '/modules/training',
            ],
            'job-posts' => [
                'title' => 'Job Posts',
                'description' => 'Kelola lowongan dan status publikasinya.',
                'columns' => [
                    ['key' => 'code', 'label' => 'Kode'],
                    ['key' => 'title', 'label' => 'Posisi'],
                    ['key' => 'department', 'label' => 'Departemen'],
                    ['key' => 'openings', 'label' => 'Kuota'],
                    ['key' => 'period', 'label' => 'Periode'],
                    ['key' => 'status', 'label' => 'Status'],
                ],
                'roles' => ['admin', 'superadmin'],
                'basePath' => '/modules/job-posts',
            ],
            'candidates' => [
                'title' => 'Candidates',
                'description' => 'Kelola kandidat dan progres rekrutmen.',
                'columns' => [
                    ['key' => 'code', 'label' => 'Kode'],
                    ['key' => 'full_name', 'label' => 'Nama'],
                    ['key' => 'job_post', 'label' => 'Job Post'],
                    ['key' => 'email', 'label' => 'Email'],
                    ['key' => 'stage', 'label' => 'Stage'],
                    ['key' => 'applied_at', 'label' => 'Apply Date'],
                ],
                'roles' => ['admin', 'superadmin'],
                'basePath' => '/modules/candidates',
            ],
            'interviews' => [
                'title' => 'Interviews',
                'description' => 'Kelola jadwal interview dan hasil evaluasi.',
                'columns' => [
                    ['key' => 'candidate', 'label' => 'Kandidat'],
                    ['key' => 'interviewer', 'label' => 'Interviewer'],
                    ['key' => 'schedule', 'label' => 'Jadwal'],
                    ['key' => 'mode', 'label' => 'Mode'],
                    ['key' => 'result', 'label' => 'Hasil'],
                    ['key' => 'score', 'label' => 'Skor'],
                ],
                'roles' => ['admin', 'superadmin'],
                'basePath' => '/modules/interviews',
            ],
            'audit-logs' => [
                'title' => 'Audit Logs',
                'description' => 'Jejak aktivitas penting untuk compliance dan keamanan.',
                'columns' => [
                    ['key' => 'module', 'label' => 'Modul'],
                    ['key' => 'action', 'label' => 'Aksi'],
                    ['key' => 'actor', 'label' => 'Aktor'],
                    ['key' => 'occurred_at', 'label' => 'Tanggal'],
                    ['key' => 'severity', 'label' => 'Severity'],
                    ['key' => 'flagged', 'label' => 'Flagged'],
                ],
                'roles' => ['superadmin'],
                'basePath' => '/modules/audit-logs',
            ],
            default => abort(404),
        };
    }

    private function listQuery(string $resource)
    {
        return match ($resource) {
            'companies' => Company::query()->orderBy('name'),
            'branches' => Branch::with('company:id,name')->orderBy('name'),
            'departments' => Department::with(['company:id,name', 'branch:id,name', 'parent:id,name'])->orderBy('name'),
            'job-levels' => JobLevel::with('company:id,name')->orderBy('rank'),
            'positions' => Position::with(['department:id,name', 'jobLevel:id,name'])->orderBy('title'),
            'work-locations' => WorkLocation::with(['company:id,name', 'branch:id,name'])->orderBy('name'),
            'shifts' => Shift::with('company:id,name')->orderBy('name'),
            'leave-types' => LeaveType::with('company:id,name')->orderBy('name'),
            'salary-components' => SalaryComponent::with('company:id,name')->orderBy('name'),
            'payroll-periods' => PayrollPeriod::with('company:id,name')->orderByDesc('start_date'),
            'schedules' => WorkSchedule::with(['employee.user:id,name', 'shift:id,name,start_time,end_time', 'workLocation:id,name'])
                ->orderByDesc('work_date'),
            'kpi-okr' => KpiOkr::with('employee.user:id,name')->orderByDesc('period_start'),
            'appraisals' => Appraisal::with(['employee.user:id,name', 'reviewer.user:id,name'])->orderByDesc('period_start'),
            'training' => Training::query()->orderByDesc('training_date'),
            'job-posts' => JobPost::with(['department:id,name', 'position:id,title'])->orderByDesc('posted_at'),
            'candidates' => Candidate::with('jobPost:id,title')->orderByDesc('applied_at'),
            'interviews' => Interview::with(['candidate:id,full_name', 'interviewer.user:id,name'])
                ->orderByDesc('interview_date')
                ->orderByDesc('interview_time'),
            'audit-logs' => AuditLog::query()->orderByDesc('occurred_at')->orderByDesc('id'),
            default => abort(404),
        };
    }

    private function mapRow(string $resource, $item, string $basePath): array
    {
        return match ($resource) {
            'companies' => [
                'id' => $item->id,
                'name' => $item->name,
                'industry' => $item->industry ?? '-',
                'city' => $item->city ?? '-',
                'status' => $item->is_active ? 'Aktif' : 'Nonaktif',
                'edit_url' => $basePath.'/'.$item->id.'/edit',
                'delete_url' => $basePath.'/'.$item->id,
            ],
            'branches' => [
                'id' => $item->id,
                'name' => $item->name,
                'company' => $item->company?->name ?? '-',
                'city' => $item->city ?? '-',
                'head_office' => $item->is_head_office ? 'Ya' : '-',
                'status' => $item->is_active ? 'Aktif' : 'Nonaktif',
                'edit_url' => $basePath.'/'.$item->id.'/edit',
                'delete_url' => $basePath.'/'.$item->id,
            ],
            'departments' => [
                'id' => $item->id,
                'name' => $item->name,
                'company' => $item->company?->name ?? '-',
                'branch' => $item->branch?->name ?? '-',
                'parent' => $item->parent?->name ?? '-',
                'status' => $item->is_active ? 'Aktif' : 'Nonaktif',
                'edit_url' => $basePath.'/'.$item->id.'/edit',
                'delete_url' => $basePath.'/'.$item->id,
            ],
            'job-levels' => [
                'id' => $item->id,
                'name' => $item->name,
                'company' => $item->company?->name ?? '-',
                'rank' => $item->rank,
                'edit_url' => $basePath.'/'.$item->id.'/edit',
                'delete_url' => $basePath.'/'.$item->id,
            ],
            'positions' => [
                'id' => $item->id,
                'title' => $item->title,
                'department' => $item->department?->name ?? '-',
                'job_level' => $item->jobLevel?->name ?? '-',
                'leadership' => $item->is_leadership ? 'Ya' : '-',
                'status' => $item->is_active ? 'Aktif' : 'Nonaktif',
                'edit_url' => $basePath.'/'.$item->id.'/edit',
                'delete_url' => $basePath.'/'.$item->id,
            ],
            'work-locations' => [
                'id' => $item->id,
                'name' => $item->name,
                'company' => $item->company?->name ?? '-',
                'branch' => $item->branch?->name ?? '-',
                'radius' => "{$item->radius_meters} m",
                'status' => $item->is_active ? 'Aktif' : 'Nonaktif',
                'edit_url' => $basePath.'/'.$item->id.'/edit',
                'delete_url' => $basePath.'/'.$item->id,
            ],
            'shifts' => [
                'id' => $item->id,
                'name' => $item->name,
                'company' => $item->company?->name ?? '-',
                'hours' => sprintf('%s - %s', $this->formatTime($item->start_time), $this->formatTime($item->end_time)),
                'break' => "{$item->break_minutes} m",
                'status' => $item->is_active ? 'Aktif' : 'Nonaktif',
                'edit_url' => $basePath.'/'.$item->id.'/edit',
                'delete_url' => $basePath.'/'.$item->id,
            ],
            'leave-types' => [
                'id' => $item->id,
                'name' => $item->name,
                'company' => $item->company?->name ?? '-',
                'category' => ucfirst($item->category),
                'allocation' => $item->default_allocation,
                'paid' => $item->paid ? 'Ya' : 'Tidak',
                'status' => $item->is_active ? 'Aktif' : 'Nonaktif',
                'edit_url' => $basePath.'/'.$item->id.'/edit',
                'delete_url' => $basePath.'/'.$item->id,
            ],
            'salary-components' => [
                'id' => $item->id,
                'name' => $item->name,
                'company' => $item->company?->name ?? '-',
                'type' => ucfirst($item->type),
                'amount' => number_format((float) $item->default_amount, 0, ',', '.'),
                'status' => $item->is_active ? 'Aktif' : 'Nonaktif',
                'edit_url' => $basePath.'/'.$item->id.'/edit',
                'delete_url' => $basePath.'/'.$item->id,
            ],
            'payroll-periods' => [
                'id' => $item->id,
                'name' => $item->name,
                'company' => $item->company?->name ?? '-',
                'range' => sprintf('%s - %s', $this->formatDate($item->start_date), $this->formatDate($item->end_date)),
                'pay_date' => $this->formatDate($item->pay_date),
                'status' => ucfirst($item->status),
                'edit_url' => $basePath.'/'.$item->id.'/edit',
                'delete_url' => $basePath.'/'.$item->id,
            ],
            'schedules' => [
                'id' => $item->id,
                'employee' => $item->employee?->user?->name ?? '-',
                'date' => $this->formatDate($item->work_date),
                'shift' => $item->shift
                    ? sprintf(
                        '%s (%s-%s)',
                        $item->shift->name,
                        $this->formatTime($item->shift->start_time),
                        $this->formatTime($item->shift->end_time),
                    )
                    : '-',
                'location' => $item->workLocation?->name ?? '-',
                'status' => ucfirst($item->status),
                'edit_url' => $basePath.'/'.$item->id.'/edit',
                'delete_url' => $basePath.'/'.$item->id,
            ],
            'kpi-okr' => [
                'id' => $item->id,
                'code' => $item->code ?? '-',
                'title' => $item->title,
                'owner' => $item->employee?->user?->name ?? '-',
                'period' => sprintf(
                    '%s - %s',
                    $this->formatDate($item->period_start),
                    $this->formatDate($item->period_end),
                ),
                'progress' => $this->formatProgress($item->current_value, $item->target_value, $item->unit),
                'status' => ucfirst(str_replace('_', ' ', $item->status)),
                'edit_url' => $basePath.'/'.$item->id.'/edit',
                'delete_url' => $basePath.'/'.$item->id,
            ],
            'appraisals' => [
                'id' => $item->id,
                'employee' => $item->employee?->user?->name ?? '-',
                'reviewer' => $item->reviewer?->user?->name ?? '-',
                'period' => sprintf(
                    '%s - %s',
                    $this->formatDate($item->period_start),
                    $this->formatDate($item->period_end),
                ),
                'score' => $item->score !== null ? number_format((float) $item->score, 2, '.', '') : '-',
                'rating' => $item->rating ?? '-',
                'status' => ucfirst(str_replace('_', ' ', $item->status)),
                'edit_url' => $basePath.'/'.$item->id.'/edit',
                'delete_url' => $basePath.'/'.$item->id,
            ],
            'training' => [
                'id' => $item->id,
                'code' => $item->code ?? '-',
                'title' => $item->title,
                'provider' => $item->provider ?? '-',
                'date' => $this->formatDate($item->training_date),
                'duration' => $item->duration_hours ? "{$item->duration_hours} jam" : '-',
                'status' => ucfirst($item->status),
                'edit_url' => $basePath.'/'.$item->id.'/edit',
                'delete_url' => $basePath.'/'.$item->id,
            ],
            'job-posts' => [
                'id' => $item->id,
                'code' => $item->code ?? '-',
                'title' => $item->title,
                'department' => $item->department?->name ?? '-',
                'openings' => $item->openings,
                'period' => sprintf(
                    '%s - %s',
                    $this->formatDate($item->posted_at),
                    $this->formatDate($item->closes_at),
                ),
                'status' => ucfirst($item->status),
                'edit_url' => $basePath.'/'.$item->id.'/edit',
                'delete_url' => $basePath.'/'.$item->id,
            ],
            'candidates' => [
                'id' => $item->id,
                'code' => $item->code ?? '-',
                'full_name' => $item->full_name,
                'job_post' => $item->jobPost?->title ?? '-',
                'email' => $item->email ?? '-',
                'stage' => ucfirst($item->stage),
                'applied_at' => $this->formatDate($item->applied_at),
                'edit_url' => $basePath.'/'.$item->id.'/edit',
                'delete_url' => $basePath.'/'.$item->id,
            ],
            'interviews' => [
                'id' => $item->id,
                'candidate' => $item->candidate?->full_name ?? '-',
                'interviewer' => $item->interviewer?->user?->name ?? '-',
                'schedule' => sprintf(
                    '%s %s',
                    $this->formatDate($item->interview_date),
                    $item->interview_time ? substr((string) $item->interview_time, 0, 5) : '',
                ),
                'mode' => ucfirst($item->mode),
                'result' => ucfirst($item->result),
                'score' => $item->score !== null ? number_format((float) $item->score, 2, '.', '') : '-',
                'edit_url' => $basePath.'/'.$item->id.'/edit',
                'delete_url' => $basePath.'/'.$item->id,
            ],
            'audit-logs' => [
                'id' => $item->id,
                'module' => $item->module,
                'action' => $item->action,
                'actor' => $item->actor_name ?? '-',
                'occurred_at' => $this->formatDate($item->occurred_at),
                'severity' => ucfirst($item->severity),
                'flagged' => $item->is_flagged ? 'Ya' : 'Tidak',
                'edit_url' => $basePath.'/'.$item->id.'/edit',
                'delete_url' => $basePath.'/'.$item->id,
            ],
            default => abort(404),
        };
    }

    private function rulesFor(string $resource, $item = null): array
    {
        $companyRule = ['required', 'exists:companies,id'];

        return match ($resource) {
            'companies' => [
                'name' => ['required', 'string', 'max:255'],
                'legal_name' => ['nullable', 'string', 'max:255'],
                'industry' => ['nullable', 'string', 'max:255'],
                'tax_id' => ['nullable', 'string', 'max:100'],
                'email' => ['nullable', 'email'],
                'phone' => ['nullable', 'string', 'max:50'],
                'website' => ['nullable', 'string', 'max:255'],
                'address_line1' => ['nullable', 'string', 'max:255'],
                'address_line2' => ['nullable', 'string', 'max:255'],
                'city' => ['nullable', 'string', 'max:100'],
                'province' => ['nullable', 'string', 'max:100'],
                'postal_code' => ['nullable', 'string', 'max:20'],
                'country' => ['nullable', 'string', 'max:2'],
                'timezone' => ['nullable', 'string', 'max:50'],
                'is_active' => ['nullable', 'boolean'],
            ],
            'branches' => [
                'company_id' => $companyRule,
                'code' => ['nullable', 'string', 'max:50'],
                'name' => ['required', 'string', 'max:255'],
                'phone' => ['nullable', 'string', 'max:50'],
                'address_line1' => ['nullable', 'string', 'max:255'],
                'address_line2' => ['nullable', 'string', 'max:255'],
                'city' => ['nullable', 'string', 'max:100'],
                'province' => ['nullable', 'string', 'max:100'],
                'postal_code' => ['nullable', 'string', 'max:20'],
                'country' => ['nullable', 'string', 'max:2'],
                'timezone' => ['nullable', 'string', 'max:50'],
                'latitude' => ['nullable', 'numeric'],
                'longitude' => ['nullable', 'numeric'],
                'is_head_office' => ['nullable', 'boolean'],
                'is_active' => ['nullable', 'boolean'],
            ],
            'departments' => [
                'company_id' => $companyRule,
                'branch_id' => ['nullable', 'exists:branches,id'],
                'parent_id' => ['nullable', 'exists:departments,id'],
                'code' => ['nullable', 'string', 'max:50'],
                'name' => ['required', 'string', 'max:255'],
                'description' => ['nullable', 'string'],
                'is_active' => ['nullable', 'boolean'],
            ],
            'job-levels' => [
                'company_id' => $companyRule,
                'code' => ['nullable', 'string', 'max:50'],
                'name' => ['required', 'string', 'max:255'],
                'rank' => ['nullable', 'integer', 'min:0'],
                'description' => ['nullable', 'string'],
            ],
            'positions' => [
                'company_id' => $companyRule,
                'department_id' => ['required', 'exists:departments,id'],
                'job_level_id' => ['nullable', 'exists:job_levels,id'],
                'code' => ['nullable', 'string', 'max:50'],
                'title' => ['required', 'string', 'max:255'],
                'description' => ['nullable', 'string'],
                'is_leadership' => ['nullable', 'boolean'],
                'is_active' => ['nullable', 'boolean'],
            ],
            'work-locations' => [
                'company_id' => $companyRule,
                'branch_id' => ['nullable', 'exists:branches,id'],
                'name' => ['required', 'string', 'max:255'],
                'address' => ['nullable', 'string'],
                'latitude' => ['required', 'numeric'],
                'longitude' => ['required', 'numeric'],
                'radius_meters' => ['nullable', 'integer', 'min:1'],
                'is_active' => ['nullable', 'boolean'],
            ],
            'shifts' => [
                'company_id' => $companyRule,
                'name' => ['required', 'string', 'max:255'],
                'start_time' => ['required', 'date_format:H:i'],
                'end_time' => ['required', 'date_format:H:i'],
                'break_minutes' => ['nullable', 'integer', 'min:0'],
                'grace_minutes' => ['nullable', 'integer', 'min:0'],
                'is_overnight' => ['nullable', 'boolean'],
                'is_active' => ['nullable', 'boolean'],
            ],
            'leave-types' => [
                'company_id' => $companyRule,
                'code' => ['required', 'string', 'max:50'],
                'name' => ['required', 'string', 'max:255'],
                'category' => ['required', Rule::in(['annual', 'sick', 'unpaid', 'maternity', 'paternity', 'special'])],
                'default_allocation' => ['nullable', 'integer', 'min:0'],
                'carry_over_limit' => ['nullable', 'integer', 'min:0'],
                'requires_attachment' => ['nullable', 'boolean'],
                'requires_approval' => ['nullable', 'boolean'],
                'paid' => ['nullable', 'boolean'],
                'description' => ['nullable', 'string'],
                'is_active' => ['nullable', 'boolean'],
            ],
            'salary-components' => [
                'company_id' => $companyRule,
                'code' => ['required', 'string', 'max:50'],
                'name' => ['required', 'string', 'max:255'],
                'type' => ['required', Rule::in(['earning', 'deduction'])],
                'taxable' => ['nullable', 'boolean'],
                'default_amount' => ['nullable', 'numeric'],
                'is_active' => ['nullable', 'boolean'],
            ],
            'payroll-periods' => [
                'company_id' => $companyRule,
                'name' => ['required', 'string', 'max:255'],
                'start_date' => ['required', 'date'],
                'end_date' => ['required', 'date'],
                'pay_date' => ['nullable', 'date'],
                'status' => ['required', Rule::in(['open', 'locked', 'closed'])],
                'notes' => ['nullable', 'string'],
            ],
            'schedules' => [
                'employee_id' => ['required', 'exists:employees,id'],
                'shift_id' => ['nullable', 'exists:shifts,id'],
                'work_location_id' => ['nullable', 'exists:work_locations,id'],
                'work_date' => ['required', 'date'],
                'status' => ['required', Rule::in(['scheduled', 'off', 'holiday'])],
                'notes' => ['nullable', 'string'],
            ],
            'kpi-okr' => [
                'code' => ['nullable', 'string', 'max:50'],
                'title' => ['required', 'string', 'max:255'],
                'objective' => ['nullable', 'string'],
                'employee_id' => ['nullable', 'exists:employees,id'],
                'period_start' => ['required', 'date'],
                'period_end' => ['required', 'date', 'after_or_equal:period_start'],
                'target_value' => ['nullable', 'numeric', 'min:0'],
                'current_value' => ['nullable', 'numeric', 'min:0'],
                'unit' => ['nullable', 'string', 'max:20'],
                'weight' => ['nullable', 'integer', 'between:0,100'],
                'status' => ['required', Rule::in(['draft', 'active', 'completed', 'on_hold'])],
                'is_active' => ['nullable', 'boolean'],
            ],
            'appraisals' => [
                'employee_id' => ['required', 'exists:employees,id'],
                'reviewer_employee_id' => ['nullable', 'exists:employees,id'],
                'period_start' => ['required', 'date'],
                'period_end' => ['required', 'date', 'after_or_equal:period_start'],
                'score' => ['nullable', 'numeric', 'between:0,100'],
                'rating' => ['nullable', 'string', 'max:20'],
                'status' => ['required', Rule::in(['draft', 'in_review', 'completed'])],
                'notes' => ['nullable', 'string'],
            ],
            'training' => [
                'code' => ['nullable', 'string', 'max:50'],
                'title' => ['required', 'string', 'max:255'],
                'provider' => ['nullable', 'string', 'max:255'],
                'training_date' => ['nullable', 'date'],
                'duration_hours' => ['nullable', 'integer', 'min:1'],
                'capacity' => ['nullable', 'integer', 'min:1'],
                'status' => ['required', Rule::in(['planned', 'ongoing', 'completed', 'cancelled'])],
                'mandatory' => ['nullable', 'boolean'],
                'notes' => ['nullable', 'string'],
            ],
            'job-posts' => [
                'code' => ['nullable', 'string', 'max:50'],
                'title' => ['required', 'string', 'max:255'],
                'department_id' => ['nullable', 'exists:departments,id'],
                'position_id' => ['nullable', 'exists:positions,id'],
                'employment_type' => ['nullable', Rule::in(['permanent', 'contract', 'internship', 'daily', 'freelance'])],
                'openings' => ['nullable', 'integer', 'min:1'],
                'posted_at' => ['nullable', 'date'],
                'closes_at' => ['nullable', 'date', 'after_or_equal:posted_at'],
                'status' => ['required', Rule::in(['draft', 'published', 'closed'])],
                'description' => ['nullable', 'string'],
            ],
            'candidates' => [
                'code' => ['nullable', 'string', 'max:50'],
                'full_name' => ['required', 'string', 'max:255'],
                'email' => ['nullable', 'email'],
                'phone' => ['nullable', 'string', 'max:50'],
                'job_post_id' => ['nullable', 'exists:job_posts,id'],
                'stage' => ['required', Rule::in(['applied', 'screening', 'interview', 'offer', 'hired', 'rejected'])],
                'source' => ['nullable', 'string', 'max:100'],
                'applied_at' => ['nullable', 'date'],
                'notes' => ['nullable', 'string'],
            ],
            'interviews' => [
                'candidate_id' => ['required', 'exists:candidates,id'],
                'interviewer_employee_id' => ['nullable', 'exists:employees,id'],
                'interview_date' => ['required', 'date'],
                'interview_time' => ['nullable', 'date_format:H:i'],
                'mode' => ['required', Rule::in(['online', 'offline', 'phone'])],
                'location' => ['nullable', 'string', 'max:255'],
                'result' => ['required', Rule::in(['scheduled', 'passed', 'failed', 'cancelled'])],
                'score' => ['nullable', 'numeric', 'between:0,100'],
                'notes' => ['nullable', 'string'],
            ],
            'audit-logs' => [
                'module' => ['required', 'string', 'max:100'],
                'action' => ['required', 'string', 'max:100'],
                'severity' => ['required', Rule::in(['info', 'warning', 'critical'])],
                'actor_name' => ['nullable', 'string', 'max:255'],
                'actor_email' => ['nullable', 'email'],
                'subject' => ['nullable', 'string', 'max:255'],
                'ip_address' => ['nullable', 'ip'],
                'occurred_at' => ['required', 'date'],
                'notes' => ['nullable', 'string'],
                'is_flagged' => ['nullable', 'boolean'],
            ],
            default => abort(404),
        };
    }

    private function fieldsFor(string $resource): array
    {
        return match ($resource) {
            'companies' => [
                $this->field('name', 'Nama Perusahaan', true),
                $this->field('legal_name', 'Nama Legal'),
                $this->field('industry', 'Industri'),
                $this->field('tax_id', 'Tax ID'),
                $this->field('email', 'Email', false, 'email'),
                $this->field('phone', 'Telepon'),
                $this->field('website', 'Website'),
                $this->field('address_line1', 'Alamat', false, 'textarea'),
                $this->field('address_line2', 'Alamat Tambahan', false, 'textarea'),
                $this->field('city', 'Kota'),
                $this->field('province', 'Provinsi'),
                $this->field('postal_code', 'Kode Pos'),
                $this->field('country', 'Country'),
                $this->field('timezone', 'Timezone'),
                $this->field('is_active', 'Status Aktif', false, 'boolean'),
            ],
            'branches' => [
                $this->selectField('company_id', 'Perusahaan', $this->companyOptions(), true),
                $this->field('code', 'Kode'),
                $this->field('name', 'Nama Cabang', true),
                $this->field('phone', 'Telepon'),
                $this->field('address_line1', 'Alamat', false, 'textarea'),
                $this->field('address_line2', 'Alamat Tambahan', false, 'textarea'),
                $this->field('city', 'Kota'),
                $this->field('province', 'Provinsi'),
                $this->field('postal_code', 'Kode Pos'),
                $this->field('country', 'Country'),
                $this->field('timezone', 'Timezone'),
                $this->field('latitude', 'Latitude', false, 'number'),
                $this->field('longitude', 'Longitude', false, 'number'),
                $this->field('is_head_office', 'Head Office', false, 'boolean'),
                $this->field('is_active', 'Status Aktif', false, 'boolean'),
            ],
            'departments' => [
                $this->selectField('company_id', 'Perusahaan', $this->companyOptions(), true),
                $this->selectField('branch_id', 'Cabang', $this->branchOptions()),
                $this->selectField('parent_id', 'Parent Department', $this->departmentOptions()),
                $this->field('code', 'Kode'),
                $this->field('name', 'Nama Departemen', true),
                $this->field('description', 'Deskripsi', false, 'textarea'),
                $this->field('is_active', 'Status Aktif', false, 'boolean'),
            ],
            'job-levels' => [
                $this->selectField('company_id', 'Perusahaan', $this->companyOptions(), true),
                $this->field('code', 'Kode'),
                $this->field('name', 'Nama Level', true),
                $this->field('rank', 'Rank', false, 'number'),
                $this->field('description', 'Deskripsi', false, 'textarea'),
            ],
            'positions' => [
                $this->selectField('company_id', 'Perusahaan', $this->companyOptions(), true),
                $this->selectField('department_id', 'Departemen', $this->departmentOptions(), true),
                $this->selectField('job_level_id', 'Job Level', $this->jobLevelOptions()),
                $this->field('code', 'Kode'),
                $this->field('title', 'Jabatan', true),
                $this->field('description', 'Deskripsi', false, 'textarea'),
                $this->field('is_leadership', 'Leadership', false, 'boolean'),
                $this->field('is_active', 'Status Aktif', false, 'boolean'),
            ],
            'work-locations' => [
                $this->selectField('company_id', 'Perusahaan', $this->companyOptions(), true),
                $this->selectField('branch_id', 'Cabang', $this->branchOptions()),
                $this->field('name', 'Nama Lokasi', true),
                $this->field('address', 'Alamat', false, 'textarea'),
                $this->field('latitude', 'Latitude', true, 'number'),
                $this->field('longitude', 'Longitude', true, 'number'),
                $this->field('radius_meters', 'Radius (meter)', false, 'number'),
                $this->field('is_active', 'Status Aktif', false, 'boolean'),
            ],
            'shifts' => [
                $this->selectField('company_id', 'Perusahaan', $this->companyOptions(), true),
                $this->field('name', 'Nama Shift', true),
                $this->field('start_time', 'Jam Masuk', true, 'time'),
                $this->field('end_time', 'Jam Pulang', true, 'time'),
                $this->field('break_minutes', 'Break (menit)', false, 'number'),
                $this->field('grace_minutes', 'Grace (menit)', false, 'number'),
                $this->field('is_overnight', 'Overnight', false, 'boolean'),
                $this->field('is_active', 'Status Aktif', false, 'boolean'),
            ],
            'leave-types' => [
                $this->selectField('company_id', 'Perusahaan', $this->companyOptions(), true),
                $this->field('code', 'Kode', true),
                $this->field('name', 'Nama', true),
                $this->selectField('category', 'Kategori', $this->leaveCategoryOptions(), true),
                $this->field('default_allocation', 'Kuota Default', false, 'number'),
                $this->field('carry_over_limit', 'Carry Over', false, 'number'),
                $this->field('requires_attachment', 'Wajib Lampiran', false, 'boolean'),
                $this->field('requires_approval', 'Wajib Approval', false, 'boolean'),
                $this->field('paid', 'Paid', false, 'boolean'),
                $this->field('description', 'Deskripsi', false, 'textarea'),
                $this->field('is_active', 'Status Aktif', false, 'boolean'),
            ],
            'salary-components' => [
                $this->selectField('company_id', 'Perusahaan', $this->companyOptions(), true),
                $this->field('code', 'Kode', true),
                $this->field('name', 'Nama Komponen', true),
                $this->selectField('type', 'Tipe', [
                    ['value' => 'earning', 'label' => 'Earning'],
                    ['value' => 'deduction', 'label' => 'Deduction'],
                ], true),
                $this->field('taxable', 'Taxable', false, 'boolean'),
                $this->field('default_amount', 'Default Amount', false, 'number'),
                $this->field('is_active', 'Status Aktif', false, 'boolean'),
            ],
            'payroll-periods' => [
                $this->selectField('company_id', 'Perusahaan', $this->companyOptions(), true),
                $this->field('name', 'Nama Periode', true),
                $this->field('start_date', 'Start Date', true, 'date'),
                $this->field('end_date', 'End Date', true, 'date'),
                $this->field('pay_date', 'Pay Date', false, 'date'),
                $this->selectField('status', 'Status', [
                    ['value' => 'open', 'label' => 'Open'],
                    ['value' => 'locked', 'label' => 'Locked'],
                    ['value' => 'closed', 'label' => 'Closed'],
                ], true),
                $this->field('notes', 'Catatan', false, 'textarea'),
            ],
            'schedules' => [
                $this->selectField('employee_id', 'Karyawan', $this->employeeOptions(), true),
                $this->selectField('shift_id', 'Shift', $this->shiftOptions()),
                $this->selectField('work_location_id', 'Lokasi Kerja', $this->workLocationOptions()),
                $this->field('work_date', 'Tanggal Kerja', true, 'date'),
                $this->selectField('status', 'Status', [
                    ['value' => 'scheduled', 'label' => 'Scheduled'],
                    ['value' => 'off', 'label' => 'Off'],
                    ['value' => 'holiday', 'label' => 'Holiday'],
                ], true),
                $this->field('notes', 'Catatan', false, 'textarea'),
            ],
            'kpi-okr' => [
                $this->field('code', 'Kode'),
                $this->field('title', 'Judul Target', true),
                $this->field('objective', 'Objective', false, 'textarea'),
                $this->selectField('employee_id', 'PIC', $this->employeeOptions()),
                $this->field('period_start', 'Periode Mulai', true, 'date'),
                $this->field('period_end', 'Periode Selesai', true, 'date'),
                $this->field('target_value', 'Target', false, 'number'),
                $this->field('current_value', 'Current', false, 'number'),
                $this->field('unit', 'Unit'),
                $this->field('weight', 'Bobot (%)', false, 'number'),
                $this->selectField('status', 'Status', [
                    ['value' => 'draft', 'label' => 'Draft'],
                    ['value' => 'active', 'label' => 'Active'],
                    ['value' => 'completed', 'label' => 'Completed'],
                    ['value' => 'on_hold', 'label' => 'On Hold'],
                ], true),
                $this->field('is_active', 'Aktif', false, 'boolean'),
            ],
            'appraisals' => [
                $this->selectField('employee_id', 'Karyawan', $this->employeeOptions(), true),
                $this->selectField('reviewer_employee_id', 'Reviewer', $this->employeeOptions()),
                $this->field('period_start', 'Periode Mulai', true, 'date'),
                $this->field('period_end', 'Periode Selesai', true, 'date'),
                $this->field('score', 'Skor', false, 'number'),
                $this->field('rating', 'Rating'),
                $this->selectField('status', 'Status', [
                    ['value' => 'draft', 'label' => 'Draft'],
                    ['value' => 'in_review', 'label' => 'In Review'],
                    ['value' => 'completed', 'label' => 'Completed'],
                ], true),
                $this->field('notes', 'Catatan', false, 'textarea'),
            ],
            'training' => [
                $this->field('code', 'Kode'),
                $this->field('title', 'Judul Pelatihan', true),
                $this->field('provider', 'Provider'),
                $this->field('training_date', 'Tanggal', false, 'date'),
                $this->field('duration_hours', 'Durasi (jam)', false, 'number'),
                $this->field('capacity', 'Kapasitas', false, 'number'),
                $this->selectField('status', 'Status', [
                    ['value' => 'planned', 'label' => 'Planned'],
                    ['value' => 'ongoing', 'label' => 'Ongoing'],
                    ['value' => 'completed', 'label' => 'Completed'],
                    ['value' => 'cancelled', 'label' => 'Cancelled'],
                ], true),
                $this->field('mandatory', 'Mandatory', false, 'boolean'),
                $this->field('notes', 'Catatan', false, 'textarea'),
            ],
            'job-posts' => [
                $this->field('code', 'Kode'),
                $this->field('title', 'Judul Posisi', true),
                $this->selectField('department_id', 'Departemen', $this->departmentOptions()),
                $this->selectField('position_id', 'Posisi', $this->positionOptions()),
                $this->selectField('employment_type', 'Tipe Karyawan', [
                    ['value' => 'permanent', 'label' => 'Permanent'],
                    ['value' => 'contract', 'label' => 'Contract'],
                    ['value' => 'internship', 'label' => 'Internship'],
                    ['value' => 'daily', 'label' => 'Daily'],
                    ['value' => 'freelance', 'label' => 'Freelance'],
                ]),
                $this->field('openings', 'Jumlah Kebutuhan', false, 'number'),
                $this->field('posted_at', 'Tanggal Publish', false, 'date'),
                $this->field('closes_at', 'Tanggal Tutup', false, 'date'),
                $this->selectField('status', 'Status', [
                    ['value' => 'draft', 'label' => 'Draft'],
                    ['value' => 'published', 'label' => 'Published'],
                    ['value' => 'closed', 'label' => 'Closed'],
                ], true),
                $this->field('description', 'Deskripsi', false, 'textarea'),
            ],
            'candidates' => [
                $this->field('code', 'Kode'),
                $this->field('full_name', 'Nama Kandidat', true),
                $this->field('email', 'Email', false, 'email'),
                $this->field('phone', 'Telepon'),
                $this->selectField('job_post_id', 'Job Post', $this->jobPostOptions()),
                $this->selectField('stage', 'Stage', [
                    ['value' => 'applied', 'label' => 'Applied'],
                    ['value' => 'screening', 'label' => 'Screening'],
                    ['value' => 'interview', 'label' => 'Interview'],
                    ['value' => 'offer', 'label' => 'Offer'],
                    ['value' => 'hired', 'label' => 'Hired'],
                    ['value' => 'rejected', 'label' => 'Rejected'],
                ], true),
                $this->field('source', 'Sumber Kandidat'),
                $this->field('applied_at', 'Tanggal Apply', false, 'date'),
                $this->field('notes', 'Catatan', false, 'textarea'),
            ],
            'interviews' => [
                $this->selectField('candidate_id', 'Kandidat', $this->candidateOptions(), true),
                $this->selectField('interviewer_employee_id', 'Interviewer', $this->employeeOptions()),
                $this->field('interview_date', 'Tanggal Interview', true, 'date'),
                $this->field('interview_time', 'Jam Interview', false, 'time'),
                $this->selectField('mode', 'Mode', [
                    ['value' => 'online', 'label' => 'Online'],
                    ['value' => 'offline', 'label' => 'Offline'],
                    ['value' => 'phone', 'label' => 'Phone'],
                ], true),
                $this->field('location', 'Lokasi / Link'),
                $this->selectField('result', 'Hasil', [
                    ['value' => 'scheduled', 'label' => 'Scheduled'],
                    ['value' => 'passed', 'label' => 'Passed'],
                    ['value' => 'failed', 'label' => 'Failed'],
                    ['value' => 'cancelled', 'label' => 'Cancelled'],
                ], true),
                $this->field('score', 'Skor', false, 'number'),
                $this->field('notes', 'Catatan', false, 'textarea'),
            ],
            'audit-logs' => [
                $this->field('module', 'Modul', true),
                $this->field('action', 'Aksi', true),
                $this->selectField('severity', 'Severity', [
                    ['value' => 'info', 'label' => 'Info'],
                    ['value' => 'warning', 'label' => 'Warning'],
                    ['value' => 'critical', 'label' => 'Critical'],
                ], true),
                $this->field('actor_name', 'Nama Aktor'),
                $this->field('actor_email', 'Email Aktor', false, 'email'),
                $this->field('subject', 'Subjek'),
                $this->field('ip_address', 'IP Address'),
                $this->field('occurred_at', 'Tanggal', true, 'date'),
                $this->field('notes', 'Catatan', false, 'textarea'),
                $this->field('is_flagged', 'Flagged', false, 'boolean'),
            ],
            default => abort(404),
        };
    }

    private function importColumns(string $resource): array
    {
        return collect($this->fieldsFor($resource))
            ->map(fn ($field) => $field['name'])
            ->values()
            ->all();
    }

    private function uniqueKeysForImport(string $resource, array $row): ?array
    {
        if (!array_key_exists('code', $row)) {
            return null;
        }

        $code = trim((string) $row['code']);
        if ($code === '') {
            return null;
        }

        $companyId = $row['company_id'] ?? null;

        return match ($resource) {
            'branches',
            'departments',
            'job-levels',
            'positions',
            'leave-types',
            'salary-components' => $companyId ? [
                'company_id' => $companyId,
                'code' => $code,
            ] : null,
            'kpi-okr',
            'training',
            'job-posts',
            'candidates' => [
                'code' => $code,
            ],
            default => null,
        };
    }

    private function recordData(string $resource, $item): array
    {
        return match ($resource) {
            'companies' => $item->only([
                'name',
                'legal_name',
                'industry',
                'tax_id',
                'email',
                'phone',
                'website',
                'address_line1',
                'address_line2',
                'city',
                'province',
                'postal_code',
                'country',
                'timezone',
                'is_active',
            ]),
            'branches' => $item->only([
                'company_id',
                'code',
                'name',
                'phone',
                'address_line1',
                'address_line2',
                'city',
                'province',
                'postal_code',
                'country',
                'timezone',
                'latitude',
                'longitude',
                'is_head_office',
                'is_active',
            ]),
            'departments' => $item->only([
                'company_id',
                'branch_id',
                'parent_id',
                'code',
                'name',
                'description',
                'is_active',
            ]),
            'job-levels' => $item->only([
                'company_id',
                'code',
                'name',
                'rank',
                'description',
            ]),
            'positions' => $item->only([
                'company_id',
                'department_id',
                'job_level_id',
                'code',
                'title',
                'description',
                'is_leadership',
                'is_active',
            ]),
            'work-locations' => $item->only([
                'company_id',
                'branch_id',
                'name',
                'address',
                'latitude',
                'longitude',
                'radius_meters',
                'is_active',
            ]),
            'shifts' => [
                'company_id' => $item->company_id,
                'name' => $item->name,
                'start_time' => $item->start_time
                    ? Carbon::parse($item->start_time)->format('H:i')
                    : null,
                'end_time' => $item->end_time
                    ? Carbon::parse($item->end_time)->format('H:i')
                    : null,
                'break_minutes' => $item->break_minutes,
                'grace_minutes' => $item->grace_minutes,
                'is_overnight' => $item->is_overnight,
                'is_active' => $item->is_active,
            ],
            'leave-types' => $item->only([
                'company_id',
                'code',
                'name',
                'category',
                'default_allocation',
                'carry_over_limit',
                'requires_attachment',
                'requires_approval',
                'paid',
                'description',
                'is_active',
            ]),
            'salary-components' => $item->only([
                'company_id',
                'code',
                'name',
                'type',
                'taxable',
                'default_amount',
                'is_active',
            ]),
            'payroll-periods' => $item->only([
                'company_id',
                'name',
                'start_date',
                'end_date',
                'pay_date',
                'status',
                'notes',
            ]),
            'schedules' => $item->only([
                'employee_id',
                'shift_id',
                'work_location_id',
                'work_date',
                'status',
                'notes',
            ]),
            'kpi-okr' => $item->only([
                'code',
                'title',
                'objective',
                'employee_id',
                'period_start',
                'period_end',
                'target_value',
                'current_value',
                'unit',
                'weight',
                'status',
                'is_active',
            ]),
            'appraisals' => $item->only([
                'employee_id',
                'reviewer_employee_id',
                'period_start',
                'period_end',
                'score',
                'rating',
                'status',
                'notes',
            ]),
            'training' => $item->only([
                'code',
                'title',
                'provider',
                'training_date',
                'duration_hours',
                'capacity',
                'status',
                'mandatory',
                'notes',
            ]),
            'job-posts' => $item->only([
                'code',
                'title',
                'department_id',
                'position_id',
                'employment_type',
                'openings',
                'posted_at',
                'closes_at',
                'status',
                'description',
            ]),
            'candidates' => $item->only([
                'code',
                'full_name',
                'email',
                'phone',
                'job_post_id',
                'stage',
                'source',
                'applied_at',
                'notes',
            ]),
            'interviews' => [
                'candidate_id' => $item->candidate_id,
                'interviewer_employee_id' => $item->interviewer_employee_id,
                'interview_date' => $item->interview_date
                    ? Carbon::parse($item->interview_date)->format('Y-m-d')
                    : null,
                'interview_time' => $item->interview_time
                    ? substr((string) $item->interview_time, 0, 5)
                    : null,
                'mode' => $item->mode,
                'location' => $item->location,
                'result' => $item->result,
                'score' => $item->score,
                'notes' => $item->notes,
            ],
            'audit-logs' => $item->only([
                'module',
                'action',
                'severity',
                'actor_name',
                'actor_email',
                'subject',
                'ip_address',
                'occurred_at',
                'notes',
                'is_flagged',
            ]),
            default => abort(404),
        };
    }

    private function normalizeImportRow(string $resource, array $row): array
    {
        foreach ($this->booleanFields($resource) as $field) {
            if (!array_key_exists($field, $row)) {
                continue;
            }

            $row[$field] = $this->parseBoolean($row[$field]);
        }

        foreach ($this->timeFields($resource) as $field) {
            if (!array_key_exists($field, $row)) {
                continue;
            }

            $row[$field] = $this->normalizeTime($row[$field]);
        }

        foreach ($this->dateFields($resource) as $field) {
            if (!array_key_exists($field, $row)) {
                continue;
            }

            $value = $row[$field];
            if ($value === null || $value === '') {
                continue;
            }

            try {
                $row[$field] = Carbon::parse($value)->format('Y-m-d');
            } catch (\Throwable $exception) {
                // Keep original value for validation to catch.
            }
        }

        return $row;
    }

    private function parseBoolean($value)
    {
        if (is_bool($value)) {
            return $value;
        }

        $normalized = strtolower(trim((string) $value));

        if ($normalized === '') {
            return null;
        }

        if (in_array($normalized, ['1', 'true', 'yes', 'ya', 'y'], true)) {
            return true;
        }

        if (in_array($normalized, ['0', 'false', 'no', 'tidak', 'n'], true)) {
            return false;
        }

        return $value;
    }

    private function normalizeTime($value)
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (preg_match('/^\d{1,2}:\d{2}:\d{2}$/', $value)) {
            return substr($value, 0, 5);
        }

        return $value;
    }

    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if ($value !== null && $value !== '') {
                return false;
            }
        }

        return true;
    }

    private function formatValueForExport(string $resource, string $column, $value)
    {
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        if (in_array($column, $this->timeFields($resource), true) && is_string($value)) {
            return $this->normalizeTime($value);
        }

        if (in_array($column, $this->dateFields($resource), true) && $value) {
            try {
                return Carbon::parse($value)->format('Y-m-d');
            } catch (\Throwable $exception) {
                return $value;
            }
        }

        return $value;
    }

    private function booleanFields(string $resource): array
    {
        return match ($resource) {
            'companies' => ['is_active'],
            'branches' => ['is_head_office', 'is_active'],
            'departments' => ['is_active'],
            'positions' => ['is_leadership', 'is_active'],
            'work-locations' => ['is_active'],
            'shifts' => ['is_overnight', 'is_active'],
            'leave-types' => [
                'requires_attachment',
                'requires_approval',
                'paid',
                'is_active',
            ],
            'salary-components' => ['taxable', 'is_active'],
            'kpi-okr' => ['is_active'],
            'training' => ['mandatory'],
            'audit-logs' => ['is_flagged'],
            default => [],
        };
    }

    private function dateFields(string $resource): array
    {
        return match ($resource) {
            'payroll-periods' => ['start_date', 'end_date', 'pay_date'],
            'schedules' => ['work_date'],
            'kpi-okr' => ['period_start', 'period_end'],
            'appraisals' => ['period_start', 'period_end'],
            'training' => ['training_date'],
            'job-posts' => ['posted_at', 'closes_at'],
            'candidates' => ['applied_at'],
            'interviews' => ['interview_date'],
            'audit-logs' => ['occurred_at'],
            default => [],
        };
    }

    private function timeFields(string $resource): array
    {
        return match ($resource) {
            'shifts' => ['start_time', 'end_time'],
            'interviews' => ['interview_time'],
            default => [],
        };
    }

    private function field(string $name, string $label, bool $required = false, string $type = 'text'): array
    {
        return [
            'name' => $name,
            'label' => $label,
            'required' => $required,
            'type' => $type,
        ];
    }

    private function selectField(string $name, string $label, array $options, bool $required = false): array
    {
        return [
            'name' => $name,
            'label' => $label,
            'required' => $required,
            'type' => 'select',
            'options' => $options,
        ];
    }

    private function companyOptions(): array
    {
        return Company::orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($company) => [
                'value' => (string) $company->id,
                'label' => $company->name,
            ])
            ->all();
    }

    private function branchOptions(): array
    {
        return Branch::orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($branch) => [
                'value' => (string) $branch->id,
                'label' => $branch->name,
            ])
            ->all();
    }

    private function departmentOptions(): array
    {
        return Department::orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($department) => [
                'value' => (string) $department->id,
                'label' => $department->name,
            ])
            ->all();
    }

    private function jobLevelOptions(): array
    {
        return JobLevel::orderBy('rank')
            ->get(['id', 'name'])
            ->map(fn ($level) => [
                'value' => (string) $level->id,
                'label' => $level->name,
            ])
            ->all();
    }

    private function positionOptions(): array
    {
        return Position::orderBy('title')
            ->get(['id', 'title'])
            ->map(fn ($position) => [
                'value' => (string) $position->id,
                'label' => $position->title,
            ])
            ->all();
    }

    private function workLocationOptions(): array
    {
        return WorkLocation::orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($location) => [
                'value' => (string) $location->id,
                'label' => $location->name,
            ])
            ->all();
    }

    private function shiftOptions(): array
    {
        return Shift::orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($shift) => [
                'value' => (string) $shift->id,
                'label' => $shift->name,
            ])
            ->all();
    }

    private function employeeOptions(): array
    {
        return Employee::with('user:id,name')
            ->orderBy('employee_code')
            ->get(['id', 'employee_code', 'user_id'])
            ->map(fn ($employee) => [
                'value' => (string) $employee->id,
                'label' => "{$employee->employee_code} · {$employee->user?->name}",
            ])
            ->all();
    }

    private function jobPostOptions(): array
    {
        return JobPost::orderBy('title')
            ->get(['id', 'title'])
            ->map(fn ($jobPost) => [
                'value' => (string) $jobPost->id,
                'label' => $jobPost->title,
            ])
            ->all();
    }

    private function candidateOptions(): array
    {
        return Candidate::orderBy('full_name')
            ->get(['id', 'full_name'])
            ->map(fn ($candidate) => [
                'value' => (string) $candidate->id,
                'label' => $candidate->full_name,
            ])
            ->all();
    }

    private function leaveCategoryOptions(): array
    {
        return [
            ['value' => 'annual', 'label' => 'Annual'],
            ['value' => 'sick', 'label' => 'Sick'],
            ['value' => 'unpaid', 'label' => 'Unpaid'],
            ['value' => 'maternity', 'label' => 'Maternity'],
            ['value' => 'paternity', 'label' => 'Paternity'],
            ['value' => 'special', 'label' => 'Special'],
        ];
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

    private function formatProgress($current, $target, $unit): string
    {
        if ($target === null || (float) $target <= 0) {
            if ($current === null) {
                return '-';
            }

            return trim(number_format((float) $current, 2, '.', '').' '.(string) $unit);
        }

        $percentage = ((float) $current / (float) $target) * 100;

        return sprintf(
            '%s/%s %s (%.1f%%)',
            number_format((float) $current, 2, '.', ''),
            number_format((float) $target, 2, '.', ''),
            (string) $unit,
            $percentage,
        );
    }
}
