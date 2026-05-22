## 1. Pre-flight (verify current state)

- [ ] 1.1 Run `vendor/bin/pest` from the package root and record the baseline pass/fail count
- [ ] 1.2 Run `vendor/bin/phpstan analyse` and record the baseline error count
- [ ] 1.3 Confirm `.github/workflows/run-tests.yml` exists; if absent, plan a minimal workflow as part of group 6

## 2. Fix #1 — Octane request-scoped binding (Security HIGH)

- [ ] 2.1 In `src/AAuthServiceProvider.php`, change `$this->app->singleton('aauth', ...)` to `$this->app->scoped('aauth', ...)` (line ~63)
- [ ] 2.2 Add a short docblock above the binding explaining that scoped lifetime is required for Octane / Vapor correctness, with a one-line pointer to the design doc
- [ ] 2.3 Create `tests/Unit/V2/OctaneScopingTest.php`:
  - [ ] 2.3.1 Resolve `app('aauth')` for User A with role X, capture `currentRole()->id` and `organizationNodeIds()`
  - [ ] 2.3.2 Call `$this->app->forgetScopedInstances()` to simulate the Octane request boundary
  - [ ] 2.3.3 Rebind `Auth::user()` and `Session` to User B with role Y, resolve `app('aauth')` again, assert it is a different instance and returns User B's role/node IDs
  - [ ] 2.3.4 Add an assertion that within a single request the same instance is returned (no per-call re-construction)
- [ ] 2.4 Verify Octane installation is NOT required by the test — `forgetScopedInstances()` is a core Laravel API

## 3. Fix #2 — Recursive transaction integrity

- [ ] 3.1 In `src/Services/OrganizationService.php`, refactor `deleteOrganizationNodesRecursively`:
  - [ ] 3.1.1 Keep the public signature `(OrganizationNode $node, ?bool $withDBTransaction = true): void`
  - [ ] 3.1.2 When `$withDBTransaction === true`, wrap the body in `DB::transaction(fn () => $this->deleteSubtree($node))`
  - [ ] 3.1.3 Extract the recursion into a `protected function deleteSubtree(OrganizationNode $node): void` that does NOT manage transactions
  - [ ] 3.1.4 Remove the `try/catch` that previously swallowed exceptions — let them propagate
- [ ] 3.2 In `src/Services/OrganizationService.php`, refactor `updateNodePathsRecursively` with the same shape (`updateSubtreePaths` helper, transaction at the outer boundary, no swallowed exceptions)
- [ ] 3.3 Create `tests/Unit/V2/TransactionIntegrityTest.php`:
  - [ ] 3.3.1 Test: successful recursive delete removes the full subtree
  - [ ] 3.3.2 Test: register a temporary `deleting` model observer that throws on a specific child node, call the recursive delete, assert (a) the exception bubbles up, (b) no node from the subtree is deleted
  - [ ] 3.3.3 Test: successful recursive path update writes all descendant paths
  - [ ] 3.3.4 Test: caller-managed `DB::transaction()` wrapping the service call rolls back the subtree when the outer transaction rolls back
- [ ] 3.4 Verify SQLite (used in tests) supports savepoints — Laravel uses them transparently via `DB::transaction()`

## 4. Fix #3 — Aligned-order detach method (BC-safe)

- [ ] 4.1 In `src/Services/RolePermissionService.php`, add a new method:
  - [ ] 4.1.1 `public function detachOrganizationRoleFromUserBy(int $organizationNodeId, int $roleId, int $userId): int`
  - [ ] 4.1.2 Internally delegates to (or duplicates the body of) the existing `detachOrganizationRoleFromUser`, with parameters mapped correctly
  - [ ] 4.1.3 Cache invalidation (`clearUserRoleCache`) behaves identically
- [ ] 4.2 Add `@deprecated since 21.1.0 use detachOrganizationRoleFromUserBy() with parameter order matching attachOrganizationRoleToUser()` to the docblock of the existing `detachOrganizationRoleFromUser`. **Do not** emit a runtime notice
- [ ] 4.3 In `tests/Unit/RolePermissionServiceTest.php`, add tests:
  - [ ] 4.3.1 `detachOrganizationRoleFromUserBy` removes the correct row when called with aligned-order args
  - [ ] 4.3.2 The existing `detachOrganizationRoleFromUser` still removes the correct row when called with historic-order args (regression guard)
  - [ ] 4.3.3 No `E_USER_DEPRECATED` notice is emitted at runtime by the existing method

## 5. Fix #4 — Fill missing tests in existing files

- [ ] 5.1 In `tests/Unit/RolePermissionServiceTest.php`, implement the four stubs:
  - [ ] 5.1.1 `can update a role`: create a role, call `updateRole`, assert name change persisted
  - [ ] 5.1.2 `can delete a role`: create a role, call `deleteRole`, assert it is gone and the related `role_permission` rows are cleaned up consistently with current behaviour
  - [ ] 5.1.3 `can activate role`: create a passive role, call `activateRole`, assert `status === 'active'`
  - [ ] 5.1.4 `can deactivate role`: create an active role, call `deactivateRole`, assert `status === 'passive'`
- [ ] 5.2 In `tests/Unit/AAuthTest.php`, implement:
  - [ ] 5.2.1 `passOrAbort`: positive path (permission granted → no exception) and negative path (no permission → 401 HttpException with the expected message)
  - [ ] 5.2.2 `can get one specified organization node`: bind aauth to user1/role3, assert `organizationNode($validId)` returns the node and `organizationNode($invalidId)` throws `InvalidOrganizationNodeException`
- [ ] 5.3 Add a new test (in `tests/Unit/AAuthTest.php` or a dedicated file) for `AAuth::switchableRolesStatic`:
  - [ ] 5.3.1 Seeded user1 has multiple assigned roles → static returns same count as the instance method

## 6. CI workflow audit and pest run

- [ ] 6.1 Inspect `.github/workflows/run-tests.yml`. If absent or stale, create a minimal workflow that runs on push and PR to `main` covering:
  - PHP `8.2`, `8.3`, `8.4`
  - Laravel `^11.0`, `^12.0` (composer matrix or two-job split)
  - Steps: checkout, setup-php, `composer install --prefer-dist --no-progress`, `vendor/bin/pest`, `vendor/bin/phpstan analyse --no-progress`
- [ ] 6.2 Run `vendor/bin/pest` locally and verify every test (including the new ones) passes
- [ ] 6.3 Run `vendor/bin/phpstan analyse` locally and confirm the baseline did not grow
- [ ] 6.4 Commit and push; verify GitHub Actions is green on the resulting PR

## 7. Documentation

- [ ] 7.1 Update `README.md`:
  - [ ] 7.1.1 Add a short "Octane / long-lived workers" callout under installation explaining that the binding is request-scoped and no extra `config/octane.php` flush entry is needed
  - [ ] 7.1.2 If `detachOrganizationRoleFromUser` is documented in README, point to the new aligned-order method
- [ ] 7.2 Update `UPGRADE.md` with three sections:
  - [ ] 7.2.1 **Octane consumers:** no action required; the previously-recommended `'flush' => ['aauth']` workaround can be removed
  - [ ] 7.2.2 **OrganizationService recursive methods:** signature unchanged, but exceptions now propagate instead of being silently swallowed — review any caller that depended on silent failure
  - [ ] 7.2.3 **detachOrganizationRoleFromUser:** the historic method continues to work; migrate to `detachOrganizationRoleFromUserBy` with aligned parameter order before the next major release
- [ ] 7.3 If the OpenSpec project lists `core-rbac` under `openspec/specs/`, do not edit it directly — sync happens in group 8

## 8. Finalize: spec sync and archive

- [ ] 8.1 Run `openspec validate fix-critical-auth-bugs` and confirm the change is well-formed
- [ ] 8.2 Run `opsx:sync` (or `openspec sync --change fix-critical-auth-bugs`) to merge the `MODIFIED Requirements` and `ADDED Requirements` deltas into `openspec/specs/core-rbac/spec.md`
- [ ] 8.3 Manually review the merged main spec and confirm the singleton requirement now describes scoped binding, and the two new requirements appear verbatim
- [ ] 8.4 Stage all changes and prepare a commit message that references this OpenSpec change
- [ ] 8.5 Do not archive the change yet — archive only after the PR is merged on `main` (handled by `opsx:archive` later)
