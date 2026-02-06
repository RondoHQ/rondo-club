---
phase: 87-acf-foundation
plan: 02
subsystem: api
tags: [acf, custom-fields, rest-api, php, crud]

# Dependency graph
requires:
  - phase: 87-01
    provides: CustomFields Manager class with CRUD operations
provides:
  - REST API endpoints for custom field management
  - Full CRUD via /rondo/v1/custom-fields/{post_type}
  - Admin-only permission checks (manage_options)
affects: [87-03, 88, 89]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - WP_REST_Controller extension for custom endpoints
    - Route pattern with post_type enum validation (person|team)
    - Soft delete via DELETE endpoint calling deactivate_field()

key-files:
  created:
    - includes/class-rest-custom-fields.php
  modified:
    - functions.php
    - includes/customfields/class-manager.php

key-decisions:
  - "Used WP_REST_Controller base class for standard REST patterns"
  - "All endpoints require manage_options capability (admin-only)"
  - "DELETE performs soft delete (deactivate) not hard delete"
  - "Route pattern allows hyphens in field keys for generated keys"

patterns-established:
  - "REST endpoint pattern: /rondo/v1/custom-fields/{post_type}/{field_key}"
  - "GET collection supports include_inactive query param"
  - "POST/PUT return full field object on success"
  - "DELETE returns { success: true, field: {...} } on success"

# Metrics
duration: 7min
completed: 2026-01-18
---

# Phase 87 Plan 02: Custom Fields REST API Summary

**REST API controller exposing CustomFields Manager via /rondo/v1/custom-fields with full CRUD operations**

## Performance

- **Duration:** 7 min
- **Started:** 2026-01-18T19:57:18Z
- **Completed:** 2026-01-18T20:04:38Z
- **Tasks:** 3
- **Files modified:** 3

## Accomplishments

- Created REST controller with 5 endpoints for custom field CRUD
- Integrated controller into functions.php with backward compatibility alias
- Tested all endpoints via WP-CLI REST request simulation on production

## Task Commits

Each task was committed atomically:

1. **Task 1: Create REST CustomFields controller** - `825684b` (feat)
2. **Task 2: Initialize in functions.php** - `895214f` (chore)
3. **Task 3: Test API endpoints** - `5f1e5bf` (fix - included fixes found during testing)

## Files Created/Modified

- `includes/class-rest-custom-fields.php` - REST controller with GET/POST/PUT/DELETE endpoints, permission checks, parameter schemas
- `functions.php` - Added use statement, RONDO_REST_Custom_Fields alias, instantiation in REST section
- `includes/customfields/class-manager.php` - Fixed get_fields() to query database directly for group post ID

## Decisions Made

- **Visibility of get_collection_params:** Changed from protected to public to satisfy WP_REST_Controller override requirements
- **Route pattern:** Used `[a-z0-9_-]+` for field_key to allow hyphens (generated keys have hyphens from sanitize_title)
- **Database lookup:** get_fields() now uses get_page_by_path() instead of acf_get_field_group() because ACF returns ID=0 when JSON file exists alongside database entry

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] WP_REST_Controller visibility requirement**
- **Found during:** Task 3 (testing endpoints)
- **Issue:** get_collection_params() was protected but WP_REST_Controller declares it public
- **Fix:** Changed to public visibility
- **Files modified:** includes/class-rest-custom-fields.php
- **Verification:** Deploy succeeded without fatal error
- **Committed in:** 5f1e5bf

**2. [Rule 1 - Bug] Route pattern missing hyphens**
- **Found during:** Task 3 (testing GET single field)
- **Issue:** Route regex [a-z0-9_]+ didn't allow hyphens, but generated field keys have hyphens
- **Fix:** Changed to [a-z0-9_-]+ to allow hyphens
- **Files modified:** includes/class-rest-custom-fields.php
- **Verification:** GET/PUT/DELETE for field with hyphen in key succeeded
- **Committed in:** 5f1e5bf

**3. [Rule 1 - Bug] acf_get_field_group() returns ID=0 when JSON file exists**
- **Found during:** Task 3 (testing list fields)
- **Issue:** get_fields() used acf_get_field_group() which returned ID=0 because group also has JSON file (ACF prefers JSON)
- **Fix:** Changed to use get_page_by_path() to query database directly for group post ID
- **Files modified:** includes/customfields/class-manager.php
- **Verification:** List fields returned correct count after fix
- **Committed in:** 5f1e5bf

---

**Total deviations:** 3 auto-fixed (all Rule 1 - Bug)
**Impact on plan:** All bugs found during planned testing task. Essential fixes for API to function correctly.

## Issues Encountered

- Testing required deployment to production since WP-CLI locally didn't have WordPress installation
- ACF JSON sync feature caused unexpected behavior - groups created via acf_import_field_group() get saved to JSON too, and JSON takes precedence on load (ID returns 0)

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- REST API complete and tested for custom field management
- All 5 endpoints functional: list, create, get single, update, deactivate
- Permission checks enforced (admin only)
- Ready for Settings UI integration (Plan 03)

---
*Phase: 87-acf-foundation*
*Completed: 2026-01-18*
