<?php

use Aurora\AAuth\Database\Seeders\SampleDataSeeder;
use Aurora\AAuth\Models\OrganizationNode;
use Aurora\AAuth\Models\OrganizationScope;
use Aurora\AAuth\Services\OrganizationService;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    Artisan::call('migrate:fresh');
    $seeder = new SampleDataSeeder();
    $seeder->run();
    $this->service = new OrganizationService();
});


test('can create OrganizationScope', function () {

    // arrange
    $data = [
        'name' => 'Org Scope1',
        'level' => 5,
        'status' => 'active',
    ];

    // act
    $createdOS = $this->service->createOrganizationScope($data);

    // assert
    $this->assertEquals($createdOS->name, $data['name']);

    $createdCount = OrganizationScope::whereName('Org Scope1')->count();
    $this->assertEquals(1, $createdCount);
});

test('can create and update OrganizationScope', function () {
    $data = [
        'name' => 'Org Scope2',
        'level' => 7,
        'status' => 'active',
    ];

    $createdOS = $this->service->createOrganizationScope($data);

    $this->assertEquals($createdOS->name, $data['name']);

    $createdCount = OrganizationScope::whereName('Org Scope2')->count();

    $data = [
        'name' => 'Org Scope3',
        'level' => 7,
        'status' => 'active',
    ];

    $updatedOS = $this->service->updateOrganizationScope($data, $createdOS->id);
    $this->assertEquals($updatedOS->name, $data['name']);
});

test('can create and delete OrganizationScope', function () {
    $data = [
        'name' => 'Org Scope be deleted',
        'level' => 7,
        'status' => 'active',
    ];

    $createdOS = $this->service->createOrganizationScope($data);

    $this->assertEquals($createdOS->name, $data['name']);

    $updatedOS = $this->service->deleteOrganizationScope($createdOS->id);

    $deletedOSCount = OrganizationScope::whereName($data['name'])->count();
    $this->assertEquals($deletedOSCount, 0);
});


test('can create OrganizationNode', function () {

    // todo org. scopeları eşleşmeleri kontrol edilecek.
    // arrange
    $os = OrganizationScope::first();

    $data = [
        'name' => 'Created Org Node 1',
        'organization_scope_id' => $os->id,
        'parent_id' => 1,
    ];

    // act
    $createdON = $this->service->createOrganizationNode($data);

    // assert
    $this->assertEquals($createdON->name, $data['name']);

    $createdCount = OrganizationNode::whereName($data['name'])->count();
    $this->assertEquals(1, $createdCount);
});


test('can create and update OrganizationNode', function () {

    // todo org. scopeları eşleşmeleri kontrol edilecek.
    $os = OrganizationScope::first();

    $data = [
        'name' => 'Updated Org Node 1',
        'organization_scope_id' => $os->id,
        'parent_id' => 1,
    ];

    $createdON = $this->service->createOrganizationNode($data);

    $this->assertEquals($createdON->name, $data['name']);

    $createdCount = OrganizationNode::whereName($data['name'])->count();
    $this->assertEquals(1, $createdCount);


    $data = [
        'name' => 'Updated Org Node 2',
        'organization_scope_id' => $os->id,
        'parent_id' => 1,
    ];

    $updatedON = $this->service->updateOrganizationNode($data, $createdON->id);
    $updatedCount = OrganizationNode::whereName($data['name'])->count();
    $this->assertEquals(1, $updatedCount);
});
