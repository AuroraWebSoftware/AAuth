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
        // Respect the DB_CONNECTION env var from phpunit.xml.dist (and from the
        // GitHub Actions matrix). The historic three-line override forced 'mysql'
        // regardless, which prevented local developers without Docker from running
        // the suite. Now:
        //   - phpunit.xml.dist sets DB_CONNECTION=mysql by default → MySQL/MariaDB.
        //   - Local devs without Docker can override via env or a custom phpunit.xml
        //     (e.g. AAUTH_TEST_DB=sqlite vendor/bin/pest).
        $connection = env('DB_CONNECTION', 'testing');

        if (env('AAUTH_TEST_DB') === 'sqlite') {
            $connection = 'testing';
        }

        config()->set('database.default', $connection);
    }
}
