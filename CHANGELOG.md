# Changelog

All notable changes to `AAuth` will be documented in this file.

## Unreleased — Security hardening

Secure-by-default fixes for confirmed authorization defects (no config flags). See
[UPGRADE.md](UPGRADE.md) for the observable behaviour changes.

- **Security:** parametric permissions fail closed; deactivated roles are rejected;
  `Gate::before` defers to a host Policy (no IDOR by name-collision); org-write helpers
  (`createWith`/`updateWith`/`deleteWithAAuthOrganizationNode`) and `attachOrganizationRoleToUser`
  authorize the target node against the active role's subtree; `updateWith` also authorizes
  the node being moved; `Role` privilege columns are no longer mass-assignable;
  `descendant()` is `/`-separator-anchored; ABAC rule attributes are allowlisted; an empty
  accessible-node set returns zero rows (fail closed).
- **Fixes:** `Role::permissions()` (returned every role's permissions), assigned-user count,
  non-atomic permission sync and organization-node create, the pgsql seed sequence, and the
  runtime-fatal org-node update/delete trait helpers.
- **Removed:** the opt-in role/permission cache (`aauth-advanced.cache`). Authorization data
  is now loaded once per request into the request-scoped instance — no persistent cache, no
  invalidation apparatus, tenant-safe; a published `cache` config key is simply ignored.
- **CI:** the suite runs on SQLite, MariaDB and PostgreSQL (GitHub Actions + GitLab CI); a
  `/pre-pr-review` agent suite lives in `.claude/`.

## 1.1.0 - 2022-10-24

Abac Features

## 1.0.8 - 2022-10-13

1.0.8
Blade directive problem fixed by @nusinan

## 1.0.7 - 2022-10-10

1.0.7

## 1.0.6 - 2022-10-05

currentRole func.

## 1.0.5 - 2022-10-05

static switchable roles function

## 1.0.4 - 2022-10-03

1.0.4

## 1.0.3 - 2022-10-03

config publish fixed

## 1.0.2 - 2022-06-28

- Typo Fix
- First Contribution

## 1.0.1 - 2022-06-27

- Docs Updated

## 0.0.3 - 2022-06-24

Namespace changed
