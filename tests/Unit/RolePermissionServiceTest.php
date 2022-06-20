<?php

use Aurora\AAuth\Database\Seeders\SampleDataSeeder;
use Aurora\AAuth\Models\OrganizationNode;
use Aurora\AAuth\Models\OrganizationScope;
use Aurora\AAuth\Models\Role;
use Aurora\AAuth\Services\RolePermissionService;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    Artisan::call('migrate:fresh');
    $seeder = new SampleDataSeeder();
    $seeder->run();
    $this->service = new RolePermissionService();
});


