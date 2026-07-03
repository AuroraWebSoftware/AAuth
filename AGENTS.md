# AGENTS.md — working with AAuth

Tool-agnostic guidance for AI coding agents (Claude Code, Cursor, Copilot, …) working
**on** or **with** the `aurorawebsoftware/aauth` package. Humans: see `README.md` (usage)
and `README-contr.md` (contributing).

## What AAuth is

A Laravel authorization package combining three models in one:

- **RBAC** — system & organization roles, permissions, and **parametric permissions**
  (`can('approve', $amount)`).
- **OrBAC** — a **materialized-path** organization tree; a role acts on its node **and its
  whole subtree**, enforced automatically on every query.
- **ABAC** — **row-level** filtering by a model's own attributes, applied as a global
  scope.

## Non-negotiable rules (this package is the security foundation of downstream apps)

1. **Zero data leak.** No change may let a role read or write rows outside its RBAC
   permissions, OrBAC subtree, or ABAC rules. When in doubt, add a **negative** test that
   proves the out-of-scope path is denied.
2. **Secure-by-default, no flags.** Fixes make the correct behaviour the default; never add
   a config flag to gate a security decision.
3. **ABAC is additive.** A role with **no** ABAC rule for a model sees **all** rows of it —
   ABAC only ever restricts a role that has a rule. Do not change this.
4. **No breaking changes** within a major: no removed/re-typed public method, no config a
   consumer reads silently removed without disclosure, no migration that fails on or
   destroys existing data. Disclose behaviour changes in `UPGRADE.md` + `CHANGELOG.md`.
5. **Lean & SOLID.** Fewer concepts, less config, smaller API, deleted code. There is **no
   cross-request cache** — authorization data loads **once per request** into the
   request-scoped `AAuth` instance; every `can()` reads it from memory. Don't reintroduce a
   persistent cache.
6. **Octane/Vapor-safe.** The `aauth` binding is `scoped()` (never `singleton()`); per-user
   state lives on that scoped instance, never in shared/global storage.

## How to work

Quality gates (must stay green — CI runs all three on SQLite, MySQL/MariaDB, PostgreSQL):

```bash
composer format                                              # PHP-CS-Fixer (this repo uses php-cs-fixer, NOT Pint)
vendor/bin/phpstan analyse --memory-limit=1G                 # Larastan level 7
AAUTH_TEST_DB=sqlite vendor/bin/pest                         # tests (or composer test:mariadb / test:pgsql via docker-compose)
```

- **Never add a PHPStan suppression** (`@phpstan-ignore`, `excludePaths`, a broad
  `ignoreErrors`, or a new baseline entry) to pass — fix the cause.
- Every behaviour change ships with a test; security fixes ship with a **negative** test.
- Before opening a PR, run the specialist review agents in `.claude/agents/` via
  `/pre-pr-review` (architect · security-pentest · test-quality · data-integrity ·
  db-engine-specialist).

## Map

- `src/AAuth.php` — the core service (RBAC checks, org-node queries, per-request context).
- `src/Scopes/` — the ABAC and OrBAC global scopes (security-critical; fail closed).
- `src/Traits/` — `AAuthUser`, `AAuthOrganizationNode`, `AAuthABACModel` (attach to host models).
- `src/Services/` — `RolePermissionService`, `OrganizationService`.
- `config/aauth.php` — permission registry; `config/aauth-advanced.php` — super-admin.

More: `README.md`, `UPGRADE.md`, `README-contr.md`, `.claude/agents/README.md`.
