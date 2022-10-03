# AAuth for Laravel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/aurorawebsoftware/aauth.svg?style=flat-square)](https://packagist.org/packages/aurorawebsoftware/aauth)
[![GitHub Tests Action Status](https://img.shields.io/github/workflow/status/aurorawebsoftware/aauth/run-tests?label=tests)](https://github.com/aurorawebsoftware/aauth/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/workflow/status/aurorawebsoftware/aauth/Check%20&%20fix%20styling?label=code%20style)](https://github.com/aurorawebsoftware/aauth/actions?query=workflow%3A"Check+%26+fix+styling"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/aurorawebsoftware/aauth.svg?style=flat-square)](https://packagist.org/packages/aurora/aauth)

Hierarchical Rol-Permission Based **Laravel Auth Package** with Limitless Hierarchical Level of Organizations

# Features

- Organization Based Access Controllable (OrBAC) Eloquent Models
- Role Based Access Control (RoBAC)
- Permissions Based Access Control
- Lean & Non-Complex Architecture
- PolyMorphic Relationships of Model & Organization Node
- Built-in Blade Directives for permission control inside **Blade** files
- Mysql, MariaDB, Postgres Support
- Community Driven and Open Source Forever

---


[<img src="https://banners.beyondco.de/AAuth%20for%20Laravel.png?theme=light&packageManager=composer+require&packageName=aurorawebsoftware%2Faauth&pattern=jigsaw&style=style_1&description=Hierarchical+Role-Permission+Based+Laravel+Auth+Package+with+Limitless+Hierarchical+Level+of+Organizations&md=1&showWatermark=0&fontSize=175px&images=shield-check&widths=auto" />](https://github.com/AuroraWebSoftware/AAuth)

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

# Main Philosophy

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
the contributors' space

### Deleting an Organization Scope
the contributors' space


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
the contributors' space

### Deleting an Organization Node
the contributors' space

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
...

### Deleting a Role
....

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
....


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

## AAuth Service and Facade Methods

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
.....


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

## Getting allowed Organization Nodes Only.

after adding `AAuthOrganizationNode` trait to your model, you are adding a global scope which filters the permitted data.

Thus you can simply use any eloquent model method without adding anything

```php
ExampleModel::all();
```

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
