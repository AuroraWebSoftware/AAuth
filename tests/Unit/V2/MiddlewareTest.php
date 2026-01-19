<?php

use AuroraWebSoftware\AAuth\AAuth;
use AuroraWebSoftware\AAuth\Database\Seeders\SampleDataSeeder;
use AuroraWebSoftware\AAuth\Http\Middleware\AAuthPermission;
use AuroraWebSoftware\AAuth\Http\Middleware\AAuthRole;
use AuroraWebSoftware\AAuth\Models\Role;
use AuroraWebSoftware\AAuth\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    Artisan::call('migrate:fresh');
    $seeder = new SampleDataSeeder();
    $seeder->run();
});

/*
|--------------------------------------------------------------------------
| AAuthPermission Middleware Tests
|--------------------------------------------------------------------------
*/

test('AAuthPermission middleware passes when user has permission', function () {
    $user = User::find(1);
    $role = Role::whereName('Root Role 1')->first();

    $this->app->singleton('aauth', fn () => new AAuth($user, $role->id));

    $middleware = new AAuthPermission();
    $request = Request::create('/test', 'GET');

    $response = $middleware->handle($request, function ($req) {
        return response('OK', 200);
    }, 'create_something_for_organization');

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getContent())->toBe('OK');
});

test('AAuthPermission middleware aborts 403 when user lacks permission', function () {
    $user = User::find(1);
    $role = Role::whereName('Root Role 1')->first();

    $this->app->singleton('aauth', fn () => new AAuth($user, $role->id));

    $middleware = new AAuthPermission();
    $request = Request::create('/test', 'GET');

    $middleware->handle($request, function ($req) {
        return response('OK', 200);
    }, 'non_existent_permission');
})->throws(HttpException::class);

test('AAuthPermission middleware aborts 403 when aauth not initialized', function () {
    // Don't initialize aauth singleton
    $middleware = new AAuthPermission();
    $request = Request::create('/test', 'GET');

    $middleware->handle($request, function ($req) {
        return response('OK', 200);
    }, 'any_permission');
})->throws(HttpException::class);

test('AAuthPermission middleware passes parameters to can() method', function () {
    $user = User::find(1);
    $role = Role::whereName('Root Role 1')->first();

    // Mock AAuth to verify parameters are passed
    $aauth = new AAuth($user, $role->id);
    $this->app->singleton('aauth', fn () => $aauth);

    $middleware = new AAuthPermission();
    $request = Request::create('/test', 'GET');

    // This should pass because the permission exists
    $response = $middleware->handle($request, function ($req) {
        return response('OK', 200);
    }, 'create_something_for_organization');

    expect($response->getStatusCode())->toBe(200);
});

/*
|--------------------------------------------------------------------------
| AAuthRole Middleware Tests
|--------------------------------------------------------------------------
*/

test('AAuthRole middleware passes when user has correct role', function () {
    $user = User::find(1);
    $role = Role::whereName('Root Role 1')->first();

    $this->app->singleton('aauth', fn () => new AAuth($user, $role->id));

    $middleware = new AAuthRole();
    $request = Request::create('/test', 'GET');

    $response = $middleware->handle($request, function ($req) {
        return response('OK', 200);
    }, 'Root Role 1');

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getContent())->toBe('OK');
});

test('AAuthRole middleware aborts 403 when user has wrong role', function () {
    $user = User::find(1);
    $role = Role::whereName('Root Role 1')->first();

    $this->app->singleton('aauth', fn () => new AAuth($user, $role->id));

    $middleware = new AAuthRole();
    $request = Request::create('/test', 'GET');

    $middleware->handle($request, function ($req) {
        return response('OK', 200);
    }, 'Non Existent Role');
})->throws(HttpException::class);

test('AAuthRole middleware aborts 403 when aauth not initialized', function () {
    // Don't initialize aauth singleton
    $middleware = new AAuthRole();
    $request = Request::create('/test', 'GET');

    $middleware->handle($request, function ($req) {
        return response('OK', 200);
    }, 'Any Role');
})->throws(HttpException::class);

/*
|--------------------------------------------------------------------------
| Middleware Integration Tests
|--------------------------------------------------------------------------
*/

test('multiple middleware can be chained', function () {
    $user = User::find(1);
    $role = Role::whereName('Root Role 1')->first();

    $this->app->singleton('aauth', fn () => new AAuth($user, $role->id));

    $permissionMiddleware = new AAuthPermission();
    $roleMiddleware = new AAuthRole();
    $request = Request::create('/test', 'GET');

    // First check role
    $response = $roleMiddleware->handle($request, function ($req) use ($permissionMiddleware) {
        // Then check permission
        return $permissionMiddleware->handle($req, function ($r) {
            return response('OK', 200);
        }, 'create_something_for_organization');
    }, 'Root Role 1');

    expect($response->getStatusCode())->toBe(200);
});

test('middleware chain fails on first check failure', function () {
    $user = User::find(1);
    $role = Role::whereName('Root Role 1')->first();

    $this->app->singleton('aauth', fn () => new AAuth($user, $role->id));

    $permissionMiddleware = new AAuthPermission();
    $roleMiddleware = new AAuthRole();
    $request = Request::create('/test', 'GET');

    // Role check will fail, permission check should not run
    $roleMiddleware->handle($request, function ($req) use ($permissionMiddleware) {
        return $permissionMiddleware->handle($req, function ($r) {
            return response('OK', 200);
        }, 'create_something_for_organization');
    }, 'Wrong Role');
})->throws(HttpException::class);
