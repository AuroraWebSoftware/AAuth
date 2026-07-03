# Contributing to AAuth

This guide is written for **both human contributors and AI coding agents**. It
covers local setup, the multi-database test matrix, the quality gates, and the
pre-PR review agents. Follow it before opening a pull request.

## Golden rule — zero data leak

AAuth is the authorization foundation of downstream apps. **No change may let a
role read or write rows outside its RBAC permissions, OrBAC org subtree, or ABAC
rules.** Any such change is a blocker. When in doubt, add a *negative* test that
proves the out-of-scope path is denied.

## 1. Local setup

```bash
composer install
```

## 2. Databases via docker-compose

The suite runs on SQLite (no services needed), and on **MariaDB** and
**PostgreSQL** via docker-compose (a `docker-compose.yml` is provided).

```bash
docker compose up -d mariadb postgres    # MariaDB :33062, PostgreSQL :54322 (healthchecked)
```

## 3. Running the tests (all three engines)

```bash
composer test:sqlite      # SQLite, no docker required
composer test:mariadb     # MariaDB  (uses phpunit.xml.dist defaults, port 33062)
composer test:pgsql       # PostgreSQL (port 54322)
composer test:all         # all three, in sequence
```

A change must be **green on SQLite, MariaDB and PostgreSQL** — materialized-path
`LIKE`, depth `whereRaw`, JSON columns and pgsql sequences are driver-sensitive.

## 4. Quality gates

```bash
composer format                                 # code style (PHP-CS-Fixer) — fix
vendor/bin/php-cs-fixer fix --dry-run --diff --allow-risky=yes   # check only (CI gate)
vendor/bin/phpstan analyse --memory-limit=1G    # static analysis (Larastan)
```

Rules:
- **Never add a new suppression** (`@phpstan-ignore`, a `phpstan-baseline.neon`
  entry, `ignoreErrors`, or `excludePaths`) to make phpstan pass — fix the cause.
- Every behaviour change ships with a test; security fixes ship with a **negative**
  test that fails without the fix.
- Assertions must be semantic (no `toBeArray()`/`count>=0`-only tautologies).

## 5. Continuous integration

- **GitHub Actions** — `.github/workflows/run-tests.yml`: PHP 8.2–8.4 × Laravel
  11–13 × stability, each cell run against **MariaDB and PostgreSQL**.
- **GitLab CI** — `.gitlab-ci.yml`: `quality` (phpstan + pint) plus
  `test:mariadb` and `test:postgres` stages.

## 6. Pre-PR review agents (Claude Code)

Before opening a PR, run the review agents on your diff:

```
/pre-pr-review
```

Five read-only specialist agents live in `.claude/agents/` and report in a common
format; the orchestrator merges them into one go/no-go:

| Agent | Lens |
|-------|------|
| `laravel-architect` | architecture, lean-SOLID, API surface, idioms |
| `security-pentest` | OWASP, authz bypass, **data leak**, injection, `composer audit` |
| `test-quality` | Pint / PHPStan (no new suppression) / Pest, edge & negative tests |
| `data-integrity` | migrations, FK/UNIQUE, path integrity, portability |
| `db-engine-specialist` | Postgres + MySQL best-use without breaking portability |

**Data leak blocks the PR from any agent, no matter how green the gates are.**
See `.claude/agents/README.md` for the full red-line list.
