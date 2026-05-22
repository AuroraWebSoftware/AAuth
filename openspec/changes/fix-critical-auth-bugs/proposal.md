## Why

A production user running AAuth on Laravel Octane reported a **HIGH severity authorization bypass**: the `app->singleton('aauth', ...)` binding survives request boundaries in long-lived workers, so User A's resolved `AAuth` instance (user, role, organization node IDs, permissions, ABAC rules, super-admin flag) is reused for User B's request. The same review surfaced two more correctness defects in the service layer — silent transaction corruption in recursive organization-node operations, and an inverted parameter order between `attachOrganizationRoleToUser` and `detachOrganizationRoleFromUser`. These three issues sit on the same lifecycle/service-layer surface and must ship together. While we are touching this code we also close the test coverage gaps that have been masked by `// todo` placeholders.

## What Changes

- **BREAKING (semantic, not API) — Auth lifecycle:** Change the `aauth` container binding from `singleton` to `scoped` so Octane / Vapor flushes the resolved instance at every `RequestTerminated` boundary. PHP-FPM behavior is unchanged. The public facade and service API are unchanged.
- **Service-layer reliability:** Rewrite `OrganizationService::updateNodePathsRecursively` and `deleteOrganizationNodesRecursively` to use `try/finally` with proper transaction handling and re-throw exceptions instead of swallowing them. Recursive calls reuse the outer transaction via savepoints.
- **API consistency (BC-safe):** Re-order `RolePermissionService::detachOrganizationRoleFromUser` parameters to match `attachOrganizationRoleToUser` (`$organizationNodeId, $roleId, $userId`). The old order is preserved as a deprecated bridge that emits an `E_USER_DEPRECATED` notice and forwards to the new signature, scheduled for removal in the next major version.
- **Test coverage:** Replace `// todo` stubs in `RolePermissionServiceTest` (updateRole, deleteRole, activateRole, deactivateRole) and `AAuthTest` (`passOrAbort`, "can get one specified organization node") with real assertions, add coverage for `AAuth::switchableRolesStatic`, and add new tests for each of the three fixes above.
- **CI:** Verify the GitHub Actions test workflow runs the suite on supported PHP/Laravel matrix; add a minimal workflow if missing.

## Capabilities

### New Capabilities

None — this change is entirely corrective. No new product capability is introduced.

### Modified Capabilities

- `core-rbac`: `AAuth` container lifecycle requirement changes — the resolved instance MUST NOT survive across HTTP requests in long-lived workers. A new requirement covers transactional integrity for recursive organization-node service operations and parameter-order consistency between paired attach/detach service methods.

## Impact

- **Code:**
  - `src/AAuthServiceProvider.php` (singleton → scoped binding)
  - `src/Services/OrganizationService.php` (transaction handling in recursive methods)
  - `src/Services/RolePermissionService.php` (detach parameter order + deprecation bridge)
- **Tests:**
  - `tests/Unit/AAuthTest.php`, `tests/Unit/RolePermissionServiceTest.php` (fill todos)
  - New `tests/Unit/V2/OctaneScopingTest.php` (forgetInstance/flush simulation)
  - New `tests/Unit/V2/TransactionIntegrityTest.php`
  - New deprecation-emission test for the detach bridge
- **CI:** `.github/workflows/run-tests.yml` audited (created if missing). Local `vendor/bin/pest` and GitHub Actions both green.
- **APIs / consumers:**
  - Octane users on the old version SHOULD continue working but the security fix is mandatory for them.
  - Anyone calling `detachOrganizationRoleFromUser($userId, $roleId, $organizationNodeId)` will continue working with a deprecation notice until the next major.
- **Migrations:** None. No schema changes.
- **PHPStan:** Baseline should not grow; ideally shrinks by the deletion of a `@phpstan-ignore` line previously masking the swallowed-exception path.
- **Docs:** README "Octane" callout added; UPGRADE.md gets a short note about the deprecation.