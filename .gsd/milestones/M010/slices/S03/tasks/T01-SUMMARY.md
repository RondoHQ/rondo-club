---
id: T01
parent: S03
milestone: M010
provides:
  - suppress_age_group REST param handling in filter_rest_query()
  - Kaderlijst fetchAllPeople() passes suppress_age_group=true
key_files:
  - includes/class-access-control.php
  - src/pages/Teams/Kaderlijst.jsx
key_decisions:
  - Bypass param checked before age-group filtering block (not inside it) so the static flag is set before any filter method reads it
patterns_established:
  - REST query param to control static filter flag pattern
observability_surfaces:
  - suppress_age_group=true visible in network requests during Kaderlijst rebuild
duration: 5m
verification_result: passed
completed_at: 2026-03-12T22:20:00+01:00
blocker_discovered: false
---

# T01: Add Kaderlijst age-group bypass in PHP and frontend

**Added `suppress_age_group` REST param in PHP `filter_rest_query()` and Kaderlijst `fetchAllPeople()` to prevent age-group filtering from corrupting Kaderlijst snapshots.**

## What Happened

Two surgical changes:

1. **PHP** (`filter_rest_query()`): Added a 3-line block before the age-group filtering section that checks if `$request->get_param('suppress_age_group')` is truthy and user is authenticated. If so, sets `self::$suppress_age_group_filter = true`. This flag is read by all three filter methods (`filter_rest_query`, `filter_queries`, `filter_rest_single_access`), so a single set covers all paths.

2. **Frontend** (`fetchAllPeople()`): Added `suppress_age_group: true` to the params object used for all paginated people requests during Kaderlijst rebuild.

The bypass only affects age-group filtering — VOG filtering, clothing access, todo visibility, and all other access controls remain unchanged.

## Verification

- `npm run build` — ✅ built successfully in 17s
- `npm run lint` — ✅ zero warnings/errors
- `grep -n "suppress_age_group" includes/class-access-control.php` — ✅ shows param check at line 381-382 in `filter_rest_query()`, plus the existing static flag and filter checks
- `grep -n "suppress_age_group" src/pages/Teams/Kaderlijst.jsx` — ✅ shows param at line 243 in `fetchAllPeople()`

### Slice-level verification (partial — T01 is first of 3 tasks):
- ✅ `npm run build` passes
- ✅ `npm run lint` passes
- ✅ `suppress_age_group` grep in access-control.php
- ✅ `suppress_age_group` grep in Kaderlijst.jsx
- ⏳ `rest_forbidden_age_group` grep in PersonDetail.jsx — T02
- ⏳ `permitted_age_groups` grep in PeopleList.jsx — T03
- ⏳ Production deploy — T03

## Diagnostics

- **Network inspection:** During Kaderlijst "Verversen" action, browser DevTools will show `/wp/v2/people?...&suppress_age_group=true` in network requests
- **Failure mode:** If the bypass fails, Kaderlijst snapshot will contain fewer people than expected for restricted users who trigger a rebuild — compare member count in snapshot vs actual total

## Deviations

None.

## Known Issues

None.

## Files Created/Modified

- `includes/class-access-control.php` — Added `suppress_age_group` param check in `filter_rest_query()` before age-group filtering block
- `src/pages/Teams/Kaderlijst.jsx` — Added `suppress_age_group: true` to `fetchAllPeople()` params
