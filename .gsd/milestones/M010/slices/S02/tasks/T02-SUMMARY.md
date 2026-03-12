---
id: T02
parent: S02
milestone: M010
provides:
  - "GET /rondo/v1/settings/age-group-access endpoint"
  - "POST /rondo/v1/settings/age-group-access endpoint"
  - "permitted_age_groups field in /rondo/v1/user/me response"
  - "getAgeGroupAccess() and updateAgeGroupAccess() frontend API client methods"
key_files:
  - includes/class-rest-api.php
  - src/api/client.js
key_decisions:
  - "Roles object cast to (object) in GET response so empty config returns {} not [] in JSON"
  - "Invalid role slugs return 400 WP_Error immediately rather than silently skipping — prevents config typos from being swallowed"
  - "Age groups sorted with natural numeric extraction (Onder 6 < Onder 7 < ... < Senioren) in GET response"
patterns_established:
  - "Settings endpoints follow capability-matrix pattern: GET/POST on same route, admin-only, POST returns fresh state"
observability_surfaces:
  - "GET /rondo/v1/settings/age-group-access — inspect current per-role config and available age groups"
  - "GET /rondo/v1/user/me — permitted_age_groups field shows null (no restriction) or string[] for current user"
  - "POST returns 400 with invalid_role_slug code for bad role slugs"
duration: 10min
verification_result: passed
completed_at: 2026-03-12
blocker_discovered: false
---

# T02: REST endpoints for age-group access and /me extension

**Added REST endpoints for age-group access config (GET/POST) with role validation, extended /me with permitted_age_groups, and added frontend API client methods.**

## What Happened

1. Registered `GET/POST /rondo/v1/settings/age-group-access` routes in `class-rest-api.php` alongside the existing capability-matrix routes, both requiring `manage_options`.
2. Implemented `get_age_group_access()` — queries distinct leeftijdsgroep meta values from published person posts, sorts naturally, returns `{ roles, available_age_groups }`.
3. Implemented `update_age_group_access()` — validates role slugs against `UserRoles::ROLES` + 'administrator', sanitizes values, strips empty arrays, saves to `rondo_age_group_access` option, returns fresh state.
4. Extended `get_current_user()` response with `permitted_age_groups` field calling `AccessControl::get_permitted_age_groups()`.
5. Added `getAgeGroupAccess()` and `updateAgeGroupAccess()` to `src/api/client.js` next to capability-matrix methods.

## Verification

- `php -l includes/class-rest-api.php` — no syntax errors ✅
- `php -l includes/class-access-control.php` — no syntax errors ✅
- `php -l includes/class-rest-people.php` — no syntax errors ✅
- `npm run build` — zero errors ✅
- `npm run lint` — zero warnings ✅
- `grep 'age-group-access' includes/class-rest-api.php` — finds route registration ✅
- `grep 'permitted_age_groups' includes/class-rest-api.php` — finds field in get_current_user ✅
- `grep 'getAgeGroupAccess\|updateAgeGroupAccess' src/api/client.js` — finds both methods ✅
- Production: `get_age_group_access()` returns 21 available age groups correctly sorted ✅
- Production: `update_age_group_access()` saves config, empty arrays removed ✅
- Production: `get_current_user()` returns `permitted_age_groups: null` for admin ✅
- Production: invalid role slug returns `WP_Error(invalid_role_slug, 400)` ✅

## Diagnostics

- `GET /rondo/v1/settings/age-group-access` — shows current per-role config and all available leeftijdsgroep values
- `GET /rondo/v1/user/me` — `permitted_age_groups` is `null` (unrestricted) or `string[]` (restricted)
- `wp option get rondo_age_group_access --format=json` — raw stored config via WP-CLI
- POST with invalid role slug returns 400 with `invalid_role_slug` error code

## Deviations

None.

## Known Issues

None.

## Files Created/Modified

- `includes/class-rest-api.php` — Added route registration, `get_age_group_access()`, `update_age_group_access()` handlers, and `permitted_age_groups` in `/me` response
- `src/api/client.js` — Added `getAgeGroupAccess()` and `updateAgeGroupAccess()` API client methods
