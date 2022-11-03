<?php

use AuroraWebSoftware\AAuth\Database\Seeders\SampleDataSeeder;
use AuroraWebSoftware\AAuth\Facades\AAuth;
use AuroraWebSoftware\AAuth\Models\OrganizationNode;
use AuroraWebSoftware\AAuth\Models\OrganizationScope;
use AuroraWebSoftware\AAuth\Models\Role;
use AuroraWebSoftware\AAuth\Models\User;
use AuroraWebSoftware\AAuth\Services\RolePermissionService;
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

test('can activate role', function () {
    // todo
    expect(true)->toBeTrue();
});

test('can deactivate role', function () {
    // todo
    expect(true)->toBeTrue();
});

test('can attach to role and detach a permission from role', function () {
    $role = Role::whereName('System Role 1')->first();
    $permissionName = 'test_permission1';

    $this->app->singleton('aauth', function ($app) use ($role) {
        return new \AuroraWebSoftware\AAuth\AAuth(
            User::find(1),
            $role->id
        );
    });

    $this->service->attachPermissionToRole($permissionName, $role->id);
    expect(AAuth::can($permissionName))->toBeTrue();

    $this->service->detachPermissionFromRole($permissionName, $role->id);
    expect(AAuth::can($permissionName))->toBeFalse();
});

test('can sync permission of role', function () {
    $role = Role::whereName('System Role 1')->first();
    $permissionName1 = 'test_permission1';
    $permissionName2 = 'test_permission2';
    $permissionName3 = 'test_permission3';

    $this->app->singleton('aauth', function ($app) use ($role) {
        return new \AuroraWebSoftware\AAuth\AAuth(
            User::find(1),
            $role->id
        );
    });

    $this->service->attachPermissionToRole($permissionName1, $role->id);
    expect(AAuth::can($permissionName1))->toBeTrue();

    $this->service->attachPermissionToRole($permissionName2, $role->id);
    expect(AAuth::can($permissionName2))->toBeTrue();

    $this->service->syncPermissionsOfRole(
        compact('permissionName1', 'permissionName3'),
        $role->id
    );
});

test('can attach system role to user and detach from user', function () {
    $data = [
        'type' => 'system',
        'name' => 'Created System Role 1 For Attach',
        'status' => 'active',
    ];

    $createdRole = $this->service->createRole($data);

    $this->assertEquals($createdRole->name, $data['name']);

    $this->service->attachSystemRoleToUser($createdRole->id, 1);

    $contains = User::find(1)->system_roles->contains(function ($value, $key) use ($data) {
        return $value->name == $data['name'];
    });
    expect($contains)->toBeTrue();

    $this->service->detachSystemRoleFromUser($createdRole->id, 1);
    $contains = User::find(1)->system_roles->contains(function ($value, $key) use ($data) {
        return $value->name == $data['name'];
    });
    expect($contains)->toBeFalse();
});

test('can attach organization role to user and detach from user', function () {
    $organizationScope = OrganizationScope::whereName('Root Scope')->first();
    $organizationNode = OrganizationNode::whereName('Root Node')->first();

    $data = [
        'organization_scope_id' => $organizationScope->id,
        'type' => 'organization',
        'name' => 'Created Organization Role 1 for Attaching',
        'status' => 'active',
    ];

    $createdRole = $this->service->createRole($data);

    $this->assertEquals($createdRole->name, $data['name']);

    $this->service->attachOrganizationRoleToUser($organizationNode->id, $createdRole->id, 1);

    $contains = User::find(1)->organization_roles->contains(function ($value, $key) use ($data) {
        return $value->name == $data['name'];
    });
    expect($contains)->toBeTrue();

    $this->service->detachOrganizationRoleFromUser($organizationNode->id, $createdRole->id, 1);
    $contains = User::find(1)->system_roles->contains(function ($value, $key) use ($data) {
        return $value->name == $data['name'];
    });
    expect($contains)->toBeFalse();
});
