## AAuth - Comprehensive Access Control for Laravel

AAuth is a Laravel package that combines **Organization-Based Access Control (OrBAC)**, **Role-Based Access Control (RBAC)**, and **Attribute-Based Access Control (ABAC)** in a single solution. It provides limitless hierarchical organization levels, parametric permissions, and attribute-based filtering for fine-grained access control.

### Core Features

- **OrBAC**: Hierarchical organization tree with unlimited levels using Materialized Path Pattern
- **RBAC**: System and Organization roles with parametric permissions
- **ABAC**: Model-level attribute filtering with JSON rules applied via global scopes
- **Automatic Data Filtering**: Global scopes filter data based on user's authorized organization nodes
- **Middleware**: Route-level permission, role, and organization scope protection
- **Gate Integration**: `Gate::before` callback for Laravel authorization integration
- **Cache**: Configurable caching with automatic invalidation via observers
- **Super Admin**: Bypass all permission checks with configurable column
- **Events**: Lifecycle events for roles and permissions
- **Blade Directives**: `@aauth`, `@aauth_can`, `@aauth_role`, `@aauth_super_admin`
- **Multi-Database**: MySQL, MariaDB, and PostgreSQL compatible

### Installation

@verbatim
<code-snippet name="Install AAuth" lang="bash">
composer require aurorawebsoftware/aauth
php artisan migrate
php artisan vendor:publish --tag="aauth-config"
</code-snippet>
@endverbatim

### Configuration Files

AAuth uses three config files:

- **`config/aauth.php`** - Permission definitions (system and organization permissions)
- **`config/aauth-advanced.php`** - Cache settings, super admin toggle
- **`config/aauth-permissions.php`** - Optional UI-focused permission definitions with parameters

@verbatim
<code-snippet name="Permission Configuration" lang="php">
// config/aauth.php
return [
    'permissions' => [
        'system' => [
            'edit_something_for_system' => 'aauth/system.edit_something_for_system',
        ],
        'organization' => [
            'edit_something_for_organization' => 'aauth/organization.edit_something_for_organization',
        ],
    ],
];
</code-snippet>
@endverbatim

@verbatim
<code-snippet name="Advanced Configuration" lang="php">
// config/aauth-advanced.php
return [
    'super_admin' => [
        'enabled' => false,
        'column' => 'is_super_admin', // column on users table
    ],
    'cache' => [
        'enabled' => true,
        'ttl' => 3600,
        'prefix' => 'aauth',
        'store' => null, // null = default cache driver
    ],
];
</code-snippet>
@endverbatim

@verbatim
<code-snippet name="Parametric Permission Definitions (Optional)" lang="php">
// config/aauth-permissions.php - Used for UI display only
return [
    'posts' => [
        'edit' => [
            'key' => 'posts.edit',
            'description' => 'Edit posts',
            'parameters' => [
                'max_edits_per_day' => [
                    'type' => 'integer',
                    'default' => null,
                    'description' => 'Maximum edits per day',
                ],
                'allowed_statuses' => [
                    'type' => 'array',
                    'default' => ['draft', 'published'],
                    'description' => 'Which statuses can be edited',
                ],
            ],
        ],
    ],
];
</code-snippet>
@endverbatim

### User Model Setup

The User model must implement `AAuthUserContract` and use the `AAuthUser` trait:

@verbatim
<code-snippet name="User Model Setup" lang="php">
use Illuminate\Foundation\Auth\User as Authenticatable;
use AuroraWebSoftware\AAuth\Traits\AAuthUser;
use AuroraWebSoftware\AAuth\Contracts\AAuthUserContract;

class User extends Authenticatable implements AAuthUserContract
{
    use AAuthUser;
}
</code-snippet>
@endverbatim

The `AAuthUser` trait provides: `roles()`, `system_roles()`, `organization_roles()`, `rolesWithOrganizationNodes()`, `getAssignedUserCountAttribute()`, `getDeletableAttribute()`, and overrides `can()` to integrate with AAuth.

The `can()` override supports both string and array abilities:

@verbatim
<code-snippet name="AAuthUser can() Override" lang="php">
// Single permission check (uses AAuth)
$user->can('edit_something');

// Multiple permissions check (all must pass)
$user->can(['edit_something', 'view_something']);
</code-snippet>
@endverbatim

### Making Models Organization-Controllable (OrBAC)

Implement `AAuthOrganizationNodeInterface` and use the `AAuthOrganizationNode` trait:

@verbatim
<code-snippet name="Organization Node Model" lang="php">
use AuroraWebSoftware\AAuth\Interfaces\AAuthOrganizationNodeInterface;
use AuroraWebSoftware\AAuth\Traits\AAuthOrganizationNode;
use Illuminate\Database\Eloquent\Model;

class School extends Model implements AAuthOrganizationNodeInterface
{
    use AAuthOrganizationNode;

    public static function getModelType(): string
    {
        return self::class;
    }

    public function getModelId(): int
    {
        return $this->id;
    }

    public function getModelName(): ?string
    {
        return $this->name;
    }
}
</code-snippet>
@endverbatim

Once a model uses this trait, all queries are **automatically filtered** by the user's authorized organization nodes. `School::all()` returns only schools the user has access to.

### Making Models ABAC-Filterable

Implement `AAuthABACModelInterface` and use the `AAuthABACModel` trait:

@verbatim
<code-snippet name="ABAC Model" lang="php">
use AuroraWebSoftware\AAuth\Interfaces\AAuthABACModelInterface;
use AuroraWebSoftware\AAuth\Traits\AAuthABACModel;
use Illuminate\Database\Eloquent\Model;

class Order extends Model implements AAuthABACModelInterface
{
    use AAuthABACModel;

    public static function getModelType(): string
    {
        return 'order';
    }

    public static function getABACRules(): array
    {
        return [
            '&&' => [
                ['=' => ['attribute' => 'status', 'value' => 'active']],
            ],
        ];
    }
}
</code-snippet>
@endverbatim

### Materialized Path Pattern

AAuth stores the full hierarchy path in a single column for each organization node. This eliminates recursive queries and makes filtering extremely fast.

**Path Format:** `parent_id/current_id` (e.g., `1/5/7`)

@verbatim
<code-snippet name="Hierarchy Example" lang="text">
Organization Node ID: 1 (School District)    Path: 1
├── ID: 5 (School A)                         Path: 1/5
│   ├── ID: 7 (Math Dept)                    Path: 1/5/7
│   │   ├── ID: 9 (Student: John)            Path: 1/5/7/9
│   │   └── ID: 10 (Student: Jane)           Path: 1/5/7/10
│   └── ID: 8 (Science Dept)                 Path: 1/5/8
│       └── ID: 11 (Student: Bob)            Path: 1/5/8/11
└── ID: 6 (School B)                         Path: 1/6
</code-snippet>
@endverbatim

The `AAuthOrganizationNodeScope` global scope automatically adds WHERE clauses using LIKE pattern matching on the path column:

@verbatim
<code-snippet name="Auto-Filtered Query Example" lang="php">
// Teacher assigned to Math Dept (node 7, path: 1/5/7)
$students = Student::all();

// AAuth transforms this to:
// SELECT students.* FROM students
// INNER JOIN organization_nodes ON organization_nodes.model_id = students.id
// WHERE organization_nodes.model_type = 'App\Models\Student'
//   AND (organization_nodes.path LIKE '1/5/7/%' OR organization_nodes.path = '1/5/7')
//
// Returns only John and Jane (not Bob from Science Dept)
</code-snippet>
@endverbatim

### Multiple Organization Node Access

Users can be assigned to **multiple organization nodes simultaneously**. AAuth combines all authorized paths with OR conditions:

@verbatim
<code-snippet name="Multiple Node Assignment" lang="php">
// Assign teacher to BOTH Math and Science departments
$rolePermissionService->attachOrganizationRoleToUser(7, $teacherRole->id, $teacher->id);
$rolePermissionService->attachOrganizationRoleToUser(8, $teacherRole->id, $teacher->id);

// Query automatically combines with OR:
// WHERE (path LIKE '1/5/7/%' OR path = '1/5/7' OR path LIKE '1/5/8/%' OR path = '1/5/8')
$students = Student::all(); // Returns students from BOTH departments
</code-snippet>
@endverbatim

### Creating Organization Structure

@verbatim
<code-snippet name="Create Organization Scope and Node" lang="php">
use AuroraWebSoftware\AAuth\Services\OrganizationService;

$organizationService = new OrganizationService();

$scope = $organizationService->createOrganizationScope([
    'name' => 'School System',
    'level' => 1,
    'status' => 'active',
]);

$node = $organizationService->createOrganizationNode([
    'name' => 'High School A',
    'organization_scope_id' => $scope->id,
    'parent_id' => null, // Root node
]);
</code-snippet>
@endverbatim

`OrganizationService` also provides: `updateOrganizationScope()`, `deleteOrganizationScope()`, `getPath()`, `updateNodePathsRecursively()`, `deleteOrganizationNodesRecursively()`.

### Creating Roles and Permissions

@verbatim
<code-snippet name="Create Role and Attach Permissions" lang="php">
use AuroraWebSoftware\AAuth\Services\RolePermissionService;

$rolePermissionService = new RolePermissionService();

$role = $rolePermissionService->createRole([
    'organization_scope_id' => $organizationScope->id,
    'type' => 'system', // or 'organization'
    'name' => 'System Administrator',
    'status' => 'active',
]);

// Attach single permission
$rolePermissionService->attachPermissionToRole('edit_something', $role->id);

// Attach multiple permissions
$rolePermissionService->attachPermissionToRole([
    'create_something', 'edit_something', 'delete_something',
], $role->id);

// Sync permissions (replaces all existing)
$rolePermissionService->syncPermissionsOfRole(['create_something', 'edit_something'], $role->id);

// Detach permissions
$rolePermissionService->detachPermissionFromRole('edit_something', $role->id);
$rolePermissionService->detachAllPermissionsFromRole($role->id);
</code-snippet>
@endverbatim

### Assigning Roles to Users

@verbatim
<code-snippet name="Assign Roles to Users" lang="php">
// System roles (organization-independent)
$rolePermissionService->attachSystemRoleToUser($role->id, $user->id);
$rolePermissionService->attachSystemRoleToUser([$role1->id, $role2->id], $user->id);
$rolePermissionService->syncUserSystemRoles($user->id, [$role1->id, $role2->id]);
$rolePermissionService->detachSystemRoleFromUser($role->id, $user->id);

// Organization roles (require an organization node)
$rolePermissionService->attachOrganizationRoleToUser(
    $organizationNode->id, $role->id, $user->id
);
$rolePermissionService->detachOrganizationRoleFromUser($user->id, $role->id, $organizationNode->id);
</code-snippet>
@endverbatim

### Session-Based Role Selection

AAuth uses session to track the current active role. The `roleId` must be set in session before using AAuth:

@verbatim
<code-snippet name="Set Active Role in Session" lang="php">
use Illuminate\Support\Facades\Session;

Session::put('roleId', $role->id);
</code-snippet>
@endverbatim

### Using AAuth Facade - Permission Checks

@verbatim
<code-snippet name="Permission Checks" lang="php">
use AuroraWebSoftware\AAuth\Facades\AAuth;

// Check permission
if (AAuth::can('edit_something')) { /* ... */ }

// Check with parametric arguments
if (AAuth::can('posts.edit', 5, 'draft')) { /* ... */ }

// Abort if no permission (returns 401)
AAuth::passOrAbort('edit_something', 'You do not have permission');

// Get all permissions for current role
$permissions = AAuth::permissions();
$orgPermissions = AAuth::organizationPermissions();
$sysPermissions = AAuth::systemPermissions();

// Check super admin
if (AAuth::isSuperAdmin()) { /* bypass all checks */ }

// Get current role
$role = AAuth::currentRole();

// Get switchable roles for current user
$roles = AAuth::switchableRoles();
$roles = AAuth::switchableRolesStatic($userId);
</code-snippet>
@endverbatim

### Using AAuth Facade - Organization Nodes

@verbatim
<code-snippet name="Organization Node Access" lang="php">
// Get all authorized organization nodes
$nodes = AAuth::organizationNodes();
$nodes = AAuth::organizationNodes(includeRootNode: true);
$nodes = AAuth::organizationNodes(modelType: School::class);

// Get query builder for custom queries
$query = AAuth::organizationNodesQuery(includeRootNode: true, modelType: School::class);

// Get specific authorized node (throws InvalidOrganizationNodeException if not authorized)
$node = AAuth::organizationNode(nodeId: 5);

// Get array of authorized node IDs
$ids = AAuth::organizationNodeIds();

// Check descendant relationship
$isDescendant = AAuth::descendant(rootNodeId: 1, childNodeId: 5);

// Advanced: filter by depth and scope
$nodes = AAuth::getAccessibleOrganizationNodes(
    minDepthFromRoot: 1,
    maxDepthFromRoot: 3,
    scopeName: 'Department',
    scopeLevel: 2,
    includeRootNode: true,
    modelType: School::class,
);
</code-snippet>
@endverbatim

### Using AAuth Facade - ABAC Rules

@verbatim
<code-snippet name="ABAC Rule Management" lang="php">
use AuroraWebSoftware\AAuth\Models\RoleModelAbacRule;

// Get ABAC rules for a model type
$rules = AAuth::ABACRules('order');

// Create ABAC rule linking a role to a model type
RoleModelAbacRule::create([
    'role_id' => $role->id,
    'model_type' => Order::getModelType(),
    'rules_json' => [
        '&&' => [
            ['=' => ['attribute' => 'status', 'value' => 'approved']],
            ['>=' => ['attribute' => 'amount', 'value' => 100]],
            ['||' => [
                ['=' => ['attribute' => 'category', 'value' => 'electronics']],
                ['=' => ['attribute' => 'category', 'value' => 'books']],
            ]],
        ],
    ],
]);

// ABAC operators: =, !=, >, <, >=, <=, like
// Logical operators: && (AND), || (OR) - can be nested
</code-snippet>
@endverbatim

### Role Model Methods

The Role model provides direct methods for permission management:

@verbatim
<code-snippet name="Role Model Permission Methods" lang="php">
// Give a permission (with optional parametric arguments)
$role->givePermission('edit_posts');
$role->givePermission('edit_posts', ['max_edits_per_day' => 10, 'allowed_statuses' => ['draft']]);

// Remove a permission
$role->removePermission('edit_posts');

// Sync permissions (replaces all existing)
$role->syncPermissions(['view_posts', 'edit_posts', 'delete_posts']);

// Sync with parameters
$role->syncPermissions([
    'view_posts' => null,
    'edit_posts' => ['max_edits_per_day' => 5],
]);

// Check if role has a permission
if ($role->hasPermission('edit_posts')) { /* ... */ }

// Relationships
$role->rolePermissions;        // HasMany -> RolePermission (with parameters)
$role->abacRules;              // HasMany -> RoleModelAbacRule
$role->organization_scope;     // BelongsTo -> OrganizationScope
$role->organization_nodes;     // BelongsToMany -> OrganizationNode

// Computed attributes
$role->assigned_user_count;    // Number of users assigned to this role
$role->deletable;              // true if no users assigned
</code-snippet>
@endverbatim

### Helper Functions

AAuth provides global helper functions (autoloaded via `src/helpers.php`):

@verbatim
<code-snippet name="Helper Functions" lang="php">
// Get AAuth service instance
$aauth = aauth();

// Check permission
if (aauth_can('edit_something', $param1, $param2)) { /* ... */ }

// Check current role name
if (aauth_has_role('System Administrator')) { /* ... */ }

// Get current active role
$role = aauth_active_role(); // returns ?Role

// Get first active organization node
$node = aauth_active_organization(); // returns ?OrganizationNode

// Check super admin status
if (aauth_is_super_admin()) { /* ... */ }
</code-snippet>
@endverbatim

### Creating Models with Organization Nodes

@verbatim
<code-snippet name="Create, Update, Delete with Organization Node" lang="php">
// Create model and its organization node together
$school = School::createWithAAuthOrganizationNode(
    ['name' => 'New School', 'address' => '123 Main St'],
    $parentOrganizationNodeId,
    $organizationScopeId
);

// Update model and sync organization node (static method)
School::updateWithAAuthOrganizationNode(
    $modelId,
    $nodeId,
    ['name' => 'Updated School'],
    $parentOrganizationNodeId,
    $organizationScopeId
);

// Delete model and its organization node recursively (static method)
School::deleteWithAAuthOrganizationNode($modelId);

// Access the related organization node
$orgNode = $school->relatedAAuthOrganizationNode();

// Get all records without organization scope filtering (instance method)
$all = (new School)->allWithoutAAuthOrganizationNodeScope();
</code-snippet>
@endverbatim

### Middleware

AAuth provides three middleware for route protection:

@verbatim
<code-snippet name="Middleware Usage" lang="php">
// Permission middleware - checks if user has the given permission
Route::get('/students', [StudentController::class, 'index'])
    ->middleware('aauth.permission:view_students');

// Permission with parametric arguments
Route::put('/posts/{post}', [PostController::class, 'update'])
    ->middleware('aauth.permission:posts.edit,5,draft');

// Role middleware - checks if current active role matches
Route::get('/admin', [AdminController::class, 'index'])
    ->middleware('aauth.role:System Administrator');

// Organization scope middleware - checks if user's role belongs to given scope
Route::get('/department', [DeptController::class, 'index'])
    ->middleware('aauth.organization:2'); // organization_scope_id = 2
</code-snippet>
@endverbatim

### Blade Directives

@verbatim
<code-snippet name="Blade Directives" lang="blade">
{{-- Permission check --}}
@@aauth('edit_students')
    <button>Edit</button>
@@endaauth

{{-- Permission check with @if-style syntax --}}
@@aauth_can('create_students')
    <button>Create</button>
@@endaauth_can

{{-- Role check --}}
@@aauth_role('System Administrator')
    <a href="/admin">Admin Panel</a>
@@endaauth_role

{{-- Super admin check --}}
@@aauth_super_admin
    <a href="/super-admin">Super Admin Tools</a>
@@endaauth_super_admin
</code-snippet>
@endverbatim

### Laravel Gate Integration

AAuth integrates with Laravel's built-in authorization via a `Gate::before` callback registered in the service provider:

@verbatim
<code-snippet name="Gate::before Integration" lang="php">
// Registered in AAuthServiceProvider::boot()
Gate::before(function ($user, $ability, $arguments = []) {
    $aauth = app('aauth');

    // Super admin bypasses all permission checks
    if ($aauth->isSuperAdmin()) {
        return true;
    }

    // Delegate to AAuth::can() for all Gate checks
    return $aauth->can($ability, ...$arguments) ?: null;
});

// This means standard Laravel Gate/Policy checks work with AAuth:
Gate::allows('edit_something');
$user->can('edit_something');
@can('edit_something') ... @endcan
</code-snippet>
@endverbatim

### Events

AAuth dispatches events for role and permission lifecycle:

@verbatim
<code-snippet name="Available Events" lang="php">
use AuroraWebSoftware\AAuth\Events\RoleCreatedEvent;       // Role $role
use AuroraWebSoftware\AAuth\Events\RoleUpdatedEvent;       // Role $role
use AuroraWebSoftware\AAuth\Events\RoleDeletedEvent;       // Role $role
use AuroraWebSoftware\AAuth\Events\RoleAssignedEvent;      // int $userId, Role $role, ?OrganizationNode $organizationNode
use AuroraWebSoftware\AAuth\Events\RoleRemovedEvent;       // int $userId, Role $role, ?OrganizationNode $organizationNode
use AuroraWebSoftware\AAuth\Events\RoleSwitchedEvent;      // int $userId, Role $newRole, ?Role $oldRole, ?OrganizationNode $organizationNode
use AuroraWebSoftware\AAuth\Events\PermissionAddedEvent;   // Role $role, string $permission, ?array $parameters
use AuroraWebSoftware\AAuth\Events\PermissionUpdatedEvent; // Role $role, string $permission, ?array $parameters, ?array $oldParameters
use AuroraWebSoftware\AAuth\Events\PermissionRemovedEvent; // Role $role, string $permission

// Listen to events in your EventServiceProvider or listener classes
</code-snippet>
@endverbatim

### Caching

AAuth uses two levels of caching:

1. **Request-level**: Laravel `Context` and in-memory `requestCache` for per-request deduplication
2. **Cross-request**: Configurable cache (via `aauth-advanced.cache`) for role data and switchable roles

Cache is **automatically invalidated** by `RoleObserver` and `RolePermissionObserver` when roles or permissions change. You can also manually clear:

@verbatim
<code-snippet name="Manual Cache Clear" lang="php">
// Clear request-level context cache
app('aauth')->clearContext();
</code-snippet>
@endverbatim

### Bypassing Organization Filtering

@verbatim
<code-snippet name="Bypass Filtering" lang="php">
use AuroraWebSoftware\AAuth\Scopes\AAuthOrganizationNodeScope;

// Remove only organization scope
$allSchools = School::withoutGlobalScope(AAuthOrganizationNodeScope::class)->get();

// Remove all global scopes (including ABAC)
$allSchools = School::withoutGlobalScopes()->get();
</code-snippet>
@endverbatim

### Database Tables

AAuth creates these tables:

- **`roles`** - id, organization_scope_id, type (system/organization), name, status
- **`role_permission`** - id, role_id, permission, parameters (JSON)
- **`user_role_organization_node`** - id, user_id, role_id, organization_node_id
- **`organization_scopes`** - id, name, level, status
- **`organization_nodes`** - id, organization_scope_id, name, model_type, model_id, path, parent_id
- **`role_model_abac_rules`** - id, role_id, model_type, rules_json (JSON)

### Key Enums

@verbatim
<code-snippet name="Enums" lang="php">
use AuroraWebSoftware\AAuth\Enums\RoleType;         // system, organization
use AuroraWebSoftware\AAuth\Enums\ActivityStatus;    // active, passive
use AuroraWebSoftware\AAuth\Enums\ABACCondition;     // =, !=, >, <, >=, <=, like
use AuroraWebSoftware\AAuth\Enums\ABACLogicalOperator; // && (and), || (or)
</code-snippet>
@endverbatim

### Exceptions

@verbatim
<code-snippet name="Exception Classes" lang="php">
use AuroraWebSoftware\AAuth\Exceptions\AuthorizationException;
use AuroraWebSoftware\AAuth\Exceptions\InvalidOrganizationNodeException;
use AuroraWebSoftware\AAuth\Exceptions\InvalidOrganizationScopeException;
use AuroraWebSoftware\AAuth\Exceptions\InvalidRoleException;
use AuroraWebSoftware\AAuth\Exceptions\InvalidRoleTypeException;
use AuroraWebSoftware\AAuth\Exceptions\InvalidUserException;
use AuroraWebSoftware\AAuth\Exceptions\MissingRoleException;
use AuroraWebSoftware\AAuth\Exceptions\OrganizationNodeAuthException;
use AuroraWebSoftware\AAuth\Exceptions\OrganizationScopesMismatchException;
use AuroraWebSoftware\AAuth\Exceptions\UserHasNoAssignedRoleException;
</code-snippet>
@endverbatim

### Internationalization (i18n)

AAuth supports multiple languages for exception messages. Language files are at `resources/lang/{locale}/aauth.php`.

@verbatim
<code-snippet name="i18n" lang="php">
// Available languages: en, tr
// Publish language files:
// php artisan vendor:publish --tag="aauth-lang"

// Exception message keys:
// aauth.authorization, aauth.invalid_location_scopes, aauth.invalid_organization_node,
// aauth.invalid_organization_scope, aauth.invalid_role, aauth.invalid_role_type,
// aauth.invalid_user, aauth.missing_role, aauth.organization_node_auth,
// aauth.organization_scopes_mismatch, aauth.user_has_no_assigned_role
</code-snippet>
@endverbatim

### Observers

AAuth registers two observers that handle cache invalidation and event dispatching:

- **RoleObserver**: On `created` fires `RoleCreatedEvent`, on `updated` clears role cache + fires `RoleUpdatedEvent`, on `deleted` clears role cache + clears affected users' switchable roles cache + fires `RoleDeletedEvent`
- **RolePermissionObserver**: On `created/updated/deleted` clears AAuth context (request cache) + clears role cache + fires `PermissionAddedEvent`/`PermissionUpdatedEvent`/`PermissionRemovedEvent`

### Validation Request Classes

AAuth provides FormRequest classes for validation:

- `StoreRoleRequest` / `UpdateRoleRequest` - name: required, min:3
- `StoreOrganizationNodeRequest` / `UpdateOrganizationNodeRequest` - name: required, min:3; parent_id: required, int
- `StoreOrganizationScopeRequest` / `UpdateOrganizationScopeRequest` - name: required, min:3; level: optional

### Best Practices

1. **Always set `roleId` in session** before using AAuth facade or any model with AAuth traits
2. **Use organization roles** for data belonging to organizational hierarchy
3. **Use system roles** for global permissions independent of organization
4. **Use middleware** for route-level protection instead of manual checks in controllers
5. **Use `AAuth::passOrAbort()`** in controllers/services for programmatic permission checks
6. **Use `withoutGlobalScope()`** when bypassing organization or ABAC filtering for admin operations
7. **Use transactions** when creating models with organization nodes for data consistency
8. **Use parametric permissions** for fine-grained control (e.g., max edits, allowed statuses)
9. **Listen to events** for audit logging and side effects on role/permission changes
10. **Enable cache** in production via `aauth-advanced.cache.enabled` for better performance

### Important Notes

- System permissions can only be assigned to system roles
- Organization permissions can only be assigned to organization roles
- Models using `AAuthOrganizationNode` trait automatically filter data via global scope
- Models using `AAuthABACModel` trait automatically filter data via ABAC global scope
- Organization nodes use path-based hierarchy (e.g., `1/5/7`)
- Users can have multiple roles, but only one active role per session
- The `Gate::before` callback integrates AAuth with Laravel's built-in authorization
- Cache is automatically invalidated when roles or permissions change
- Super admin (when enabled) bypasses all permission checks
