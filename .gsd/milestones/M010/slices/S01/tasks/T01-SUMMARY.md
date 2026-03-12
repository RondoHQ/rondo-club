---
id: T01
parent: S01
milestone: M010
provides:
  - REST GET/POST endpoints for role×capability matrix at /rondo/v1/settings/capability-matrix
  - Fixed register_role() to not re-add capabilities to existing roles
  - Replaced all 6 current_user_can('administrator') with current_user_can('manage_options')
key_files:
  - includes/class-rest-api.php
  - includes/class-user-roles.php
  - includes/class-rest-people.php
  - includes/class-rest-teams.php
  - includes/class-rest-commissies.php
key_decisions:
  - Matrix reads/writes WP roles directly (no separate wp_option storage) — wp_roles() is the single source of truth
  - Only 5 custom Rondo capabilities exposed in matrix (fairplay, vog, financieel, toegangscontrole, manage_clothing)
  - Administrator manage_options is protected from removal in POST handler
patterns_established:
  - capability-matrix REST endpoints follow exact same registration pattern as functie-capability-map
  - GET returns { roles, capability_labels } with human-readable labels for UI consumption
observability_surfaces:
  - GET /rondo/v1/settings/capability-matrix returns current role×capability state
  - POST returns updated matrix for immediate UI verification
  - HTTP 403 if not admin, 400 for invalid data
duration: 10m
verification_result: passed
completed_at: 2026-03-12
blocker_discovered: false
---

# T01: Backend — REST endpoints, register_role fix, and role-name check fixes

**Added REST capability-matrix endpoints, fixed register_role() cap-override bug, and replaced 6 hardcoded administrator role checks with manage_options capability checks.**

## What Happened

All 5 steps executed cleanly:

1. **REST routes registered** — `GET /rondo/v1/settings/capability-matrix` and `POST /rondo/v1/settings/capability-matrix` added to `class-rest-api.php` following the `functie-capability-map` pattern. Both use `check_admin_permission()`.

2. **register_role() fixed** — Removed the `add_cap()` loop (lines 76-82) that ran on ALL roles including existing ones. Now `add_role()` sets capabilities on creation (new roles only), and existing roles are untouched — the matrix becomes the ongoing management tool. Administrator-specific `add_cap()` calls preserved for fresh-install safety.

3. **6 administrator role checks replaced** — 3 in `class-rest-people.php` (lines 594, 630, 763), 1 in `class-rest-teams.php` (line 486), 2 in `class-rest-commissies.php` (lines 254, 378). All changed from `current_user_can('administrator')` to `current_user_can('manage_options')`.

4. **Handler methods implemented** — `get_capability_matrix()` builds the matrix from `wp_roles()` with human-readable capability labels. `update_capability_matrix()` diffs desired vs current state, calls `add_cap()`/`remove_cap()` per role, and protects administrator's `manage_options` from removal.

## Verification

- `php -l` on all 5 files — **all pass**, no syntax errors
- `grep -c "current_user_can( 'administrator' )"` on 3 REST files — **all return 0**
- `npm run build` — **passes** (109 precache entries)
- `npm run lint` — **passes** (0 warnings)
- Diff review: all changes match plan, minimal and correct

## Diagnostics

- `GET /rondo/v1/settings/capability-matrix` — inspect current role×capability state (requires admin auth)
- `wp role list --fields=name,capabilities` via WP-CLI — raw WordPress role state
- POST endpoint returns fresh matrix after writes, enabling immediate UI verification
- Errors return structured `WP_Error` with descriptive messages and appropriate HTTP status codes

## Deviations

None — implementation matches the task plan exactly.

## Known Issues

None.

## Files Created/Modified

- `includes/class-rest-api.php` — Added 2 REST route registrations and 2 handler methods (get_capability_matrix, update_capability_matrix)
- `includes/class-user-roles.php` — Removed add_cap() loop that re-added capabilities to existing roles
- `includes/class-rest-people.php` — 3 instances of administrator → manage_options
- `includes/class-rest-teams.php` — 1 instance of administrator → manage_options
- `includes/class-rest-commissies.php` — 2 instances of administrator → manage_options
