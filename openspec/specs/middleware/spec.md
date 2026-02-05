# Middleware

## Purpose

Provide HTTP middleware for route-level authorization, allowing routes to be protected by permission checks, role requirements, and organization scope enforcement.

## Requirements

### Requirement: AAuthPermission middleware SHALL enforce permission checks on routes

The `aauth.permission` middleware SHALL check if the current user's active role has the specified permission before allowing the request to proceed.

#### Scenario: Permission granted
- **WHEN** a route uses `middleware('aauth.permission:edit-post')` and user has permission
- **THEN** the request proceeds normally

#### Scenario: Permission denied
- **WHEN** a route uses `middleware('aauth.permission:edit-post')` and user lacks permission
- **THEN** a 403 response is returned

### Requirement: AAuthRole middleware SHALL enforce role name checks on routes

The `aauth.role` middleware SHALL check if the current user's active role name matches the specified role name.

#### Scenario: Correct role
- **WHEN** a route uses `middleware('aauth.role:admin')` and user's active role is "admin"
- **THEN** the request proceeds normally

#### Scenario: Wrong role
- **WHEN** a route uses `middleware('aauth.role:admin')` and user's active role is "editor"
- **THEN** a 403 response is returned

### Requirement: AAuthOrganizationScope middleware SHALL enforce organization context

The `aauth.organization` middleware SHALL ensure organization scope context is available for the request.

#### Scenario: Organization context present
- **WHEN** a route uses `middleware('aauth.organization')` and user has organization role context
- **THEN** the request proceeds normally

### Requirement: Middleware SHALL be auto-registered with aliases

All three middleware classes SHALL be automatically registered with their aliases in AAuthServiceProvider.

#### Scenario: Middleware registration
- **WHEN** AAuth package is loaded
- **THEN** `aauth.permission`, `aauth.role`, and `aauth.organization` aliases are available
