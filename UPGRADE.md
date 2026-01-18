# Upgrade Guide

This guide covers upgrading from AAuth v1 to v2.

## Requirements

- PHP 8.2 or higher
- Laravel 11 or 12
- MySQL 8.0+ or PostgreSQL 13+

## Step 1: Update Composer

```bash
composer require aurorawebsoftware/aauth:^2.0
```

## Step 2: Run Migrations

```bash
php artisan migrate
```

This will apply the following changes:

- Add `panel_id` column to `roles` table (nullable)
- Add `parameters` JSON column to `role_permission` table
- Make `type` column nullable in `roles` table
- Add performance indexes

## Step 3: Publish New Config (Optional)

```bash
php artisan vendor:publish --tag="aauth-config" --force
```

New config options in v2:

```php
// config/aauth.php
return [
    'super_admin' => [
        'enabled' => false,
        'column' => 'is_super_admin',
    ],
    'cache' => [
        'enabled' => true,
        'ttl' => 3600,
        'prefix' => 'aauth',
        'store' => null,
    ],
];
```

## Breaking Changes

### Type Column Deprecation

The `type` column in `roles` table is deprecated. Use `organization_scope_id` instead.

**Before (v1):**

```php
$data = [
    'type' => 'organization',  // Deprecated
    'name' => 'Manager',
];
$rolePermissionService->createRole($data);
```

**After (v2):**

```php
$data = [
    'organization_scope_id' => $scope->id,  // Use this instead
    'name' => 'Manager',
];
$rolePermissionService->createRole($data);
```

**Note:** The `type` column still works for backward compatibility. Existing code will continue to work.

### Validation Rules

Minimum name length changed from 5 to 3 characters:

```php
// v1: 'name' => ['required', 'min:5']
// v2: 'name' => ['required', 'min:3']
```

## New Features in v2

### Filament Panel Support

```php
// New static factory methods
$aauth = AAuth::forPanel($user, $roleId, 'admin');
$aauth = AAuth::forCurrentPanel($user, $roleId);

// New instance methods
$roles = $aauth->switchableRolesForPanel('admin');
$panelId = $aauth->getCurrentPanel();

// New helper functions (uses Auth::user() and Session internally)
$aauth = aauth_for_panel('admin');
$roles = aauth_panel_roles('admin');
```

### Caching

Role and permission data is now cached by default:

```php
// Disable caching if needed
// config/aauth.php
'cache' => [
    'enabled' => false,
],
```

### Organization Depth Filtering

```php
$nodes = $aauth->getAccessibleOrganizationNodes(
    minDepthFromRoot: 1,
    maxDepthFromRoot: 3,
    scopeName: 'Region'
);
```

### Internationalization

Exception messages now support translation:

```bash
php artisan vendor:publish --tag="aauth-lang"
```

Customize messages in `resources/lang/{locale}/aauth.php`.

## Migration Script (Optional)

If you want to migrate from `type` to `organization_scope_id`:

```php
// Create a migration
use Illuminate\Support\Facades\DB;

// Map type values to organization_scope_id
$mapping = [
    'system' => null,  // System roles have no scope
    'organization' => 1,  // Replace with your scope ID
];

DB::table('roles')
    ->whereNotNull('type')
    ->whereNull('organization_scope_id')
    ->get()
    ->each(function ($role) use ($mapping) {
        DB::table('roles')
            ->where('id', $role->id)
            ->update([
                'organization_scope_id' => $mapping[$role->type] ?? null,
            ]);
    });
```

## Troubleshooting

### Cache Issues

If you experience stale data after updates:

```php
// Clear AAuth cache manually
$prefix = config('aauth.cache.prefix', 'aauth');
Cache::forget("{$prefix}:role:{$roleId}");
Cache::forget("{$prefix}:user:{$userId}:switchable_roles");

// Or disable cache temporarily
// config/aauth.php: 'cache' => ['enabled' => false]
```

### Database Index Errors

If migration fails due to existing indexes:

```bash
# Check existing indexes
SHOW INDEX FROM roles;
SHOW INDEX FROM role_permission;

# Drop duplicate indexes manually if needed
DROP INDEX idx_roles_panel_id ON roles;
```

### Filament Panel Not Detected

Ensure Filament is properly installed:

```php
// Check if panel is detected
$panelId = AAuth::detectCurrentPanelId();
dd($panelId); // Should show current panel ID or null
```

## Getting Help

- [GitHub Issues](https://github.com/aurorawebsoftware/aauth/issues)
- [Documentation](https://github.com/aurorawebsoftware/aauth#readme)
