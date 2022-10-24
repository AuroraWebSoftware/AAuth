<?php

use AuroraWebSoftware\AAuth\Database\Seeders\SampleDataSeeder;
use AuroraWebSoftware\AAuth\Models\RoleModelAbacRule;
use AuroraWebSoftware\AAuth\Models\User;
use AuroraWebSoftware\AAuth\Services\ABACService;
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


test('can validate abac rule array', function () {
    $rules1 =
        [
            "&&" => [
                ["=" => ["attribute" => "name", "value" => "Test Organization Nodeable 2.2"]],
                ["=" => ["attribute" => "age", "value" => "19"]],
            ],
        ];

    $rules2 =
        [
            "&&" => [
                ["=" => ["attribute" => "name", "value" => "Test Organization Nodeable 2.2"]],
                ["=" => ["attribute" => "age", "value" => "19"]],
                [
                    "&&" => [
                        ["=" => ["attribute" => "name", "value" => "Test Organization Nodeable 2.2"]],
                        ["=" => ["attribute" => "age", "value" => "19"]],
                    ],
                ],
            ],
        ];

    ABACService::validateAbacRuleArray($rules1);
    ABACService::validateAbacRuleArray($rules2);
    $this->assertTrue(true);
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

test('can get all model instances proper (and) conditions', function () {
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

    $data3 = ['name' => 'Test Organization Nodeable 2.3', 'age' => 19];
    $createdModel3 = OrganizationNodeable::createWithAAuthOrganizationNode(
        $data3,
        1,
        2
    );

    $data4 = ['name' => 'Test Organization Nodeable 2.4', 'age' => 20];
    $createdModel4 = OrganizationNodeable::createWithAAuthOrganizationNode(
        $data4,
        1,
        2
    );

    $data5 = ['name' => 'Test Organization Nodeable 2.5', 'age' => 21];
    $createdModel5 = OrganizationNodeable::createWithAAuthOrganizationNode(
        $data5,
        1,
        2
    );

    $data6 = ['name' => 'Test Organization Nodeable 3.1', 'age' => 30];
    $createdModel6 = OrganizationNodeable::createWithAAuthOrganizationNode(
        $data6,
        1,
        2
    );


    // 1 - 2 equals
    $rules1 =
        [
            "&&" => [
                ["=" => ["attribute" => "name", "value" => "Test Organization Nodeable 2.2"]],
                ["=" => ["attribute" => "age", "value" => "19"]],
            ],
        ];


    $dataRule = [
        'role_id' => 3,
        'model_type' => OrganizationNodeable::getModelType(),
        "rules_json" => $rules1,
    ];

    $roleModelAbacRuleModelInstance = RoleModelAbacRule::create($dataRule);

    $this->assertEquals(1, OrganizationNodeable::count());

    // 1 end


    // 2 - greater then
    $rules2 =
        [
            "&&" => [
                [">" => ["attribute" => "age", "value" => "19"]],
            ],
        ];

    $roleModelAbacRuleModelInstance->rules_json = $rules2;
    $roleModelAbacRuleModelInstance->save();

    $this->assertEquals(3, OrganizationNodeable::count());
    // 2 end


    // 3 - greater than and equals to
    $rules3 =
        [
            "&&" => [
                [">=" => ["attribute" => "age", "value" => "19"]],
            ],
        ];

    $roleModelAbacRuleModelInstance->rules_json = $rules3;
    $roleModelAbacRuleModelInstance->save();

    $this->assertEquals(5, OrganizationNodeable::count());
    // 3 end


    // 3 - like
    $rules3 =
        [
            "&&" => [
                ["like" => ["attribute" => "name", "value" => "%2.%"]],
            ],
        ];

    $roleModelAbacRuleModelInstance->rules_json = $rules3;
    $roleModelAbacRuleModelInstance->save();

    $this->assertEquals(5, OrganizationNodeable::count());
    // 3 end


    // 4 - 2 like
    $rules4 =
        [
            "&&" => [
                ["like" => ["attribute" => "name", "value" => "%2.%"]],
                ["like" => ["attribute" => "name", "value" => "%3.%"]],
            ],
        ];

    $roleModelAbacRuleModelInstance->rules_json = $rules4;
    $roleModelAbacRuleModelInstance->save();

    $this->assertEquals(0, OrganizationNodeable::count());
    // 4 end

    // 5 - like and equal
    $rules5 =
        [
            "&&" => [
                ["like" => ["attribute" => "name", "value" => "%2.%"]],
                ["=" => ["attribute" => "age", "value" => "19"]],
            ],
        ];

    $roleModelAbacRuleModelInstance->rules_json = $rules5;
    $roleModelAbacRuleModelInstance->save();

    $this->assertEquals(2, OrganizationNodeable::count());
    // 5 end

    // todo not_equal,nested and


    // 20 - like or equal
    $rules20 =
        [
            "||" => [
                ["like" => ["attribute" => "name", "value" => "%3.%"]],
                ["=" => ["attribute" => "age", "value" => "21"]],
            ],
        ];

    $roleModelAbacRuleModelInstance->rules_json = $rules20;
    $roleModelAbacRuleModelInstance->save();


    $this->assertEquals(2, OrganizationNodeable::count());
    // 20 end


    // 21 - greater or equal
    $rules21 =
        [
            "||" => [
                [">" => ["attribute" => "age", "value" => "21"]],
                ["=" => ["attribute" => "age", "value" => "19"]],
            ],
        ];

    $roleModelAbacRuleModelInstance->rules_json = $rules21;
    $roleModelAbacRuleModelInstance->save();

    $this->assertEquals(3, OrganizationNodeable::count());
    // 21 end

    // 22 - greater or equal
    $rules22 =
        [
            "||" => [
                ["like" => ["attribute" => "name", "value" => "3.%"]],
                "&&" => [
                    ["=" => ["attribute" => "age", "value" => "19"]],
                    ["like" => ["attribute" => "name", "value" => "2.%"]],
                    "||" => [
                        ["like" => ["attribute" => "name", "value" => "3.%"]],
                        ["like" => ["attribute" => "name", "value" => "3.%"]],
                    ]
                ],
            ],
        ];

    $roleModelAbacRuleModelInstance->rules_json = $rules22;
    $roleModelAbacRuleModelInstance->save();

    DB::enableQueryLog();
    OrganizationNodeable::all();
    dd(DB::getQueryLog());

    $this->assertEquals(3, OrganizationNodeable::count());
    // 22 end

    // DB::enableQueryLog();
    // OrganizationNodeable::all();
    // dd(DB::getQueryLog());
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
