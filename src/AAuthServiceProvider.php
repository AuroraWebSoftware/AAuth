<?php

namespace AuroraWebSoftware\AAuth;

use AuroraWebSoftware\AAuth\Commands\AAuthCommand;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Session;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class AAuthServiceProvider extends PackageServiceProvider
{
    /**
     * @param  Package  $package
     * @return void
     */
    public function configurePackage(Package $package): void
    {
        $package
            ->name('aauth')
            ->hasConfigFile()
            // ->hasViews()
            // ->hasMigration()
            // ->hasCommand(AAuthCommand::class)
        ;
    }

    /**
     * @return void
     */
    public function boot(): void
    {
        parent::boot();

        // load packages migrations
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->publishes([
            __DIR__.'/../database/seeders' => resource_path('../database/seeders'),
        ], 'aauth-seeders');

        $this->publishes([
            __DIR__.'/../config' => config_path(),
        ], 'aauth-config');

        // todo singleton bind ??
        $this->app->singleton('aauth', function ($app) {
            return new AAuth(
                Auth::user(), // @phpstan-ignore-line
                Session::get('roleId')
            );
        });

        // Register AAuth permissions with Laravel's Gate system
        // This allows AAuth permissions to work alongside Laravel's native gates and policies
        Gate::before(function ($user, $ability, $arguments = []) {
            // Only return true if AAuth explicitly grants this permission
            // Return null to allow Laravel's gate/policy checks to continue
            try {
                if (app('aauth')->can($ability)) {
                    return true;
                }
            } catch (\Throwable $e) {
                // If AAuth service is not properly initialized, let Laravel's gates handle it
                return null;
            }

            // Return null instead of false to allow Laravel's gates and policies to be checked
            return null;
        });

        Blade::directive('aauth', function ($permission) {
            return "<?php if(\AuroraWebSoftware\AAuth\Facades\AAuth::can($permission)){ ?>";
        });
        Blade::directive('endaauth', function () {
            return '<?php } ?>';
        });
    }
}
