<?php

use AuroraWebSoftware\AAuth\AAuth;
use AuroraWebSoftware\AAuth\Database\Seeders\SampleDataSeeder;
use AuroraWebSoftware\AAuth\Models\Role;
use AuroraWebSoftware\AAuth\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    Artisan::call('migrate:fresh');
    (new SampleDataSeeder())->run();
});

// F2 — Role::permissions() must return ONLY this role's permissions (no all-roles leak).
it('F2: Role::permissions() does not leak other roles permissions', function () {
    $a = Role::create(['type' => 'system', 'name' => 'A', 'status' => 'active']);
    $b = Role::create(['type' => 'system', 'name' => 'B', 'status' => 'active']);
    $a->givePermission('perm.a');
    $b->givePermission('perm.b');

    expect($a->permissions())->toBe(['perm.a']);
    expect($b->permissions())->toBe(['perm.b']);
});

// F3 — getAssignedUserCount counts DISTINCT users, not pivot rows.
it('F3: assigned_user_count is distinct users, not pivot rows', function () {
    $role = Role::create(['type' => 'organization', 'organization_scope_id' => 1, 'name' => 'C', 'status' => 'active']);

    // Same user assigned at two different nodes → two pivot rows, one distinct user.
    DB::table('user_role_organization_node')->insert([
        ['user_id' => 1, 'role_id' => $role->id, 'organization_node_id' => 1],
        ['user_id' => 1, 'role_id' => $role->id, 'organization_node_id' => 2],
    ]);

    expect($role->assigned_user_count)->toBe(1);
    expect($role->deletable)->toBeFalse();
});

// F5 — a role with no accessible org nodes returns ZERO nodes (fail closed), never the whole table, never a throw.
it('F5: empty accessible-node set returns zero rows (fail closed)', function () {
    // role 1 is a system role assigned with no organization_node_id.
    $this->app->singleton('aauth', fn ($app) => new AAuth(User::find(1), 1));

    expect(app('aauth')->organizationNodes())->toHaveCount(0);
    expect(app('aauth')->organizationNodesQuery()->get())->toHaveCount(0);
    expect(app('aauth')->getAccessibleOrganizationNodes())->toHaveCount(0);
});
