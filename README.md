# AAuth for Laravel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/aurorawebsoftware/aauth.svg?style=flat-square)](https://packagist.org/packages/aurorawebsoftware/aauth)
[![Tests](https://github.com/aurorawebsoftware/aauth/actions/workflows/tests.yml/badge.svg?branch=main)](https://github.com/aurorawebsoftware/aauth/actions/workflows/tests.yml)
[![Code Style](https://github.com/aurorawebsoftware/aauth/actions/workflows/code-style.yml/badge.svg?branch=main)](https://github.com/aurorawebsoftware/aauth/actions/workflows/code-style.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/aurorawebsoftware/aauth.svg?style=flat-square)](https://packagist.org/packages/aurora/aauth)

Organization Based (OrBAC) , Attibute Based (ABAC) , Rol-Permission (RBAC)  Based Authentication Methods Combined **Laravel Auth Package** with Limitless Hierarchical Level of Organizations and Limitless Attribute Conditions

# Features

- Organization Based Access Controllable (OrBAC) Eloquent Models
- Attribute Based Access Controllable (ABAC) Eloquent Models
- Role Based Access Control (RoBAC)
- Permissions Based Access Control
- Lean & Non-Complex Architecture
- PolyMorphic Relationships of Model & Organization Node
- DB Row Level Filtering for the Role with ABAC
- Built-in Blade Directives for permission control inside **Blade** files
- Mysql, MariaDB, Postgres Support
- Community Driven and Open Source Forever

---


# Installation

You can install the package via composer:

```bash
composer require aurorawebsoftware/aauth
```

You must add AAuthUser Trait to the User Model and User Model must implement AAuthUserContract

```php
use Illuminate\Foundation\Auth\User as Authenticatable;
use AuroraWebSoftware\AAuth\Traits\AAuthUser;
use AuroraWebSoftware\AAuth\Contracts\AAuthUserContract;

class User extends Authenticatable implements AAuthUserContract
{
    use AAuthUser;

    // ...
}
```

You can publish and run the migrations with:

```bash
php artisan migrate
```

You can publish the sample data seeder with:

```bash
php artisan vendor:publish --tag="aauth-seeders"
php artisan db:seed --class=SampleDataSeeder
```

Optionally, You can seed the sample data with:

```bash
php artisan db:seed --class=SampleDataSeeder
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="aauth-config"
```

This is the example contents of the published config file:

```php
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
```

# Main Philosophy of AAuth OrBAC

In computer system security, there are several approaches to restrict system access to authorized users.

Most used and known *access control method* is Rol Based Access Control (RoBAC).

In most circumstances, it's sufficient for software projects.
Basically; Roles and Permissions are assigned to the Users, The data can be accessed horizontally as single level

What if your data access needs are further more than one level?
and what if you need to restrict and filter the data in organizational and hierarchical manner?

Let's assume we need to implement a multi-zone, multi-level school system and be our structure like this.

- Türkiye
    - A High School
        - Class 1A
        - Class 2A
    - B High School
        - Class 1A
- Germany
    - X High School
        - Class 1B
        - Class 2B

How can you restrict A High School's data from X High School Principal and Teachers?

How can you give permissions to a Class Teacher to see their students **only** ?

What if we need another level of organization in the future like this?
and want to give access to see students data under their responsibility only for Europe Zone Principal, Türkiye
Principal dynamically *without writing one line of code?*

- Europe
    - Türkiye
        - A High School
            - Class 1A
            - Class 2A
        - B High School
            - Class 1A
    - Germany
        - X High School
            - Class 1B
            - Class 2B
- America
    - USA
        - ....
        - ....
    - Canada
        - .....

# Main Philosophy of AAuth ABAC

In the context of the AAuth package, Attribute-Based Access Control (ABAC) is an advanced system for creating dynamic, attribute-based filtering scopes for your Eloquent models. It allows you to define granular access rules that operate directly on the **attributes of the models themselves**. When a user attempts to retrieve data, these rules are automatically applied to the database queries, ensuring that only records matching the specified attribute conditions for the user's current role are returned.

**Core Concepts in AAuth's ABAC:**

*   **Model Attributes:** These are the characteristics of your Eloquent model instances, directly corresponding to the columns in your database tables (e.g., an `Order` model might have attributes like `status`, `amount`, `created_at`, `customer_id`). AAuth's ABAC rules are built around evaluating these attributes.
*   **Rules/Policies:** In AAuth, ABAC rules are structured statements that define conditions based on model attributes. For example, a rule might state "only allow access to `Order` models where the `status` attribute is 'completed' AND the `amount` attribute is greater than 100." These rules are stored and associated with user roles (see "Managing ABAC Rules and Associations") but the conditions within the rules operate on the attributes of the Eloquent model being queried. The syntax for these rules is detailed in "Defining ABAC Rules".
    *   While traditional ABAC systems might also heavily feature subject attributes (e.g., user's department, security clearance) and environment conditions (e.g., time of day) as direct inputs into the rule *structure*, AAuth's primary focus for its ABAC *rule definitions* is on the attributes of the data/model itself. External factors like user properties or current time can be incorporated by dynamically constructing the rule's *values* before they are stored or used, but the rule engine itself primarily processes conditions against model attributes.

**Benefits of ABAC in AAuth:**

Framed by AAuth's model-centric approach, the benefits include:

*   **Fine-Grained Data Filtering:** ABAC enables highly specific filtering of Eloquent model records, moving beyond broad role-based permissions to control access down to individual records based on their attributes.
*   **Dynamic Query Modification:** Access to data can change dynamically as the attributes of the model records change, without needing to redefine roles or permissions. For instance, if an `Invoice` model's `payment_status` attribute changes to 'paid', an ABAC rule can automatically restrict access for roles that should only see unpaid invoices.
*   **Reduced Complexity in Roles:** Instead of creating numerous specific roles for slightly different data access needs, ABAC allows for fewer roles, with the data visibility for those roles being dynamically shaped by attribute-based rules.
*   **Centralized Access Logic for Data Records:** Rules pertaining to data attributes are defined and managed in a structured way, making it easier to understand and audit who can access what data.

**ABAC in AAuth: How It Works**

AAuth implements its ABAC capabilities by allowing you to:
1.  Define rules based on the attributes of your Eloquent models (e.g., for an `Order` model, rules might use `Order->status` or `Order->amount`).
2.  Associate these rules with specific user roles.
3.  Automatically apply these rules as database query conditions whenever data is fetched for an ABAC-enabled model.

For example, a rule might specify that users with a "Support Tier 1" role can only access `Ticket` models where the `Ticket->priority` is 'low' and `Ticket->is_open` is true. If a `Ticket`'s `priority` changes, or it's closed, it automatically falls out of scope for that user role. This powerful filtering is applied seamlessly, as detailed in the "Automatic Query Filtering (ABAC)" section.

This model-attribute-focused ABAC complements the Organization-Based Access Control (OrBAC) and Role-Based Access Control (RBAC) features of AAuth, allowing for a robust, multi-layered access control strategy.


---
**AAuth may be your first class assistant package.**

---
> If you don't need organizational roles, **AAuth** may not be suitable for your work.
---

# AAuth Terminology

Before using AAuth its worth to understand the main terminology of AAuth.
AAuth differs from other Auth Packages due to its organizational structure.

## What is Organization?

Organization is a kind of term
which refers to hierarchical arrangement of eloquent models in sequential tree.

It consists of a central root organization node, and sub organization nodes,
which are connected via edges.
We can also say that organization tree has one root node, many sub organization nodes polymorphic-connected with one
eloquent model.

## Organization Scope

In Organization Tree, each node has an organization scope.
Organization scope has a level property to determine the level of the organization node in the tree.

## Organization Node

Each node in the organization tree means organization node.
Each Organization Node is an Eloquent Model.
Organization Node can be polymorphic-related with an Eloquent Model.

## Permission

In This Package there are 2 types of Permissions.

1. System Permissions
2. Organization Permissions

**System Permission** is plain permission non-related to the organization which is useful for system related access
controls like backup_db, edit_website_logo, edit_contact_info etc..
A System permission can only be assigned to a System Role. System Permissions should be added inside `aauth.php` config
file's permission['system'] array.

**Organization Permission** is hierarchical controllable permission. An Organization permission can only be assigned to
an Organization Role.
Organization Permissions should be added inside `aauth.php` config file's permission['organization'] array.

## ABAC
Attribute-Based Access Control. AAuth provides comprehensive ABAC features, allowing for fine-grained access control based on model attributes. For a detailed explanation, see the sections:
- "Main Philosophy of AAuth ABAC"
- "Using ABAC Interface and Trait with Eloquent Models"
- "Defining ABAC Rules"
- "Managing ABAC Rules and Associations"
- "Automatic Query Filtering (ABAC)"

## Role

Roles are assigned to users. Each User can have multiple roles.

In This Package there are 2 types of Roles.

1. System Roles
2. Organization Roles

**System Role** is plain role for non-related to the organization which is useful for system related users like system
admin, super admin etc..

**Organization Role** is hierarchical position of a User in Organization Tree.
An Organization Role can be assigned to a user with 3 parameters.

- user_id (related user's id)
- role_id
- organization_node_id (id of the organization node which defines the position of the user's role on the organization
  Tree)

> ! it can be a little overwhelming at the first, but it is not complex lol. :)

## User

Just a usual Laravel User.
AAuthUser trait must be added to Default User Model.

## Permission Config File

Permissions are stored inside `config/aauth.php` which is published after installing.

### Model - Organization Node Relations

Each Organization Node can have a polymorphic relationship with an Eloquent Model. By doing this, an Eloquent Model can
be an organization node and can be access controllable.

It means that; Only Authorized User Role can be access the relating model, or in other words, Each role only can access
the models which is on Authenticated Sub-Organization Tree of User's Role.

### Defining ABAC Rules

Attribute-Based Access Control (ABAC) rules in AAuth determine whether a user has access to a specific Eloquent model instance based on its attributes. These rules are defined as a PHP array, which can also be represented as a JSON string that decodes into the equivalent array structure. This allows for dynamic rule creation and storage.

The rules are typically defined within your ABAC-enabled Eloquent model by implementing the `getABACRules(): array` method from the `AAuthABACModelInterface`. These rules are then automatically applied by the `AAuthABACModelScope` when querying the model.

**Overall Structure:**

An ABAC rule set is fundamentally a nested array structure that always starts with a top-level logical operator, either `&&` (AND) or `||` (OR). This operator dictates how the conditions or condition groups directly under it are evaluated.

*   If the top-level operator is `&&`, all direct child conditions/groups must evaluate to true.
*   If the top-level operator is `||`, at least one direct child condition/group must evaluate to true.

Each element within the top-level operator's array is either a single condition or another nested group of conditions (which itself starts with a logical operator).

**Example of basic structure:**

```php
[
    // Top-level logical operator
    '&&' => [
        // Condition 1
        ['=' => ['attribute' => 'status', 'value' => 'active']],
        // Condition 2
        ['>' => ['attribute' => 'amount', 'value' => 100]],
        // Nested group of conditions
        ['||' => [
            ['=' => ['attribute' => 'category', 'value' => 'electronics']],
            ['=' => ['attribute' => 'category', 'value' => 'books']]
        ]]
    ]
]
```

In this example:
Access is granted if (`status` is 'active' **AND** `amount` is greater than 100) **AND** (`category` is 'electronics' **OR** `category` is 'books').

**Logical Operators:**

Logical operators define how multiple conditions are combined.

*   **`&&` (AND):**
    All conditions or nested groups within this block must be true for this part of the rule to be satisfied.
    ```php
    [
        '&&' => [
            // Condition A
            // Condition B
        ]
    ]
    // Both Condition A AND Condition B must be true.
    ```

*   **`||` (OR):**
    At least one of the conditions or nested groups within this block must be true for this part of the rule to be satisfied.
    ```php
    [
        '||' => [
            // Condition X
            // Condition Y
        ]
    ]
    // Either Condition X OR Condition Y (or both) must be true.
    ```

**Nesting Logical Operators:**
You can nest these operators to create complex logic:

```php
[
    '&&' => [ // Outer AND
        ['=' => ['attribute' => 'is_published', 'value' => true]], // Condition 1
        ['||' => [ // Inner OR
            ['=' => ['attribute' => 'visibility', 'value' => 'public']], // Condition 2a
            ['=' => ['attribute' => 'owner_id', 'value' => '$USER_ID']] // Condition 2b (assuming $USER_ID is a placeholder you'd replace)
        ]]
    ]
]
// Access if: is_published is true AND (visibility is 'public' OR owner_id matches the user's ID)
```

**Conditional Operators:**

Conditional operators are used to compare a model's attribute with a specific value. The `AAuthABACModelScope` leverages Laravel's underlying database query builder, so standard SQL comparison operators are generally available. Common ones include:

*   `=` : Equal to.
*   `!=` or `<>` : Not equal to.
*   `>` : Greater than.
*   `<` : Less than.
*   `>=` : Greater than or equal to.
*   `<=` : Less than or equal to.
*   `LIKE` : Simple string matching (e.g., `value` can be `'%' . $searchTerm . '%'`).
*   `NOT LIKE` : Negated string matching.
*   `IN` : Value is within a given array. The `value` for an `IN` condition should be an array.
*   `NOT IN` : Value is not within a given array. The `value` for a `NOT IN` condition should be an array.

**Condition Structure:**

Each individual condition is an array where the key is the conditional operator, and the value is another array containing `attribute` and `value` keys.

```php
[
    // Conditional Operator (e.g., '=')
    '=' => [
        'attribute' => 'model_column_name', // The column name on your Eloquent model
        'value'     => 'the_value_to_compare_against'
    ]
]
```

*   `attribute`: A string representing the name of the attribute (database column) on the Eloquent model being queried.
*   `value`: The value to compare the attribute against. This can be a string, number, boolean, or an array (especially for `IN` and `NOT IN` operators).

**Practical Examples:**

1.  **Allow if `status` is 'active':**
    ```php
    [
        '&&' => [
            ['=' => ['attribute' => 'status', 'value' => 'active']]
        ]
    ]
    ```
    *(Note: Even for a single condition, it must be wrapped in a top-level logical operator.)*

2.  **Allow if `order_value` is greater than or equal to 1000:**
    ```php
    [
        '&&' => [
            ['>=' => ['attribute' => 'order_value', 'value' => 1000]]
        ]
    ]
    ```

3.  **Allow if `is_urgent` is true AND `priority` is greater than 5:**
    ```php
    [
        '&&' => [
            ['=' => ['attribute' => 'is_urgent', 'value' => true]],
            ['>' => ['attribute' => 'priority', 'value' => 5]]
        ]
    ]
    ```

4.  **Allow if `department` is 'sales' OR `department` is 'support':**
    This can be written in two ways:
    Using `||`:
    ```php
    [
        '||' => [
            ['=' => ['attribute' => 'department', 'value' => 'sales']],
            ['=' => ['attribute' => 'department', 'value' => 'support']]
        ]
    ]
    ```
    Using `IN`:
    ```php
    [
        '&&' => [ // Top level can be && if this is the only group
            ['IN' => ['attribute' => 'department', 'value' => ['sales', 'support']]]
        ]
    ]
    ```

5.  **Complex: Allow if (`type` is 'document' AND `file_format` is 'pdf') OR (`type` is 'image' AND `resolution` is 'high'):**
    ```php
    [
        '||' => [ // Top-level OR
            [ // First group: document conditions
                '&&' => [
                    ['=' => ['attribute' => 'type', 'value' => 'document']],
                    ['=' => ['attribute' => 'file_format', 'value' => 'pdf']]
                ]
            ],
            [ // Second group: image conditions
                '&&' => [
                    ['=' => ['attribute' => 'type', 'value' => 'image']],
                    ['=' => ['attribute' => 'resolution', 'value' => 'high']]
                ]
            ]
        ]
    ]
    ```

6.  **Example adapted from `README-abac.md` (made concrete):**
    Original abstract idea:
    ```json
    // {
    //     "&&": [
    //         { "==": [ "$attribute", "asd" ] },
    //         { "==": [ "$attribute", "asd" ] },
    //         { "||": [
    //                 { "==": [ "$attribute", "asd" ] },
    //                 { "==": [ "$attribute", "asd" ] }
    //             ]
    //         }
    //     ]
    // }
    ```
    Concrete AAuth PHP array:
    ```php
    [
        '&&' => [
            ['=' => ['attribute' => 'product_category', 'value' => 'electronics']],
            ['=' => ['attribute' => 'brand', 'value' => 'AwesomeBrand']],
            [
                '||' => [
                    ['=' => ['attribute' => 'region', 'value' => 'EU']],
                    ['=' => ['attribute' => 'region', 'value' => 'US']]
                ]
            ]
        ]
    ]
    // Access if: category is 'electronics' AND brand is 'AwesomeBrand' AND (region is 'EU' OR region is 'US')
    ```

By defining these rules in the `getABACRules()` method of your ABAC-enabled models, AAuth will automatically filter database queries to ensure users can only access records that meet the specified attribute conditions for their role.


# Usage

Before using this, please make sure that you published the config files.

## AAuth Services, Service Provider and `roleId` Session and Facade

AAuth Services are initialized inside AAuthService Provider.

roleId session must be set before initializing **AAuth** Service.
`AAuthServiceProvider.php`

```php
$this->app->singleton('aauth', function ($app) {
    return new AAuth(
        Auth::user(),
        Session::get('roleId')
    );
});
```

there is also a AAuth Facade to access AAuth Service class statically.
Example;

```php
AAuth::can();
```

##  OrganizationService

Organization Service is used for organization related jobs.
The service can be initialized as

```php
$organizationService = new OrganizationService()
```

or via dependency injecting

```php
public function index(OrganizationService $organizationService)
{
    .....
}
```

### Creating an Organization Scope
```php
$data = [
    'name' => 'Org Scope1',
    'level' => 5,
    'status' => 'active',
];

$organizationService->createOrganizationScope($data);
```

### Updating an Organization Scope
// todo help wanted

### Deleting an Organization Scope
// todo help wanted


### Creating an Organization Node without Model Relationship
```php

$orgScope = OrganizationScope::first();

$data = [
    'name' => 'Created Org Node 1',
    'organization_scope_id' => $orgScope->id,
    'parent_id' => 1,
];

$organizationService->createOrganizationNode($data);
```

### Updating an Organization Node
// todo help wanted

### Deleting an Organization Node
// todo help wanted

##  Role Permission Service

This Service is used for role related jobs.
The service can be initialized as

```php
$rolePermissionService = new RolePermissionService()
```

or via dependency injecting

```php
public function index(RolePermissionService $rolePermissionService)
{
    .....
}
```
### Creating a Role
```php
$organizationScope = OrganizationScope::whereName('Root Scope')->first();

$data = [
    'organization_scope_id' => $organizationScope->id,
    'type' => 'system',
    'name' => 'Created System Role 1',
    'status' => 'active',
];

$createdRole = $rolePermissionService->createRole($data);
```

### Updating a Role
// todo help wanted

### Deleting a Role
// todo help wanted

### Attaching a Role to a User
```php
$role = Role::whereName('System Role 1')->first();
$permissionName = 'test_permission1';

$rolePermissionService->attachPermissionToRole($permissionName, $role->id);
```

### Syncing All Permissions for a Role
```php
$role = Role::whereName('System Role 1')->first();
$permissionName1 = 'test_permission1';
$permissionName2 = 'test_permission2';
$permissionName3 = 'test_permission3';

$rolePermissionService->syncPermissionsOfRole(
    compact('permissionName1', 'permissionName2', 'permissionName3'),
    $role->id
);
```

### Detaching Permission from a Role
```php
$rolePermissionService->detachSystemRoleFromUser($role->id, $user->id);
```

### Creating an Organization Role and Attaching to a User
```php
$organizationScope = OrganizationScope::whereName('Root Scope')->first();
$organizationNode = OrganizationNode::whereName('Root Node')->first();

$data = [
    'organization_scope_id' => $organizationScope->id,
    'type' => 'organization',
    'name' => 'Created Organization Role 1 for Attaching',
    'status' => 'active',
];

$createdRole = $rolePermissionService->createRole($data);
$rolePermissionService->attachOrganizationRoleToUser($organizationNode->id, $createdRole->id, $user->id);
```

### Creating a System Role and Attaching to a User
// todo help wanted


## Using AAuth Interface and Trait with Eloquent Models
To turn an Eloquent Model into an AAuth Organization Node; Model must implement `AAuthOrganizationNodeInterface` and use `AAuthOrganizationNode` Trait.
After adding `AAuthOrganizationNode` trait, you will be able to use AAuth methods within the model

```php
namespace App\Models\ExampleModel;

use AuroraWebSoftware\AAuth\Interfaces\AAuthOrganizationNodeInterface;
use AuroraWebSoftware\AAuth\Traits\AAuthOrganizationNode;
use Illuminate\Database\Eloquent\Model;

class ExampleModel extends Model implements AAuthOrganizationNodeInterface
{
    use AAuthOrganizationNode;
    
    // implementation
}
```

## Using ABAC Interface and Trait with Eloquent Models

To make your Eloquent models controllable via Attribute-Based Access Control (ABAC) with AAuth, you need to implement an interface and use a specific trait. This allows AAuth to understand how to apply attribute-based rules to your models.

**Requirements:**

1.  Your Eloquent model **must** implement the `AuroraWebSoftware\AAuth\Contracts\AAuthABACModelInterface`.
2.  Your Eloquent model **must** use the `AuroraWebSoftware\AAuth\Traits\AAuthABACModel` trait.

**Implementation Example:**

Here's an example of how to set up an Eloquent model (e.g., `Order`) for ABAC:

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use AuroraWebSoftware\AAuth\Contracts\AAuthABACModelInterface;
use AuroraWebSoftware\AAuth\Traits\AAuthABACModel;

class Order extends Model implements AAuthABACModelInterface
{
    use AAuthABACModel;

    // Your model's other properties and methods...

    /**
     * Get the type of the model for ABAC.
     * This is typically a string that identifies your model.
     *
     * @return string
     */
    public static function getModelType(): string
    {
        return 'order'; // Or any unique string identifier for this model type
    }

    /**
     * Define the ABAC rules for this model.
     * These rules determine how access is granted based on attributes.
     * The detailed structure and syntax for these rules are covered in the "Defining ABAC Rules" section.
     * This method can serve as a fallback or default if no specific rule is found for the current user's role
     * via the `RoleModelAbacRule` model, or it can be the primary source if role-specific ABAC rules are not used.
     *
     * @return array
     */
    public static function getABACRules(): array
    {
        // Example: Return an empty array as a placeholder, or define default/fallback rules here.
        // For instance, to allow access if 'status' is 'active' by default:
        // return [
        //     '&&' => [
        //         ['=' => ['attribute' => 'status', 'value' => 'active']]
        //     ]
        // ];
        // If no role-specific rule is found via RoleModelAbacRule, these rules (if any) might be applied.
        // If you exclusively use RoleModelAbacRule for all ABAC logic, this method can safely return an empty array.
        return [];
    }
}
```

This setup prepares your model to have ABAC rules applied to it. The `getModelType()` method provides a string identifier for your model type, which can be used in rule definitions. The `getABACRules()` method is where you can define default or fallback attribute conditions for accessing instances of this model. While the primary mechanism for applying role-specific rules is via the `RoleModelAbacRule` model (see "Managing ABAC Rules and Associations"), these model-defined rules can act as a base or default. The detailed format for the rule syntax is covered in the "Defining ABAC Rules" section.

## AAuth Service and Facade Methods
// todo

### Current Roles All Permissions
current user's selected roles permissions with **AAuth Facade**
```php
$permissions = AAuth::permissions();
```

### Check allowed permission with can() method
```php
AAuth::can('create_something_for_organization');
```
```php
if (AAuth::can('create_something_for_organization')) {
    // codes here
}
```

### Check permission and abort if not user and current allowed
```php
AAuth::passOrAbort('create_something_for_organization');
```

### Get all permitted organization nodes
it will return OrganizationNode collection.

organizationNodes(bool $includeRootNode = false, ?string $modelType = null): \Illuminate\Support\Collection

```php
$organizationNodes = AAuth::organizationNodes();
```

### Get one specified organization node
// todo help wanted

### Descendant nodes can be checked
with this method you can check is a organization node is descendant of another organization node.
in other words, checks if node is sub-node of specified node.

```php
$isDescendant = AAuth::descendant(1, 3);
```

### Creating an Organization Node-able Model and Related Org. Node
with this method, you can create a model and organization node with relationship together.
```php
$data = ['name' => 'Test Organization Node-able Example'];

$createdModel = ExampleModel::createWithAAuthOrganizationNode($data, 1, 2);
```

### Getting Related Organization Node of Model
```php
$exampleModel = ExampleModel::find(1);
$relatedOrganizationModel = $exampleModel->relatedAAuthOrganizationNode()
```

## Getting authorized Models only. (OrBAC)

after adding `AAuthOrganizationNode` trait to your model, you are adding a global scope which filters the permitted data.

Thus, you can simply use any eloquent model method without adding anything

```php
ExampleModel::all();
```

## Managing ABAC Rules and Associations

While the "Defining ABAC Rules" section explains the *structure* of ABAC rules, this section details how those rules are associated with specific roles and managed within the AAuth system. AAuth uses a dedicated Eloquent model to store these associations, allowing for dynamic and granular control over which rules apply to which roles for different types of models.

**1. Association with Roles: The `RoleModelAbacRule` Model**

ABAC rules are not globally applied or solely defined in the model's `getABACRules()` method (though that method can serve as a default or fallback). Instead, for role-specific ABAC, rules are linked to roles via the `AuroraWebSoftware\AAuth\Models\RoleModelAbacRule` Eloquent model.

This model has the following key fields:

*   `role_id`: The ID of the `Role` to which this specific ABAC rule applies.
*   `model_type`: A string that identifies the ABAC-enabled Eloquent model. This string **must** match the value returned by the static `getModelType()` method on your ABAC-enabled model (e.g., `'order'`, `'post'`, `'product'`). This ensures the rules are applied to the correct model.
*   `rules_json`: A JSON field where the actual ABAC rule array (as documented in "Defining ABAC Rules") is stored. When creating or updating, you can typically provide a PHP array, and Eloquent will handle the JSON conversion if the model's casts are set up appropriately (which is common for JSON fields).

**2. Creating and Assigning Rules**

To apply a specific set of ABAC rules to a role for a particular model, you create an instance of `RoleModelAbacRule`.

**Example:**

Let's say you have an `Order` model that is ABAC-enabled (implements `AAuthABACModelInterface` and uses `AAuthABACModel` trait, with `Order::getModelType()` returning `'order'`). You want to create a rule for a specific role (e.g., "Regional Manager") that only allows them to see 'approved' orders with an amount greater than or equal to 100.

```php
use AuroraWebSoftware\AAuth\Models\Role;
use AuroraWebSoftware\AAuth\Models\RoleModelAbacRule;
use App\Models\Order; // Your ABAC-enabled Order model

// Assume $regionalManagerRole is an existing Role instance
$regionalManagerRole = Role::where('name', 'Regional Manager')->first();

if ($regionalManagerRole) {
    // Define the ABAC rules for the 'Order' model specifically for this role
    $orderRulesForRole = [
        '&&' => [
            ['=' => ['attribute' => 'status', 'value' => 'approved']],
            ['>=' => ['attribute' => 'amount', 'value' => 100]]
        ]
    ];

    // Create or update the rule for this role and model type
    RoleModelAbacRule::updateOrCreate(
        [
            'role_id' => $regionalManagerRole->id,
            'model_type' => Order::getModelType(), // This will return 'order'
        ],
        [
            'rules_json' => $orderRulesForRole // Provide the array directly
        ]
    );

    echo "ABAC rules for Orders assigned to Regional Manager role.\n";
}
```
In this example, `updateOrCreate` is used to either create a new rule association or update an existing one if a rule for that specific `role_id` and `model_type` already exists.

**3. Viewing, Modifying, and Deleting Rules**

Since `RoleModelAbacRule` is a standard Eloquent model, you can manage these rule associations using familiar Eloquent methods:

*   **Viewing:**
    ```php
    // Get all rules for a specific role
    $rulesForRole = RoleModelAbacRule::where('role_id', $role->id)->get();

    // Get the rule for a specific role and model
    $specificRule = RoleModelAbacRule::where('role_id', $role->id)
                                     ->where('model_type', Order::getModelType())
                                     ->first();
    if ($specificRule) {
        $rulesArray = $specificRule->rules_json; // Accesses the casted array
    }
    ```

*   **Modifying:**
    ```php
    $ruleToUpdate = RoleModelAbacRule::find(1); // Or fetch by role_id and model_type
    if ($ruleToUpdate) {
        $newRules = [ /* ... new rule definition ... */ ];
        $ruleToUpdate->update(['rules_json' => $newRules]);
    }
    ```

*   **Deleting:**
    ```php
    $ruleToDelete = RoleModelAbacRule::find(1);
    if ($ruleToDelete) {
        $ruleToDelete->delete();
    }

    // Or delete by role and model type
    RoleModelAbacRule::where('role_id', $role->id)
                     ->where('model_type', Order::getModelType())
                     ->delete();
    ```

**4. Facade Method for Rule Retrieval: `AAuth::ABACRules()`**

AAuth provides a facade method to retrieve the applicable ABAC rules for the currently authenticated user's active role and a specific model type:

`AAuth::ABACRules(string $modelType): ?array`

*   `$modelType`: The string identifier for the model (e.g., `'order'`, as returned by `YourModel::getModelType()`).

This method is primarily used internally by the `AAuthABACModelScope`. When you query an ABAC-enabled model (e.g., `Order::all()`), the scope automatically calls `AAuth::ABACRules(Order::getModelType())`.

Here's how it works:
1.  AAuth identifies the current user (typically via `Auth::user()`).
2.  It determines the user's currently active role. This is usually set via `Session::get('roleId')` when the AAuth service is initialized (see "AAuth Services, Service Provider and `roleId` Session and Facade").
3.  It then queries the `role_model_abac_rules` table for an entry matching the active `role_id` and the provided `$modelType`.
4.  If a matching rule is found, it returns the `rules_json` content as a PHP array.
5.  If no specific rule is found for that role and model type, it may return `null` (or potentially fall back to default rules defined in the model's `getABACRules()` method, depending on the full implementation logic of `AAuthABACModelScope` and `AAuth::ABACRules()`).

This mechanism ensures that the ABAC rules applied are specific to the user's current operational role, providing a powerful and flexible way to manage data access.

## Automatic Query Filtering (ABAC)

A key strength of AAuth's Attribute-Based Access Control (ABAC) implementation is its ability to automatically filter Eloquent queries. This ensures that users only retrieve model records that they are authorized to access based on the ABAC rules defined for their current role, without needing to manually add conditions to every query.

**1. Introduction to Global Scope: `AAuthABACModelScope`**

When you use the `AuroraWebSoftware\AAuth\Traits\AAuthABACModel` trait in your Eloquent model, AAuth automatically registers a global Eloquent scope called `AuroraWebSoftware\AAuth\Scopes\AAuthABACModelScope`. This scope is responsible for intercepting database queries for that model and applying the necessary ABAC rule conditions.

**2. Automatic Filtering in Action**

Once your model is correctly set up with the `AAuthABACModel` trait, and you have defined ABAC rules and associated them with a user's current role via the `RoleModelAbacRule` model (as described in "Managing ABAC Rules and Associations"), the filtering is seamless:

*   Any standard Eloquent query you execute, such as `YourModel::all()`, `YourModel::where('some_column', 'some_value')->get()`, `YourModel::find($id)`, or even queries through relationships, will automatically have the ABAC rules applied.
*   The `AAuthABACModelScope` fetches the relevant ABAC rules for the active user's role and the model being queried (using `AAuth::ABACRules(YourModel::getModelType())`).
*   These rules are then translated into `WHERE` clauses that are added to your database query.
*   Consequently, the query results will only include records that satisfy the conditions defined in the applicable ABAC rules. If no rules are defined for the role, or if the rules permit all access, then all records will be returned (subject to other query conditions).

**3. Example Scenario**

Let's illustrate with an `Order` model that is ABAC-enabled:

*   The `App\Models\Order` model uses the `AAuthABACModel` trait.
*   The user's currently active role has an ABAC rule associated with the `'order'` model type (Order::getModelType() returns 'order'). This rule is:
    ```php
    // Rule stored in RoleModelAbacRule for the user's role and 'order' model_type:
    // [
    //     '&&' => [
    //         ['=' => ['attribute' => 'status', 'value' => 'completed']]
    //     ]
    // ]
    ```
*   The `orders` table in your database contains orders with various statuses: 'pending', 'processing', 'completed', 'cancelled'.

Now, when the user performs queries:

```php
use App\Models\Order;

// Fetch all orders
// Despite no explicit where('status', 'completed') here,
// AAuth will automatically add this condition based on the user's role's ABAC rules.
// $completedOrders will only contain orders where status is 'completed'.
$completedOrders = Order::all();

foreach ($completedOrders as $order) {
    // echo $order->status; // Will always output 'completed'
}

// Fetch a specific order by ID
$order1 = Order::find(1); // If Order ID 1 has status 'pending', $order1 will be null.
$order2 = Order::find(2); // If Order ID 2 has status 'completed', $order2 will be the Order model.

// Even more complex queries are filtered:
$highValueCompletedOrders = Order::where('amount', '>', 500)->get();
// This will return orders where amount > 500 AND status is 'completed'.
```
This automatic filtering significantly enhances security and simplifies development, as the access control logic is centralized and consistently applied without requiring developers to remember to add specific `WHERE` clauses for authorization in every query.

**4. Bypassing the ABAC Scope**

There might be rare situations (e.g., in administrative tools or specific internal processes) where you need to retrieve all records without ABAC filtering. You can bypass the global `AAuthABACModelScope` just like any other global Eloquent scope:

```php
use AuroraWebSoftware\AAuth\Scopes\AAuthABACModelScope;
use App\Models\Order;

// Retrieve all orders, ignoring ABAC rules for this specific query
$allOrdersIncludingNonCompleted = Order::withoutGlobalScope(AAuthABACModelScope::class)->get();

// Find a specific order by ID, ignoring ABAC rules
$anyOrder = Order::withoutGlobalScope(AAuthABACModelScope::class)->find(1);
```
The `withoutGlobalScopes()->all()` method mentioned earlier in the "Getting All Model Collection without any access control" section also effectively bypasses this and all other global scopes. Use this capability judiciously, as it circumvents the defined access controls.

## Getting All Model Collection without any access control
```php
ExampleModel::withoutGlobalScopes()->all()
```

that's all.

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](README-contr.md) for details.

## Security Vulnerabilities

// todo ?
Please review [our security policy](../../security/policy) on how to report security vulnerabilities.


## Credits

- [Aurora Web Software Team](https://github.com/AuroraWebSoftware)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## Development Environment using Dev Containers

This project includes a [Dev Container](https://containers.dev/) configuration, which allows you to use a Docker container as a fully-featured development environment.

### Prerequisites

- [Docker Desktop](https://www.docker.com/products/docker-desktop/) installed and running.
- [Visual Studio Code](https://code.visualstudio.com/) installed.
- [Dev Containers extension](https://marketplace.visualstudio.com/items?itemName=ms-vscode-remote.remote-containers) for VS Code installed.

### Getting Started

1.  Clone this repository to your local machine.
2.  Open the cloned repository in Visual Studio Code.
3.  When prompted with "Reopen in Container", click the button. (If you don't see the prompt, open the Command Palette (`Ctrl+Shift+P` or `Cmd+Shift+P`) and run "Dev Containers: Reopen in Container".)

This will build the dev container and install all necessary dependencies. You can then develop and run the application from within this isolated environment.

[![Open in Dev Containers](https://img.shields.io/static/v1?label=Dev%20Containers&message=Open&color=blue&logo=visualstudiocode)](https://vscode.dev/redirect?url=vscode://ms-vscode-remote.remote-containers/cloneInVolume?url=https://github.com/AuroraWebSoftware/AAuth)
