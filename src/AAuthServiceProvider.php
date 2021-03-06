<?php

namespace AuroraWebSoftware\AAuth;

use AuroraWebSoftware\AAuth\Commands\AAuthCommand;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Session;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class AAuthServiceProvider extends PackageServiceProvider
{
    /**
     * @param Package $package
     * @return void
     */
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
            //->hasMigration('create_aauth_table')
            ->hasCommand(AAuthCommand::class);
    }

    public function boot()
    {
        // load packages migrations
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        $this->publishes([
            __DIR__ . '/../database/seeders' => resource_path('../database/seeders'),
        ], 'aauth-seeders');

        // todo singleton bind ??
        // AStart ?
        $this->app->singleton('aauth', function ($app) {
            return new AAuth(
                Auth::user(),
                Session::get('roleId')
            );
        });

        Blade::directive('aauth', function ($permission) {
            return "<?php if(\AuroraWebSoftware\AAuth\Tests\Facades\AAuth::can($permission)){ ?>";
        });
        Blade::directive('endaauth', function () {
            return "<?php } ?>";
        });
    }
}
