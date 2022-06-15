<?php

namespace Aurora\AAuth;

use Aurora\AAuth\Commands\AAuthCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

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
