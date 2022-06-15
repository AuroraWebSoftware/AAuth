<?php

namespace Aurora\AAuth;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Aurora\AAuth\Commands\AAuthCommand;

class AAuthServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('aauth')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_aauth_table')
            ->hasCommand(AAuthCommand::class);
    }
}
