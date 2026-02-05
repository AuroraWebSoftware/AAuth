# Gate Integration

## Purpose

Integrate with Laravel's built-in authorization system (Gates) so that standard Laravel authorization methods (`$user->can()`, `@can`, `authorize()`) work seamlessly with AAuth permissions.

## Requirements

### Requirement: Gate::before SHALL route authorization through AAuth

The system SHALL register a `Gate::before` callback that intercepts all ability checks and routes them through `AAuth::can()`.

#### Scenario: Permission exists and granted
- **WHEN** `$user->can('edit-post')` is called and AAuth grants the permission
- **THEN** the Gate returns true

#### Scenario: Permission not granted
- **WHEN** `$user->can('edit-post')` is called and AAuth denies the permission
- **THEN** the Gate returns null (falls through to other Gate definitions)

#### Scenario: AAuth error
- **WHEN** an exception occurs during AAuth permission check
- **THEN** the Gate returns null (graceful fallback, does not block)

### Requirement: Super admin SHALL bypass all Gate checks

When super admin is enabled and the user has the super admin flag, all Gate ability checks SHALL return true immediately.

#### Scenario: Super admin gate bypass
- **WHEN** user is super admin and any ability is checked via Gate
- **THEN** true is returned without checking specific permissions

### Requirement: Standard Laravel authorization patterns SHALL work with AAuth

All Laravel authorization patterns SHALL work with AAuth-managed permissions.

#### Scenario: Controller authorize
- **WHEN** `$this->authorize('edit-post')` is called in a controller
- **THEN** AAuth permission check is executed via Gate::before

#### Scenario: Blade @can directive
- **WHEN** `@can('edit-post')` is used in a Blade template
- **THEN** AAuth permission check determines the output
