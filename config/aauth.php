<?php

// config for AuroraWebSoftware/AAuth
return [
    /*
    |--------------------------------------------------------------------------
    | Super Admin
    |--------------------------------------------------------------------------
    | Enables super admin functionality. When enabled, users with the
    | specified column set to true will bypass all permission checks.
    */
    'super_admin' => [
        'enabled' => false,
        'column' => 'is_super_admin',
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    | Cache settings for permission and role data.
    */
    'cache' => [
        'enabled' => true,
        'ttl' => 3600, // 1 hour
        'prefix' => 'aauth',
        'store' => null, // null = default cache driver
    ],

];
