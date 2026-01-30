# API Reference

Complete API documentation for AAuth package.

## AAuth Class

The main class for authorization operations.

### Constructor

```php
public function __construct(?AAuthUserContract $user, ?int $roleId, ?string $panelId = null)
```

Creates a new AAuth instance.

| Parameter | Type | Description |
|-----------|------|-------------|
| `$user` | `AAuthUserContract\|null` | The authenticated user |
| `$roleId` | `int\|null` | The role ID to use |
| `$panelId` | `string\|null` | Optional Filament panel ID |

**Throws:**
- `AuthenticationException` - If user is null
- `MissingRoleException` - If roleId is null or role not found
- `UserHasNoAssignedRoleException` - If user doesn't have the specified role

**Example:**
```php
$aauth = new AAuth(Auth::user(), Session::get('roleId'));
```

---

### Static Factory Methods

#### forPanel()

```php
public static function forPanel(AAuthUserContract $user, int $roleId, string $panelId): self
```

Creates AAuth instance for a specific Filament panel.

**Example:**
```php
$aauth = AAuth::forPanel($user, 5, 'admin');
```

#### forCurrentPanel()

```php
public static function forCurrentPanel(AAuthUserContract $user, int $roleId): self
```

Creates AAuth instance with auto-detected Filament panel.

**Example:**
```php
$aauth = AAuth::forCurrentPanel($user, 5);
```

#### detectCurrentPanelId()

```php
public static function detectCurrentPanelId(): ?string
```

Detects current Filament panel ID. Returns `null` if Filament is not installed or no panel is active.

---

### Permission Methods

#### can()

```php
public function can(string $permissionName, mixed ...$arguments): bool
```

Checks if current role has the specified permission.

| Parameter | Type | Description |
|-----------|------|-------------|
| `$permissionName` | `string` | Permission name to check |
| `$arguments` | `mixed` | Optional arguments for parametrized permissions |

**Example:**
```php
if (AAuth::can('edit.users')) {
    // User has permission
}

// With parameters
if (AAuth::can('edit.users', ['max_count' => 10])) {
    // User has parametrized permission
}
```

#### canModel()

```php
public function canModel(string $permissionName, object $model): bool
```

Checks permission with ABAC rules against a specific model instance.

**Example:**
```php
$order = Order::find(1);
if (AAuth::canModel('view.order', $order)) {
    // User can view this specific order
}
```

#### passOrAbort()

```php
public function passOrAbort(string $permissionName): void
```

Checks permission and aborts with 403 if not allowed.

**Throws:** `HttpException` with 403 status code

**Example:**
```php
AAuth::passOrAbort('delete.users');
// If we get here, user has permission
```

#### permissions()

```php
public function permissions(): array
```

Returns all permissions for the current role.

**Returns:** `array` of permission names

---

### Role Methods

#### currentRole()

```php
public function currentRole(): ?Role
```

Returns the current role model.

#### switchableRoles()

```php
public function switchableRoles(): array|Collection
```

Returns all roles the user can switch to.

#### switchableRolesForPanel()

```php
public function switchableRolesForPanel(string $panelId): Collection
```

Returns roles available for a specific panel.

#### switchableRolesForCurrentPanel()

```php
public function switchableRolesForCurrentPanel(): Collection
```

Returns roles available for the current Filament panel.

#### switchableRolesStatic()

```php
public static function switchableRolesStatic(int $userId): array|Collection
```

Static method to get switchable roles by user ID.

#### switchableRolesForPanelStatic()

```php
public static function switchableRolesForPanelStatic(int $userId, string $panelId): Collection
```

Static method to get panel-specific roles by user ID.

---

### Panel Methods

#### getCurrentPanel()

```php
public function getCurrentPanel(): ?string
```

Returns the current panel context (if set).

#### getPanelId()

```php
public function getPanelId(): ?string
```

Returns the role's panel_id from database.

#### isInPanel()

```php
public function isInPanel(string $panelId): bool
```

Checks if currently in a specific panel.

**Example:**
```php
if ($aauth->isInPanel('admin')) {
    // We're in admin panel
}
```

---

### Organization Methods

#### organizationNodes()

```php
public function organizationNodes(bool $includeRootNode = false, ?string $modelType = null): Collection
```

Returns all accessible organization nodes.

| Parameter | Type | Description |
|-----------|------|-------------|
| `$includeRootNode` | `bool` | Include root node in results |
| `$modelType` | `string\|null` | Filter by model type |

#### getAccessibleOrganizationNodes()

```php
public function getAccessibleOrganizationNodes(
    ?int $minDepthFromRoot = null,
    ?int $maxDepthFromRoot = null,
    ?string $scopeName = null,
    ?int $scopeLevel = null,
    bool $includeRootNode = false,
    ?string $modelType = null
): Collection
```

Returns organization nodes with depth and scope filtering.

| Parameter | Type | Description |
|-----------|------|-------------|
| `$minDepthFromRoot` | `int\|null` | Minimum depth (0-based) |
| `$maxDepthFromRoot` | `int\|null` | Maximum depth (0-based) |
| `$scopeName` | `string\|null` | Filter by scope name |
| `$scopeLevel` | `int\|null` | Filter by scope level |
| `$includeRootNode` | `bool` | Include root node |
| `$modelType` | `string\|null` | Filter by model type |

**Example:**
```php
// Get only level 1-2 nodes
$nodes = $aauth->getAccessibleOrganizationNodes(
    minDepthFromRoot: 1,
    maxDepthFromRoot: 2
);

// Get nodes with specific scope
$nodes = $aauth->getAccessibleOrganizationNodes(
    scopeName: 'Region'
);
```

#### organizationNodesQuery()

```php
public function organizationNodesQuery(bool $includeRootNode = false, ?string $modelType = null): Builder
```

Returns query builder for organization nodes (for custom queries).

#### organizationNode()

```php
public function organizationNode(int $nodeId, ?string $modelType = null): OrganizationNode
```

Returns a specific organization node if accessible.

**Throws:** `InvalidOrganizationNodeException` if not accessible

#### organizationNodeIds()

```php
public function organizationNodeIds(): ?array
```

Returns array of accessible organization node IDs.

#### descendant()

```php
public function descendant(int $rootNodeId, int $childNodeId): bool
```

Checks if a node is descendant of another node.

---

### ABAC Methods

#### ABACRules()

```php
public function ABACRules(string $modelType): ?array
```

Returns ABAC rules for a specific model type.

---

### Context Methods

#### loadAndCacheContext()

```php
public function loadAndCacheContext(): void
```

Loads and caches authorization context for the request.

#### clearContext()

```php
public function clearContext(): void
```

Clears the cached authorization context.

---

## RolePermissionService Class

Service for managing roles and permissions.

### Role Management

#### createRole()

```php
public function createRole(array $data): Role
```

Creates a new role.

| Field | Type | Description |
|-------|------|-------------|
| `name` | `string` | Role name (min 3 chars) |
| `organization_scope_id` | `int\|null` | Scope ID (null for system roles) |
| `panel_id` | `string\|null` | Filament panel ID |
| `status` | `string` | Role status |

#### updateRole()

```php
public function updateRole(array $data, int $roleId): Role
```

Updates an existing role.

### Permission Management

#### attachPermissionToRole()

```php
public function attachPermissionToRole(string|array $permissionOrPermissions, int $roleId): bool
```

Attaches permission(s) to a role.

#### detachPermissionFromRole()

```php
public function detachPermissionFromRole(string|array $permissions, int $roleId): bool
```

Removes permission(s) from a role.

#### syncPermissionsOfRole()

```php
public function syncPermissionsOfRole(array $permissions, int $roleId): bool
```

Syncs all permissions for a role (replaces existing).

### User-Role Assignment

#### attachSystemRoleToUser()

```php
public function attachSystemRoleToUser(array|int $roleIdOrIds, int $userId): array
```

Attaches system role(s) to a user.

#### detachSystemRoleFromUser()

```php
public function detachSystemRoleFromUser(array|int $roleIdOrIds, int $userId): int
```

Removes system role(s) from a user.

#### attachOrganizationRoleToUser()

```php
public function attachOrganizationRoleToUser(int $organizationNodeId, int $roleId, int $userId): bool
```

Attaches organization role to user at specific node.

#### detachOrganizationRoleFromUser()

```php
public function detachOrganizationRoleFromUser(int $userId, int $roleId, int $organizationNodeId): int
```

Removes organization role from user.

---

## OrganizationService Class

Service for managing organization structure.

#### createOrganizationScope()

```php
public function createOrganizationScope(array $data): OrganizationScope
```

Creates a new organization scope.

#### createOrganizationNode()

```php
public function createOrganizationNode(array $data): OrganizationNode
```

Creates a new organization node.

---

## Helper Functions

```php
// Permission check
aauth_can(string $permission, ...$arguments): bool

// Create panel-aware instance (uses Auth::user() and Session::get('roleId') internally)
aauth_for_panel(?string $panelId = null): AAuth

// Get panel roles for current user
aauth_panel_roles(?string $panelId = null): Collection

// Check if in panel
aauth_in_panel(string $panelId): bool

// Get current panel ID
aauth_current_panel(): ?string
```

---

## Blade Directives

```blade
{{-- Permission check --}}
@aauth_can('permission.name')
    {{-- Content --}}
@endaauth_can

{{-- Panel context --}}
@panel('admin')
    {{-- Admin panel content --}}
@endpanel

{{-- Panel permission check --}}
@aauth_panel_can('permission.name', 'admin')
    {{-- Content --}}
@endaauth_panel_can
```

---

## Exceptions

| Exception | Description |
|-----------|-------------|
| `AuthorizationException` | Authorization failed |
| `InvalidOrganizationNodeException` | Invalid or inaccessible organization node |
| `InvalidOrganizationScopeException` | Invalid organization scope |
| `InvalidRoleException` | Invalid role |
| `InvalidRoleTypeException` | Invalid role type |
| `InvalidUserException` | Invalid user |
| `MissingRoleException` | Role not found |
| `OrganizationNodeAuthException` | Organization node authorization failed |
| `OrganizationScopesMismatchException` | Organization scopes mismatch |
| `UserHasNoAssignedRoleException` | User has no assigned role |
