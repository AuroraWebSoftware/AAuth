## Context

AAuth is a Laravel package that resolves a request-scoped authorization service (`aauth`) carrying the active user, role, organization node IDs, permissions and ABAC rules. It is registered in `AAuthServiceProvider::boot()` as a singleton and the resolved instance is consulted by:

- `Gate::before` → which delegates `$user->can()` and Blade `@can` to AAuth
- Three middlewares (`aauth.permission`, `aauth.role`, `aauth.organization`)
- Blade directives (`@aauth`, `@aauth_can`, `@aauth_role`, `@aauth_super_admin`)
- 6 helper functions (`aauth()`, `aauth_can()`, etc.)
- The `AAuthUser` trait's overridden `can()` method
- Two Eloquent global scopes (`AAuthOrganizationNodeScope`, `AAuthABACModelScope`)

Under PHP-FPM each HTTP request is a fresh PHP process, so the singleton is re-resolved every time and the binding lifetime is invisible. Under Laravel Octane (FrankenPHP / Swoole / RoadRunner) workers persist between requests; the singleton instance — including its public `$user`, `$role`, `$organizationNodeIds` properties and its `requestCache` array — leaks from one user to the next. A reproduction in a single-worker FrankenPHP setup demonstrates that User B's request returns User A's `organizationNodeIds()`.

The service layer carries two adjacent reliability defects. `OrganizationService::updateNodePathsRecursively` and `deleteOrganizationNodesRecursively` open a DB transaction, recursively call themselves with `$withDBTransaction = false`, and wrap the body in `try/catch (\Exception)`. The catch calls `DB::rollback()` but **does not re-throw**; control falls through and the outer `DB::commit()` still executes on a transaction stack where the inner state is undefined. Worse, the catch is unreachable for the recursive callee (which is itself wrapped in another try/catch). A failed delete therefore leaves a half-deleted subtree with no error surfaced to the caller. Similarly, `RolePermissionService::detachOrganizationRoleFromUser($userId, $roleId, $organizationNodeId)` takes its parameters in the opposite order from its sibling `attachOrganizationRoleToUser($organizationNodeId, $roleId, $userId)`. Every consumer that swapped attach for detach without re-reading the signature is silently writing the wrong row.

The customer who reported the Octane issue is running production traffic. The other two defects were uncovered by the same audit. All three touch lifecycle and service-layer contracts that consumers depend on, so they ship in one coordinated change.

## Goals / Non-Goals

**Goals:**

- Eliminate cross-request state leak of the `aauth` binding in Octane / Vapor without changing PHP-FPM behaviour or the public API surface.
- Make recursive organization-node service operations atomic: either every change in a subtree commits or none does, with the original exception surfaced to the caller.
- Make `attachOrganizationRoleToUser` and `detachOrganizationRoleFromUser` parameter-compatible while preserving backward compatibility for one minor cycle via a deprecated bridge.
- Raise the test suite from "demonstrative" to "regression-grade" for these three areas (every fix has at least one failing-before / passing-after test).
- Keep the PHPStan baseline stable or shrinking and keep Pest green both locally and on GitHub Actions.

**Non-Goals:**

- Refactoring the broader singleton resolution path (lazy boot, multi-guard awareness, role switching mid-request). Those are valid follow-ups but out of scope here — this change is corrective, not architectural.
- Changing migrations or the storage schema.
- Reworking ABAC rule evaluation or the materialized-path organization logic.
- Replacing the `// todo`s outside the four named test files. Only the test files that already exist gain real assertions here, plus the three new test files for the three fixes.

**Backward-compatibility contract (hard constraint for this change):**

- `composer update` from any version in the `21.x` line to the version that ships this change MUST NOT break a working host application.
- Classic PHP-FPM behaviour (`php artisan serve`, `php-fpm`) MUST be byte-equivalent to today: same number of constructor calls per request, same DB query count, same observable side effects.
- No public method signature is altered. No constructor parameter is added, removed or reordered. No exception type that wasn't already thrown becomes mandatory.
- No `E_USER_DEPRECATED` or `E_USER_WARNING` is emitted by code paths that were previously silent. Static `@deprecated` docblock annotations are the only deprecation signal in this release.
- Configuration defaults are unchanged. Existing `aauth.php` and `aauth-advanced.php` files keep working.
- The `scoped()` binding behaves identically to `singleton()` under PHP-FPM (each request is a new process, so the lifetime distinction is invisible). Under Octane / Vapor the change is observable but the observable effect is precisely the security fix consumers are asking for.

## Decisions

### Decision 1: `singleton('aauth', ...)` → `scoped('aauth', ...)`

Laravel 9+ ships `Application::scoped()`, a binding type that behaves like a singleton until a "scope flush" event, after which the next resolution returns a fresh instance. Octane's `Octane::prepareApplicationForNextRequest()` fires `RequestTerminated`; the `FlushTemporaryContainerInstances` listener flushes all scoped bindings. PHP-FPM has no analogous lifecycle, but because each request is a fresh process the distinction is moot.

**Why scoped over alternatives:**

- *Make it transient (`$app->bind`)*: Resolves a fresh instance per `app('aauth')` call. Within one request, `Gate::before` calls `app('aauth')` once per permission check; we would do 5–50× the constructor work (which performs 3 DB queries) per request. Rejected on performance grounds.
- *Use a request middleware that forgets the instance*: Works, but adds a new middleware that consumers must install in their kernel. `scoped()` requires no consumer action.
- *Document `config/octane.php` `'flush' => ['aauth']`*: This is the documented Octane workaround, but it puts the burden on every consumer. Suitable as a stopgap for unupgraded consumers; a footnote in `UPGRADE.md` covers them. Vendor-level fix is cleaner.

The constructor's per-resolution cost (`Auth::user()` + `Session::get('roleId')` + role load + node IDs pluck + context build) is unchanged — we still resolve once per request, just per-request instead of per-worker-lifetime.

### Decision 2: `try/finally` with re-throw, savepoints for recursion — **signature preserved**

The recursive methods currently look like:

```php
public function deleteOrganizationNodesRecursively(OrganizationNode $node, ?bool $withDBTransaction = true): void
{
    if ($withDBTransaction) { DB::beginTransaction(); }
    try {
        // ... recurse ...
        $node->delete();
    } catch (\Exception $exception) {
        DB::rollback();
    }
    if ($withDBTransaction) { DB::commit(); }
}
```

Two defects:
1. Caught exception is swallowed (no re-throw, no return signal).
2. `commit()` runs after `rollback()` on the outer transaction — the second statement targets a transaction that no longer exists at that depth.

**New shape** uses Laravel's `DB::transaction()` closure helper, which handles `beginTransaction` / `commit` / `rollback` with native savepoint nesting when called recursively. **The public method signature stays identical** so that `composer update` is non-breaking for any caller:

```php
public function deleteOrganizationNodesRecursively(OrganizationNode $node, ?bool $withDBTransaction = true): void
{
    if ($withDBTransaction) {
        DB::transaction(fn () => $this->deleteSubtree($node));
        return;
    }
    $this->deleteSubtree($node);
}

protected function deleteSubtree(OrganizationNode $node): void
{
    foreach (OrganizationNode::whereParentId($node->id)->get() as $child) {
        $this->deleteSubtree($child);
    }
    $node->delete();
}
```

The `$withDBTransaction` parameter is **kept** (BC). Its semantic is unchanged: `true` = open a top-level transaction here, `false` = the caller is already managing one. The behavioural change is that exceptions are no longer swallowed — they propagate to the caller. Code that silently relied on the bug ("never throws") will now see real errors, but that path was a defect and not a documented behaviour.

**Alternative considered:** Drop the `$withDBTransaction` parameter. Rejected — the parameter is part of the public signature and dropping it would break `composer update` for anyone passing it explicitly.

**Alternative considered:** Manual `try/finally` with depth tracking. Rejected — re-implementing what `DB::transaction()` already does correctly is a worse outcome than calling the framework helper.

### Decision 3: `detachOrganizationRoleFromUser` parameter order — **existing method untouched, new aligned method added**

The constraint here is strict: `composer update` must not silently break any existing call site. The three parameters are all `int`, so a runtime heuristic cannot distinguish the old order from the new order. Re-ordering the existing method's parameters would silently corrupt data for every caller that does not rewrite the call site. **We do not re-order the existing method.**

Instead:

- **Keep** `detachOrganizationRoleFromUser(int $userId, int $roleId, int $organizationNodeId)` with its current parameter order and current behaviour, fully BC.
- Mark it `@deprecated since 21.1.0` in its docblock with a clear pointer to the new method.
- **Add** a new method whose name signals the aligned order:

  ```php
  public function detachOrganizationRoleFromUserBy(
      int $organizationNodeId,
      int $roleId,
      int $userId,
  ): int
  ```

  Same internal behaviour as the legacy method, just with parameter order matching `attachOrganizationRoleToUser`.

- The deprecation is a docblock-only signal in this minor release. We do **not** emit a runtime `E_USER_DEPRECATED` for the legacy method because triggering errors from a previously-correct call path would surface as noise (and on strict error handlers, as exceptions) immediately after `composer update`. The static deprecation annotation lets PHPStan/IDEs flag call sites without altering runtime behaviour.

- A future major version (`22.0.0`) renames `detachOrganizationRoleFromUserBy` to `detachOrganizationRoleFromUser` (taking over the canonical name) and removes the legacy method. That cleanup is **explicitly out of scope here** and is documented in `UPGRADE.md` as the planned next step.

**Why a new method instead of re-ordering the old one:**
Three positional `int`s are indistinguishable at runtime, so silent data corruption is the only possible outcome of a re-order without consumer code changes. A separately-named method makes the migration explicit and visible in diffs and IDEs without any breakage.

**Why no runtime deprecation notice:**
Strict consumer environments convert `E_USER_DEPRECATED` to exceptions. Emitting one from a previously-valid call path turns a "non-breaking minor" into a hard break on `composer update`. The docblock `@deprecated` tag gives static analysers and IDEs everything they need without changing runtime behaviour.

**Test coverage:**
- The new `detachOrganizationRoleFromUserBy` writes/reads correct rows when called with the aligned order.
- The existing `detachOrganizationRoleFromUser` continues to write/read correct rows when called with its historic order — no regression.
- (No deprecation-notice test in this release because we do not emit one.)

### Decision 4: Test infrastructure improvements scoped to this change

Pest test files affected:
- `tests/Unit/RolePermissionServiceTest.php`: implement `updateRole`, `deleteRole`, `activateRole`, `deactivateRole`. Add `detachOrganizationRoleFromUser` happy-path and legacy-bridge tests.
- `tests/Unit/AAuthTest.php`: implement `passOrAbort`, "can get one specified organization node", and `switchableRolesStatic`.
- New `tests/Unit/V2/OctaneScopingTest.php`: simulates `RequestTerminated` by calling `$app->forgetScopedInstances()` and verifies a fresh AAuth is resolved with a different user/role.
- New `tests/Unit/V2/TransactionIntegrityTest.php`: forces a failure mid-recursion (e.g. throw inside an observer on the second child) and verifies no node was deleted.
- New `tests/Unit/V2/DeprecationBridgeTest.php`: verifies the legacy detach signature still works and emits `E_USER_DEPRECATED`.

We do **not** wire a real Octane runtime into the test suite; `Application::forgetScopedInstances()` is the exact method Octane calls and is sufficient to prove the binding lifetime.

### Decision 5: CI workflow audit

Existing `.github/workflows/run-tests.yml` (referenced in README badges) is checked. If missing or stale, we add a minimal matrix:
- PHP `8.2`, `8.3`, `8.4`
- Laravel `^11.0`, `^12.0`
- Runs `composer install`, `vendor/bin/pest`, `vendor/bin/phpstan analyse`

No new CI service is introduced.

## Risks / Trade-offs

- **Risk:** `scoped()` changes binding lifetime in a way some advanced consumer relies on (e.g., resolving `aauth` from a queued job that boots in the same worker). → **Mitigation:** Queued jobs run outside the HTTP scope flush; they receive a fresh resolution per job execution, which is the desired behaviour. Documented in `UPGRADE.md`.
- **Risk:** `DB::transaction()` swallows exceptions during recursion if a caller is already inside a transaction and savepoints are unsupported by their DB driver. → **Mitigation:** MySQL ≥ 5.0.3, MariaDB, PostgreSQL all support savepoints. SQLite ≥ 3.6.8 also supports them. These are the documented supported drivers.
- **Risk:** The legacy detach bridge confuses static analysers (PHPStan flags deprecated method usage). → **Mitigation:** That is the desired signal — consumers see the warning in their own CI.
- **Trade-off:** We do not detect old-order calls on the canonical method. Consumers passing args in the old order will silently write the wrong rows. → **Mitigation:** Documented prominently in `UPGRADE.md` with a migration grep recipe (`grep -rn 'detachOrganizationRoleFromUser('`).
- **Risk:** New tests slow CI noticeably. → **Mitigation:** All new tests use the existing `migrate:fresh + SampleDataSeeder` pattern. Combined runtime budget < 3 s on the matrix runners.

## Migration Plan

1. Ship the three code fixes behind one PR with all tests green locally and on GitHub Actions.
2. Tag a minor release (e.g. `21.1.0`) — the package is currently `^21.0.0` and the changes are BC-preserving except for the protected `$withDBTransaction` parameter (acceptable in a minor under our current versioning).
3. Update `UPGRADE.md` with three sections:
   - **Octane consumers:** "no action required, but if you previously added `'flush' => ['aauth']` to `config/octane.php` you can remove it."
   - **OrganizationService:** "if you were passing `false` as the second arg to `updateNodePathsRecursively` / `deleteOrganizationNodesRecursively`, drop that arg — transaction nesting is now automatic."
   - **detachOrganizationRoleFromUser:** "the canonical signature now matches `attach`. Migrate calls; the old order is available on `detachOrganizationRoleFromUserLegacy()` until the next major."
4. Open follow-up issues for the remaining items from the audit (singleton lazy-boot, parameter validation algorithm, schema migration cleanup) — out of scope here.

**Rollback:** Tag a `21.1.1` revert if regressions surface. None of the changes touch persisted data.
