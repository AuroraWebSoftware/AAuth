# Upgrade Guide

## Upgrading to 21.1.0 (security + reliability minor)

This release is fully backward compatible — `composer update` from any 21.x will not break a working host application. No migration, no config change, no signature change.

### What changed

**1. AAuth container binding is now request-scoped (security fix, HIGH severity)**

The `aauth` binding in `AAuthServiceProvider` was a `singleton` and now uses `scoped()`. Under classic PHP-FPM each request is a fresh process, so this is a no-op (same number of constructor calls per request, same DB queries, same observable behaviour).

Under **Laravel Octane / Vapor** the change closes an authorization-bypass vector: the resolved `AAuth` instance no longer survives the request boundary, so User A's role / organization node IDs / permissions / ABAC rules / super-admin flag cannot leak into User B's request on the same worker.

- **Action required for PHP-FPM consumers:** none.
- **Action required for Octane / Vapor consumers:** none. If you previously worked around this with `config/octane.php`'s `'flush' => ['aauth']` entry, you can remove it (it's harmless if you leave it).

**2. Recursive organization-node service methods now propagate exceptions**

`OrganizationService::updateNodePathsRecursively()` and `deleteOrganizationNodesRecursively()` previously wrapped their body in a `try/catch` that silently swallowed exceptions while still committing the outer transaction. The new implementation wraps the recursion in `DB::transaction()` so any failure rolls back the entire subtree and re-throws the original exception.

- The public method signatures (`OrganizationNode $node, ?bool $withDBTransaction = true`) are unchanged.
- The only observable behavioural difference: errors that were previously silent now surface to the caller. If you relied on the silent-failure behaviour, wrap the call in your own `try/catch`.

**3. New aligned-order `detachOrganizationRoleFromUserBy()` method**

`RolePermissionService::detachOrganizationRoleFromUser()` historically takes parameters in the inverse order from `attachOrganizationRoleToUser()`. We did not change the existing method's parameter order — that would silently corrupt data for every consumer. Instead we added:

```php
$service->detachOrganizationRoleFromUserBy(
    $organizationNodeId,
    $roleId,
    $userId,
); // matches attachOrganizationRoleToUser order
```

The existing `detachOrganizationRoleFromUser($userId, $roleId, $organizationNodeId)` keeps working and emits no runtime notice. It is marked `@deprecated` in its docblock so IDEs and PHPStan flag call sites for migration. It will be removed in the next major release.

- **Migration recipe:**
  ```bash
  # Find call sites
  grep -rn 'detachOrganizationRoleFromUser(' app/ packages/
  ```
  Replace `detachOrganizationRoleFromUser($u, $r, $n)` with `detachOrganizationRoleFromUserBy($n, $r, $u)`.

### Verifying the upgrade

After `composer update`:

```bash
vendor/bin/pest          # your own test suite should still pass
vendor/bin/phpstan       # may surface @deprecated notices for the legacy detach method — informational only
```

---

## Upgrading from v1 to v2

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
```

## Getting Help

- [GitHub Issues](https://github.com/aurorawebsoftware/aauth/issues)
- [Documentation](https://github.com/aurorawebsoftware/aauth#readme)
