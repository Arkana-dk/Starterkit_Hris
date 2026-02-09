<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('allows superadmin to open operations feature pages', function (string $url) {
    $user = User::factory()->create([
        'role' => 'superadmin',
    ]);

    $this->actingAs($user)
        ->get($url)
        ->assertOk();
})->with([
    '/modules/attendance',
    '/modules/shifts',
    '/modules/schedules',
    '/modules/overtime',
    '/modules/leave-requests',
    '/modules/leave-types',
    '/modules/payroll-periods',
    '/modules/salary-components',
    '/modules/payslips',
    '/modules/reimburse',
    '/modules/kpi-okr',
    '/modules/appraisals',
    '/modules/training',
    '/modules/job-posts',
    '/modules/candidates',
    '/modules/interviews',
]);

it('renders candidates module as active master-data page', function () {
    $user = User::factory()->create([
        'role' => 'superadmin',
    ]);

    $this->actingAs($user)
        ->get('/modules/candidates')
        ->assertInertia(fn (Assert $page) => $page
            ->component('master-data/index')
            ->where('resource', 'candidates'));
});

it('renders audit logs module as active master-data page', function () {
    $user = User::factory()->create([
        'role' => 'superadmin',
    ]);

    $this->actingAs($user)
        ->get('/modules/audit-logs')
        ->assertInertia(fn (Assert $page) => $page
            ->component('master-data/index')
            ->where('resource', 'audit-logs'));
});

it('allows admin to open non-restricted operations pages', function (string $url) {
    $user = User::factory()->create([
        'role' => 'admin',
    ]);

    $this->actingAs($user)
        ->get($url)
        ->assertOk();
})->with([
    '/modules/attendance',
    '/modules/shifts',
    '/modules/schedules',
    '/modules/overtime',
    '/modules/leave-requests',
    '/modules/leave-types',
    '/modules/reimburse',
    '/modules/kpi-okr',
    '/modules/appraisals',
    '/modules/training',
    '/modules/job-posts',
    '/modules/candidates',
    '/modules/interviews',
]);

it('blocks admin from superadmin-only operations pages', function (string $url) {
    $user = User::factory()->create([
        'role' => 'admin',
    ]);

    $this->actingAs($user)
        ->get($url)
        ->assertForbidden();
})->with([
    '/modules/organization',
    '/modules/payroll-periods',
    '/modules/salary-components',
    '/modules/payslips',
    '/modules/audit-logs',
]);
