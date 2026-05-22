## MODIFIED Requirements

### Requirement: AAuth singleton SHALL be resolved with authenticated user and active role

The system SHALL register an `aauth` binding in Laravel's service container as a **request-scoped** binding (`Application::scoped()`), resolved on first use within a request from the authenticated user and the active roleId in the session. The resolved instance MUST NOT survive across HTTP requests in long-lived workers (Octane, Vapor); the container SHALL flush the instance on `RequestTerminated` so the next request resolves a fresh `AAuth` against the current authenticated user.

Under classic PHP-FPM each request is a fresh process, so the scoped lifetime is equivalent to the previous singleton lifetime. Under Octane / Vapor, the scoped lifetime prevents cross-request state leakage of the resolved user, role, organization node IDs, permissions, ABAC rules, and super-admin flag.

#### Scenario: Authenticated user with valid role

- **WHEN** an authenticated user has an active roleId in session
- **THEN** the AAuth instance is resolved with that user context and role loaded

#### Scenario: Unauthenticated user

- **WHEN** no authenticated user exists
- **THEN** an AuthenticationException is thrown

#### Scenario: Missing role

- **WHEN** no roleId is in the session
- **THEN** a MissingRoleException is thrown

#### Scenario: User not assigned to role

- **WHEN** the user does not have the specified role assigned
- **THEN** a UserHasNoAssignedRoleException is thrown

#### Scenario: Request boundary flushes the resolved instance

- **WHEN** a request terminates in a long-lived worker (Octane / Vapor) and a new request begins with a different authenticated user
- **THEN** resolving `aauth` again returns a new `AAuth` instance bound to the new user — the previous user's `organizationNodeIds`, permissions, role, and super-admin flag are not reused

#### Scenario: Manual scope flush by simulation

- **WHEN** a test or framework code calls `$app->forgetScopedInstances()` between two `app('aauth')` resolutions targeting different users
- **THEN** the second resolution returns a fresh instance whose `currentRole()->id`, `organizationNodeIds()`, and `can()` results reflect the second user

## ADDED Requirements

### Requirement: Recursive organization-node service operations SHALL be atomic

`OrganizationService::updateNodePathsRecursively` and `OrganizationService::deleteOrganizationNodesRecursively` SHALL execute under a single logical database transaction. If any node operation in the subtree fails, the entire subtree SHALL be rolled back to its pre-call state and the original exception SHALL propagate to the caller. The methods MUST NOT silently swallow exceptions.

When called recursively (or when the caller is already inside a transaction), the implementation SHALL use the database driver's nested-transaction support (savepoints). The existing `$withDBTransaction = true` parameter SHALL be preserved on the public signatures of both methods so that `composer update` does not break any existing call site; its semantics remain unchanged (`true` = open a top-level transaction here, `false` = participate in the caller's transaction).

#### Scenario: Successful recursive delete

- **WHEN** `deleteOrganizationNodesRecursively($subtreeRoot)` is called and every descendant deletes successfully
- **THEN** every node in the subtree is removed and the method returns normally

#### Scenario: Mid-recursion failure rolls back

- **WHEN** `deleteOrganizationNodesRecursively($subtreeRoot)` is called and a descendant raises an exception (for example, via a model observer)
- **THEN** no node from the subtree is deleted (the parent and all children remain), and the original exception is re-thrown to the caller

#### Scenario: Path update failure rolls back

- **WHEN** `updateNodePathsRecursively($node)` is called and a descendant `save()` fails partway through
- **THEN** no descendant `path` value is mutated and the original exception is re-thrown to the caller

#### Scenario: Caller-side transaction integration

- **WHEN** a caller wraps `deleteOrganizationNodesRecursively($node)` inside its own `DB::transaction()` block
- **THEN** the service participates in the outer transaction via savepoints; rolling back the outer transaction reverts the subtree deletion

### Requirement: A canonical aligned-order detach method SHALL be added without breaking the existing one

The `RolePermissionService` SHALL expose a new method `detachOrganizationRoleFromUserBy(int $organizationNodeId, int $roleId, int $userId): int` whose parameter order matches `attachOrganizationRoleToUser`. The existing `detachOrganizationRoleFromUser(int $userId, int $roleId, int $organizationNodeId): int` method SHALL remain in place with its current parameter order and current behaviour, fully backward compatible. The existing method SHALL be marked `@deprecated since 21.1.0` in its docblock with a pointer to the new method. No runtime deprecation notice SHALL be emitted by the existing method in this release. The existing method SHALL be removed only in the next major version.

#### Scenario: New aligned-order method removes correct row

- **WHEN** a caller invokes `detachOrganizationRoleFromUserBy($nodeId, $roleId, $userId)`
- **THEN** the row identified by `(user_id=$userId, role_id=$roleId, organization_node_id=$nodeId)` is removed from `user_role_organization_node`

#### Scenario: Existing method continues to work unchanged

- **WHEN** an existing consumer invokes `detachOrganizationRoleFromUser($userId, $roleId, $nodeId)` after `composer update`
- **THEN** the row identified by `(user_id=$userId, role_id=$roleId, organization_node_id=$nodeId)` is removed exactly as before, with no warning, notice, or exception emitted at runtime

#### Scenario: Static analysis flags the deprecation

- **WHEN** PHPStan or an IDE inspects a call site of `detachOrganizationRoleFromUser`
- **THEN** the `@deprecated` docblock annotation is surfaced, directing the developer to `detachOrganizationRoleFromUserBy`

#### Scenario: Future major release removes the legacy method

- **WHEN** the next major version of the package is released
- **THEN** the legacy `detachOrganizationRoleFromUser` method is removed and only the aligned-order canonical method remains

### Requirement: Test suite SHALL cover the corrected lifecycle and service behaviours

The Pest test suite SHALL contain at least one regression test per requirement above, including a scoped-binding simulation, a transaction rollback assertion, and a deprecation-notice assertion. Pre-existing `// todo` stubs for `updateRole`, `deleteRole`, `activateRole`, `deactivateRole`, `passOrAbort`, the single-node lookup, and `switchableRolesStatic` SHALL be replaced with real assertions.

#### Scenario: Suite is green locally

- **WHEN** `vendor/bin/pest` is invoked from the package root
- **THEN** every test passes and the previously-stubbed tests assert behaviour rather than `expect(1)->toBeTruthy()`

#### Scenario: Suite is green on GitHub Actions

- **WHEN** the configured workflow runs on a push to `main` against PHP 8.2/8.3/8.4 and Laravel 11/12
- **THEN** every test in every matrix cell passes
