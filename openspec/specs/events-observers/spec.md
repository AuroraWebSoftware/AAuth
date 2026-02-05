# Events & Observers

## Purpose

Provide event-driven hooks for role and permission lifecycle changes, enabling host applications to react to authorization state changes (e.g., cache invalidation, audit logging, notifications).

## Requirements

### Requirement: Role lifecycle events SHALL be dispatched

The system SHALL dispatch events when roles are created.

#### Scenario: Role created
- **WHEN** a new Role model is created
- **THEN** a RoleCreatedEvent is dispatched with the Role instance

### Requirement: Permission assignment events SHALL be dispatched

The system SHALL dispatch events when permissions are assigned to roles.

#### Scenario: Permission added to role
- **WHEN** a permission is assigned to a role via RolePermissionService
- **THEN** a PermissionAddedEvent is dispatched with the Role, permission key, and optional parameters

### Requirement: Observers SHALL be auto-registered for Role and RolePermission models

RoleObserver and RolePermissionObserver SHALL be automatically registered in the service provider.

#### Scenario: Observer registration
- **WHEN** the AAuth package is loaded
- **THEN** Role model uses RoleObserver and RolePermission model uses RolePermissionObserver

### Requirement: Form request validation SHALL be provided for CRUD operations

The system SHALL provide FormRequest classes for validating Role, OrganizationNode, and OrganizationScope create/update operations.

#### Scenario: Creating a role with validation
- **WHEN** a StoreRoleRequest is used to create a role
- **THEN** the `name` field is required with minimum 3 characters

#### Scenario: Creating an organization node with validation
- **WHEN** a StoreOrganizationNodeRequest is used
- **THEN** `name` and `parent_id` fields are required

### Requirement: Custom exceptions SHALL provide meaningful error context

The system SHALL define specific exception classes for different authorization failure scenarios.

#### Scenario: Authorization exception
- **WHEN** a permission check fails in a context requiring it
- **THEN** an AuthorizationException with appropriate message is thrown

#### Scenario: Invalid organization node
- **WHEN** an operation references a node outside the user's accessible scope
- **THEN** an InvalidOrganizationNodeException is thrown
