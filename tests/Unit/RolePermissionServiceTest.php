<?php

use Aurora\AAuth\Database\Seeders\SampleDataSeeder;
use Aurora\AAuth\Models\OrganizationScope;
use Aurora\AAuth\Models\Role;
use Aurora\AAuth\Services\RolePermissionService;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    Artisan::call('migrate:fresh');
    $seeder = new SampleDataSeeder();
    $seeder->run();
    $this->service = new RolePermissionService();
});

test('can create a role', function () {
    $organizationScope = OrganizationScope::whereName('Root Scope')->first();

    $data = [
        'organization_scope_id' => $organizationScope->id,
        'type' => 'system',
        'name' => 'Created System Role 1',
        'status' => 'active',
    ];

    $createdRole = $this->service->createRole($data);

    $this->assertEquals($createdRole->name, $data['name']);

    $createdCount = Role::whereName($data['name'])->count();
    $this->assertEquals(1, $createdCount);


    $data = [
        'organization_scope_id' => $organizationScope->id,
        'type' => 'organization',
        'name' => 'Created Organization Role 1',
        'status' => 'active',
    ];

    $createdRole = $this->service->createRole($data);

    $this->assertEquals($createdRole->name, $data['name']);

    $createdCount = Role::whereName($data['name'])->count();
    $this->assertEquals(1, $createdCount);
});


test('can update a role', function () {
    // todo
    expect(true)->toBeTrue();
});

test('can delete a role', function () {
    // todo
    expect(true)->toBeTrue();
});
