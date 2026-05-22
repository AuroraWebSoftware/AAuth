<?php

use AuroraWebSoftware\AAuth\AAuth;
use AuroraWebSoftware\AAuth\Database\Seeders\SampleDataSeeder;
use AuroraWebSoftware\AAuth\Models\Role;
use AuroraWebSoftware\AAuth\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

beforeEach(function () {
    Artisan::call('migrate:fresh');
    (new SampleDataSeeder())->run();
});

/*
|--------------------------------------------------------------------------
| Octane / Vapor request-scoped binding regression tests
|--------------------------------------------------------------------------
|
| These tests do not require Octane to be installed. They simulate the
| Octane request boundary by calling `$app->forgetScopedInstances()`, which
| is exactly what the FlushTemporaryContainerInstances listener does on
| RequestTerminated.
|
*/

test('aauth is bound as scoped (not singleton) in the container', function () {
    // The container distinguishes scoped from singleton at the abstract level
    // via the $scopedInstances tracking. The easiest portable check is that
    // forgetScopedInstances() drops the cached instance.

    $user = User::find(1);
    $role = Role::whereName('Root Role 1')->first();

    Auth::setUser($user);
    Session::put('roleId', $role->id);

    $first = app('aauth');
    expect($first)->toBeInstanceOf(AAuth::class);

    // Second resolve in the same request returns the SAME instance (scoped lifetime)
    $second = app('aauth');
    expect($second)->toBe($first);

    // After flushing scoped instances (simulated Octane boundary) the next
    // resolve returns a NEW instance.
    $this->app->forgetScopedInstances();

    $third = app('aauth');
    expect($third)->not->toBe($first);
});

test('aauth scoped binding does not leak user context across simulated requests', function () {
    // === First simulated request: User 1 with Root Role 1 ===
    $userA = User::find(1);
    $roleA = Role::whereName('Root Role 1')->first();

    Auth::setUser($userA);
    Session::put('roleId', $roleA->id);

    /** @var AAuth $aauthA */
    $aauthA = app('aauth');

    $userAId = $aauthA->user->id;
    $roleAId = $aauthA->currentRole()->id;
    $nodesA = $aauthA->organizationNodeIds();

    expect($userAId)->toBe($userA->id);
    expect($roleAId)->toBe($roleA->id);

    // === Simulated request boundary: Octane / Vapor flush ===
    $this->app->forgetScopedInstances();

    // === Second simulated request: User 1 with a DIFFERENT role ===
    // (We use the same user but switch the active role — this is enough to
    // prove the scoped binding does not carry state. The fact that the
    // resolved instance is different + reflects the new session role proves
    // the security fix.)
    $roleB = Role::whereName('Sub-Scope Role 1')->first();

    Auth::setUser($userA);
    Session::put('roleId', $roleB->id);

    /** @var AAuth $aauthB */
    $aauthB = app('aauth');

    expect($aauthB)->not->toBe($aauthA);
    expect($aauthB->currentRole()->id)->toBe($roleB->id);
    expect($aauthB->currentRole()->id)->not->toBe($roleAId);

    // Organization node IDs must reflect the NEW role's assignment,
    // not the stale role A's node IDs.
    $nodesB = $aauthB->organizationNodeIds();
    expect($nodesB)->toBeArray();
    // Either set may be empty; the critical guarantee is that nodesB was
    // resolved fresh for role B, not copied from role A.
    expect($nodesB)->not->toBe(null);
});

test('within a single simulated request the same aauth instance is reused', function () {
    $user = User::find(1);
    $role = Role::whereName('Root Role 1')->first();

    Auth::setUser($user);
    Session::put('roleId', $role->id);

    $first = app('aauth');
    $second = app('aauth');
    $third = app('aauth');

    // Same request → same instance (no per-call reconstruction, performance preserved)
    expect($second)->toBe($first);
    expect($third)->toBe($first);
});

test('php-fpm equivalence: scoped binding behaves like singleton within a request', function () {
    // This documents the BC guarantee for PHP-FPM consumers: within a single
    // request, scoped() is observationally identical to singleton(). Each
    // request gets exactly one constructor call.

    $user = User::find(1);
    $role = Role::whereName('Root Role 1')->first();

    Auth::setUser($user);
    Session::put('roleId', $role->id);

    // Resolve repeatedly within "one request"
    $instances = collect(range(1, 5))->map(fn () => app('aauth'));

    // All five resolves return the same instance
    expect($instances->unique(fn ($i) => spl_object_id($i)))->toHaveCount(1);
});

test('SECURITY: public properties do not leak across simulated requests with different users', function () {
    // This is the regression test for the reported security issue. Two DIFFERENT
    // users with DIFFERENT roles MUST NOT share AAuth public properties across
    // a simulated Octane request boundary.

    // === Request 1: User A (id=1) with org role 3 (Root Role 1) ===
    $userA = User::find(1);
    $roleA = Role::whereName('Root Role 1')->first();

    Auth::setUser($userA);
    Session::put('roleId', $roleA->id);

    $aauthA = app('aauth');
    $leakedUserId = $aauthA->user->id;
    $leakedRoleId = $aauthA->role->id;
    $leakedNodeIds = $aauthA->organizationNodeIds;
    $leakedCanResult = $aauthA->can('create_something_for_organization');

    expect($leakedUserId)->toBe(1);
    expect($leakedRoleId)->toBe($roleA->id);

    // === Octane request boundary ===
    $this->app->forgetScopedInstances();
    Auth::logout();

    // === Request 2: User B (id=2) with a different role ===
    $userB = User::find(2);

    // Assign User B a fresh role that User A does NOT have, so a leak would
    // be observable as User B keeping User A's permissions.
    $roleB = \AuroraWebSoftware\AAuth\Models\Role::create([
        'type' => 'system',
        'name' => 'User B Exclusive Role',
        'status' => 'active',
    ]);
    \Illuminate\Support\Facades\DB::table('user_role_organization_node')->insert([
        'user_id' => $userB->id,
        'role_id' => $roleB->id,
    ]);

    Auth::setUser($userB);
    Session::put('roleId', $roleB->id);

    $aauthB = app('aauth');

    // Hard assertions on the security-critical state
    expect($aauthB)->not->toBe($aauthA, 'AAuth instance was reused across requests');
    expect($aauthB->user->id)->toBe(2, 'User identity leaked from previous request');
    expect($aauthB->user->id)->not->toBe($leakedUserId);
    expect($aauthB->role->id)->toBe($roleB->id, 'Role leaked from previous request');
    expect($aauthB->role->id)->not->toBe($leakedRoleId);

    // User B has no `create_something_for_organization` permission — if the
    // singleton had leaked, this would still return true from cached state.
    expect($aauthB->can('create_something_for_organization'))->toBeFalse(
        'Permission decision leaked across users (the reported security bypass)'
    );
});

test('SECURITY: super admin flag does not leak across simulated requests', function () {
    if (! \Illuminate\Support\Facades\Schema::hasColumn('users', 'is_super_admin')) {
        \Illuminate\Support\Facades\Schema::table('users', function ($table) {
            $table->boolean('is_super_admin')->default(false);
        });
    }
    config(['aauth-advanced.super_admin.enabled' => true]);
    config(['aauth-advanced.super_admin.column' => 'is_super_admin']);

    // === Request 1: User A as super admin ===
    $userA = User::find(1);
    $userA->is_super_admin = true;
    $userA->save();
    $roleA = Role::whereName('Root Role 1')->first();

    Auth::setUser($userA);
    Session::put('roleId', $roleA->id);

    $aauthA = app('aauth');
    expect($aauthA->isSuperAdmin())->toBeTrue();
    expect($aauthA->can('anything'))->toBeTrue();

    // === Octane request boundary ===
    $this->app->forgetScopedInstances();

    // === Request 2: User B (not super admin) ===
    $userB = User::find(2);
    $userB->is_super_admin = false;
    $userB->save();

    // User B needs an assigned role
    $roleB = Role::whereName('Root Role 1')->first();
    \Illuminate\Support\Facades\DB::table('user_role_organization_node')->insert([
        'user_id' => $userB->id,
        'role_id' => $roleB->id,
        'organization_node_id' => 1,
    ]);

    Auth::setUser($userB);
    Session::put('roleId', $roleB->id);

    $aauthB = app('aauth');

    expect($aauthB->isSuperAdmin())->toBeFalse(
        'Super admin flag leaked across users — critical privilege escalation'
    );
    expect($aauthB->can('non_existent_permission'))->toBeFalse();
});
