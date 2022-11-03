<?php

namespace AuroraWebSoftware\AAuth\Tests;

use AuroraWebSoftware\AAuth\AAuthServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'AuroraWebSoftware\\AAuth\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    protected function getPackageProviders($app)
    {
        return [
            AAuthServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        // for GitHub tests
        config()->set('database.default', 'mysql');

        // for local tests
        config()->set('database.default', 'testing');
    }
}
