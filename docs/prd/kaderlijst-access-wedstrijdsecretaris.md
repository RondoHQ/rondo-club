# PRD: Kaderlijst-toegang voor "Wedstrijdsecretaris mDWF"

> Give people whose Sportlink functie is **Wedstrijdsecretaris mDWF** access to the Kaderlijst —
> and establish that this needs **no theme code**, only two admin settings.

**Status:** Investigation — awaiting a decision on Option A vs Option B
**Components:** club (config only), sync (no change)
**Date:** 2026-08-03
**Baseline:** `main` @ `a8502197`

---

## 1. Conclusion first

**There is no `kaderlijst` capability, and no allowlist of functies anywhere in the code.** Nothing
in `rondo-club` or `rondo-sync` names a role for this page. Access is entirely derived from two
admin-managed `wp_options`:

| Option | Edited in | Governs |
|---|---|---|
| `rondo_functie_capability_map` | Instellingen → Beheer → Rollen | Sportlink functie → WP role |
| `rondo_age_group_access` | Instellingen → Beheer → Capabilities | WP role → visible leeftijdsgroepen |

So this request is a **configuration change on production**, not a pull request of code. What
follows is the trace that proves it, the choice that still has to be made, and the exact steps.

---

## 2. How the Kaderlijst is gated today

**Route and UI** — [`src/pages/Teams/Kaderlijst.jsx`](../../src/pages/Teams/Kaderlijst.jsx),
mounted at `/kaderlijst` in [`src/router.jsx:484`](../../src/router.jsx). Both the route and the
sidebar entry ([`Layout.jsx:60`](../../src/components/layout/Layout.jsx)) use the same single gate:

```jsx
{ path: 'kaderlijst', element: <KaderOrVrijwilligRedirect><Kaderlijst /></KaderOrVrijwilligRedirect> }
{ name: 'Kaderlijst', href: '/kaderlijst', icon: Users, indent: true, requiresKader: true }
```

Both read `currentUser.is_kader`. That flag has one definition, in
[`class-rest-user-settings.php:1124`](../../includes/class-rest-user-settings.php):

```php
$is_kader = $is_admin
    || $has_extra_roles                       // any WP role beyond rondo_user / subscriber
    || current_user_can( 'fairplay' ) || … || current_user_can( 'vrijwilligers' );
```

`$has_extra_roles` is the operative clause: **any** Rondo role other than the `rondo_user` baseline
makes a user kader. There is no Kaderlijst-specific capability to add a role to.

**Data** — `GET /rondo/v1/kaderlijst/people`
([`class-rest-api.php:440`](../../includes/class-rest-api.php)) is guarded only by
`check_user_approved`. Every approved user may call it; the *rows returned* are scoped server-side
by `AccessControl::get_permitted_age_groups()`:

| `get_permitted_age_groups()` | Who | Kaderlijst shows |
|---|---|---|
| `null` | has a management capability | every kaderlid |
| non-empty list | coordinator | kader of teams whose roster has those leeftijdsgroepen |
| `[]` | plain member | only their own household |

This is the trap in this request: granting a *role* opens the page, but if that role has neither a
management capability nor an `rondo_age_group_access` entry, `get_permitted_age_groups()` returns
`[]` and the page opens **empty**. Both options below therefore change two settings, not one.

---

## 3. How a Sportlink functie reaches Rondo

The string is carried verbatim end to end — no normalisation, no truncation, no case folding:

```
Sportlink  FunctionDescription
  └─ steps/download-functions-from-sportlink.js:47   → member_functions.function_description
       ├─ steps/submit-capability-sync.js:29         → POST /rondo/v1/capability-sync { knvb_id, functies }
       │    └─ CapabilitySync::sync_user()           → FunctieCapabilityMap::get_roles_for_functie( $functie )
       │         └─ $map[ $functie ] ?? []           ← exact array-key lookup
       └─ steps/submit-rondo-club-commissie-work-history.js:309
            → work_history row, job_title = function_description, commissie "Verenigingsbreed"
```

The second path is what populates the picker: `get_available_werkfuncties()`
([`class-rest-capabilities.php:312`](../../includes/class-rest-capabilities.php)) lists the distinct
`work_history.job_title` values that actually exist. **"Wedstrijdsecretaris mDWF" will therefore
appear in the Rollen dropdown by itself, spelled exactly as Sportlink spells it** — provided at
least one active member currently holds it. If it is absent from the dropdown, the functie is not
in the club's data and nothing here applies yet.

`CapabilitySync::sync_user()` reconciles: it grants and **revokes** to match the map, so removing
the mapping later removes the role on the next sync run. Administrators are skipped entirely, and
`rondo_user` is never touched. Admin overrides (`_rondo_cap_manual_grants` / `_manual_revokes`)
survive sync runs.

---

## 4. The change

Both options follow the existing pattern — the role→capability map — and neither adds a third one.
Which role to hang it on is the open question.

### Option A — dedicated role, coordinator scope (recommended)

1. **Instellingen → Beheer → Rollen** — create a custom role, e.g. **Wedstrijdzaken**
   (slug `rondo_wedstrijdzaken`). Custom roles start with no capabilities
   ([`UserRoles::get_all_roles()`](../../includes/class-user-roles.php)), which is the point.
2. In the same tab, tick `Wedstrijdsecretaris mDWF` → **Wedstrijdzaken** in the functie map.
3. **Instellingen → Beheer → Capabilities** — under age-group access, give `rondo_wedstrijdzaken`
   every leeftijdsgroep.

Result: `is_kader` true via `$has_extra_roles`, no management capability, read-only Kaderlijst
scoped to all age groups. Nothing else in the sidebar opens.

**Known limitation:** a coordinator-scoped viewer only sees kader attached to a team
(`filter_candidates_by_teams()`). Teamless kader — bestuursfuncties, verenigingsbrede rollen —
are filtered out, even though `kaderlijst_candidate_ids()` deliberately includes them for
management. If the wedstrijdsecretaris must see those too, Option B is the only current answer.

### Option B — reuse a management role

Map `Wedstrijdsecretaris mDWF` → an existing role holding a bypass capability, e.g.
`rondo_vrijwilligers`. `get_permitted_age_groups()` returns `null`, so the full Kaderlijst appears
including teamless kader — but the user also gets the Vrijwilligers module and management-level
person visibility across the club. Broader than the request.

**Recommendation: Option A.** It matches the ask literally ("see the Kaderlijst"), and it is a
setting away from Option B if the teamless-kader gap turns out to matter in practice.

### Option C — a "Wedstrijdzaken" commissie — do not use (it flaps)

There *is* a second grant path: `CommissieCapabilityMap` maps a commissie post ID → roles, and
`CapabilitySync::sync_user()` unions those with the functie-derived roles. So creating a
Wedstrijdzaken commissie, adding the wedstrijdsecretarissen to it, and mapping that commissie to a
role looks like it should work. It does not — because the two sync entry points disagree about
whether commissies exist:

| Entry point | Commissie roles computed? |
|---|---|
| `POST /rondo/v1/capability-sync` (per member) — what the **scheduled** `pipelines/sync-functions.js` calls | **No.** `sync_user_by_knvb_id()` ([`class-capability-sync.php:291`](../../includes/class-capability-sync.php)) calls `sync_user( $user_id, $functies )` and lets `$commissie_ids` default to `[]` |
| `POST /rondo/v1/capability-sync/all` — the manual "Sync now" button | Yes, via `derive_from_work_history()` |
| Per-person "Sync rollen" ([`class-rest-api.php:2082`](../../includes/class-rest-api.php)) | Yes |

`sync_user()` is a reconciler: `to_revoke = current_roles − target_roles`. On the scheduled path the
commissie contributes nothing to `target_roles`, so the commissie-granted role is **revoked**. Press
"Sync now" and it comes back. The user would lose the Kaderlijst on every scheduled functions sync
(weekly full run per `rondo-sync/docs/operations.md`) and regain it whenever an admin syncs manually.
Intermittent access is worse than none — it generates support tickets that look like login bugs.

Two things that are *not* the problem, for the record: commissie membership itself is durable
(`submit-rondo-club-commissie-work-history.js:156` leaves manually-added, non-sync-created
work_history rows alone), and a commissie still needs the same age-group setting as Option A — so
it is not a shortcut around section 2's trap either.

The escape hatch that would normally cover this — `_rondo_cap_manual_grants`, which `sync_user()`
honours across runs — is **read-only in practice: nothing in the codebase ever writes those two
meta keys.** They can only be set by hand via WP-CLI.

Fixing Option C properly is a one-line change with a real blast radius: have
`sync_user_by_knvb_id()` derive commissie IDs the way `sync_user_by_person_id()` already does, so
all three entry points agree. That would retroactively activate every existing
`rondo_commissie_capability_map` entry on the scheduled path — which may be exactly right, or may
grant roles nobody has audited since the map was filled in. It deserves its own PR and a look at the
live option value first. Until then, Option A is the only path that survives a sync run.

---

## 5. Edge cases

- **`Wedstrijdsecretaris` vs `Wedstrijdsecretaris mDWF` do not bleed into each other.**
  `get_roles_for_functie()` is an exact array-key lookup (`$map[ $functie ] ?? []`), not a substring
  match, so mapping the mDWF variant grants nothing to the base functie. This is the *opposite* of
  the older `RoleFinder`, which does substring matching and needed a documented case-sensitivity fix
  precisely so `Secretaris` would not match `Wedstrijdsecretaris`. Both spellings must be ticked
  separately if both are wanted — and someone should confirm in the picker whether the club's
  Sportlink data actually uses the base string, the mDWF variant, or both.
- **View-only stays view-only.** `/rondo/v1/kaderlijst/people` registers `READABLE` only, returns a
  fixed field allowlist (no financial flags, no private meta), and `Kaderlijst.jsx` renders a
  `DataTable` with no edit, no mutation and no export control. Nothing in either option touches
  `can_edit_people`, `can_edit_person_contact`, or any finance capability.
- **Age-group config and management capabilities are mutually exclusive.** The capability matrix
  clears a role's age-group config when that role gains a management capability
  ([`AccessControl::get_management_capabilities()`](../../includes/class-access-control.php)).
  Adding a management cap to the Option A role later would silently widen it into Option B.
- **Multi-club:** Rondo is single-tenant per WordPress install (production, demo and legacy are
  separate installs). Both options are per-site `wp_options`, so this must be applied on each site
  separately — production first — and does **not** leak across clubs. There is no club-scoping
  dimension to design for.
- **Sync timing:** the role appears after the next `capability-sync` run, or immediately via the
  per-person "Sync rollen" action ([`class-rest-api.php:2071`](../../includes/class-rest-api.php)).
  Existing sessions need a reload — `is_kader` is read once per `/users/me` fetch.
- **Doc drift found in passing:** the functie-map example in
  `developer/src/content/docs/api/rest-api.md:1207` shows `"Wedstrijdsecretaris": ["wedstrijdzaken"]`,
  an array of strings. The endpoint stores and returns `{ "role_slug": bool }`. Worth correcting
  separately.

---

## 6. Verification after the config change

1. Sign in as a member whose only functie is `Wedstrijdsecretaris mDWF`.
2. `Teams → Kaderlijst` is present in the sidebar and `/kaderlijst` renders rows (not empty, not a
   redirect to `/vrijwillig`).
3. `GET /wp-json/rondo/v1/users/me` → `is_kader: true`, `permitted_age_groups` is the configured
   list, and every `can_edit_*` / `can_access_financieel` flag is still `false`.
4. A member holding only the base `Wedstrijdsecretaris` functie is **unchanged** — this is the
   regression that matters most.

---

## 7. Tests: no new test here, but a coverage gap worth naming

A test asserting that the literal string `Wedstrijdsecretaris mDWF` maps to a role would be
asserting the contents of a production `wp_option`, which the suite deliberately does not own. So
this change carries no test.

The mechanism underneath it, however, is thinner than it looks. `tests/Wpunit/AgeGroupAccessTest.php`
covers `get_permitted_age_groups()` and the person-visibility tiers. But **`CapabilitySync` and
`FunctieCapabilityMap` have no test coverage at all** — `grep -rl 'CapabilitySync\|FunctieCapabilityMap' tests/`
returns nothing. The exact-key lookup that section 5 leans on for the
`Wedstrijdsecretaris` / `Wedstrijdsecretaris mDWF` separation is therefore asserted by reading the
code, not by a test.

That gap predates this request and is not caused by it, but it is the one thing here I would
actually spend code on: a wpunit test that a mapped functie grants exactly its role, that a
near-miss spelling grants nothing, and that `sync_user()` revokes when the mapping is removed. If
the decision instead becomes "ship a real `kaderlijst` capability in code", that is a different
PRD and it needs tests of its own.
