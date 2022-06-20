<?php

namespace Aurora\AAuth;

use Aurora\AAuth\Commands\AAuthCommand;
use Aurora\AAuth\Exceptions\MissingRoleExcepiton;
use Aurora\AAuth\Exceptions\UserHasNoAssignedRoleException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
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
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->publishes([
            __DIR__.'/../database/seeders' => resource_path('../database/seeders'),
        ], 'aauth-seeders');

        // todo singleton bind ??
        $this->app->singleton('aauth', function ($app) {
            try {
                return new AAuth(
                    Auth::user(),
                    session('roleId')
                );
            } catch (AuthenticationException $e) {
                // todo refactor
                dd('error aurora service provider 1');
            } catch (UserHasNoAssignedRoleException|MissingRoleExcepiton $e) {
                Redirect::route('/')->send();
            }
        });
    }
}
