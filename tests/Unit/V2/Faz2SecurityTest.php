<?php

use AuroraWebSoftware\AAuth\AAuth;
use AuroraWebSoftware\AAuth\Database\Seeders\SampleDataSeeder;
use AuroraWebSoftware\AAuth\Exceptions\UserHasNoAssignedRoleException;
use AuroraWebSoftware\AAuth\Models\Role;
use AuroraWebSoftware\AAuth\Models\User;
use AuroraWebSoftware\AAuth\Services\RolePermissionService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    Artisan::call('migrate:fresh');
    (new SampleDataSeeder())->run();
});

// S1 — parametric permission enforcement is FAIL-CLOSED (positional).
it('S1: parametric permission fails closed', function () {
    $role = Role::create(['type' => 'system', 'name' => 'P', 'status' => 'active']);
    $role->givePermission('approve', ['max_amount' => 1000]);
    DB::table('user_role_organization_node')->insert(['user_id' => 1, 'role_id' => $role->id]);
    $this->app->singleton('aauth', fn ($app) => new AAuth(User::find(1), $role->id));

    $aauth = app('aauth');
    expect($aauth->can('approve', 500))->toBeTrue();    // within limit
    expect($aauth->can('approve', 1500))->toBeFalse();  // over limit
    expect($aauth->can('approve'))->toBeFalse();        // no arg on a parametric perm → deny
    expect($aauth->can('approve', 'abc'))->toBeFalse(); // non-numeric vs int → deny
});

// S2 — a deactivated (passive) role can no longer be selected.
it('S2: a deactivated role is rejected on the next request', function () {
    app(RolePermissionService::class)->deactivateRole(3);

    expect(fn () => new AAuth(User::find(1), 3))
        ->toThrow(UserHasNoAssignedRoleException::class);
});

// S5 — privilege columns are not mass-assignable.
it('S5: type/organization_scope_id cannot be mass-assigned', function () {
    $raw = Role::create(['name' => 'X', 'status' => 'active', 'type' => 'system', 'organization_scope_id' => 1]);
    expect($raw->type)->toBeNull();
    expect($raw->organization_scope_id)->toBeNull();

    // The authorized service still sets them explicitly.
    $viaService = app(RolePermissionService::class)->createRole(
        ['name' => 'ValidRole', 'type' => 'organization', 'organization_scope_id' => 1, 'status' => 'active']
    );
    expect($viaService->type)->toBe('organization');
    expect($viaService->organization_scope_id)->toBe(1);
});

// S6 — descendant() is anchored to the '/' separator (no numeric-prefix sibling match).
it('S6: descendant does not match a numeric-prefix sibling', function () {
    $this->app->singleton('aauth', fn ($app) => new AAuth(User::find(1), 3)); // root role
    $aauth = app('aauth');

    // Sibling-prefix node: path '1/20' shares the prefix "1/2" but is NOT under node 2 ('1/2').
    $siblingId = DB::table('organization_nodes')->insertGetId([
        'organization_scope_id' => 2, 'name' => 'sibling', 'path' => '1/20', 'parent_id' => 1,
    ]);

    expect($aauth->descendant(2, $siblingId))->toBeFalse(); // '1/20' is NOT a descendant of '1/2'
    expect($aauth->descendant(2, 4))->toBeTrue();           // '1/2/4' IS a descendant of '1/2'
});

// S1 (string constraint) — a string-valued parameter enforces exact match, fail-closed.
it('S1: string parametric constraint enforces exact match', function () {
    $role = Role::create(['type' => 'system', 'name' => 'Q', 'status' => 'active']);
    $role->givePermission('export', ['region' => 'EU']);
    DB::table('user_role_organization_node')->insert(['user_id' => 1, 'role_id' => $role->id]);
    $this->app->singleton('aauth', fn ($app) => new AAuth(User::find(1), $role->id));

    $aauth = app('aauth');
    expect($aauth->can('export', 'EU'))->toBeTrue();
    expect($aauth->can('export', 'US'))->toBeFalse(); // wrong value
    expect($aauth->can('export'))->toBeFalse();       // no arg on a parametric perm
});

// S2 (switchable) — a deactivated role is excluded from the switchable list.
it('S2: a deactivated role is excluded from switchableRoles', function () {
    app(RolePermissionService::class)->deactivateRole(1); // system role assigned to user 1
    $this->app->singleton('aauth', fn ($app) => new AAuth(User::find(1), 3)); // stay on an active role

    $ids = collect(app('aauth')->switchableRoles())->pluck('id')->all();
    expect($ids)->not->toContain(1);
});
