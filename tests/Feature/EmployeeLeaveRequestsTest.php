<?php

use App\Models\Approval;
use App\Models\Company;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;

function seedEmployeeLeaveContext(bool $leaveTypeActive = true): array
{
    $user = User::factory()->create([
        'role' => 'employee',
    ]);

    $company = Company::create([
        'name' => 'Acme Inc',
    ]);

    $employee = Employee::create([
        'user_id' => $user->id,
        'company_id' => $company->id,
        'employee_code' => 'EMP-001',
        'join_date' => '2026-01-01',
    ]);

    $leaveType = LeaveType::create([
        'company_id' => $company->id,
        'code' => 'ANNUAL',
        'name' => 'Cuti Tahunan',
        'is_active' => $leaveTypeActive,
    ]);

    return [$user, $employee, $leaveType];
}

test('employee can open leave request page', function () {
    [$user] = seedEmployeeLeaveContext();

    $this->actingAs($user)
        ->get('/employee/leave-requests')
        ->assertOk();
});

test('employee can submit leave request and approval steps are generated', function () {
    [$user, $employee, $leaveType] = seedEmployeeLeaveContext();

    $this->actingAs($user)
        ->post('/employee/leave-requests', [
            'leave_type_id' => $leaveType->id,
            'start_date' => '2026-02-10',
            'end_date' => '2026-02-12',
            'reason' => 'Keperluan keluarga',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $leaveRequest = LeaveRequest::first();
    expect($leaveRequest)->not->toBeNull();

    $this->assertDatabaseHas('leave_requests', [
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
        'status' => 'pending',
    ]);

    $approval = Approval::first();
    expect($approval)->not->toBeNull();

    $this->assertDatabaseHas('approvals', [
        'id' => $approval->id,
        'approvable_type' => LeaveRequest::class,
        'approvable_id' => $leaveRequest->id,
        'status' => 'pending',
        'current_step' => 1,
        'requested_by_user_id' => $user->id,
    ]);

    $this->assertDatabaseHas('approval_steps', [
        'approval_id' => $approval->id,
        'step' => 1,
        'status' => 'pending',
    ]);
    $this->assertDatabaseHas('approval_steps', [
        'approval_id' => $approval->id,
        'step' => 2,
        'status' => 'pending',
    ]);
});

test('employee cannot submit leave request with inactive leave type', function () {
    [$user, $employee, $leaveType] = seedEmployeeLeaveContext(false);

    $this->actingAs($user)
        ->post('/employee/leave-requests', [
            'leave_type_id' => $leaveType->id,
            'start_date' => '2026-02-10',
            'end_date' => '2026-02-10',
        ])
        ->assertRedirect()
        ->assertSessionHasErrors(['leave_type_id']);

    $this->assertDatabaseMissing('leave_requests', [
        'employee_id' => $employee->id,
    ]);
});

test('employee cannot submit overlapping leave request', function () {
    [$user, $employee, $leaveType] = seedEmployeeLeaveContext();

    LeaveRequest::create([
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
        'start_date' => '2026-02-10',
        'end_date' => '2026-02-12',
        'total_days' => 3,
        'status' => 'pending',
        'requested_at' => now(),
    ]);

    $this->actingAs($user)
        ->post('/employee/leave-requests', [
            'leave_type_id' => $leaveType->id,
            'start_date' => '2026-02-11',
            'end_date' => '2026-02-13',
        ])
        ->assertRedirect()
        ->assertSessionHasErrors(['start_date']);

    expect(LeaveRequest::count())->toBe(1);
});

test('user without employee profile cannot submit leave request', function () {
    $user = User::factory()->create([
        'role' => 'employee',
    ]);

    $this->actingAs($user)
        ->post('/employee/leave-requests', [
            'leave_type_id' => 9999,
            'start_date' => '2026-02-10',
            'end_date' => '2026-02-11',
        ])
        ->assertRedirect()
        ->assertSessionHasErrors(['employee']);
});

