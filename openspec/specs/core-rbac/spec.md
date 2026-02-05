# Core RBAC

## Purpose

Provide a complete Role-Based Access Control system for Laravel applications with two role types (system and organization), permission assignment to roles, user-role bindings, and a singleton service class for authorization checks.

## Requirements

### Requirement: AAuth singleton SHALL be resolved with authenticated user and active role

The system SHALL register an `aauth` singleton in Laravel's service container that requires an authenticated user and an active roleId from the session.

#### Scenario: Authenticated user with valid role
- **WHEN** an authenticated user has an active roleId in session
- **THEN** the AAuth singleton is resolved with user context and role loaded

#### Scenario: Unauthenticated user
- **WHEN** no authenticated user exists
- **THEN** an AuthenticationException is thrown

#### Scenario: Missing role
- **WHEN** no roleId is in the session
- **THEN** a MissingRoleException is thrown

#### Scenario: User not assigned to role
- **WHEN** the user does not have the specified role assigned
- **THEN** a UserHasNoAssignedRoleException is thrown

### Requirement: Permission checks SHALL validate against role permissions

The `can($permission, ...$arguments)` method SHALL check if the current role has the given permission, optionally validating parametric arguments.

#### Scenario: Simple permission granted
- **WHEN** the current role has permission `edit-post`
- **THEN** `AAuth::can('edit-post')` returns true

#### Scenario: Simple permission denied
- **WHEN** the current role does not have permission `edit-post`
- **THEN** `AAuth::can('edit-post')` returns false

#### Scenario: passOrAbort with denied permission
- **WHEN** `AAuth::passOrAbort('edit-post')` is called and permission is denied
- **THEN** a 403 HTTP response is returned

### Requirement: Users SHALL be able to switch between assigned roles

The system SHALL provide `switchableRoles()` to list all roles assigned to the current user, and `switchableRolesStatic($userId)` for static context.

#### Scenario: User with multiple roles
- **WHEN** a user has system role "admin" and organization role "manager"
- **THEN** `switchableRoles()` returns both roles

### Requirement: Roles SHALL support system and organization types

Roles SHALL have a `type` field distinguishing `system` (global) roles from `organization` (hierarchy-bound) roles.

#### Scenario: System role
- **WHEN** a role has type `system`
- **THEN** it applies globally without organization node binding

#### Scenario: Organization role
- **WHEN** a role has type `organization`
- **THEN** it requires an `organization_scope_id` and is bound to organization nodes

### Requirement: Super admin SHALL bypass all permission checks

When enabled in config, users with the super admin flag SHALL bypass all permission checks.

#### Scenario: Super admin user
- **WHEN** `aauth-advanced.super_admin.enabled` is true and user has `is_super_admin = true`
- **THEN** all `can()` checks return true

### Requirement: Host application User model SHALL implement AAuthUserContract

The host application's User model SHALL implement `AAuthUserContract` and use the `AAuthUser` trait to gain role relationship methods.

#### Scenario: Compliant user model
- **WHEN** User model implements AAuthUserContract and uses AAuthUser trait
- **THEN** `$user->roles()` returns the user's assigned roles
