<?php

use AuroraWebSoftware\AAuth\AAuth;
use AuroraWebSoftware\AAuth\Database\Seeders\SampleDataSeeder;
use AuroraWebSoftware\AAuth\Models\Role;
use AuroraWebSoftware\AAuth\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Artisan::call('migrate:fresh');
    $seeder = new SampleDataSeeder();
    $seeder->run();

    // Add panel_id column to roles if not exists
    if (! Schema::hasColumn('roles', 'panel_id')) {
        Schema::table('roles', function ($table) {
            $table->string('panel_id')->nullable()->after('status');
        });
    }
});

/*
|--------------------------------------------------------------------------
| Panel Support Tests
|--------------------------------------------------------------------------
*/

test('AAuth can be created with panel context', function () {
    $user = User::find(1);
    $role = Role::whereName('Root Role 1')->first();

    $aauth = new AAuth($user, $role->id, 'admin');

    expect($aauth->getCurrentPanel())->toBe('admin')
        ->and($aauth->isInPanel('admin'))->toBeTrue()
        ->and($aauth->isInPanel('user'))->toBeFalse();
});

test('forPanel static method creates AAuth with panel', function () {
    $user = User::find(1);
    $role = Role::whereName('Root Role 1')->first();

    $aauth = AAuth::forPanel($user, $role->id, 'admin');

    expect($aauth->getCurrentPanel())->toBe('admin');
});

test('getPanelId returns role panel_id from database', function () {
    $user = User::find(1);
    $role = Role::whereName('Root Role 1')->first();
    $role->panel_id = 'admin';
    $role->save();

    $aauth = new AAuth($user, $role->id);

    expect($aauth->getPanelId())->toBe('admin');
});

test('getPanelId returns null when role has no panel_id', function () {
    $user = User::find(1);
    $role = Role::whereName('Root Role 1')->first();
    $role->panel_id = null;
    $role->save();

    $aauth = new AAuth($user, $role->id);

    expect($aauth->getPanelId())->toBeNull();
});

test('switchableRolesForPanel filters roles by panel_id', function () {
    $user = User::find(1);
    $role = Role::whereName('Root Role 1')->first();

    // Assign panel_id to some roles
    Role::whereName('System Role 1')->update(['panel_id' => 'admin']);
    Role::whereName('System Role 2')->update(['panel_id' => 'user']);
    Role::whereName('Root Role 1')->update(['panel_id' => 'admin']);

    $aauth = new AAuth($user, $role->id);

    $adminRoles = $aauth->switchableRolesForPanel('admin');
    $userRoles = $aauth->switchableRolesForPanel('user');

    // Admin roles should include roles with panel_id='admin' or null
    foreach ($adminRoles as $r) {
        expect($r->panel_id)->toBeIn(['admin', null]);
    }

    // User roles should include roles with panel_id='user' or null
    foreach ($userRoles as $r) {
        expect($r->panel_id)->toBeIn(['user', null]);
    }
});

test('switchableRolesForPanelStatic returns roles for specific panel', function () {
    $user = User::find(1);

    // Assign panel_id to some roles
    Role::whereName('System Role 1')->update(['panel_id' => 'admin']);

    $roles = AAuth::switchableRolesForPanelStatic($user->id, 'admin');

    expect($roles)->not->toBeEmpty();
    foreach ($roles as $role) {
        expect($role->panel_id)->toBeIn(['admin', null]);
    }
});

test('role cache key includes panel_id when panel is set', function () {
    config(['aauth-advanced.cache.enabled' => true]);
    config(['aauth-advanced.cache.prefix' => 'aauth_test']);

    $user = User::find(1);
    $role = Role::whereName('Root Role 1')->first();

    // Clear cache
    \Illuminate\Support\Facades\Cache::forget("aauth_test:role:{$role->id}");
    \Illuminate\Support\Facades\Cache::forget("aauth_test:role:{$role->id}:panel:admin");

    // Create with panel
    $aauth = new AAuth($user, $role->id, 'admin');

    // Cache key should include panel
    $cachedRole = \Illuminate\Support\Facades\Cache::get("aauth_test:role:{$role->id}:panel:admin");
    expect($cachedRole)->not->toBeNull();
});

test('roles without panel_id are accessible from any panel', function () {
    $user = User::find(1);

    // Create a role without panel_id
    $globalRole = Role::create([
        'type' => 'system',
        'name' => 'Global System Role',
        'status' => 'active',
        'panel_id' => null,
    ]);

    // Assign to user
    \Illuminate\Support\Facades\DB::table('user_role_organization_node')->insert([
        'user_id' => $user->id,
        'role_id' => $globalRole->id,
    ]);

    // Should appear in both admin and user panel role lists
    $adminRoles = AAuth::switchableRolesForPanelStatic($user->id, 'admin');
    $userRoles = AAuth::switchableRolesForPanelStatic($user->id, 'user');

    $adminRoleIds = $adminRoles->pluck('id')->toArray();
    $userRoleIds = $userRoles->pluck('id')->toArray();

    expect($adminRoleIds)->toContain($globalRole->id)
        ->and($userRoleIds)->toContain($globalRole->id);
});

/*
|--------------------------------------------------------------------------
| Panel Blade Directive Tests
|--------------------------------------------------------------------------
*/

test('panel blade directive compiles correctly', function () {
    $directive = \Illuminate\Support\Facades\Blade::compileString("@panel('admin') content @endpanel");

    expect($directive)->toContain("isInPanel")
        ->and($directive)->toContain('admin');
});

test('aauth_panel_can condition works in panel context', function () {
    $user = User::find(1);
    $role = Role::whereName('Root Role 1')->first();

    $this->app->singleton('aauth', fn () => new AAuth($user, $role->id, 'admin'));

    // When in correct panel, permission check should work
    $result = app('aauth')->can('create_something_for_organization');
    expect($result)->toBeTrue();

    // isInPanel should return true for admin
    expect(app('aauth')->isInPanel('admin'))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Additional Panel Methods Tests (100% Coverage)
|--------------------------------------------------------------------------
*/

test('switchableRolesForCurrentPanel returns roles for current panel context', function () {
    $user = User::find(1);
    $role = Role::whereName('Root Role 1')->first();

    // Set panel_id on roles
    Role::whereName('System Role 1')->update(['panel_id' => 'admin']);
    Role::whereName('Root Role 1')->update(['panel_id' => 'admin']);

    // Create AAuth with panel context
    $aauth = new AAuth($user, $role->id, 'admin');

    // Get roles for current panel
    $roles = $aauth->switchableRolesForCurrentPanel();

    expect($roles)->not->toBeEmpty();
    foreach ($roles as $r) {
        expect($r->panel_id)->toBeIn(['admin', null]);
    }
});

test('switchableRolesForCurrentPanel uses panelId when set', function () {
    $user = User::find(1);
    $role = Role::whereName('Root Role 1')->first();

    $aauth = new AAuth($user, $role->id, 'dashboard');

    $roles = $aauth->switchableRolesForCurrentPanel();

    // Should return collection (may be empty if no dashboard roles)
    expect($roles)->toBeInstanceOf(\Illuminate\Support\Collection::class);
});

test('forCurrentPanel creates AAuth with auto-detected panel', function () {
    $user = User::find(1);
    $role = Role::whereName('Root Role 1')->first();

    // Without Filament context, panel will be null
    $aauth = AAuth::forCurrentPanel($user, $role->id);

    // Should create instance successfully
    expect($aauth)->toBeInstanceOf(AAuth::class)
        ->and($aauth->currentRole()->id)->toBe($role->id);
});

test('detectCurrentPanelId returns null when Filament not installed', function () {
    // Without Filament, should return null
    $panelId = AAuth::detectCurrentPanelId();

    expect($panelId)->toBeNull();
});

test('switchableRolesStatic returns all switchable roles for user', function () {
    $user = User::find(1);

    $roles = AAuth::switchableRolesStatic($user->id);

    expect($roles)->not->toBeEmpty()
        ->and($roles)->toBeIterable();

    // Should contain the roles assigned to user
    $roleNames = collect($roles)->pluck('name')->toArray();
    expect($roleNames)->toContain('Root Role 1');
});
