<?php

use App\Models\Company;
use App\Models\Employee;
use App\Models\User;

function createSuperadmin(): User
{
    return User::factory()->create([
        'role' => 'superadmin',
    ]);
}

function createCompany(string $name = 'Acme Corp'): Company
{
    return Company::create([
        'name' => $name,
    ]);
}

function createEmployeeForCompany(Company $company): Employee
{
    $user = User::factory()->create([
        'role' => 'employee',
    ]);

    return Employee::create([
        'user_id' => $user->id,
        'company_id' => $company->id,
        'employee_code' => 'EMP-'.str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT),
        'employment_status' => 'active',
        'employment_type' => 'permanent',
        'join_date' => '2026-01-01',
    ]);
}

test('superadmin can create company from organization module', function () {
    $superadmin = createSuperadmin();

    $this->actingAs($superadmin)
        ->post('/modules/organization/companies', [
            'name' => 'Human Web Group',
            'industry' => 'Technology',
            'is_active' => true,
        ])
        ->assertRedirect('/modules/organization/companies')
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('companies', [
        'name' => 'Human Web Group',
    ]);
});

test('superadmin can create employee from employee master page', function () {
    $superadmin = createSuperadmin();
    $company = createCompany();

    $this->actingAs($superadmin)
        ->post('/modules/employees', [
            'name' => 'Budi Santoso',
            'email' => 'budi@example.com',
            'role' => 'employee',
            'password' => 'Password123!',
            'employee_code' => 'EMP-1001',
            'company_id' => $company->id,
            'employment_status' => 'active',
            'employment_type' => 'permanent',
            'join_date' => '2026-02-01',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('users', [
        'email' => 'budi@example.com',
        'role' => 'employee',
    ]);

    $this->assertDatabaseHas('employees', [
        'employee_code' => 'EMP-1001',
        'company_id' => $company->id,
    ]);
});

test('superadmin can create contract document and asset records', function () {
    $superadmin = createSuperadmin();
    $company = createCompany();
    $employee = createEmployeeForCompany($company);

    $this->actingAs($superadmin)
        ->post('/modules/contracts', [
            'employee_id' => $employee->id,
            'type' => 'contract',
            'start_date' => '2026-02-01',
            'status' => 'active',
        ])
        ->assertRedirect('/modules/contracts')
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('employee_contracts', [
        'employee_id' => $employee->id,
        'type' => 'contract',
        'status' => 'active',
    ]);

    $this->actingAs($superadmin)
        ->post('/modules/documents', [
            'employee_id' => $employee->id,
            'type' => 'KTP',
            'number' => '3174XXXXXXXXXXXX',
        ])
        ->assertRedirect('/modules/documents')
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('employee_documents', [
        'employee_id' => $employee->id,
        'type' => 'KTP',
    ]);

    $this->actingAs($superadmin)
        ->post('/modules/assets', [
            'company_id' => $company->id,
            'code' => 'AST-001',
            'name' => 'Laptop Lenovo',
            'status' => 'available',
        ])
        ->assertRedirect('/modules/assets')
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('assets', [
        'company_id' => $company->id,
        'code' => 'AST-001',
        'name' => 'Laptop Lenovo',
        'status' => 'available',
    ]);
});

