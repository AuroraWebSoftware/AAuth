<?php

use Spatie\ModelInfo\ModelFinder;
use Spatie\ModelInfo\ModelInfo;

test('b', function () {
    ModelFinder::all();

    // dd(ModelFinder::all());

    $modelInfo = ModelInfo::forModel(\AuroraWebSoftware\AAuth\Tests\Models\OrganizationNodeable::class);

    dd($modelInfo->attributes);

    $this->assertTrue(true);
});
