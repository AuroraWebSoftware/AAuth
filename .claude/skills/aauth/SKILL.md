---
name: aauth
description: AAuth Laravel RBAC package development assistant
---

# AAuth Package Skill

## Overview
AAuth is a Laravel package providing advanced Role-Based Access Control (RBAC) with organization hierarchy support, parametric permissions, and Filament integration.

The package maintains full backward compatibility with legacy implementations while providing modern features like parametric permissions, multi-panel support, and intelligent caching.

### Key Features
- **Parametric Permissions**: Permissions with runtime parameters (integers, booleans, arrays)
- **Organization Hierarchy**: Nested organization structure with scope management
- **Filament Panel Support**: Multi-panel role management with `panel_id`
- **Performance Caching**: Redis/File/Database cache support for roles and permissions
- **Super Admin**: Configurable super admin bypass functionality
- **ABAC Support**: Attribute-Based Access Control with JSON rules (basic implementation)
- **Laravel Context API**: In-request caching with automatic invalidation
- **Backward Compatible**: Legacy v1 code continues to work
- **Comprehensive Tests**: 128 tests with 243 assertions

### Architecture
```
User (AAuthUserContract)
  ↓ Many-to-Many (user_role_organization_node pivot)
Role (with panel_id, type: system|organization, organization_scope_id)
  ↓ One-to-Many
RolePermission (permission name + JSON parameters)

Organization Structure:
OrganizationScope (defines hierarchy levels)
  ↓ One-to-Many
OrganizationNode (nested set with path, level, parent_id)
```

### Permission System
- **Simple Permissions**: Basic permission checks without parameters
- **Parametric Permissions**: Advanced permissions with runtime validation
  - Integer parameters: Max value validation (e.g., budget approval limits)
  - Boolean parameters: Exact match validation (e.g., admin access)
  - Array parameters: Allowed values validation (e.g., department access)

## Common Tasks

### Running Tests
```bash
# All tests
./vendor/bin/pest

# Specific test suite
./vendor/bin/pest tests/Unit/V2/

# With coverage
./vendor/bin/pest --coverage

# PHPStan analysis
./vendor/bin/phpstan analyse
```

### Code Quality
```bash
# Laravel Pint (code style)
./vendor/bin/pint

# PHPStan (static analysis)
./vendor/bin/phpstan analyse --no-progress

# Both
./vendor/bin/pint && ./vendor/bin/phpstan analyse --no-progress
```

### Database Migrations
Recent migrations added:
- **Parametric Permissions**: JSON parameters column in `role_permission` table
- **Panel Support**: `panel_id` column in `roles` table for Filament multi-panel
- **Type Flexibility**: Nullable `type` column for backward compatibility
- **Performance Indexes**: Database indexes on frequently queried columns

All migrations are database-agnostic (MySQL, PostgreSQL, SQLite compatible)

### Usage Examples

#### Basic Permission Check
```php
use AuroraWebSoftware\AAuth\Facades\AAuth;

// Simple permission
if (AAuth::can('edit-post')) {
    // User has permission
}

// Parametric permission (integer max value)
if (AAuth::can('approve-budget', [1000])) {
    // User can approve up to 1000
}

// Parametric permission (boolean exact match)
if (AAuth::can('access-reports', [true])) {
    // User has access
}

// Parametric permission (array - allowed values)
if (AAuth::can('manage-department', ['HR'])) {
    // User can manage HR department
}
```

#### Panel-Specific Operations
```php
// Create AAuth for specific panel
$aauth = AAuth::forPanel($user, $roleId, 'admin');

// Auto-detect current panel
$aauth = AAuth::forCurrentPanel($user, $roleId);

// Get switchable roles for panel
$roles = AAuth::switchableRolesForPanel($user, 'admin');
```

#### Organization Hierarchy
```php
// Get accessible nodes
$nodes = AAuth::organizationNodes();

// With filters
$nodes = AAuth::organizationNodesQuery()
    ->where('level', '>=', 2)
    ->get();

// Check descendant
if (AAuth::descendant($parentId, $childId)) {
    // Child is descendant of parent
}
```

#### Role Management
```php
use AuroraWebSoftware\AAuth\Services\RolePermissionService;

$service = app(RolePermissionService::class);

// Give parametric permission to role
$service->givePermissionToRole($roleId, 'approve-budget', [
    'max_amount' => 5000
]);

// Remove permission
$service->removePermissionFromRole($roleId, 'approve-budget');

// Sync permissions
$service->syncPermissionsOfRole($roleId, [
    ['permission' => 'view-reports', 'parameters' => null],
    ['permission' => 'approve-budget', 'parameters' => ['max_amount' => 10000]],
]);
```

## File Structure

### Core Files
- `src/AAuth.php` - Main AAuth class with permission logic
- `src/Facades/AAuth.php` - Laravel Facade
- `src/AAuthServiceProvider.php` - Service provider
- `src/Contracts/AAuthUserContract.php` - User interface

### Models
- `src/Models/Role.php` - Role model with panel support and type (system/organization)
- `src/Models/RolePermission.php` - Parametric permissions with JSON parameters
- `src/Models/OrganizationNode.php` - Hierarchy nodes with nested set pattern
- `src/Models/OrganizationScope.php` - Hierarchy level definitions
- `src/Models/User.php` - Example user model implementing AAuthUserContract
- `src/Models/RoleModelAbacRule.php` - ABAC rules storage

### Services
- `src/Services/RolePermissionService.php` - Role/permission operations
- `src/Services/OrganizationNodeService.php` - Node operations

### Utilities
- `src/Utils/ABACUtil.php` - ABAC rule evaluation (basic)
- `src/Utils/PanelDetector.php` - Filament panel detection

### Observers
- `src/Observers/RoleObserver.php` - Cache invalidation on role changes
- `src/Observers/RolePermissionObserver.php` - Cache invalidation on permission changes

### Middleware
- `src/Http/Middleware/AAuthPermission.php` - Permission middleware
- `src/Http/Middleware/AAuthRole.php` - Role middleware

## Configuration

### Cache Settings
```php
'cache' => [
    'enabled' => env('AAUTH_CACHE_ENABLED', true),
    'store' => env('AAUTH_CACHE_STORE', null), // null = default
    'ttl' => env('AAUTH_CACHE_TTL', 3600),
    'prefix' => env('AAUTH_CACHE_PREFIX', 'aauth'),
],
```

### Super Admin
```php
'super_admin' => [
    'enabled' => env('AAUTH_SUPER_ADMIN_ENABLED', false),
    'column' => env('AAUTH_SUPER_ADMIN_COLUMN', 'is_super_admin'),
],
```

## Known Issues & Roadmap

### Future Improvements (See IMPROVEMENT_ROADMAP.md)
- **ABAC Enhancements**: Depth limit enforcement, operator whitelist, enhanced validation
- **Performance**: Additional query optimizations and batch operations
- **Security**: Enhanced input validation and sanitization

### Recent Fixes
- ✅ Database-agnostic migrations (PostgreSQL, MySQL, SQLite support)
- ✅ Cache invalidation race condition prevention
- ✅ RoleObserver N+1 query optimization
- ✅ Security: serialize() → json_encode() for cache keys
- ✅ Form Request authorization defaults

## Helper Functions

### Available Helpers
```php
// Global helper
aauth_can('permission-name', [params]);

// Blade directives
@aauth('edit-post')
    <button>Edit</button>
@endaauth

@aauth_panel('admin', 'manage-users')
    <a href="/users">Manage Users</a>
@endaauth_panel
```

## Testing Strategy

### Test Organization
- `tests/Unit/V2/` - Core feature tests
  - `AAuthCoreTest.php` - Core functionality (role switching, permissions, organization nodes)
  - `V2FeaturesTest.php` - Parametric permissions and caching
  - `PanelSupportTest.php` - Filament panel integration
  - `ExceptionTest.php` - Exception handling and custom exceptions
  - `MiddlewareTest.php` - Permission and role middleware
- `tests/Unit/` - Service and helper tests
  - `RolePermissionServiceTest.php` - Role/permission service operations
  - `BladeDirectiveTest.php` - Blade directive compilation and rendering

### Test Database
Uses MySQL on port 33062 (configurable in `phpunit.xml.dist`)
Supports database-agnostic tests for PostgreSQL and SQLite compatibility

## Code Review Checklist

When reviewing AAuth changes:
1. ✅ Backward compatibility maintained?
2. ✅ Tests added/updated?
3. ✅ Cache invalidation handled?
4. ✅ Database-agnostic queries?
5. ✅ PHPStan passes?
6. ✅ Documentation updated?
7. ✅ Migration includes both up() and down()?
8. ✅ Observer events triggered?

## Common Patterns

### Adding New Permission
1. No code change needed - permissions are dynamic
2. Just use `givePermissionToRole()` or insert into DB
3. Cache automatically invalidated by observer

### Adding New Feature
1. Add tests first (TDD approach)
2. Implement in `AAuth.php` or create service
3. Update facade if needed
4. Add helper function if user-facing
5. Update `API.md` documentation
6. Run PHPStan and tests

### Performance Optimization
1. Check cache usage - enable in production
2. Use `organizationNodesQuery()` for custom filters (lazy loading)
3. Avoid N+1 - use eager loading in observers
4. Use Laravel Context for request-level caching

## Debugging Tips

### Cache Issues
```php
// Clear specific cache
Cache::forget('aauth:role:1');
Cache::forget('aauth:user:1:switchable_roles');

// Disable cache for debugging
config(['aauth-advanced.cache.enabled' => false]);
```

### Context Issues
```php
// Clear context
AAuth::clearContext();

// Check context
$context = Context::getHidden('aauth_context');
dd($context);
```

### Permission Not Working
1. Check if cache is stale → `php artisan cache:clear`
2. Check role has permission → `$role->rolePermissions`
3. Check parameter validation → debug `validateParameters()`
4. Check super admin bypass → config `super_admin.enabled`

## Git Workflow

### Branch Strategy
- `main` - Stable production releases
- `aauth-v2` - Active development branch
- Feature branches - Created from development branch

### Before Merging to Main
```bash
# Run quality checks
./vendor/bin/pint
./vendor/bin/phpstan analyse --no-progress
./vendor/bin/pest

# Verify backward compatibility
# Update documentation (API.md, UPGRADE.md)
# Update RELEASE_NOTES.md with changes
```

## Package Commands

```bash
# Publish config
php artisan vendor:publish --tag=aauth-config

# Publish migrations
php artisan vendor:publish --tag=aauth-migrations

# Run migrations
php artisan migrate
```

## Environment Variables

```bash
# Cache
AAUTH_CACHE_ENABLED=true
AAUTH_CACHE_STORE=redis
AAUTH_CACHE_TTL=3600
AAUTH_CACHE_PREFIX=aauth

# Super Admin
AAUTH_SUPER_ADMIN_ENABLED=false
AAUTH_SUPER_ADMIN_COLUMN=is_super_admin

# Event Broadcasting
AAUTH_EVENTS_ENABLED=true
```

## Support & Documentation

- **API Documentation**: `API.md` - Complete API reference with examples
- **Upgrade Guide**: `UPGRADE.md` - Migration guide from older versions
- **Implementation Details**: `V2_IMPLEMENTATION_DETAILS.md` - Technical architecture and design decisions
- **Roadmap**: `IMPROVEMENT_ROADMAP.md` - Planned features and improvements
- **Release Notes**: `RELEASE_NOTES.md` - Version history and changes
- **Task Management**: `task.md` - Current development tasks and progress

## Quick Reference

### Permission Types
| Type | Example | Parameters |
|------|---------|------------|
| Simple | `edit-post` | null |
| Integer (max) | `approve-budget` | `{"max_amount": 5000}` |
| Boolean (exact) | `access-reports` | `{"is_admin": true}` |
| Array (allowed) | `manage-department` | `{"departments": ["HR", "IT"]}` |

### Cache Keys
| Type | Key Pattern |
|------|-------------|
| Role | `aauth:role:{role_id}` |
| Role with panel | `aauth:role:{role_id}:panel:{panel_id}` |
| Switchable roles | `aauth:user:{user_id}:switchable_roles` |

### Database Tables
- `roles` - Role definitions
- `role_permission` - v2 parametric permissions
- `user_role_organization_node` - Pivot table
- `organization_nodes` - Hierarchy nodes
- `organization_scopes` - Hierarchy definitions
- `role_model_abac_rules` - ABAC rules (basic)
