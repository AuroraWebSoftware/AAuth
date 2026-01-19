<?php

use AuroraWebSoftware\AAuth\AAuth;
use AuroraWebSoftware\AAuth\Database\Seeders\SampleDataSeeder;
use AuroraWebSoftware\AAuth\Models\Role;
use AuroraWebSoftware\AAuth\Models\RolePermission;
use AuroraWebSoftware\AAuth\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Context;
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

test('context is loaded and cached on AAuth instantiation', function () {
    $user = User::find(1);
    $role = Role::whereName('Root Role 1')->first();

    $aauth = new AAuth($user, $role->id);

    // Context should be stored
    $context = Context::getHidden('aauth_context');

    expect($context)->not->toBeNull()
        ->and($context['user_id'])->toBe($user->id)
        ->and($context['role_id'])->toBe($role->id)
        ->and($context['permissions'])->toBeArray();
});

test('clearContext clears both request cache and context', function () {
    $user = User::find(1);
    $role = Role::whereName('Root Role 1')->first();

    $aauth = new AAuth($user, $role->id);

    // Call can() to populate request cache
    $aauth->can('create_something_for_organization');

    // Clear context
    $aauth->clearContext();

    // Context should be null
    $context = Context::getHidden('aauth_context');
    expect($context)->toBeNull();
});

test('context is reloaded after clearContext when can() is called', function () {
    $user = User::find(1);
    $role = Role::whereName('Root Role 1')->first();

    $aauth = new AAuth($user, $role->id);

    // Clear context
    $aauth->clearContext();

    // Call can() - should reload context
    $result = $aauth->can('create_something_for_organization');

    // Context should be restored
    $context = Context::getHidden('aauth_context');
    expect($context)->not->toBeNull()
        ->and($result)->toBeTrue();
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

test('role is cached when cache is enabled', function () {
    config(['aauth-advanced.cache.enabled' => true]);
    config(['aauth-advanced.cache.ttl' => 3600]);
    config(['aauth-advanced.cache.prefix' => 'aauth_test']);

    $user = User::find(1);
    $role = Role::whereName('Root Role 1')->first();

    // Clear any existing cache
    Cache::forget("aauth_test:role:{$role->id}");

    $aauth = new AAuth($user, $role->id);

    // Role should be cached
    $cachedRole = Cache::get("aauth_test:role:{$role->id}");
    expect($cachedRole)->not->toBeNull()
        ->and($cachedRole->id)->toBe($role->id);
});

test('switchable roles are cached when cache is enabled', function () {
    config(['aauth-advanced.cache.enabled' => true]);
    config(['aauth-advanced.cache.ttl' => 3600]);
    config(['aauth-advanced.cache.prefix' => 'aauth_test']);

    $user = User::find(1);
    $role = Role::whereName('Root Role 1')->first();

    // Clear any existing cache
    Cache::forget("aauth_test:user:{$user->id}:switchable_roles");

    $aauth = new AAuth($user, $role->id);
    $aauth->switchableRoles();

    // Switchable roles should be cached
    $cachedRoles = Cache::get("aauth_test:user:{$user->id}:switchable_roles");
    expect($cachedRoles)->not->toBeNull();
});

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

test('role isGlobal and isOrganizational methods', function () {
    $systemRole = Role::whereName('System Role 1')->first();
    $orgRole = Role::whereName('Root Role 1')->first();

    expect($systemRole->isGlobal())->toBeTrue()
        ->and($systemRole->isOrganizational())->toBeFalse()
        ->and($orgRole->isGlobal())->toBeFalse()
        ->and($orgRole->isOrganizational())->toBeTrue();
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
