<?php

use AuroraWebSoftware\AAuth\AAuth;
use AuroraWebSoftware\AAuth\Database\Seeders\SampleDataSeeder;
use AuroraWebSoftware\AAuth\Exceptions\InvalidOrganizationNodeException;
use AuroraWebSoftware\AAuth\Models\User;
use AuroraWebSoftware\AAuth\Services\RolePermissionService;
use AuroraWebSoftware\AAuth\Tests\Models\OrganizationNodeable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * FAZ 2 write-authorization boundary (S4 + S9).
 * Role 5 ("Sub-Scope Role 1") is scoped to node 2 (path "1/2"), so it can act under
 * node 2's subtree but NOT under node 3 ("1/3"). These NEGATIVE tests pin that a
 * restricted role cannot create / delete a model, or assign a role, outside its subtree —
 * so a future refactor that drops an assertOrganizationNodeAuthorized() call fails CI.
 */
beforeEach(function () {
    Artisan::call('migrate:fresh');
    (new SampleDataSeeder())->run();

    Schema::create('organization_nodeables', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->integer('age');
        $table->timestamps();
    });

    // Bind role 5 → scoped to node 2 ("1/2").
    $this->app->singleton('aauth', fn ($app) => new AAuth(User::find(1), 5));
});

// S4 — createWith
it('S4: createWith rejects a parent outside the active role subtree', function () {
    // node 3 ("1/3") is a sibling of node 2 — outside role 5's subtree.
    expect(fn () => OrganizationNodeable::createWithAAuthOrganizationNode(['name' => 'ForeignModel', 'age' => 1], 3, 2))
        ->toThrow(InvalidOrganizationNodeException::class);

    // Nothing was written.
    expect(OrganizationNodeable::withoutGlobalScopes()->count())->toBe(0);
});

it('S4: createWith allows a parent inside the active role subtree', function () {
    $model = OrganizationNodeable::createWithAAuthOrganizationNode(['name' => 'InSubtreeModel', 'age' => 2], 2, 2);
    expect($model->exists)->toBeTrue();
});

// S4 — deleteWith
it('S4: deleteWith rejects a model whose node is outside the subtree', function () {
    // A model grafted under node 3 ("1/3"), out of role 5's reach.
    DB::table('organization_nodeables')->insert(['id' => 300, 'name' => 'Foreign', 'age' => 9]);
    DB::table('organization_nodes')->insert([
        'organization_scope_id' => 2, 'name' => 'foreign', 'model_type' => OrganizationNodeable::getModelType(),
        'model_id' => 300, 'path' => '1/3/300', 'parent_id' => 3,
    ]);

    expect(fn () => OrganizationNodeable::deleteWithAAuthOrganizationNode(300))
        ->toThrow(InvalidOrganizationNodeException::class);

    // The row survives (delete was refused).
    expect(OrganizationNodeable::withoutGlobalScopes()->whereKey(300)->exists())->toBeTrue();
});

// S4 — updateWith must authorize the NODE BEING MOVED, not just the destination parent.
it('S4: updateWith rejects re-parenting a node from outside the subtree', function () {
    // Victim model + node under node 3 ("1/3"), out of role 5's reach.
    DB::table('organization_nodeables')->insert(['id' => 200, 'name' => 'VictimModel', 'age' => 7]);
    DB::table('organization_nodes')->insert([
        'id' => 600, 'organization_scope_id' => 2, 'name' => 'victim', 'model_type' => OrganizationNodeable::getModelType(),
        'model_id' => 200, 'path' => '1/3/600', 'parent_id' => 3,
    ]);

    // Attacker (role 5) tries to graft the victim node 600 under their own node 2.
    expect(fn () => OrganizationNodeable::updateWithAAuthOrganizationNode(200, 600, ['name' => 'pwned'], 2, 2))
        ->toThrow(InvalidOrganizationNodeException::class);

    // The victim node was NOT re-parented.
    expect(DB::table('organization_nodes')->where('id', 600)->value('parent_id'))->toBe(3);
});

// S9 — attachOrganizationRoleToUser
it('S9: attachOrganizationRoleToUser rejects a node outside the subtree', function () {
    expect(fn () => app(RolePermissionService::class)->attachOrganizationRoleToUser(3, 6, 2))
        ->toThrow(InvalidOrganizationNodeException::class);
});

it('S9: attachOrganizationRoleToUser allows a node inside the subtree', function () {
    expect(app(RolePermissionService::class)->attachOrganizationRoleToUser(2, 6, 2))->toBeTrue();
});
