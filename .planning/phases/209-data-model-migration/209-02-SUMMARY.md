---
phase: 209-data-model-migration
plan: 02
subsystem: backend
tags: [php, rest-api, contact-fields, backward-compatibility]

# Dependency graph
requires: [209-01]
provides:
  - All PHP backend code reads from fixed contact fields
  - REST API backward-compatible contact_info array from fixed fields
  - vCard export/import using fixed fields (social links dropped)
  - CardDAV sync writing to fixed fields
affects: [209-03, rondo-sync]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Static build_contact_info_from_fixed_fields() for REST API backward compatibility"
    - "meta_query for email lookups instead of full-table scan with repeater iteration"

key-files:
  created: []
  modified:
    - includes/class-rest-api.php
    - includes/class-rest-people.php
    - includes/class-user-provisioning.php
    - includes/class-lettermint-webhook.php
    - includes/class-invoice-pdf-generator.php
    - includes/class-invoice-email-sender.php
    - includes/class-vog-email.php
    - includes/class-rest-todos.php
    - includes/class-auto-title.php
    - includes/class-calendar-matcher.php
    - includes/class-vcard-export.php
    - includes/carddav/class-carddav-backend.php
    - includes/class-rest-google-sheets.php
    - includes/class-wp-cli.php
    - includes/class-demo-import.php
    - includes/class-demo-export.php

key-decisions:
  - "Static method build_contact_info_from_fixed_fields() shared between rest_prepare_person filter and list endpoint"
  - "Email lookup functions use meta_query instead of loading all persons (performance improvement)"
  - "Lettermint bounce handler stores inactive status only in _rondo_inactive_emails meta, no longer modifies email value"
  - "vCard parse returns fixed field names directly, dropping social/web contact types"
  - "Teams/commissies keep their own contact_info repeaters (separate ACF field groups)"
  - "Demo import supports both new fixed-field format and legacy contact_info array format"

patterns-established:
  - "Fixed field email lookup via meta_query on email_1/email_2"
  - "get_person_email pattern: try email_1 then email_2"

requirements-completed: [DATA-04]

# Metrics
duration: 13min
completed: 2026-03-08
---

# Phase 209 Plan 02: Backend PHP Migration to Fixed Contact Fields Summary

**All 16 PHP files updated to read contact data from fixed fields instead of contact_info repeater, with backward-compatible REST API response**

## Performance

- **Duration:** 13 min
- **Started:** 2026-03-08T10:49:07Z
- **Completed:** 2026-03-08T11:02:09Z
- **Tasks:** 2
- **Files modified:** 16

## Accomplishments
- Updated 10 core PHP consumers (REST API, user provisioning, webhooks, email, calendar) to use fixed fields
- Added backward-compatible contact_info array builder in REST API responses (both single and list endpoints)
- Updated vCard export to read from fixed fields, vCard import to write to fixed fields
- Dropped social/web URL types from vCard import per requirements
- Updated CardDAV backend to write fixed fields on create/update
- Updated Google Sheets export, WP-CLI commands, demo import/export
- Improved email lookup performance by using meta_query instead of loading all persons

## Task Commits

Each task was committed atomically:

1. **Task 1: Update REST API and core PHP consumers** - `6542f847` (feat)
2. **Task 2: Update export, import, and secondary PHP consumers** - `e71097a4` (feat)

## Files Created/Modified
- `includes/class-rest-api.php` - find_person_by_email, email search, person email helpers, backward-compat filter
- `includes/class-rest-people.php` - List endpoint contact_info from fixed fields
- `includes/class-user-provisioning.php` - get_person_email from fixed fields
- `includes/class-lettermint-webhook.php` - mark_person_email_inactive, find_person_by_email
- `includes/class-invoice-pdf-generator.php` - Email fallback from fixed fields
- `includes/class-invoice-email-sender.php` - get_person_email_addresses from fixed fields
- `includes/class-vog-email.php` - get_person_email from fixed fields
- `includes/class-rest-todos.php` - find_first_email_from_persons from fixed fields
- `includes/class-auto-title.php` - Email lowercase hooks for fixed fields
- `includes/class-calendar-matcher.php` - Email lookup from fixed fields
- `includes/class-vcard-export.php` - Export from fixed fields, import to fixed fields
- `includes/carddav/class-carddav-backend.php` - updatePersonFields writes fixed fields
- `includes/class-rest-google-sheets.php` - get_first_contact_by_type/get_first_phone from fixed fields
- `includes/class-wp-cli.php` - test-vcard-parse and find-duplicates use fixed fields
- `includes/class-demo-import.php` - Import writes to fixed fields with legacy fallback
- `includes/class-demo-export.php` - Person export uses fixed fields, teams/commissies keep repeater

## Decisions Made
- Static `build_contact_info_from_fixed_fields()` method allows reuse between single and list endpoints
- Email lookups via meta_query are more efficient than iterating all persons
- Teams/commissies retain their own contact_info repeaters (different ACF field groups)
- Demo import supports both new and legacy data formats for backward compatibility

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 2 - Missing] REST people list endpoint also needed contact_info update**
- **Found during:** Task 1
- **Issue:** The plan mentioned the REST API person response but the list endpoint in class-rest-people.php also includes ACF data with contact_info
- **Fix:** Added build_contact_info_from_fixed_fields() call in list endpoint
- **Files modified:** includes/class-rest-people.php
- **Commit:** 6542f847

**2. [Rule 1 - Bug] Teams/commissies export_contact_info function removed prematurely**
- **Found during:** Task 2
- **Issue:** export_contact_info was renamed but teams/commissies still use their own contact_info repeaters
- **Fix:** Kept export_contact_info for teams/commissies, added separate export_contact_fields for persons
- **Files modified:** includes/class-demo-export.php
- **Commit:** e71097a4

## Issues Encountered
None.

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- All PHP backend code reads from fixed fields
- REST API provides backward-compatible contact_info array
- Ready for Plan 03: Frontend migration to use fixed fields directly
- contact_info repeater still present in ACF for Plan 03 cleanup

## Self-Check: PASSED
