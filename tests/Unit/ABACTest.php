<?php

use AuroraWebSoftware\AAuth\Database\Seeders\SampleDataSeeder;
use AuroraWebSoftware\AAuth\Models\RoleModelAbacRule;
use AuroraWebSoftware\AAuth\Models\User;
use AuroraWebSoftware\AAuth\Services\OrganizationService;
use AuroraWebSoftware\AAuth\Tests\Models\OrganizationNodeable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\ModelInfo\ModelFinder;
use Spatie\ModelInfo\ModelInfo;

beforeEach(function () {
    Artisan::call('migrate:fresh');
    $seeder = new SampleDataSeeder();
    $seeder->run();
    $this->service = new OrganizationService();

    Schema::create('organization_nodeables', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->integer('age');
        $table->timestamps();
    });

    $this->app->singleton('aauth', function ($app) {
        return new \AuroraWebSoftware\AAuth\AAuth(
            User::find(1),
            3
        );
    });
});

test('can get all model instances without created role abac rule', function () {
    $data1 = ['name' => 'Test Organization Nodeable 1.1', 'age' => 18];
    $createdModel1 = OrganizationNodeable::createWithAAuthOrganizationNode(
        $data1,
        1,
        2
    );

    $data2 = ['name' => 'Test Organization Nodeable 1.2', 'age' => 19];
    $createdModel2 = OrganizationNodeable::createWithAAuthOrganizationNode(
        $data2,
        1,
        2
    );

    $count = OrganizationNodeable::all()->pluck('name')->count();
    $this->assertEquals($count, 2);
});

test('can get all model instances with (and) operator and 2 (equal) condition', function () {
    $data1 = ['name' => 'Test Organization Nodeable 2.1', 'age' => 18];
    $createdModel1 = OrganizationNodeable::createWithAAuthOrganizationNode(
        $data1,
        1,
        2
    );

    $data2 = ['name' => 'Test Organization Nodeable 2.2', 'age' => 19];
    $createdModel2 = OrganizationNodeable::createWithAAuthOrganizationNode(
        $data2,
        1,
        2
    );

    $rules =
        [
            "&&" => [
                ["=" => ["attribute" => "name", "value" => "Test Organization Nodeable 2.2"]],
                ["=" => ["attribute" => "age", "value" => "19"]],
            ],
        ];


    $data3 = [
        'role_id' => 3,
        'model_type' => OrganizationNodeable::getModelType(),
        "rules_json" => $rules,
    ];

    RoleModelAbacRule::create($data3);


     dd(OrganizationNodeable::all());

    // $count = OrganizationNodeable::all()->pluck('name');
    // $this->assertEquals($count, 2);
});


test('b', function () {
    // ModelFinder::all();
    // dd(ModelFinder::all());
    // $modelInfo = ModelInfo::forModel(\AuroraWebSoftware\AAuth\Tests\Models\OrganizationNodeable::class);
    // dd($modelInfo->attributes);

    $data1 = ['name' => 'Test Organization Nodeable 1'];
    $createdModel1 = OrganizationNodeable::createWithAAuthOrganizationNode(
        $data1,
        1,
        2
    );

    $data2 = ['name' => 'Test Organization Nodeable 2'];
    $createdModel2 = OrganizationNodeable::createWithAAuthOrganizationNode(
        $data2,
        1,
        2
    );

    $rules =
        [
            "&&" => [
                ["like" => ["attribute" => "name", "value" => "%asd%"]],
                ["&&" =>
                    [
                        ["like" => ["attribute" => "name", "value" => "%asd%"]],
                    ],
                ],
                ["||" =>
                    [
                        ["like" => ["attribute" => "name", "value" => "%1%"]],
                        ["like" => ["attribute" => "name", "value" => "%1%"]],
                    ],
                ],
            ],
        ];

    $data3 = [
        'role_id' => 3,
        'model_type' => OrganizationNodeable::getModelType(),
        "rules_json" => $rules,
    ];

    RoleModelAbacRule::create($data3);

    //dd(RoleModelAbacRule::find(1)->rules_json);


    // dd(OrganizationNodeable::all()->pluck('name'));

    DB::enableQueryLog();

    OrganizationNodeable::all()->pluck('name');

    // dd(DB::getQueryLog());

    $this->assertTrue(true);
});
