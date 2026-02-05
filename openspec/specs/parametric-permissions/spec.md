# Parametric Permissions (ABAC)

## Purpose

Extend the RBAC system with parametric permissions (value-based conditions on permissions) and an ABAC rule engine for model-level query scoping, enabling fine-grained authorization beyond simple boolean checks.

## Requirements

### Requirement: Permissions SHALL support parameter-based validation

When assigning a permission to a role, optional parameters (JSON) SHALL be stored alongside the permission. Permission checks SHALL validate provided arguments against stored parameters.

#### Scenario: Max value parameter
- **WHEN** role has permission `approve-budget` with parameter `max_amount: 5000`
- **THEN** `can('approve-budget', [3000])` returns true and `can('approve-budget', [6000])` returns false

#### Scenario: Allowed values parameter
- **WHEN** role has permission `view-dept` with parameter `depts: ['HR', 'IT']`
- **THEN** `can('view-dept', ['HR'])` returns true and `can('view-dept', ['Finance'])` returns false

#### Scenario: Permission without parameters
- **WHEN** role has permission `edit-post` with no parameters
- **THEN** `can('edit-post')` returns true as a simple boolean check

### Requirement: ABAC rules SHALL scope Eloquent queries per role per model

RoleModelAbacRule records SHALL define column-level conditions that are automatically applied as query scopes on models using the AAuthABACModel trait.

#### Scenario: ABAC rule scoping
- **WHEN** a role has ABAC rule `status = 'active'` for Post model
- **THEN** queries on Post model automatically include `WHERE status = 'active'`

#### Scenario: Multiple ABAC rules with logical operators
- **WHEN** a role has multiple ABAC rules with AND/OR operators
- **THEN** rules are combined using the specified logical operators

### Requirement: AAuthABACModel trait SHALL auto-apply ABAC scopes

Eloquent models using the `AAuthABACModel` trait SHALL automatically have ABAC-based query constraints applied based on the current user's role.

#### Scenario: Model with ABAC trait
- **WHEN** a model uses AAuthABACModel trait and user queries it
- **THEN** results are filtered according to the role's ABAC rules for that model type
