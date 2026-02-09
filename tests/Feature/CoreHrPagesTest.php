<?php

use App\Models\User;

it('allows superadmin to open core hr pages', function (string $url) {
    $user = User::factory()->create([
        'role' => 'superadmin',
    ]);

    $this->actingAs($user)
        ->get($url)
        ->assertOk();
})->with([
    '/modules/employees',
    '/modules/organization/companies',
    '/modules/contracts',
    '/modules/documents',
    '/modules/assets',
]);

it('redirects organization entry to company master data', function () {
    $user = User::factory()->create([
        'role' => 'superadmin',
    ]);

    $this->actingAs($user)
        ->get('/modules/organization')
        ->assertRedirect('/modules/organization/companies');
});
