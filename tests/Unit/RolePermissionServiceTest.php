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
    $organizationScope = OrganizationScope::whereName('Root Scope')->first();

    $created = $this->service->createRole([
        'organization_scope_id' => $organizationScope->id,
        'type' => 'organization',
        'name' => 'Role For Update',
        'status' => 'active',
    ]);

    $updated = $this->service->updateRole(['name' => 'Role Renamed'], $created->id);

    expect($updated)->not->toBeNull()
        ->and($updated->name)->toBe('Role Renamed')
        ->and(Role::find($created->id)->name)->toBe('Role Renamed');
});

test('can delete a role', function () {
    $created = $this->service->createRole([
        'type' => 'system',
        'name' => 'Role For Delete',
        'status' => 'active',
    ]);

    $result = $this->service->deleteRole($created->id);

    expect($result)->toBeTrue()
        ->and(Role::find($created->id))->toBeNull();
});

test('can activate role', function () {
    $created = $this->service->createRole([
        'type' => 'system',
        'name' => 'Role For Activate',
        'status' => 'passive',
    ]);

    $result = $this->service->activateRole($created->id);

    expect($result)->toBeTrue()
        ->and(Role::find($created->id)->status)->toBe('active');
});

test('can deactivate role', function () {
    $created = $this->service->createRole([
        'type' => 'system',
        'name' => 'Role For Deactivate',
        'status' => 'active',
    ]);

    $result = $this->service->deactivateRole($created->id);

    expect($result)->toBeTrue()
        ->and(Role::find($created->id)->status)->toBe('passive');
});

test('detachOrganizationRoleFromUserBy removes correct row with aligned-order args', function () {
    $organizationScope = OrganizationScope::whereName('Root Scope')->first();
    $organizationNode = OrganizationNode::whereName('Root Node')->first();

    $role = $this->service->createRole([
        'organization_scope_id' => $organizationScope->id,
        'type' => 'organization',
        'name' => 'Detach By Test Role',
        'status' => 'active',
    ]);

    $this->service->attachOrganizationRoleToUser($organizationNode->id, $role->id, 1);

    $pivotBefore = \Illuminate\Support\Facades\DB::table('user_role_organization_node')
        ->where(['user_id' => 1, 'role_id' => $role->id, 'organization_node_id' => $organizationNode->id])
        ->count();
    expect($pivotBefore)->toBe(1);

    $deleted = $this->service->detachOrganizationRoleFromUserBy($organizationNode->id, $role->id, 1);

    expect($deleted)->toBe(1);

    $pivotAfter = \Illuminate\Support\Facades\DB::table('user_role_organization_node')
        ->where(['user_id' => 1, 'role_id' => $role->id, 'organization_node_id' => $organizationNode->id])
        ->count();
    expect($pivotAfter)->toBe(0);
});

test('legacy detachOrganizationRoleFromUser still works with historic param order', function () {
    $organizationScope = OrganizationScope::whereName('Root Scope')->first();
    $organizationNode = OrganizationNode::whereName('Root Node')->first();

    $role = $this->service->createRole([
        'organization_scope_id' => $organizationScope->id,
        'type' => 'organization',
        'name' => 'Detach Legacy Test Role',
        'status' => 'active',
    ]);

    $this->service->attachOrganizationRoleToUser($organizationNode->id, $role->id, 1);

    // Historic signature: ($userId, $roleId, $organizationNodeId)
    $deleted = $this->service->detachOrganizationRoleFromUser(1, $role->id, $organizationNode->id);

    expect($deleted)->toBe(1);

    $pivotAfter = \Illuminate\Support\Facades\DB::table('user_role_organization_node')
        ->where(['user_id' => 1, 'role_id' => $role->id, 'organization_node_id' => $organizationNode->id])
        ->count();
    expect($pivotAfter)->toBe(0);
});

test('legacy detachOrganizationRoleFromUser does not emit a runtime deprecation notice', function () {
    $organizationScope = OrganizationScope::whereName('Root Scope')->first();
    $organizationNode = OrganizationNode::whereName('Root Node')->first();

    $role = $this->service->createRole([
        'organization_scope_id' => $organizationScope->id,
        'type' => 'organization',
        'name' => 'Detach Quiet Test Role',
        'status' => 'active',
    ]);

    $this->service->attachOrganizationRoleToUser($organizationNode->id, $role->id, 1);

    $notices = [];
    $prev = set_error_handler(function ($severity, $message) use (&$notices) {
        if ($severity === E_USER_DEPRECATED || $severity === E_DEPRECATED) {
            $notices[] = $message;
        }

        return false; // let default handler also run for non-deprecated
    });

    try {
        $this->service->detachOrganizationRoleFromUser(1, $role->id, $organizationNode->id);
    } finally {
        set_error_handler($prev);
    }

    expect($notices)->toBeEmpty();
});

test('detachOrganizationRoleFromUserBy throws InvalidUserException for unknown user', function () {
    $organizationScope = OrganizationScope::whereName('Root Scope')->first();
    $organizationNode = OrganizationNode::whereName('Root Node')->first();

    $role = $this->service->createRole([
        'organization_scope_id' => $organizationScope->id,
        'type' => 'organization',
        'name' => 'Validation Test Role',
        'status' => 'active',
    ]);

    $this->service->detachOrganizationRoleFromUserBy($organizationNode->id, $role->id, 99999);
})->throws(\AuroraWebSoftware\AAuth\Exceptions\InvalidUserException::class);

test('detachOrganizationRoleFromUserBy throws InvalidRoleException for unknown organization role', function () {
    $organizationNode = OrganizationNode::whereName('Root Node')->first();

    $this->service->detachOrganizationRoleFromUserBy($organizationNode->id, 99999, 1);
})->throws(\AuroraWebSoftware\AAuth\Exceptions\InvalidRoleException::class);

test('detachOrganizationRoleFromUserBy throws InvalidOrganizationNodeException for unknown node', function () {
    $organizationScope = OrganizationScope::whereName('Root Scope')->first();

    $role = $this->service->createRole([
        'organization_scope_id' => $organizationScope->id,
        'type' => 'organization',
        'name' => 'Validation Node Test Role',
        'status' => 'active',
    ]);

    $this->service->detachOrganizationRoleFromUserBy(99999, $role->id, 1);
})->throws(\AuroraWebSoftware\AAuth\Exceptions\InvalidOrganizationNodeException::class);

test('detachOrganizationRoleFromUserBy clears user role cache when enabled', function () {
    config(['aauth-advanced.cache.enabled' => true]);
    config(['aauth-advanced.cache.prefix' => 'aauth']);

    $organizationScope = OrganizationScope::whereName('Root Scope')->first();
    $organizationNode = OrganizationNode::whereName('Root Node')->first();

    $role = $this->service->createRole([
        'organization_scope_id' => $organizationScope->id,
        'type' => 'organization',
        'name' => 'Cache Test Detach Role',
        'status' => 'active',
    ]);

    $this->service->attachOrganizationRoleToUser($organizationNode->id, $role->id, 1);

    // Prime the cache
    \Illuminate\Support\Facades\Cache::put('aauth:user:1:switchable_roles', ['sentinel'], 60);
    expect(\Illuminate\Support\Facades\Cache::get('aauth:user:1:switchable_roles'))->toBe(['sentinel']);

    $this->service->detachOrganizationRoleFromUserBy($organizationNode->id, $role->id, 1);

    // The detach must have invalidated the cache key
    expect(\Illuminate\Support\Facades\Cache::get('aauth:user:1:switchable_roles'))->toBeNull();
});

test('both detach methods coexist on the service class', function () {
    $reflection = new \ReflectionClass(RolePermissionService::class);

    expect($reflection->hasMethod('detachOrganizationRoleFromUser'))->toBeTrue();
    expect($reflection->hasMethod('detachOrganizationRoleFromUserBy'))->toBeTrue();

    $legacy = $reflection->getMethod('detachOrganizationRoleFromUser');
    $canonical = $reflection->getMethod('detachOrganizationRoleFromUserBy');

    expect($legacy->getNumberOfParameters())->toBe(3);
    expect($canonical->getNumberOfParameters())->toBe(3);

    // Verify legacy has @deprecated in its docblock
    expect($legacy->getDocComment())->toContain('@deprecated');
    expect($canonical->getDocComment())->not->toContain('@deprecated');

    // Verify aligned-order parameter names on the canonical method
    $params = $canonical->getParameters();
    expect($params[0]->getName())->toBe('organizationNodeId');
    expect($params[1]->getName())->toBe('roleId');
    expect($params[2]->getName())->toBe('userId');
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
    Context::forget('role_permissions');
    expect(AAuth::can($permissionName))->toBeTrue();

    $this->service->detachPermissionFromRole($permissionName, $role->id);
    Context::forget('role_permissions');
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
    Context::forget('role_permissions');
    expect(AAuth::can($permissionName1))->toBeTrue();

    $this->service->attachPermissionToRole($permissionName2, $role->id);
    Context::forget('role_permissions');
    expect(AAuth::can($permissionName2))->toBeTrue();

    $this->service->syncPermissionsOfRole(
        compact('permissionName1', 'permissionName3'),
        $role->id
    );
    Context::forget('role_permissions');
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
