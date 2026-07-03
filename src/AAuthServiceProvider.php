<?php

namespace AuroraWebSoftware\AAuth;

use AuroraWebSoftware\AAuth\Http\Middleware\AAuthOrganizationScope;
use AuroraWebSoftware\AAuth\Http\Middleware\AAuthPermission;
use AuroraWebSoftware\AAuth\Http\Middleware\AAuthRole;
use AuroraWebSoftware\AAuth\Models\Role;
use AuroraWebSoftware\AAuth\Models\RolePermission;
use AuroraWebSoftware\AAuth\Observers\RoleObserver;
use AuroraWebSoftware\AAuth\Observers\RolePermissionObserver;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Session;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class AAuthServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('aauth')
            ->hasConfigFile(['aauth', 'aauth-advanced']);
    }

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
            __DIR__.'/../config/aauth-advanced.php' => config_path('aauth-advanced.php'),
        ], 'aauth-config');

        // Language files
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'aauth');

        $this->publishes([
            __DIR__.'/../resources/lang' => resource_path('lang/vendor/aauth'),
        ], 'aauth-lang');

        $this->registerObservers();
        $this->registerMiddleware();

        // Request-scoped binding (not singleton): under Laravel Octane / Vapor the
        // resolved AAuth instance carries per-user state (role, organization node IDs,
        // permissions, ABAC rules, super-admin flag). A singleton binding survives the
        // request boundary in long-lived workers and leaks one user's authorization
        // context into the next user's request. `scoped()` is flushed automatically by
        // Octane's FlushTemporaryContainerInstances listener on RequestTerminated. Under
        // classic PHP-FPM each request is a fresh process, so scoped() and singleton()
        // are observationally equivalent (no behaviour change for PHP-FPM consumers).
        $this->app->scoped('aauth', function ($app) {
            /** @var \AuroraWebSoftware\AAuth\Contracts\AAuthUserContract|null $user */
            $user = Auth::user();

            return new AAuth($user, Session::get('roleId'));
        });

        Gate::before(function ($user, $ability, $arguments = []) {
            try {
                // Defer to a host-app Policy when one handles this ability for the given
                // model, so object-level checks (e.g. ownership) still run. A name-only
                // AAuth permission must NOT silently shadow a registered policy (IDOR).
                $model = is_array($arguments) ? ($arguments[0] ?? null) : $arguments;
                if (is_object($model) || (is_string($model) && class_exists($model))) {
                    $policy = Gate::getPolicyFor($model);
                    if ($policy !== null && method_exists($policy, $ability)) {
                        return null;
                    }
                }

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
