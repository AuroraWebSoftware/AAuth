<?php

/*
|--------------------------------------------------------------------------
| AAuth Advanced Configuration (v2 Features)
|--------------------------------------------------------------------------
|
| This config contains v2 features: super admin, and other
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
];
