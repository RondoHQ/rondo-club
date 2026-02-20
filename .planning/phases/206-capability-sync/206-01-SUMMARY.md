---
phase: 206-capability-sync
plan: 01
subsystem: api
tags: [wordpress, php, roles, capability-sync, rest-api, rondo-users]

# Dependency graph
requires:
  - phase: 204-functie-capability-map
    provides: FunctieCapabilityMap::get_roles_for_functie() static method
  - phase: 205-user-provisioning
    provides: UserProvisioning::META_KNVB_ID constant and provisioning pattern
provides:
  - CapabilitySync service class with sync_user(), sync_all(), sync_user_by_knvb_id()
  - POST /rondo/v1/capability-sync REST endpoint (per-user, knvb_id + functies)
  - POST /rondo/v1/capability-sync/all REST endpoint (body-less, ACF-derived)
  - Manual override meta keys: META_MANUAL_GRANTS, META_MANUAL_REVOKES
affects: [206-02-rondo-sync-step, settings-functies-ui]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Pure PHP service class pattern (Rondo\\Users namespace, no hooks, PSR-4 autoloaded) — same as UserProvisioning"
    - "Administrator guard: in_array('administrator', $user->roles, true) checked before any mutation"
    - "Role reconciliation: add_role()/remove_role() for diff application — never set_role()"
    - "Manual override tracking via user meta JSON arrays (_rondo_cap_manual_grants, _rondo_cap_manual_revokes)"

key-files:
  created:
    - includes/class-capability-sync.php
  modified:
    - includes/class-rest-api.php

key-decisions:
  - "sync_all() derives functies from ACF work_history (body-less endpoint) — server-side re-application of current map"
  - "sync_user_by_knvb_id() returns {status: no_user} with HTTP 200 (not 404) when no WP user has the KNVB ID"
  - "rondo_user excluded from syncable roles: only rondo_fairplay, rondo_vog, rondo_bestuur are managed by sync"
  - "Manual overrides: target_roles = (mapped ∪ manual_grants) − manual_revokes, filtered to syncable"

patterns-established:
  - "Pattern 1: Administrator guard is first check in sync_user() before any role mutations"
  - "Pattern 2: sync_user_by_knvb_id() returns no_user status (HTTP 200) for members without WP accounts"

# Metrics
duration: 12min
completed: 2026-02-20
---

# Phase 206 Plan 01: Capability Sync Backend Summary

**CapabilitySync PHP service class with grant/revoke reconciliation, administrator guard, manual override tracking, and two admin-only REST endpoints for per-user and bulk capability sync**

## Performance

- **Duration:** 12 min
- **Started:** 2026-02-20T19:55:48Z
- **Completed:** 2026-02-20T20:07:30Z
- **Tasks:** 2
- **Files modified:** 2

## Accomplishments
- Created `CapabilitySync` pure service class in `Rondo\Users` namespace with three public methods
- Reconciliation algorithm: computes syncable roles diff respecting manual override grants/revokes
- Administrator guard prevents any role mutations on administrator users (CAPS-07)
- Registered two admin-only REST endpoints in `class-rest-api.php`
- `npm run build` passes — no frontend regressions

## Task Commits

Each task was committed atomically:

1. **Task 1: Create CapabilitySync service class** - `312b82c2` (feat)
2. **Task 2: Register capability-sync REST endpoints** - `33042f7c` (feat)

**Plan metadata:** (docs commit follows)

## Files Created/Modified
- `includes/class-capability-sync.php` - Pure service class with sync_user(), sync_all(), sync_user_by_knvb_id(), derive_functies_from_work_history()
- `includes/class-rest-api.php` - Added two REST routes + sync_user_capabilities() and sync_all_capabilities() callbacks

## Decisions Made
- `sync_all()` is body-less: derives functies from each provisioned user's linked person's ACF `work_history` field (is_current entries). Settings UI doesn't have access to Sportlink data — server re-applies current map against existing ACF data.
- `sync_user_by_knvb_id()` returns `{status: 'no_user'}` with HTTP 200 (not a 404 or WP_Error). Most tracked members won't have a WP account; a non-error response prevents error-log flooding in rondo-sync.
- `rondo_user` excluded from syncable roles set — it's the base role managed only by provisioning/deletion.
- Manual overrides: target = (mapped ∪ manual_grants) − manual_revokes. Overrides stored as JSON-encoded arrays in user meta.

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered
None.

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- Backend complete: CapabilitySync service + two REST endpoints ready for consumption
- Plan 02 (rondo-sync step) can now call `POST /rondo/v1/capability-sync` per member
- Settings UI can call `POST /rondo/v1/capability-sync/all` for on-demand sync button
- Manual override UI (AccountCard role toggles) deferred to future phase — data model is in place

---
*Phase: 206-capability-sync*
*Completed: 2026-02-20*
