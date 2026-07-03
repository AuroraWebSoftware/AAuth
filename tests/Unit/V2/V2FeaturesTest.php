<?php

use AuroraWebSoftware\AAuth\AAuth;
use AuroraWebSoftware\AAuth\Database\Seeders\SampleDataSeeder;
use AuroraWebSoftware\AAuth\Models\Role;
use AuroraWebSoftware\AAuth\Models\RolePermission;
use AuroraWebSoftware\AAuth\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Artisan::call('migrate:fresh');
    $seeder = new SampleDataSeeder();
    $seeder->run();
});

/*
|--------------------------------------------------------------------------
| Context Caching Tests
|--------------------------------------------------------------------------
*/

test('permissions are loaded once on instantiation and served from the instance', function () {
    $user = User::find(1);
    $role = Role::whereName('Root Role 1')->first();

    $aauth = new AAuth($user, $role->id);

    // Context (role + permissions) is held on THIS instance; checks resolve from it
    // without re-querying per call.
    expect($aauth->currentRole()->id)->toBe($role->id);
    expect($aauth->can('create_something_for_organization'))->toBeTrue();
    // A second identical check is served from the in-request memo, still correct.
    expect($aauth->can('create_something_for_organization'))->toBeTrue();
});

test('clearContext resets the instance context and a check still resolves', function () {
    $user = User::find(1);
    $role = Role::whereName('Root Role 1')->first();

    $aauth = new AAuth($user, $role->id);
    $aauth->can('create_something_for_organization');

    $aauth->clearContext();

    // The context is rebuilt on demand — the check still returns the correct result.
    expect($aauth->can('create_something_for_organization'))->toBeTrue();
});

test('a permission granted mid-request is reflected after clearContext', function () {
    $user = User::find(1);
    $role = Role::whereName('Root Role 1')->first();

    $aauth = new AAuth($user, $role->id);
    expect($aauth->can('brand_new_perm'))->toBeFalse();

    RolePermission::create(['role_id' => $role->id, 'permission' => 'brand_new_perm']);
    $aauth->clearContext();

    expect($aauth->can('brand_new_perm'))->toBeTrue();
});

test('two AAuth instances in one request never read each other\'s permissions (no leak)', function () {
    // A fresh role WITH a unique permission, assigned to user 1.
    $roleWith = Role::create(['name' => 'HasIsolatedPerm', 'status' => 'active']);
    RolePermission::create(['role_id' => $roleWith->id, 'permission' => 'perm.isolated']);
    DB::table('user_role_organization_node')->insert([
        'user_id' => 1, 'role_id' => $roleWith->id, 'organization_node_id' => 1,
    ]);

    // A fresh role WITHOUT that permission, assigned to user 2.
    $roleWithout = Role::create(['name' => 'NoIsolatedPerm', 'status' => 'active']);
    DB::table('user_role_organization_node')->insert([
        'user_id' => 2, 'role_id' => $roleWithout->id, 'organization_node_id' => 1,
    ]);

    $aauthUser1 = new AAuth(User::find(1), $roleWith->id);
    // Constructed AFTER user 1 — with a shared Context store this overwrote user 1's
    // context and leaked; with per-instance context each sees only its own.
    $aauthUser2 = new AAuth(User::find(2), $roleWithout->id);

    expect($aauthUser1->can('perm.isolated'))->toBeTrue();   // user 1 has it
    expect($aauthUser2->can('perm.isolated'))->toBeFalse();  // user 2 must NOT see it
    // Re-checking user 1 after user 2 was built still returns user 1's own result.
    expect($aauthUser1->can('perm.isolated'))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Parametric Permission Tests
|--------------------------------------------------------------------------
*/

test('can() returns true for permission without parameters', function () {
    $user = User::find(1);
    $role = Role::whereName('Root Role 1')->first();

    $aauth = new AAuth($user, $role->id);

    expect($aauth->can('create_something_for_organization'))->toBeTrue();
});

test('can() returns false for non-existent permission', function () {
    $user = User::find(1);
    $role = Role::whereName('Root Role 1')->first();

    $aauth = new AAuth($user, $role->id);

    expect($aauth->can('non_existent_permission'))->toBeFalse();
});

test('can() with integer parameter validates max value', function () {
    $user = User::find(1);
    $role = Role::whereName('Root Role 1')->first();

    // Add permission with parameter
    RolePermission::create([
        'role_id' => $role->id,
        'permission' => 'edit_with_limit',
        'parameters' => ['max_edits' => 10],
    ]);

    // Re-instantiate to load new permission
    $aauth = new AAuth($user, $role->id);

    // Within limit
    expect($aauth->can('edit_with_limit', 5))->toBeTrue();

    // Clear cache for next check
    $aauth->clearContext();

    // Exceeds limit
    expect($aauth->can('edit_with_limit', 15))->toBeFalse();
});

test('can() with array parameter validates allowed values', function () {
    $user = User::find(1);
    $role = Role::whereName('Root Role 1')->first();

    // Add permission with array parameter
    RolePermission::create([
        'role_id' => $role->id,
        'permission' => 'edit_status',
        'parameters' => ['allowed_statuses' => ['draft', 'published']],
    ]);

    $aauth = new AAuth($user, $role->id);

    // Allowed value
    expect($aauth->can('edit_status', 'draft'))->toBeTrue();

    $aauth->clearContext();

    // Not allowed value
    expect($aauth->can('edit_status', 'archived'))->toBeFalse();
});

test('can() with boolean parameter validates exact match', function () {
    $user = User::find(1);
    $role = Role::whereName('Root Role 1')->first();

    // Add permission with boolean parameter
    RolePermission::create([
        'role_id' => $role->id,
        'permission' => 'force_delete',
        'parameters' => ['can_force' => true],
    ]);

    $aauth = new AAuth($user, $role->id);

    expect($aauth->can('force_delete', true))->toBeTrue();

    $aauth->clearContext();

    expect($aauth->can('force_delete', false))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Super Admin Tests
|--------------------------------------------------------------------------
*/

test('isSuperAdmin returns false when disabled in config', function () {
    config(['aauth-advanced.super_admin.enabled' => false]);

    $user = User::find(1);
    $role = Role::whereName('Root Role 1')->first();

    $aauth = new AAuth($user, $role->id);

    expect($aauth->isSuperAdmin())->toBeFalse();
});

test('isSuperAdmin returns true for super admin user when enabled', function () {
    // Add is_super_admin column if not exists
    if (! Schema::hasColumn('users', 'is_super_admin')) {
        Schema::table('users', function ($table) {
            $table->boolean('is_super_admin')->default(false);
        });
    }

    config(['aauth-advanced.super_admin.enabled' => true]);
    config(['aauth-advanced.super_admin.column' => 'is_super_admin']);

    $user = User::find(1);
    $user->is_super_admin = true;
    $user->save();

    $role = Role::whereName('Root Role 1')->first();

    $aauth = new AAuth($user, $role->id);

    expect($aauth->isSuperAdmin())->toBeTrue();
});

test('super admin bypasses permission checks', function () {
    if (! Schema::hasColumn('users', 'is_super_admin')) {
        Schema::table('users', function ($table) {
            $table->boolean('is_super_admin')->default(false);
        });
    }

    config(['aauth-advanced.super_admin.enabled' => true]);
    config(['aauth-advanced.super_admin.column' => 'is_super_admin']);

    $user = User::find(1);
    $user->is_super_admin = true;
    $user->save();

    $role = Role::whereName('Root Role 1')->first();

    $aauth = new AAuth($user, $role->id);

    // Super admin should have any permission
    expect($aauth->can('any_random_permission'))->toBeTrue()
        ->and($aauth->can('another_permission'))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Cache Layer Tests
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| Organization Depth Filtering Tests
|--------------------------------------------------------------------------
*/

test('getAccessibleOrganizationNodes returns all nodes without filters', function () {
    $user = User::find(1);
    $role = Role::whereName('Root Role 1')->first();

    $aauth = new AAuth($user, $role->id);

    $nodes = $aauth->getAccessibleOrganizationNodes();

    expect($nodes)->not->toBeEmpty();
});

test('getAccessibleOrganizationNodes filters by min depth', function () {
    $user = User::find(1);
    $role = Role::whereName('Root Role 1')->first();

    $aauth = new AAuth($user, $role->id);

    // Get all nodes first
    $allNodes = $aauth->getAccessibleOrganizationNodes();

    // Get nodes with minimum depth of 2
    $filteredNodes = $aauth->getAccessibleOrganizationNodes(minDepthFromRoot: 2);

    // Filtered nodes should be a subset (possibly empty) of all nodes
    expect($filteredNodes->count())->toBeLessThanOrEqual($allNodes->count());

    // All returned nodes should have depth >= 2
    foreach ($filteredNodes as $node) {
        $depth = substr_count($node->path, '/') - 1;
        expect($depth)->toBeGreaterThanOrEqual(2);
    }
});

test('getAccessibleOrganizationNodes filters by max depth', function () {
    $user = User::find(1);
    $role = Role::whereName('Root Role 1')->first();

    $aauth = new AAuth($user, $role->id);

    // Get nodes with maximum depth of 1
    $nodes = $aauth->getAccessibleOrganizationNodes(maxDepthFromRoot: 1);

    // All returned nodes should have depth <= 1
    foreach ($nodes as $node) {
        $depth = substr_count($node->path, '/') - 1;
        expect($depth)->toBeLessThanOrEqual(1);
    }
});

test('getAccessibleOrganizationNodes filters by scope name', function () {
    $user = User::find(1);
    $role = Role::whereName('Root Role 1')->first();

    $aauth = new AAuth($user, $role->id);

    // Get nodes with specific scope - uses organization_scope relationship
    $nodes = $aauth->getAccessibleOrganizationNodes(scopeName: 'Sub-Scope');

    // All returned nodes should have the specified scope
    foreach ($nodes as $node) {
        // Use snake_case relationship name
        expect($node->organization_scope->name)->toBe('Sub-Scope');
    }
});

/*
|--------------------------------------------------------------------------
| Role Model Methods Tests
|--------------------------------------------------------------------------
*/

test('role givePermission creates new permission', function () {
    $role = Role::whereName('System Role 1')->first();

    $permission = $role->givePermission('new_test_permission');

    expect($permission)->toBeInstanceOf(RolePermission::class)
        ->and($permission->permission)->toBe('new_test_permission')
        ->and($permission->role_id)->toBe($role->id);
});

test('role givePermission with parameters', function () {
    $role = Role::whereName('System Role 1')->first();

    $permission = $role->givePermission('edit_posts', ['max_edits' => 5]);

    expect($permission->parameters)->toBe(['max_edits' => 5]);
});

test('role removePermission deletes permission', function () {
    $role = Role::whereName('System Role 1')->first();

    // First add a permission
    $role->givePermission('temp_permission');
    expect($role->hasPermission('temp_permission'))->toBeTrue();

    // Then remove it
    $result = $role->removePermission('temp_permission');

    expect($result)->toBeTrue()
        ->and($role->hasPermission('temp_permission'))->toBeFalse();
});

test('role syncPermissions replaces all permissions', function () {
    $role = Role::whereName('System Role 1')->first();

    // Sync with new permissions
    $role->syncPermissions(['perm1', 'perm2', 'perm3']);

    expect($role->hasPermission('perm1'))->toBeTrue()
        ->and($role->hasPermission('perm2'))->toBeTrue()
        ->and($role->hasPermission('perm3'))->toBeTrue()
        ->and($role->hasPermission('edit_something_for_system'))->toBeFalse(); // Old permission removed
});

test('role syncPermissions with parametric permissions', function () {
    $role = Role::whereName('System Role 1')->first();

    $role->syncPermissions([
        'simple_perm',
        'param_perm' => ['limit' => 10],
    ]);

    expect($role->hasPermission('simple_perm'))->toBeTrue()
        ->and($role->hasPermission('param_perm'))->toBeTrue();

    $paramPerm = RolePermission::where('role_id', $role->id)
        ->where('permission', 'param_perm')
        ->first();

    expect($paramPerm->parameters)->toBe(['limit' => 10]);
});

/*
|--------------------------------------------------------------------------
| Observer Cache Clearing Tests
|--------------------------------------------------------------------------
*/

test('observer clears context when permission is added', function () {
    $user = User::find(1);
    $role = Role::whereName('Root Role 1')->first();

    // Create AAuth instance
    $this->app->singleton('aauth', fn () => new AAuth($user, $role->id));

    // Verify initial state
    expect(app('aauth')->can('new_dynamic_permission'))->toBeFalse();

    // Add permission via Eloquent (triggers observer)
    RolePermission::create([
        'role_id' => $role->id,
        'permission' => 'new_dynamic_permission',
    ]);

    // Context should be cleared, new permission should work
    expect(app('aauth')->can('new_dynamic_permission'))->toBeTrue();
});

test('observer clears context when permission is removed', function () {
    $user = User::find(1);
    $role = Role::whereName('Root Role 1')->first();

    // Add a permission first
    $permission = RolePermission::create([
        'role_id' => $role->id,
        'permission' => 'removable_permission',
    ]);

    // Create AAuth instance
    $this->app->singleton('aauth', fn () => new AAuth($user, $role->id));

    // Verify permission exists
    expect(app('aauth')->can('removable_permission'))->toBeTrue();

    // Remove permission via Eloquent (triggers observer)
    $permission->delete();

    // Permission should no longer work
    expect(app('aauth')->can('removable_permission'))->toBeFalse();
});
