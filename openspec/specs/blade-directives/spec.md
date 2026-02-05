# Blade Directives

## Purpose

Provide custom Blade directives and conditional blocks for checking permissions, roles, and super admin status directly in Laravel Blade templates.

## Requirements

### Requirement: @aauth directive SHALL check simple permissions

The `@aauth('permission')` / `@endaauth` block SHALL conditionally render content based on a permission check via the AAuth facade.

#### Scenario: Permission granted
- **WHEN** `@aauth('edit-post')` is used and user has the permission
- **THEN** the content inside the block is rendered

#### Scenario: Permission denied
- **WHEN** `@aauth('edit-post')` is used and user lacks the permission
- **THEN** the content inside the block is not rendered

### Requirement: @aauth_can SHALL support parametric permissions

The `@aauth_can('permission', ...args)` conditional block SHALL check permissions with optional parametric arguments.

#### Scenario: Parametric permission check
- **WHEN** `@aauth_can('approve-budget', 1000)` is used
- **THEN** content is rendered only if `can('approve-budget', [1000])` returns true

### Requirement: @aauth_role SHALL check active role name

The `@aauth_role('roleName')` conditional block SHALL check if the current active role's name matches.

#### Scenario: Matching role
- **WHEN** `@aauth_role('admin')` is used and active role is "admin"
- **THEN** the content is rendered

#### Scenario: Non-matching role
- **WHEN** `@aauth_role('admin')` is used and active role is "editor"
- **THEN** the content is not rendered

### Requirement: @aauth_super_admin SHALL check super admin status

The `@aauth_super_admin` conditional block SHALL render content only if the current user is a super admin.

#### Scenario: Super admin user
- **WHEN** `@aauth_super_admin` is used and user is super admin
- **THEN** the content is rendered

#### Scenario: Non-super admin user
- **WHEN** `@aauth_super_admin` is used and user is not super admin
- **THEN** the content is not rendered
