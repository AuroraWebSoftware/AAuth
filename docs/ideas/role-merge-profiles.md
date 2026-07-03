# Feasibility Report: Role Merging ("Profiles" / Multi-Role Activation)

> **Status: IDEA — not scheduled, not implemented.**
> This is a design/feasibility study only. Nothing described here exists in the codebase.
> Date: 2026-07-04 · Produced by an 8-agent independent analysis (security, architecture,
> database, industry prior-art + 4 adversarial verifications executed against the real code).

## The idea

In large systems a user may hold many roles and must switch between them constantly.
The proposal: merge several of a user's roles into one active **profile** so they switch
less (speed + UX). Requirements from the product owner:

1. Several merge strategies, parametric and config-driven, combinable — e.g.
   *merge roles sharing the same organization scope*, *merge roles of the same type*.
2. The **default must remain the current single-role behavior** (zero breaking change).
3. An API to **list the available profiles** per configured strategy.
4. Permissions must merge accordingly; data-level access (ABAC rules and
   organization-node query scopes) must merge accordingly.

## What already works today: one role at many nodes

**A single role assigned at multiple organization nodes already merges those nodes —
no profile needed.** The constructor collects *all* pivot rows for the (user, role)
pair (`src/AAuth.php:67-70`), and `organizationNodesQuery()` ORs every node's subtree
into one union (`src/AAuth.php:402-408`), which the query scopes apply to model
queries. A "Branch Manager" role attached at 12 branches sees the union of all 12
subtrees today, with zero switching.

This is safe because one role is one consistent triple: a single permission set +
a single ABAC rule + the node union — none of the cross-product leaks below can occur.

**Consequence for this feature:** the real need for profiles is merging *different*
roles (different permission sets) — which is exactly where all the risk lives. Before
reaching for profiles, deployments suffering from frequent role switching should first
check whether their "many roles" are really per-node copies of the same role; if so,
consolidating them into one role attached at many nodes solves the UX problem with the
existing mechanism.

## Verdict

**Feasible ✅ · Reasonable ✅ · BUT a naive merge is a guaranteed data leak ❌**

The concept is standard: NIST RBAC defines a *session* as activating a **subset of a
user's roles** — multi-role activation is the textbook model, not an exception. A
zero-breaking-change introduction path was verified. However, the natural
implementation — *union of permissions × union of row scopes* — mathematically grants
(permission, row) combinations that **no single member role could grant**. Safe merge
semantics must be designed first; this document specifies them.

---

## 1. Where the leaks come from (3 confirmed BLOCKERs)

Root cause (architectural, not a bug): `can()` is row/model-agnostic, while the two
row filters — `AAuthOrganizationNodeScope` and `AAuthABACModelScope` — are
**permission-agnostic global scopes**. In single-role mode this decoupling is safe
because one role supplies one consistent (permissions, org-subtree, ABAC) triple.
Merging the dimensions independently creates a **cross-product**.

### Leak 1 — Role A's permission × Role B's organization nodes

> Role A: `patient:read`, attached only at Hospital-X. Role B: only `invoice:read`,
> attached at Hospital-Y.
> Profile {A, B}: `can('patient:read')` passes (from A) and the org scope returns the
> X ∪ Y subtrees → the user **reads patients at Hospital-Y**. Neither role alone could
> grant that pair.

### Leak 2 — Role A's permission × Role B's "no ABAC rule = all rows"

> Role A: `document:read` + ABAC rule `dept = 'legal'`. Role B: unrelated permission,
> **no** ABAC rule row for Document.
> A "permissive" ABAC merge produces `(dept = 'legal') OR (unrestricted)` = **all
> documents**. Role A's restriction is erased.

### Leak 3 — The subtle one: org × ABAC cross term (a restrictive ABAC merge cannot close it)

> Role A: node X + rule `dept = 'legal'`. Role B: node Y + rule `dept = 'finance'`
> (both with `doc:read`).
> Naive SQL: `(X ∨ Y) ∧ (legal ∨ finance)` → a **finance document at node X** becomes
> visible. A@X sees only legal; B sees finance only at Y. The correct predicate is
> `(X ∧ legal) ∨ (Y ∧ finance)` — per-role conjunction, cross-role disjunction.

### Additional high-severity findings

- **Kill-switch bypass:** a role deactivated mid-session must not keep contributing
  through a cached profile; membership must be re-resolved (active-only) every request.
- **Parametric permission collision:** the context flattens permissions into
  `name => parameters`; two member roles sharing a permission name would be
  **last-write-wins / nondeterministic**. (Duplicate `role_permission` rows can trigger
  this today even in single-role mode — profiles amplify it.) The fix is per-role
  parameter **lists**, pass if **any** role's grant passes.
- **Write-path escalation:** `createWith/updateWithAAuthOrganizationNode` assert node
  membership against the node set with no coupling to the permission-granting role.
- **Helper drift:** `aauth_has_role()` and `aauth_active_organization()` become
  ambiguous under a profile and need defined semantics.
- **Separation of Duty:** some deployments split roles *intentionally* (maker/checker).
  Auto-merge silently defeats that; it is not caught by the formal no-leak invariant.
  Mitigation: strictly opt-in + (later) mutually-exclusive-role constraints.

## 2. Industry prior art

| System | Approach | Lesson |
|---|---|---|
| **AWS IAM** | Multiple policies = union of Allow, but **each statement is evaluated intact with its own Condition** | Grants are OR'ed as whole tuples; dimensions are never unioned separately |
| **PostgreSQL RLS** | Multiple permissive policies OR together, each policy's USING expression intact | Same lesson: per-grant-coupled union |
| **NIST RBAC** | A session activates a subset of roles; DSD constraints block toxic co-activations | The feature is standard; SoD constraints are part of the standard |
| **Spatie laravel-permission** | All roles always active, flat permission union | Safe **only because it has no per-role row scoping** — not transplantable to AAuth |
| **Keycloak** | Composite roles: admin-curated unions | The curated alternative to dynamic merge strategies |

**Consensus:** the only safe combination semantics is the **per-grant-coupled union**:

```
access(profile, permission, row) = ∃ role r ∈ profile:
      r grants permission
  AND r's parameters accept the runtime arguments
  AND row satisfies (r's org subtree ∧ r's ABAC rule)
```

## 3. Recommended safe design

**Golden rule: evaluate k single-role contexts in parallel and union the *answers* —
never union the dimensions.**

1. **`can()`** — permission map becomes *per-role parameter lists*; the check passes if
   **any** member role's grant (with its own parameters) passes. Safe and complete;
   also fixes the existing last-write-wins nondeterminism.
2. **Row scopes (profile mode)** — one parenthesized branch per member role:
   `(subtree_A ∧ abac_A) OR (subtree_B ∧ abac_B)`.
   - A role with **no ABAC rule** contributes a TRUE ABAC leg (preserves today's
     "no rule = all rows" per role; avoids the under-grant surprise of a
     "restrictive-only" merge).
   - A role with **no nodes** contributes a fail-closed `1 = 0` org leg (preserved).
   - Models with only one of the two traits degenerate to a plain union — cheap.
3. **Write paths** — **coupled check required**: a *single* member role must grant both
   the permission and the target node (for updates: both endpoints). Writes are the
   dangerous direction; no compromise here.
4. **Membership resolution** — only the profile *key* lives in the session; member
   roles are re-resolved every request with `status = 'active'` filtering. The
   `deactivateRole()` kill switch keeps working.
5. **The residual read-side gap** — a permission granted narrowly by role A applies to
   role B's rows in listings, because global scopes cannot know which permission gated
   the request. Phase 1: **document** it ("a profile shows the union of what its roles
   can see"). Phase 2 (optional): a coupled query API such as
   `canOnNode($permission, $nodeId)`. Making the global scopes permission-aware was
   evaluated and **rejected** (Octane-hostile, invasive).

### Verified mechanics (executed against the real scope classes)

- The merged ABAC rule is expressible in the **existing grammar** —
  `[['||' => [['&&' => ruleA], ['&&' => ruleB]]]]` — and the unmodified
  `AAuthABACModelScope` compiles it to correctly parenthesized
  `((A-conds) OR (B-conds))` SQL. ✅
- The org-node union is the query shape that already exists (more LIKE branches). ✅
- Context loading stays at a **constant query count** for k roles (`whereIn`). ✅
- ⚠️ One trap: an **explicit empty rule `[]` inside an OR group is silently dropped**
  by Laravel's query builder (`addNestedWhereQuery` skips empty groups) — the branch
  means "contributes nothing" instead of "sees all". Fail-safe direction (under-grant,
  never a leak) but semantically wrong; must be short-circuited in PHP.

## 4. Non-breaking introduction path (verified)

- Constructor untouched; new static factory `AAuth::forRoles($user, array $roleIds)`.
- `$role` (public property) and `currentRole()` return a deterministic **primary role**;
  additive members: `roles()`, `hasRole(string)`, `isProfile()`.
- `switchableRoles()`'s select is **not widened** (serialized payload shapes would
  change); a separate `switchableProfiles()` returns
  `{key, label, strategy, role_ids}`.
- Strategy = one-method `ProfileStrategy` interface + class-string array in
  `config/aauth-advanced.php`. **Default empty = current behavior, byte-identical**
  (proof: the whole existing test suite must pass with `strategies = []`).
- No protected-method signature changes (userland subclass safety); Octane `scoped`
  binding unchanged.

## 5. Preconditions & database notes

| # | Issue | Action |
|---|---|---|
| P1 | `role_model_abac_rules` has no `UNIQUE(role_id, model_type)` — duplicate rows turn an OR-merge into "widest rule wins" | **Hard precondition:** dedupe + UNIQUE migration |
| P2 | SQLite hard-errors at 1000 OR terms | Deduplicate ancestor-covered nodes in PHP; collapse root-equality terms into `IN (ids)`. MySQL/PostgreSQL handle hundreds of terms fine (the pgsql `varchar_pattern_ops` index already ships) |
| P3 | The ABAC scope re-queries the rule per scope application | Serve rules from the per-request context — profile mode then becomes *cheaper* than today per query |

## 6. Worked example: the school

> Tree: `School → 9-A, 10-B, 11-C`
> Teacher Ayşe holds: **Math Teacher** @ 9-A, 10-B · **PE Teacher** @ 9-A, 10-B, 11-C
> A "Teacher profile" (same-scope strategy) is the textbook use case for this feature.

Three shapes, three risk levels:

| Case | Shape | Naive merge | Recommended design |
|---|---|---|---|
| 1 | Both roles at the **same nodes**, no/identical ABAC | **Safe** — the union creates no new (permission, row) pair. (If the permission sets are identical too, they should be *one role at many nodes* — no profile needed.) | Safe |
| 2 | **Different node sets** | **Leak 1 materializes:** `can('math_grade:enter')` passes (from the Math role) and the org union includes 11-C → Ayşe can enter *math grades* in a class where she only teaches PE. No single role grants that. In a school this is a grade-integrity violation. | **Blocked:** the coupled write check requires a *single* role to grant both the permission and the node — the Math role has no 11-C. |
| 3 | **Different ABAC rules** (e.g. Exam model: Math role `subject='math'`, PE role `subject='pe'`) | **Leak 3 materializes:** `(9A∨10B∨11C) ∧ (math∨pe)` shows Ayşe the *math exams of 11-C*, where she is not the math teacher. | **Correct:** per-role branches `(mathClasses ∧ math) ∨ (peClasses ∧ pe)` — math exams only from 9-A/10-B, PE records from all three. |

**Residual read gap in this scenario:** if the PE role has *no* ABAC rule for the Exam
model, its branch is `peClasses ∧ TRUE`, so a `math_exam:view` permission (granted by
the Math role) applies to 11-C's exam rows in listings — the documented read-side gap.
The practical fix here is clean: give every subject role a `subject='X'` ABAC rule on
Exam, which closes the gap entirely without the Phase 2 coupled-query API.

**SoD note:** math × PE is not a toxic combination, so SoD risk is low here. But the
same mechanism applied to e.g. *teacher + vice-principal* would merge grade-*entry*
with grade-*approval* — exactly the maker/checker separation profiles can silently
erase. Curate strategies with that in mind.

## 7. Open decisions (for the product owner)

1. **Row-visibility semantics** — recommended: per-role-coupled branches
   (`(subtree_r ∧ abac_r)` OR'ed). The cheaper "plain unions + documented
   virtual-union-role" alternative accepts Leak 3 and conflicts with the package's
   zero-leak principle — not recommended.
2. **SoD / curation level** — Phase 1: config opt-in + docs only. Phase 2 candidates:
   mutually-exclusive-role constraints, per-role `mergeable` flag, admin-curated
   persistent profiles (Keycloak-style).

## 8. Naming, effort, test gate

- **Name:** "Profile", documented as *multi-role activation (NIST RBAC session
  semantics)*. "Session" (Laravel collision) and "composite role" (implies
  persistence) were rejected.
- **Effort:** Phase 1 (safe core + full leak-test matrix + docs) ≈ **8–12 dev-days**.
- **Merge gate — required test matrix:**
  - a profile of one role behaves identically to today (byte-identical regression);
  - A@X + B@Y profile **cannot** perform A's action on Y's rows;
  - a permission shared with different parameters passes if either role's set passes;
  - a deactivated member role drops out on the next request;
  - explicit-empty-rule short-circuit;
  - Octane: two profile instances in one process never share state;
  - property test: profile read-set ⊆ union of member-role read-sets and ⊇ every
    single member-role read-set.
