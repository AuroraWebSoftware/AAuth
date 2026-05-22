<?php

use AuroraWebSoftware\AAuth\Database\Seeders\SampleDataSeeder;
use AuroraWebSoftware\AAuth\Facades\AAuth;
use AuroraWebSoftware\AAuth\Models\OrganizationNode;
use AuroraWebSoftware\AAuth\Models\User;
use AuroraWebSoftware\AAuth\Services\OrganizationService;
use AuroraWebSoftware\AAuth\Tests\Models\OrganizationNodeable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Artisan::call('migrate:fresh');
    $seeder = new SampleDataSeeder();
    $seeder->run();
    $this->service = new OrganizationService();

    Schema::create('organization_nodeables', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->timestamps();
    });

    $this->app->singleton('aauth', function ($app) {
        return new \AuroraWebSoftware\AAuth\AAuth(
            User::find(1),
            3
        );
    });
});

test('user can see all switchable roles', function () {
    $switchableRolesCount = count(AAuth::switchableRoles());

    $srCount = DB::table('user_role_organization_node')
        ->where('user_id', '=', 1)
        ->groupBy('role_id', 'user_id')
        ->pluck('role_id')->count();

    $this->assertEquals($switchableRolesCount, $srCount);
});

test('role can get all permissions', function () {
    $permissionsCount = count(AAuth::permissions());

    $actualPermissionsCount = DB::table('role_permission')
        ->where('role_id', '=', 3)
        ->pluck('role_id')->count();

    $this->assertEquals($permissionsCount, $actualPermissionsCount);
});

test('role can check allowed permission with can() method', function () {
    expect(AAuth::can('create_something_for_organization'))->toBeTrue()
        ->and(AAuth::can('create_something_for_organization_k'))->toBeFalse();
});

test('passOrAbort passes silently when permission is granted', function () {
    AAuth::passOrAbort('create_something_for_organization');
    expect(true)->toBeTrue();
});

test('passOrAbort aborts with 401 and custom message when permission is missing', function () {
    try {
        AAuth::passOrAbort('non_existent_perm', 'Specific denial');
        $this->fail('passOrAbort should have aborted');
    } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
        expect($e->getStatusCode())->toBe(401)
            ->and($e->getMessage())->toBe('Specific denial');
    }
});

test('passOrAbort aborts with 401 and default message when permission is missing', function () {
    try {
        AAuth::passOrAbort('non_existent_perm');
        $this->fail('passOrAbort should have aborted');
    } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
        expect($e->getStatusCode())->toBe(401)
            ->and($e->getMessage())->toBe('No Permission');
    }
});

test('can get all permitted organization nodes', function () {
    $organizationNodeCount = AAuth::organizationNodes()->count();
    $actualOrganizationNodeCount = OrganizationNode::where('parent_id', '!=', null)->get()->count();

    expect($organizationNodeCount)->toEqual($actualOrganizationNodeCount);

    $organizationNodeCount = AAuth::organizationNodes(true)->count();
    $actualOrganizationNodeCount = OrganizationNode::get()->count();

    expect($organizationNodeCount)->toEqual($actualOrganizationNodeCount);
});

test('can get one specified organization node', function () {
    // The seeded AAuth in beforeEach binds user1/role3 which has access to
    // organization nodes 1..N via the role3 -> node1 pivot. Sub-tree
    // organizationNodes() returns nodes whose path is under node 1.
    $accessible = AAuth::organizationNodes(true);
    expect($accessible)->not->toBeEmpty();

    $someNode = $accessible->first();
    $resolved = AAuth::organizationNode($someNode->id);

    expect($resolved)->toBeInstanceOf(OrganizationNode::class)
        ->and($resolved->id)->toBe($someNode->id);
});

test('organizationNode throws when accessing a node outside the role scope', function () {
    AAuth::organizationNode(99999);
})->throws(\AuroraWebSoftware\AAuth\Exceptions\InvalidOrganizationNodeException::class);

test('switchableRolesStatic returns the same roles as the instance method', function () {
    $static = \AuroraWebSoftware\AAuth\AAuth::switchableRolesStatic(1);
    $instance = AAuth::switchableRoles();

    expect(count($static))->toBe(count($instance));
});

test('descendant nodes can be checked', function () {
    expect(AAuth::descendant(1, 3))->toBeTrue()
        ->and(AAuth::descendant(1, 2))->toBeTrue()
        ->and(AAuth::descendant(1, 4))->toBeTrue()
        ->and(AAuth::descendant(3, 1))->toBeFalse();
});

test('can create a org. node-able model and related org. node', function () {
    $data = ['name' => 'Test Organization Nodeable 1'];
    $createdModel = OrganizationNodeable::createWithAAuthOrganizationNode(
        $data,
        1,
        2
    );

    $expectedModel = OrganizationNodeable::where('name', '=', $data['name'])->first();

    $this->assertEquals($data['name'], $expectedModel->name);

    $expectedOrganizationNode = OrganizationNode::where('name', '=', $data['name'])->first();

    $this->assertEquals($data['name'], $expectedOrganizationNode->name);

    $this->assertEquals($data['name'], $createdModel->relatedAAuthOrganizationNode()->name);
});
