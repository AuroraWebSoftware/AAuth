<?php

namespace AuroraWebSoftware\AAuth;

use AuroraWebSoftware\AAuth\Commands\AAuthCommand;
use AuroraWebSoftware\AAuth\Http\Middleware\AAuthOrganizationScope;
use AuroraWebSoftware\AAuth\Http\Middleware\AAuthPermission;
use AuroraWebSoftware\AAuth\Http\Middleware\AAuthRole;
use AuroraWebSoftware\AAuth\Models\Role;
use AuroraWebSoftware\AAuth\Models\RolePermission;
use AuroraWebSoftware\AAuth\Observers\RoleObserver;
use AuroraWebSoftware\AAuth\Observers\RolePermissionObserver;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Routing\Router;
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

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->publishes([
            __DIR__.'/../database/seeders' => resource_path('../database/seeders'),
        ], 'aauth-seeders');

        $this->publishes([
            __DIR__.'/../config/aauth.php' => config_path('aauth.php'),
            __DIR__.'/../config/aauth-permissions.php' => config_path('aauth-permissions.php'),
        ], 'aauth-config');

        $this->registerObservers();
        $this->registerMiddleware();

        $this->app->singleton('aauth', function ($app) {
            return new AAuth(
                Auth::user(), // @phpstan-ignore-line
                Session::get('roleId')
            );
        });

        Gate::before(function ($user, $ability, $arguments = []) {
            try {
                /** @var AAuth $aauth */
                $aauth = app('aauth');

                if ($aauth->isSuperAdmin()) {
                    return true;
                }

                return $aauth->can($ability, ...$arguments) ?: null;
            } catch (\Throwable $e) {
                return null;
            }
        });

        $this->registerBladeDirectives();
    }

    protected function registerObservers(): void
    {
        Role::observe(RoleObserver::class);
        RolePermission::observe(RolePermissionObserver::class);
    }

    protected function registerMiddleware(): void
    {
        $router = $this->app->make(Router::class);

        $router->aliasMiddleware('aauth.permission', AAuthPermission::class);
        $router->aliasMiddleware('aauth.role', AAuthRole::class);
        $router->aliasMiddleware('aauth.organization', AAuthOrganizationScope::class);
    }

    protected function registerBladeDirectives(): void
    {
        Blade::directive('aauth', function ($permission) {
            return "<?php if(\AuroraWebSoftware\AAuth\Facades\AAuth::can($permission)){ ?>";
        });
        Blade::directive('endaauth', function () {
            return '<?php } ?>';
        });

        Blade::if('aauth_can', function ($permission, ...$arguments) {
            return app('aauth')->can($permission, ...$arguments);
        });

        Blade::if('aauth_role', function ($roleName) {
            return app('aauth')->currentRole()?->name === $roleName;
        });

        Blade::if('aauth_super_admin', function () {
            return app('aauth')->isSuperAdmin();
        });
    }
}
