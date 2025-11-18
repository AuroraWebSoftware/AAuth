## AAuth

AAuth is a comprehensive Laravel authentication and authorization package that combines Organization-Based Access Control (OrBAC), Role-Based Access Control (RBAC), and Attribute-Based Access Control (ABAC) in a single, powerful solution. It provides limitless hierarchical organization levels and attribute-based filtering for fine-grained access control.

### Features

- **Organization-Based Access Control (OrBAC)**: Hierarchical organization tree structure with unlimited levels
- **Role-Based Access Control (RBAC)**: System and Organization roles with permissions
- **Attribute-Based Access Control (ABAC)**: Model-level attribute filtering with JSON rules
- **Automatic Data Filtering**: Global scopes automatically filter data based on user's authorized organization nodes
- **Polymorphic Relationships**: Models can be linked to organization nodes polymorphically
- **Blade Directives**: Built-in Blade directives for permission checks in views
- **Multi-Database Support**: MySQL, MariaDB, and PostgreSQL compatible

### Installation

Install AAuth via Composer:

@verbatim
<code-snippet name="Install AAuth" lang="bash">
composer require aurorawebsoftware/aauth
</code-snippet>
@endverbatim

Publish and run migrations:

@verbatim
<code-snippet name="Run Migrations" lang="bash">
php artisan migrate
php artisan vendor:publish --tag="aauth-config"
</code-snippet>
@endverbatim

### User Model Setup

The User model must implement `AAuthUserContract` and use the `AAuthUser` trait;

@verbatim
<code-snippet name="User Model Setup" lang="php">
use Illuminate\Foundation\Auth\User as Authenticatable;
use AuroraWebSoftware\AAuth\Traits\AAuthUser;
use AuroraWebSoftware\AAuth\Contracts\AAuthUserContract;

class User extends Authenticatable implements AAuthUserContract
{
    use AAuthUser;

    // Your model code...
}
</code-snippet>
@endverbatim

### Making Models Organization-Controllable

To make an Eloquent model controllable by organization hierarchy, implement `AAuthOrganizationNodeInterface` and use the `AAuthOrganizationNode` trait:

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

Once a model uses `AAuthOrganizationNode` trait, it automatically filters data based on the current user's authorized organization nodes. Queries like `School::all()` will only return schools the user has access to.

### How Organization-Based Filtering Works

AAuth uses the **Materialized Path Pattern** combined with **Laravel Global Scopes** to automatically filter every database query based on the user's authorized organization nodes. This ensures that users can only access data within their organizational boundaries without writing any additional filtering code.

#### Understanding Materialized Path Pattern

The **Materialized Path Pattern** stores the full path from root to each node in a single column. This eliminates the need for recursive queries and makes hierarchical filtering extremely fast.

**Path Format:** `/parent_id/current_id/`

**Example Hierarchy with Paths:**
```
Organization Node ID: 1 (School District)
├── Path: /1/
├── Organization Node ID: 5 (School A)
│   ├── Path: /1/5/
│   ├── Organization Node ID: 7 (Mathematics Department)
│   │   ├── Path: /1/5/7/
│   │   ├── Organization Node ID: 9 (Student: John Doe)
│   │   │   └── Path: /1/5/7/9/
│   │   └── Organization Node ID: 10 (Student: Jane Smith)
│   │       └── Path: /1/5/7/10/
│   └── Organization Node ID: 8 (Science Department)
│       ├── Path: /1/5/8/
│       └── Organization Node ID: 11 (Student: Bob Wilson)
│           └── Path: /1/5/8/11/
└── Organization Node ID: 6 (School B)
    └── Path: /1/6/
```

**Key Benefits of Materialized Path:**
- **Single Query**: Find all descendants with one LIKE query instead of recursive joins
- **Fast Filtering**: Path matching is index-friendly
- **Simple Logic**: Easy to understand and maintain
- **Scalable**: Works efficiently even with deep hierarchies

#### How Global Scope Applies Materialized Path Filtering

When a model uses `AAuthOrganizationNode` trait, Laravel automatically applies the `AAuthOrganizationNodeScope` to **every query** on that model. This happens transparently - you don't need to remember to add filtering.

**Step-by-Step Process:**

1. **User Role Assignment** - User is assigned a role at a specific organization node
2. **Session Tracking** - Current role ID is stored in session
3. **Path Resolution** - AAuth finds the path of user's authorized organization nodes
4. **Query Modification** - Global scope modifies every query to include path-based filtering
5. **Automatic Filtering** - Only records matching authorized paths are returned

#### Detailed Example: Teacher Querying Students

**Setup:**
@verbatim
<code-snippet name="Teacher Role Assignment" lang="php">
// Teacher is assigned "Teacher" role at Mathematics Department (node ID: 7)
$rolePermissionService->attachOrganizationRoleToUser(
    organizationNodeId: 7,  // Mathematics Department
    roleId: $teacherRole->id,
    userId: $teacher->id
);

// This creates a record in user_role_organization_node:
// user_id: 123, role_id: 5, organization_node_id: 7
</code-snippet>
@endverbatim

**When Teacher Queries Students:**
@verbatim
<code-snippet name="Simple Query with Auto-Filtering" lang="php">
// Teacher writes simple query:
$students = Student::all();

// AAuth Global Scope automatically transforms this to:
// 
// SELECT students.* 
// FROM students
// INNER JOIN organization_nodes 
//     ON organization_nodes.model_id = students.id
// WHERE organization_nodes.model_type = 'App\Models\Student'
//   AND (
//       organization_nodes.path LIKE '/1/5/7/%'  -- All descendants of node 7
//       OR organization_nodes.path = '/1/5/7/'   -- Node 7 itself
//   )
//
// Result: Only returns Student ID 9 and 10 (John Doe, Jane Smith)
// Does NOT return Student ID 11 (Bob Wilson from Science Department)
</code-snippet>
@endverbatim

#### Complex Query Examples

**Example 1: Filtered Query with Additional Conditions**
@verbatim
<code-snippet name="Complex Query with Where Conditions" lang="php">
// Teacher queries students with additional filters:
$activeStudents = Student::where('status', 'active')
    ->where('grade', '>', 70)
    ->orderBy('name')
    ->get();

// AAuth automatically adds organization filtering:
// 
// SELECT students.* 
// FROM students
// INNER JOIN organization_nodes 
//     ON organization_nodes.model_id = students.id
// WHERE students.status = 'active'           -- Your condition
//   AND students.grade > 70                  -- Your condition
//   AND organization_nodes.model_type = 'App\Models\Student'
//   AND (
//       organization_nodes.path LIKE '/1/5/7/%'
//       OR organization_nodes.path = '/1/5/7/'
//   )
// ORDER BY students.name
//
// Returns only active students with grade > 70 from Mathematics Department
</code-snippet>
@endverbatim

**Example 2: Relationship Queries**
@verbatim
<code-snippet name="Relationship Queries with Filtering" lang="php">
// Teacher accesses students through school relationship:
$school = School::find(5);  // High School A
$students = $school->students;  // Eager loading relationship

// AAuth filters both School and Student queries:
// 
// First query (School):
// SELECT schools.* FROM schools
// INNER JOIN organization_nodes ON organization_nodes.model_id = schools.id
// WHERE schools.id = 5
//   AND organization_nodes.model_type = 'App\Models\School'
//   AND (organization_nodes.path LIKE '/1/5/7/%' OR organization_nodes.path = '/1/5/7/')
//
// Second query (Students):
// SELECT students.* FROM students
// INNER JOIN organization_nodes ON organization_nodes.model_id = students.id
// WHERE students.school_id = 5
//   AND organization_nodes.model_type = 'App\Models\Student'
//   AND (organization_nodes.path LIKE '/1/5/7/%' OR organization_nodes.path = '/1/5/7/')
//
// Both queries are automatically filtered!
</code-snippet>
@endverbatim

**Example 3: Aggregations and Counts**
@verbatim
<code-snippet name="Aggregate Queries with Filtering" lang="php">
// Teacher counts students:
$studentCount = Student::count();

// AAuth transforms to:
// SELECT COUNT(*) FROM students
// INNER JOIN organization_nodes ON organization_nodes.model_id = students.id
// WHERE organization_nodes.model_type = 'App\Models\Student'
//   AND (organization_nodes.path LIKE '/1/5/7/%' OR organization_nodes.path = '/1/5/7/')
//
// Returns: 2 (only students from Mathematics Department)

// Average grade calculation:
$avgGrade = Student::avg('grade');
// Automatically calculates average only for authorized students
</code-snippet>
@endverbatim

#### Multiple Organization Nodes Access

AAuth supports assigning a user to **multiple organization nodes simultaneously**, even within the same organization scope. When a user has roles at multiple organization nodes, AAuth automatically combines all authorized paths using **OR conditions** in the WHERE clause, allowing access to data from all assigned organizations.

**Key Concept:**
- A user can be assigned to multiple organization nodes **at the same time**
- These nodes can be at the **same organization scope level** or **different levels**
- All assigned nodes' paths are combined with **OR** operators
- The user gets **combined access** to all descendant data from all assigned nodes

**Example: User Assigned to Multiple Departments**

@verbatim
<code-snippet name="Multiple Organization Nodes Assignment" lang="php">
// Organization Structure:
// School District (ID: 1, path: /1/)
//   └── High School A (ID: 5, path: /1/5/)
//       ├── Mathematics Dept (ID: 7, path: /1/5/7/)
//       │   ├── Student: John (ID: 9, path: /1/5/7/9/)
//       │   └── Student: Jane (ID: 10, path: /1/5/7/10/)
//       └── Science Dept (ID: 8, path: /1/5/8/)
//           └── Student: Bob (ID: 11, path: /1/5/8/11/)

// Assign user to BOTH departments (same organization scope):
$rolePermissionService->attachOrganizationRoleToUser(
    organizationNodeId: 7,  // Mathematics Dept
    roleId: $teacherRole->id,
    userId: $teacher->id
);

$rolePermissionService->attachOrganizationRoleToUser(
    organizationNodeId: 8,  // Science Dept
    roleId: $teacherRole->id,
    userId: $teacher->id
);

// Now user has access to BOTH departments
</code-snippet>
@endverbatim

**How OR Conditions Work:**

When the user queries data, AAuth automatically builds a query that combines all assigned organization node paths with OR conditions:

@verbatim
<code-snippet name="OR Condition Query Building" lang="php">
// User queries students:
$students = Student::all();

// AAuth internally:
// 1. Gets user's organization node IDs: [7, 8]
// 2. Finds paths:
//    - Node 7: /1/5/7/
//    - Node 8: /1/5/8/
// 3. Builds WHERE clause with OR conditions:
//
// SELECT students.* 
// FROM students
// INNER JOIN organization_nodes 
//     ON organization_nodes.model_id = students.id
// WHERE organization_nodes.model_type = 'App\Models\Student'
//   AND (
//       organization_nodes.path LIKE '/1/5/7/%'  -- Mathematics Dept descendants
//       OR organization_nodes.path = '/1/5/7/'   -- Mathematics Dept itself
//       OR organization_nodes.path LIKE '/1/5/8/%'  -- Science Dept descendants
//       OR organization_nodes.path = '/1/5/8/'     -- Science Dept itself
//   )
//
// Result: Returns ALL students from BOTH departments
// - John (ID: 9) from Mathematics
// - Jane (ID: 10) from Mathematics  
// - Bob (ID: 11) from Science
</code-snippet>
@endverbatim

**Same Organization Scope, Multiple Nodes:**

This is particularly useful when a user needs access to multiple branches within the same organizational level:

@verbatim
<code-snippet name="Same Scope Multiple Nodes" lang="php">
// Organization Structure (all under same scope):
// School District (scope level: 1)
//   ├── High School A (node ID: 5, path: /1/5/)
//   │   ├── Mathematics Dept (node ID: 7, path: /1/5/7/)
//   │   └── Science Dept (node ID: 8, path: /1/5/8/)
//   └── High School B (node ID: 6, path: /1/6/)
//       └── Mathematics Dept (node ID: 12, path: /1/6/12/)

// Teacher assigned to Mathematics departments in BOTH schools:
$rolePermissionService->attachOrganizationRoleToUser(7, $teacherRole->id, $teacher->id); // HS A Math
$rolePermissionService->attachOrganizationRoleToUser(12, $teacherRole->id, $teacher->id); // HS B Math

// When querying:
$students = Student::all();

// AAuth builds query:
// WHERE (
//     organization_nodes.path LIKE '/1/5/7/%' OR organization_nodes.path = '/1/5/7/'
//     OR
//     organization_nodes.path LIKE '/1/6/12/%' OR organization_nodes.path = '/1/6/12/'
// )
//
// Returns students from Mathematics departments in BOTH High School A and High School B
</code-snippet>
@endverbatim

**Internal Implementation:**

AAuth's `organizationNodes()` method automatically handles multiple node assignments:

@verbatim
<code-snippet name="Internal OR Condition Implementation" lang="php">
// Inside AAuth::organizationNodes():
// 
// $this->organizationNodeIds = [7, 8]; // User's assigned node IDs
//
// foreach ($this->organizationNodeIds as $organizationNodeId) {
//     $rootNode = OrganizationNode::find($organizationNodeId);
//     // Path: /1/5/7/ for node 7
//     // Path: /1/5/8/ for node 8
//     
//     // Each iteration adds OR condition:
//     $query->orWhere('path', 'like', $rootNode->path . '/%');
//     $query->orWhere('path', '=', $rootNode->path); // if includeRootNode
// }
//
// Final WHERE clause:
// WHERE (
//     path LIKE '/1/5/7/%' OR path = '/1/5/7/'
//     OR
//     path LIKE '/1/5/8/%' OR path = '/1/5/8/'
// )
</code-snippet>
@endverbatim

**Benefits of Multiple Node Assignment:**

1. **Flexible Access Control**: Users can access multiple organizational branches without needing a higher-level role
2. **Granular Permissions**: Different permissions can be assigned per organization node
3. **Efficient Queries**: Single query returns data from all authorized nodes
4. **Automatic Combination**: No manual query building needed - AAuth handles OR logic automatically

**Important Notes:**

- All assigned nodes are **combined with OR**, not AND
- User gets access to **all descendants** of each assigned node
- Path matching is **automatic** - no manual path construction needed
- Works seamlessly with **global scopes** - filtering happens transparently
- **Same role** can be assigned to multiple nodes, or **different roles** can be assigned to different nodes

#### Path-Based Descendant Access Logic

The materialized path pattern automatically grants access to **all descendants** using simple LIKE pattern matching:

@verbatim
<code-snippet name="Path Matching Logic" lang="php">
// User has role at node 7 (path: /1/5/7/)
$authorizedPath = '/1/5/7/';

// AAuth uses LIKE pattern to find all descendants:
// WHERE path LIKE '/1/5/7/%'
//
// This matches:
// ✅ /1/5/7/        (node 7 itself, if includeRootNode = true)
// ✅ /1/5/7/9/      (direct child)
// ✅ /1/5/7/10/     (direct child)
// ✅ /1/5/7/12/15/  (nested descendant, any depth)
//
// Does NOT match:
// ❌ /1/5/8/        (sibling node - different path)
// ❌ /1/6/          (different branch)
// ❌ /1/5/          (parent - path doesn't start with authorized path)
</code-snippet>
@endverbatim

#### Real-World Scenario: School System

**Complete Example:**
@verbatim
<code-snippet name="Complete School System Example" lang="php">
// Organization Structure:
// School District (ID: 1, path: /1/)
//   ├── High School A (ID: 5, path: /1/5/)
//   │   ├── Mathematics Dept (ID: 7, path: /1/5/7/)
//   │   │   ├── Student: John (ID: 9, path: /1/5/7/9/)
//   │   │   └── Student: Jane (ID: 10, path: /1/5/7/10/)
//   │   └── Science Dept (ID: 8, path: /1/5/8/)
//   │       └── Student: Bob (ID: 11, path: /1/5/8/11/)
//   └── High School B (ID: 6, path: /1/6/)
//       └── Mathematics Dept (ID: 12, path: /1/6/12/)

// Scenario 1: Mathematics Teacher at High School A
$mathTeacher = User::find(1);
$rolePermissionService->attachOrganizationRoleToUser(7, $teacherRole->id, $mathTeacher->id);

// Teacher queries:
$myStudents = Student::all();
// Returns: John (ID: 9), Jane (ID: 10)
// Does NOT return: Bob (different dept), or students from High School B

// Scenario 2: Principal at High School A
$principal = User::find(2);
$rolePermissionService->attachOrganizationRoleToUser(5, $principalRole->id, $principal->id);

// Principal queries:
$schoolStudents = Student::all();
// Returns: John, Jane, Bob (all students from High School A)
// Does NOT return: students from High School B

// Scenario 3: District Administrator
$admin = User::find(3);
$rolePermissionService->attachOrganizationRoleToUser(1, $adminRole->id, $admin->id);

// Admin queries:
$allStudents = Student::all();
// Returns: ALL students from entire district (all descendants of node 1)
</code-snippet>
@endverbatim

#### Bypassing Organization Filtering

Sometimes you need to access all records (e.g., for system administrators or reports):

@verbatim
<code-snippet name="Bypass Organization Filtering" lang="php">
use AuroraWebSoftware\AAuth\Scopes\AAuthOrganizationNodeScope;

// Remove only organization scope
$allSchools = School::withoutGlobalScope(AAuthOrganizationNodeScope::class)->get();

// Remove all global scopes (including ABAC if applied)
$allSchools = School::withoutGlobalScopes()->get();

// Use in queries
$totalCount = School::withoutGlobalScope(AAuthOrganizationNodeScope::class)->count();
</code-snippet>
@endverbatim

#### Performance Optimization

The materialized path pattern is highly optimized:

**1. Index-Friendly Queries:**
@verbatim
<code-snippet name="Index Optimization" lang="sql">
-- Recommended index for organization_nodes table:
CREATE INDEX idx_organization_nodes_path ON organization_nodes(path);
CREATE INDEX idx_organization_nodes_model ON organization_nodes(model_type, model_id);

-- LIKE queries with leading path are index-friendly:
-- WHERE path LIKE '/1/5/7/%'  ✅ Uses index
-- WHERE path LIKE '%/7/%'     ❌ Cannot use index efficiently
</code-snippet>
@endverbatim

**2. Single Query Advantage:**
- **Materialized Path**: One query with LIKE pattern
- **Adjacency List**: Requires recursive CTE or multiple queries
- **Nested Sets**: Complex queries, difficult to maintain

**3. Caching Opportunities:**
@verbatim
<code-snippet name="Path Caching Example" lang="php">
// Cache user's authorized paths to avoid repeated queries
$cacheKey = "user_{$userId}_role_{$roleId}_paths";
$authorizedPaths = Cache::remember($cacheKey, 3600, function () use ($userId, $roleId) {
    return AAuth::organizationNodes()->pluck('path')->toArray();
});
</code-snippet>
@endverbatim

#### Key Takeaways

1. **Every Query is Filtered**: Global scope applies to ALL queries automatically
2. **No Manual Filtering Needed**: You write `Student::all()` and filtering happens automatically
3. **Path-Based Hierarchy**: Materialized path makes descendant queries fast and simple
4. **Transparent Operation**: Works with relationships, aggregations, and complex queries
5. **Automatic Descendant Access**: Users automatically get access to all child nodes
6. **Multiple Roles Supported**: Users with multiple roles get combined access
7. **Bypass When Needed**: Use `withoutGlobalScope()` for admin/system operations

### Creating Organization Structure

Use `OrganizationService` to create organization scopes and nodes:

@verbatim
<code-snippet name="Create Organization Scope" lang="php">
use AuroraWebSoftware\AAuth\Services\OrganizationService;

$organizationService = new OrganizationService();

$scope = $organizationService->createOrganizationScope([
    'name' => 'School System',
    'level' => 1,
    'status' => 'active',
]);
</code-snippet>
@endverbatim

@verbatim
<code-snippet name="Create Organization Node" lang="php">
$node = $organizationService->createOrganizationNode([
    'name' => 'High School A',
    'organization_scope_id' => $scope->id,
    'parent_id' => null, // Root node
]);
</code-snippet>
@endverbatim

### Creating Roles and Permissions

Use `RolePermissionService` to manage roles and permissions:

@verbatim
<code-snippet name="Create System Role" lang="php">
use AuroraWebSoftware\AAuth\Services\RolePermissionService;
use AuroraWebSoftware\AAuth\Models\OrganizationScope;

$rolePermissionService = new RolePermissionService();
$organizationScope = OrganizationScope::whereName('Root Scope')->first();

$role = $rolePermissionService->createRole([
    'organization_scope_id' => $organizationScope->id,
    'type' => 'system', // or 'organization'
    'name' => 'System Administrator',
    'status' => 'active',
]);
</code-snippet>
@endverbatim

@verbatim
<code-snippet name="Attach Permissions to Role" lang="php">
// Attach single permission
$rolePermissionService->attachPermissionToRole('edit_something', $role->id);

// Attach multiple permissions
$rolePermissionService->attachPermissionToRole([
    'create_something',
    'edit_something',
    'delete_something',
], $role->id);

// Sync permissions (removes all existing and adds new ones)
$rolePermissionService->syncPermissionsOfRole([
    'create_something',
    'edit_something',
], $role->id);
</code-snippet>
@endverbatim

### Assigning Roles to Users

@verbatim
<code-snippet name="Attach System Role to User" lang="php">
// System roles are organization-independent
$rolePermissionService->attachSystemRoleToUser($role->id, $user->id);
</code-snippet>
@endverbatim

@verbatim
<code-snippet name="Attach Organization Role to User" lang="php">
use AuroraWebSoftware\AAuth\Models\OrganizationNode;

$organizationNode = OrganizationNode::whereName('High School A')->first();

// Organization roles require an organization node
$rolePermissionService->attachOrganizationRoleToUser(
    $organizationNode->id,
    $role->id,
    $user->id
);
</code-snippet>
@endverbatim

### Using AAuth Facade

The AAuth facade provides methods to check permissions and access organization nodes:

@verbatim
<code-snippet name="Check Permissions" lang="php">
use AuroraWebSoftware\AAuth\Facades\AAuth;

// Check if user has permission
if (AAuth::can('edit_something')) {
    // User has permission
}

// Get all permissions for current role
$permissions = AAuth::permissions();

// Abort if user doesn't have permission to check inside contoller or inside services
AAuth::passOrAbort('edit_something', 'You do not have permission');
</code-snippet>
@endverbatim

@verbatim
<code-snippet name="Get Organization Nodes" lang="php">
// Get all authorized organization nodes
$nodes = AAuth::organizationNodes();

// Include root nodes
$nodes = AAuth::organizationNodes(includeRootNode: true);

// Filter by model type
$schoolNodes = AAuth::organizationNodes(modelType: School::class);

// Get query builder for custom queries to attach any query
$query = AAuth::organizationNodesQuery(modelType: School::class);
</code-snippet>
@endverbatim

### Creating Models with Organization Nodes

When creating a model that implements `AAuthOrganizationNodeInterface`, you can create both the model and its organization node together:

@verbatim
<code-snippet name="Create Model with Organization Node" lang="php">
$school = School::createWithAAuthOrganizationNode(
    ['name' => 'New School', 'address' => '123 Main St'],
    $parentOrganizationNodeId,
    $organizationScopeId
);
</code-snippet>
@endverbatim

### Session-Based Role Selection

AAuth uses session to track the current active role. Before using AAuth, ensure the `roleId` is set in the session:

@verbatim
<code-snippet name="Set Active Role" lang="php">
use Illuminate\Support\Facades\Session;

Session::put('roleId', $role->id);
</code-snippet>
@endverbatim

### Permission Configuration

Permissions are defined in `config/aauth.php`:

@verbatim
<code-snippet name="Permission Configuration for system roles and organization separately" lang="php">
return [
    'permissions' => [
        'system' => [
            'edit_something_for_system' => 'aauth/system.edit_something_for_system',
            'create_something_for_system' => 'aauth/system.create_something_for_system',
        ],
        'organization' => [
            'edit_something_for_organization' => 'aauth/organization.edit_something_for_organization',
            'create_something_for_organization' => 'aauth/organization.create_something_for_organization',
        ],
    ],
];
</code-snippet>
@endverbatim

### Best Practices

1. **Always set roleId in session** before using AAuth facade or service
2. **Use organization roles** for data that belongs to organizational hierarchy
3. **Use system roles** for global permissions that don't depend on organization
4. **Implement interface methods correctly** when using `AAuthOrganizationNode` trait
5. **Use `withoutGlobalScopes()`** when you need to bypass organization and abac filtering
6. **Validate organization scope levels** when creating hierarchical structures
7. **Use transactions** when creating models with organization nodes to ensure data consistency

### Important Notes

- System permissions can only be assigned to system roles
- Organization permissions can only be assigned to organization roles
- Models using `AAuthOrganizationNode` trait automatically filter data via global scope
- Organization nodes use path-based hierarchy (e.g., `/1/2/3`)
- Users can have multiple roles, but only one active role per session

