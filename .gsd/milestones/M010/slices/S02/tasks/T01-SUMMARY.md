---
id: T01
parent: S02
milestone: M010
provides:
  - AccessControl::get_permitted_age_groups() static method with management capability bypass
  - Age-group filtering at all 3 query points (filter_queries, filter_rest_query, get_filtered_people)
  - Single-item access control via filter_rest_single_access with rest_forbidden_age_group error code
  - Static $suppress_age_group_filter flag for Kaderlijst bypass
  - WPUnit tests for get_permitted_age_groups()
key_files:
  - includes/class-access-control.php
  - includes/class-rest-people.php
  - tests/Wpunit/AgeGroupAccessTest.php
key_decisions:
  - Used INNER JOIN (not LEFT JOIN) for age-group SQL filtering in get_filtered_people() — people without a leeftijdsgroep value are excluded when access filtering is active, which is the correct behavior (unclassified people should not leak through)
  - Age-group access config stored as PHP array in wp_option (WordPress serializes it); also handles JSON string for forward compatibility with REST save
patterns_established:
  - AGE_GROUP_BYPASS_CAPS constant lists all 6 management capabilities that bypass filtering, reusable by other methods
  - get_permitted_age_groups() is static so it can be called from REST People class without an AccessControl instance
observability_surfaces:
  - rest_forbidden_age_group error code in 403 responses for single-item access denied
  - rondo_age_group_access wp_option inspectable via WP-CLI
  - permitted_age_groups in /rondo/v1/user/me (to be added in T02)
duration: 15m
verification_result: passed
completed_at: 2026-03-12T22:25:00+01:00
blocker_discovered: false
---

# T01: Backend storage, helper methods, and query filtering

**Added `get_permitted_age_groups()` with 6-capability management bypass and age-group filtering at all 3 query points plus single-item REST access control.**

## What Happened

Implemented the core backend for age-group access filtering in `AccessControl`:

1. Added `$suppress_age_group_filter` static flag and `AGE_GROUP_BYPASS_CAPS` constant (6 management capabilities).
2. Added `get_permitted_age_groups($user_id)` — reads `rondo_age_group_access` wp_option, iterates user roles, returns merged unique array or null for unrestricted users. Handles both PHP array and JSON string config formats.
3. Added `has_age_group_restriction()` instance helper and `apply_age_group_filter()` WP_Query modifier (follows `apply_vog_filter()` pattern).
4. Modified `filter_queries()` — adds leeftijdsgroep meta_query for person queries when restricted and not suppressed.
5. Modified `filter_rest_query()` — adds same meta_query to REST person query args.
6. Modified `filter_rest_single_access()` — returns `WP_Error` with `rest_forbidden_age_group` code for person posts outside permitted age groups.
7. Modified `get_filtered_people()` in REST People — adds `INNER JOIN` on postmeta for leeftijdsgroep with `IN (...)` prepared placeholders, using `ag` alias (distinct from user-filter's `lg` alias).
8. Created comprehensive WPUnit test file with 12 test cases covering all bypass capabilities, empty/missing config, single-role config, multi-role merge, JSON string config, and suppress flag.

## Verification

- `php -l includes/class-access-control.php` — no syntax errors ✅
- `php -l includes/class-rest-people.php` — no syntax errors ✅
- `php -l includes/class-rest-api.php` — no syntax errors ✅
- `npm run build` — zero errors ✅
- `npm run lint` — zero warnings ✅
- `grep -c 'suppress_age_group_filter' includes/class-access-control.php` → 4 (≥3 required) ✅
- `grep -c 'get_permitted_age_groups' includes/class-access-control.php` → 5 (≥2 required) ✅
- `grep -c 'leeftijdsgroep' includes/class-access-control.php` → 5 (≥2 required) ✅
- `tests/Wpunit/AgeGroupAccessTest.php` exists with 12 meaningful test methods ✅

### Slice-level checks (partial — T01 is not final task):
- ✅ PHP syntax valid on all 3 PHP files
- ✅ `npm run build` passes
- ✅ `npm run lint` passes
- ⏳ REST endpoints for age-group access (T02)
- ⏳ `/rondo/v1/user/me` permitted_age_groups field (T02)
- ⏳ CapabilitiesTab Ledendata column (T03)
- ⏳ Production verification (T04)

## Diagnostics

- `wp option get rondo_age_group_access --format=json` — inspect current per-role config
- REST 403 responses with `rest_forbidden_age_group` code indicate age-group access denial
- `AccessControl::$suppress_age_group_filter` can be set to `true` to temporarily bypass (Kaderlijst use case)

## Deviations

None.

## Known Issues

None.

## Files Created/Modified

- `includes/class-access-control.php` — Added $suppress_age_group_filter flag, AGE_GROUP_BYPASS_CAPS constant, get_permitted_age_groups(), has_age_group_restriction(), apply_age_group_filter(); modified filter_queries(), filter_rest_query(), filter_rest_single_access()
- `includes/class-rest-people.php` — Extended get_filtered_people() with age-group SQL INNER JOIN + WHERE clause
- `tests/Wpunit/AgeGroupAccessTest.php` — New test file with 12 test cases for get_permitted_age_groups()
