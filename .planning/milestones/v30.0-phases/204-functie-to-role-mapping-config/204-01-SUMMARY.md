---
phase: 204-functie-to-role-mapping-config
plan: 01
subsystem: ui, api, config
tags: [react, php, wordpress-options, rest-api, settings, checkbox-matrix]

# Dependency graph
requires:
  - phase: 203-wp-admin-blocking
    provides: WP Admin Blocking — users blocked from wp-admin, REST API unaffected
  - phase: 201-werkfuncties-available-endpoint
    provides: GET /rondo/v1/werkfuncties/available endpoint for row data
provides:
  - FunctieCapabilityMap PHP class with get_map(), update_map(), get_roles_for_functie() static methods
  - GET/POST /rondo/v1/functie-capability-map REST endpoints (admin only)
  - FunctiesTab checkbox matrix in Settings > Beheer > Functies
  - Persisted mapping from Sportlink Functies to Rondo WordPress roles
affects:
  - phase-206-capability-sync
  - rondo-sync

# Tech tracking
tech-stack:
  added: []
  patterns:
    - Static config class in Rondo\Config namespace (OPTION_KEY constant, get_map/update_map/get_roles_for_ pattern)
    - Checkbox matrix UI component with stale-row detection (not in availableFuncties => "(niet meer actief)")
    - Admin-only GET+POST REST route pair returning { map, roles } shape

key-files:
  created:
    - includes/class-functie-capability-map.php
  modified:
    - includes/class-rest-api.php
    - src/api/client.js
    - src/pages/Settings/Settings.jsx
    - CHANGELOG.md
    - style.css
    - package.json

key-decisions:
  - "FunctieCapabilityMap is a pure static class with no constructor or hooks — PSR-4 autoloaded, used statically from REST API"
  - "GET endpoint returns both the map and roles list in one response so the UI can render columns without a separate request"
  - "Row list = union(availableFuncties, keys(functieMapState)) so stale Functies remain visible until admin cleans them up"
  - "Stale Functies shown with (niet meer actief) label in gray italic — not removed automatically"
  - "No functions.php instantiation needed — FunctieCapabilityMap is autoloaded via PSR-4, called statically"

patterns-established:
  - "Checkbox matrix pattern: grid-cols-[1fr,auto] with dynamic gridTemplateColumns for N role columns"
  - "functie-capability-map REST shape: { map: {functie: {role_slug: bool}}, roles: [{slug, label}] }"

# Metrics
duration: 6min
completed: 2026-02-20
---

# Phase 204 Plan 01: Functie-to-Role Mapping Config Summary

**Admin-configurable checkbox matrix mapping Sportlink Functies to Rondo WordPress roles, with FunctieCapabilityMap PHP config class, GET/POST REST endpoints, and FunctiesTab in Settings > Beheer**

## Performance

- **Duration:** 6 min
- **Started:** 2026-02-20T17:04:29Z
- **Completed:** 2026-02-20T17:10:53Z
- **Tasks:** 2
- **Files modified:** 6 (plus developer docs in separate repo)

## Accomplishments
- `FunctieCapabilityMap` static PHP class stores Functie->role mapping in `rondo_functie_capability_map` WP option
- GET/POST `/rondo/v1/functie-capability-map` REST endpoints (admin-only) for reading and persisting the mapping
- `FunctiesTab` React component renders checkbox matrix (Functies as rows, Rondo roles as columns) in Settings > Beheer > Functies
- Stale Functies (in saved map but no longer in DB) appear with "(niet meer actief)" label
- Developer docs updated in `developer/src/content/docs/features/access-control.md`
- Version bumped to 29.1.0

## Task Commits

Each task was committed atomically:

1. **Task 1: Create FunctieCapabilityMap PHP class and REST endpoints** - `698a6486` (feat)
2. **Task 2: Add FunctiesTab UI, API client methods, and developer docs** - `2e796fad` (feat)
3. **Version/changelog bump** - `e898865e` (chore)

## Files Created/Modified
- `includes/class-functie-capability-map.php` — New: `Rondo\Config\FunctieCapabilityMap` static class with `get_map()`, `update_map()`, `get_roles_for_functie()`
- `includes/class-rest-api.php` — Added route registration for `/rondo/v1/functie-capability-map` and three new methods: `get_rondo_roles_list()`, `get_functie_capability_map()`, `update_functie_capability_map()`
- `src/api/client.js` — Added `getFunctieCapabilityMap` and `updateFunctieCapabilityMap` methods
- `src/pages/Settings/Settings.jsx` — Added `ADMIN_SUBTABS` Functies entry, functie state variables, `useEffect` fetch, `handleFunctiesSave`, updated `AdminTabWithSubtabs`, added `FunctiesTab` component
- `CHANGELOG.md` — Added 29.1.0 entry
- `style.css` / `package.json` — Bumped version to 29.1.0

## Decisions Made
- `FunctieCapabilityMap` is a pure static class — no constructor, no hooks, no `functions.php` entry needed. PSR-4 autoloads it; the REST API class calls it statically.
- GET endpoint returns `{ map, roles }` so the UI gets both data sources in one round trip instead of two.
- The row list is a union of `availableFuncties` and `Object.keys(functieMapState)` so stale Functies remain visible (with label) until admin explicitly cleans them up.

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

None — no external service configuration required.

## Next Phase Readiness

- Phase 206 (Capability Sync) can now call `FunctieCapabilityMap::get_roles_for_functie($functie)` to determine which roles a user's active Functies should grant during rondo-sync runs
- The mapping endpoint is live at `/rondo/v1/functie-capability-map` (admin only)
- UI is deployed and accessible at Settings > Beheer > Functies on production

---
*Phase: 204-functie-to-role-mapping-config*
*Completed: 2026-02-20*

## Self-Check: PASSED

All files verified present:
- `includes/class-functie-capability-map.php` - FOUND
- `includes/class-rest-api.php` - FOUND
- `src/api/client.js` - FOUND
- `src/pages/Settings/Settings.jsx` - FOUND
- `.planning/phases/204-functie-to-role-mapping-config/204-01-SUMMARY.md` - FOUND

All commits verified:
- `698a6486` (Task 1: PHP class + REST endpoints) - FOUND
- `2e796fad` (Task 2: FunctiesTab UI + API client) - FOUND
