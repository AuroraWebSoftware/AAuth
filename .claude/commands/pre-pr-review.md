---
description: Run the four AAuth review sub-agents on the current diff and produce one PR go/no-go
---

# Pre-PR Review — AAuth

Run a full pre-PR review of the **current working diff** using the four specialist sub-agents in `.claude/agents/`, then produce **one consolidated go/no-go**. The agents are **read-only** — they recommend; you (or the human) apply fixes.

## Steps

1. **Scope the diff.** `git diff --name-only main...HEAD` (fall back to `git diff` for uncommitted work). If empty, say so and stop.

2. **Run the gates ONCE** (so agents cite results instead of re-running):
   - `vendor/bin/pint --test`
   - `vendor/bin/phpstan analyse --memory-limit=1G` (must have the memory flag or it OOMs)
   - `AAUTH_TEST_DB=sqlite vendor/bin/pest` (or the MySQL target on :33062)
   - `composer audit`

3. **Fan out the sub-agents in parallel** (one Task each), giving every one the same `main...HEAD` range + the diff:
   - `laravel-architect` — architecture, LEAN-SOLID, idiom, API surface
   - `security-pentest` — OWASP + authz bypass + **data leak** + injection + `composer audit`
   - `test-quality` — pint/phpstan(no new suppression)/pest + negative/edge tests
   - `data-integrity` — migrations, FK/UNIQUE, path integrity, portability (**correctness/leak**)
   - `db-engine-specialist` — Postgres+MySQL best-use (tree/search/JSON, driver-conditional indexes) — **only when queries/indexes/migrations changed**

4. **Aggregate — dedupe by OWNER** (do NOT print four copies of the same issue):

   | Issue | Owner (attribute the finding to) |
   |-------|----------------------------------|
   | Empty-scope-returns-all-rows | security-pentest (test-quality confirms the negative test) |
   | ABAC-alone leak | security-pentest + laravel-architect (model wiring) |
   | Unanchored path LIKE / descendant `/` | data-integrity |
   | Mass-assignment of authz columns | security-pentest (DBA notes schema angle) |
   | New `@phpstan-ignore`/baseline/excludePaths | test-quality |
   | New config flag / abstraction | laravel-architect (security escalates only if it defaults permissive) |
   | Write-side authz gap (createWith/updateWith/deleteWith) | security-pentest |
   | Non-atomic write | data-integrity (path) / test-quality (permission sync) |
   | Engine-only construct on a core query path (portability break) | data-integrity BLOCKS; db-engine-specialist flags the perf angle |
   | Suboptimal tree/search/JSON query or missing driver-conditional index | db-engine-specialist |
   | Behavior-change disclosure (CHANGELOG/UPGRADE) | test-quality |
   | composer audit advisory | test-quality runs, security-pentest interprets |
   | scoped()→singleton() state bleed | laravel-architect + security-pentest |

5. **Consolidated verdict:**
   - **BLOCK** if ANY agent returns BLOCK, ANY deduped finding is BLOCKER, or ANY [global red line](../agents/README.md) is tripped. **Data leak decides first and alone** — no matter how green the gates are.
   - **BLOCK + surface for accept-risk** if no BLOCKER but any HIGH.
   - **APPROVE** only when nothing above LOW/NIT remains.

6. **Emit ONE lean report:** merged **BLOCKERS** at the top, then the **deduped findings** table (BLOCKER→NIT), then per-agent verdicts + gate status. Do **not** restate passing checklist items. Call out praiseworthy deletions/simplifications and any added negative tests.

7. **Do NOT open the PR** while the consolidated verdict is BLOCK. List exactly what must change; after fixes, re-run the affected agents before flipping to APPROVE.

$ARGUMENTS
