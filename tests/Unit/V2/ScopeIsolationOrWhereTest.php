<?php

use AuroraWebSoftware\AAuth\Database\Seeders\SampleDataSeeder;
use AuroraWebSoftware\AAuth\Models\User;
use AuroraWebSoftware\AAuth\Tests\Models\OrganizationNodeable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * SECURITY REGRESSION GUARD — no data leak via standard where()/orWhere().
 *
 * A model scoped by AAuth (OrBAC org-node scope + ABAC scope) must NEVER return
 * rows outside the active role's accessible subtree, even when the consumer uses
 * a top-level orWhere(). Laravel groups scope-added wheres separately
 * (Builder::callScope -> addNewWheresWithinGroup), so a consumer orWhere stays
 * AND'ed with the AAuth constraints:
 *   WHERE (consumer OR group) AND (org path group) AND model_type = ...
 * This test pins that behaviour so a future change to AAuthOrganizationNodeScope /
 * AAuthABACModelScope cannot silently reintroduce the classic orWhere-precedence leak.
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

    $type = OrganizationNodeable::getModelType();

    // Two model rows: one whose org-node is IN role-5's subtree ("1/2/*"),
    // one whose org-node is OUTSIDE it (under node 3, "1/3/*").
    DB::table('organization_nodeables')->insert([
        ['id' => 100, 'name' => 'InScope', 'age' => 10],
        ['id' => 200, 'name' => 'OutOfScope', 'age' => 999],
    ]);
    DB::table('organization_nodes')->insert([
        ['organization_scope_id' => 2, 'name' => 'in', 'model_type' => $type, 'model_id' => 100, 'path' => '1/2/500', 'parent_id' => 2],
        ['organization_scope_id' => 2, 'name' => 'out', 'model_type' => $type, 'model_id' => 200, 'path' => '1/3/600', 'parent_id' => 3],
    ]);

    // Bind role 5 = "Sub-Scope Role 1", scoped to node 2 (path "1/2").
    // It can see "1/2/*" (the InScope row) but NOT "1/3/*" (the OutOfScope row).
    $this->app->singleton('aauth', fn ($app) => new \AuroraWebSoftware\AAuth\AAuth(User::find(1), 5));
});

it('hides out-of-scope rows on a plain query (baseline)', function () {
    expect(OrganizationNodeable::where('age', 999)->get())->toHaveCount(0);
    expect(OrganizationNodeable::all()->pluck('name')->all())->toBe(['InScope']);
});

it('does not leak out-of-scope rows through a top-level orWhere', function () {
    $names = OrganizationNodeable::where('age', 10)->orWhere('age', 999)->get()->pluck('name')->all();

    expect($names)->toContain('InScope');
    expect($names)->not->toContain('OutOfScope'); // the orWhere branch must stay org-scoped
    expect($names)->toHaveCount(1);
});

it('does not leak out-of-scope rows through a grouped orWhere', function () {
    $names = OrganizationNodeable::where(fn ($q) => $q->where('age', 10)->orWhere('age', 999))
        ->get()->pluck('name')->all();

    expect($names)->toContain('InScope');
    expect($names)->not->toContain('OutOfScope');
});
