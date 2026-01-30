<?php

use AuroraWebSoftware\AAuth\AAuth;
use AuroraWebSoftware\AAuth\Database\Seeders\SampleDataSeeder;
use AuroraWebSoftware\AAuth\Exceptions\AuthorizationException;
use AuroraWebSoftware\AAuth\Exceptions\InvalidRoleException;
use AuroraWebSoftware\AAuth\Exceptions\InvalidUserException;
use AuroraWebSoftware\AAuth\Exceptions\MissingRoleException;
use AuroraWebSoftware\AAuth\Exceptions\UserHasNoAssignedRoleException;
use AuroraWebSoftware\AAuth\Models\Role;
use AuroraWebSoftware\AAuth\Models\User;
use AuroraWebSoftware\AAuth\Services\RolePermissionService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    Artisan::call('migrate:fresh');
    $seeder = new SampleDataSeeder();
    $seeder->run();
});

/*
|--------------------------------------------------------------------------
| AuthenticationException Tests (when user is null)
|--------------------------------------------------------------------------
*/

test('AuthenticationException is thrown when user is null', function () {
    new AAuth(null, 1);
})->throws(AuthenticationException::class);

test('AuthenticationException has Unauthenticated message', function () {
    try {
        new AAuth(null, 1);
    } catch (AuthenticationException $e) {
        expect($e->getMessage())->toBe('Unauthenticated.');
    }
});

/*
|--------------------------------------------------------------------------
| MissingRoleException Tests
|--------------------------------------------------------------------------
*/

test('MissingRoleException is thrown when roleId is null', function () {
    $user = User::find(1);
    new AAuth($user, null);
})->throws(MissingRoleException::class);

test('UserHasNoAssignedRoleException is thrown when role does not exist', function () {
    $user = User::find(1);
    new AAuth($user, 99999);
})->throws(UserHasNoAssignedRoleException::class);

test('MissingRoleException has default message', function () {
    $exception = new MissingRoleException();
    expect($exception->getMessage())->toBe('Current Role Missing.');
});

test('MissingRoleException accepts custom message', function () {
    $exception = new MissingRoleException('Custom message');
    expect($exception->getMessage())->toBe('Custom message');
});

test('MissingRoleException guards method returns empty array by default', function () {
    $exception = new MissingRoleException();
    expect($exception->guards())->toBe([]);
});

test('MissingRoleException guards can be set', function () {
    $exception = new MissingRoleException('msg', ['web', 'api']);
    expect($exception->guards())->toBe(['web', 'api']);
});

test('MissingRoleException redirectTo is null by default', function () {
    $exception = new MissingRoleException();
    expect($exception->redirectTo())->toBeNull();
});

test('MissingRoleException redirectTo can be set', function () {
    $exception = new MissingRoleException('msg', [], '/login');
    expect($exception->redirectTo())->toBe('/login');
});

/*
|--------------------------------------------------------------------------
| AuthorizationException Tests
|--------------------------------------------------------------------------
*/

test('AuthorizationException has default message', function () {
    $exception = new AuthorizationException();
    expect($exception->getMessage())->toBe('Authorization Error');
});

test('AuthorizationException accepts custom message', function () {
    $exception = new AuthorizationException('No permission for this action');
    expect($exception->getMessage())->toBe('No permission for this action');
});

test('AuthorizationException guards method returns empty array by default', function () {
    $exception = new AuthorizationException();
    expect($exception->guards())->toBe([]);
});

test('AuthorizationException redirectTo is null by default', function () {
    $exception = new AuthorizationException();
    expect($exception->redirectTo())->toBeNull();
});

/*
|--------------------------------------------------------------------------
| InvalidRoleException Tests
|--------------------------------------------------------------------------
*/

test('InvalidRoleException can be instantiated', function () {
    $exception = new InvalidRoleException('Invalid role provided');
    expect($exception)->toBeInstanceOf(InvalidRoleException::class)
        ->and($exception->getMessage())->toBe('Invalid role provided');
});

/*
|--------------------------------------------------------------------------
| InvalidUserException Tests
|--------------------------------------------------------------------------
*/

test('InvalidUserException can be instantiated', function () {
    $exception = new InvalidUserException('Invalid user provided');
    expect($exception)->toBeInstanceOf(InvalidUserException::class)
        ->and($exception->getMessage())->toBe('Invalid user provided');
});

/*
|--------------------------------------------------------------------------
| Service Layer Exception Tests
|--------------------------------------------------------------------------
*/

test('RolePermissionService throws exception for non-existent role', function () {
    $service = new RolePermissionService();

    // This will throw because Role::find(99999) returns null
    $service->attachPermissionToRole('test_permission', 99999);
})->throws(\ErrorException::class);

test('RolePermissionService throws exception for invalid user when attaching role', function () {
    $service = new RolePermissionService();
    $role = Role::whereName('Root Role 1')->first();

    // This will throw InvalidUserException
    $service->attachSystemRoleToUser($role->id, 99999);
})->throws(\AuroraWebSoftware\AAuth\Exceptions\InvalidUserException::class);

/*
|--------------------------------------------------------------------------
| Exception Inheritance Tests
|--------------------------------------------------------------------------
*/

test('all AAuth exceptions extend base Exception', function () {
    expect(new MissingRoleException())->toBeInstanceOf(\Exception::class)
        ->and(new AuthorizationException())->toBeInstanceOf(\Exception::class)
        ->and(new InvalidRoleException())->toBeInstanceOf(\Exception::class)
        ->and(new InvalidUserException())->toBeInstanceOf(\Exception::class);
});

/*
|--------------------------------------------------------------------------
| passOrAbort Exception Tests
|--------------------------------------------------------------------------
*/

test('passOrAbort throws HttpException with custom message', function () {
    $user = User::find(1);
    $role = Role::whereName('Root Role 1')->first();

    $this->app->singleton('aauth', fn () => new AAuth($user, $role->id));

    try {
        app('aauth')->passOrAbort('non_existent_permission', 'Custom error message');
    } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
        // passOrAbort uses HTTP_UNAUTHORIZED (401)
        expect($e->getStatusCode())->toBe(401)
            ->and($e->getMessage())->toBe('Custom error message');
    }
});

test('passOrAbort throws HttpException with default message', function () {
    $user = User::find(1);
    $role = Role::whereName('Root Role 1')->first();

    $this->app->singleton('aauth', fn () => new AAuth($user, $role->id));

    try {
        app('aauth')->passOrAbort('non_existent_permission');
    } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
        // passOrAbort uses HTTP_UNAUTHORIZED (401)
        expect($e->getStatusCode())->toBe(401)
            ->and($e->getMessage())->toBe('No Permission');
    }
});
