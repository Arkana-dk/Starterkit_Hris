<?php

use App\Models\Appraisal;
use App\Models\AuditLog;
use App\Models\Candidate;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Interview;
use App\Models\JobPost;
use App\Models\KpiOkr;
use App\Models\Training;
use App\Models\User;

function createEmployeeRecord(string $role = 'employee'): Employee
{
    $company = Company::create([
        'name' => 'Company '.strtoupper(substr(md5((string) microtime(true)), 0, 6)),
    ]);
    $user = User::factory()->create([
        'role' => $role,
    ]);

    return Employee::create([
        'user_id' => $user->id,
        'company_id' => $company->id,
        'employee_code' => 'EMP-'.strtoupper(substr(md5((string) $user->id), 0, 8)),
        'employment_status' => 'active',
        'employment_type' => 'permanent',
        'join_date' => now()->toDateString(),
        'is_active' => true,
    ]);
}

it('superadmin can create KPI OKR record', function () {
    $superadmin = User::factory()->create([
        'role' => 'superadmin',
    ]);
    $owner = createEmployeeRecord();

    $this->actingAs($superadmin)
        ->post('/modules/kpi-okr', [
            'code' => 'KPI-001',
            'title' => 'Turunkan turnover',
            'objective' => 'Menjaga retensi tim',
            'employee_id' => $owner->id,
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'target_value' => 100,
            'current_value' => 25,
            'unit' => '%',
            'weight' => 30,
            'status' => 'active',
            'is_active' => true,
        ])
        ->assertRedirect('/modules/kpi-okr');

    expect(KpiOkr::where('code', 'KPI-001')->exists())->toBeTrue();
});

it('superadmin can create appraisal record', function () {
    $superadmin = User::factory()->create([
        'role' => 'superadmin',
    ]);
    $employee = createEmployeeRecord();
    $reviewer = createEmployeeRecord();

    $this->actingAs($superadmin)
        ->post('/modules/appraisals', [
            'employee_id' => $employee->id,
            'reviewer_employee_id' => $reviewer->id,
            'period_start' => now()->subMonth()->startOfMonth()->toDateString(),
            'period_end' => now()->subMonth()->endOfMonth()->toDateString(),
            'score' => 88.5,
            'rating' => 'A',
            'status' => 'completed',
            'notes' => 'Kinerja sangat baik.',
        ])
        ->assertRedirect('/modules/appraisals');

    expect(Appraisal::where('employee_id', $employee->id)->exists())->toBeTrue();
});

it('superadmin can create training record', function () {
    $superadmin = User::factory()->create([
        'role' => 'superadmin',
    ]);

    $this->actingAs($superadmin)
        ->post('/modules/training', [
            'code' => 'TRN-001',
            'title' => 'Leadership Bootcamp',
            'provider' => 'Internal L&D',
            'training_date' => now()->addWeek()->toDateString(),
            'duration_hours' => 8,
            'capacity' => 25,
            'status' => 'planned',
            'mandatory' => true,
            'notes' => 'Untuk calon manager.',
        ])
        ->assertRedirect('/modules/training');

    expect(Training::where('code', 'TRN-001')->exists())->toBeTrue();
});

it('superadmin can create recruitment records', function () {
    $superadmin = User::factory()->create([
        'role' => 'superadmin',
    ]);

    $this->actingAs($superadmin)
        ->post('/modules/job-posts', [
            'code' => 'JOB-001',
            'title' => 'Frontend Engineer',
            'openings' => 2,
            'posted_at' => now()->toDateString(),
            'closes_at' => now()->addDays(14)->toDateString(),
            'status' => 'published',
            'description' => 'React + TypeScript',
        ])
        ->assertRedirect('/modules/job-posts');

    $jobPost = JobPost::where('code', 'JOB-001')->first();
    expect($jobPost)->not->toBeNull();

    $this->actingAs($superadmin)
        ->post('/modules/candidates', [
            'code' => 'CAN-001',
            'full_name' => 'Budi Santoso',
            'email' => 'budi@example.test',
            'phone' => '081234567890',
            'job_post_id' => $jobPost?->id,
            'stage' => 'interview',
            'source' => 'LinkedIn',
            'applied_at' => now()->toDateString(),
            'notes' => 'Kandidat potensial.',
        ])
        ->assertRedirect('/modules/candidates');

    $candidate = Candidate::where('code', 'CAN-001')->first();
    expect($candidate)->not->toBeNull();

    $interviewer = createEmployeeRecord();

    $this->actingAs($superadmin)
        ->post('/modules/interviews', [
            'candidate_id' => $candidate?->id,
            'interviewer_employee_id' => $interviewer->id,
            'interview_date' => now()->addDays(3)->toDateString(),
            'interview_time' => '09:30',
            'mode' => 'online',
            'location' => 'https://meet.example.test',
            'result' => 'scheduled',
            'score' => 0,
            'notes' => 'Interview pertama.',
        ])
        ->assertRedirect('/modules/interviews');

    expect(Interview::where('candidate_id', $candidate?->id)->exists())->toBeTrue();
});

it('superadmin can create audit log record', function () {
    $superadmin = User::factory()->create([
        'role' => 'superadmin',
    ]);

    $this->actingAs($superadmin)
        ->post('/modules/audit-logs', [
            'module' => 'recruitment',
            'action' => 'create_candidate',
            'severity' => 'info',
            'actor_name' => 'Super Admin',
            'actor_email' => 'superadmin@example.test',
            'subject' => 'Candidate CAN-001',
            'ip_address' => '127.0.0.1',
            'occurred_at' => now()->toDateString(),
            'notes' => 'Manual audit entry.',
            'is_flagged' => false,
        ])
        ->assertRedirect('/modules/audit-logs');

    expect(AuditLog::where('module', 'recruitment')->exists())->toBeTrue();
});
