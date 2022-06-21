<?php

use Aurora\AAuth\Database\Seeders\SampleDataSeeder;
use Aurora\AAuth\Facades\AAuth;
use Aurora\AAuth\Models\OrganizationNode;
use Aurora\AAuth\Services\OrganizationService;
use Aurora\AAuth\Tests\Models\OrganizationNodeable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
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


    /*
    $model = OrganizationNodeable::create($data);

    $data = [
        'name' => $model->getModelName(),
        'organization_scope_id' => 2,
        'parent_id' => 1,
        'model_type' => OrganizationNodeable::getModelType(),
        'model_id' => $model->getModelId(),
    ];

    $this->service->createOrganizationNode($data);

    foreach (AAuth::organizationNodes(true, OrganizationNodeable::getModelType()) as $node) {
        echo $node->name;
    }

    die();

    $this->assertTrue(true);
    */
});
