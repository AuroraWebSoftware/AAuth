<?php

/*
|--------------------------------------------------------------------------
| AAuth Advanced Configuration (v2 Features)
|--------------------------------------------------------------------------
|
| This config contains v2 features: caching, super admin, and other
| advanced settings. These features are optional and backward compatible.
|
| To enable these features, publish this config:
| php artisan vendor:publish --tag="aauth-advanced-config"
|
*/

return [
    /*
    |--------------------------------------------------------------------------
    | Super Admin
    |--------------------------------------------------------------------------
    |
    | When enabled, users with the specified column set to true will
    | bypass all permission checks.
    |
    */
    'super_admin' => [
        'enabled' => false,
        'column' => 'is_super_admin',
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Cache settings for role and permission data. When enabled, role data
    | and switchable roles are cached for better performance.
    |
    | Cache is automatically invalidated when:
    | - Role is updated/deleted (RoleObserver)
    | - Permission is added/updated/removed (RolePermissionObserver)
    | - User role assignment changes (RolePermissionService)
    |
    */
    'cache' => [
        'enabled' => true,
        'ttl' => 3600, // 1 hour
        'prefix' => 'aauth',
        'store' => null, // null = default cache driver
    ],
];
