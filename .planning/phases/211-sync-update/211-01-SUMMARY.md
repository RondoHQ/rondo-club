---
phase: 211-sync-update
plan: 01
subsystem: sync
tags: [rondo-sync, e164, phone-normalization, sqlite-migration, reverse-sync]

# Dependency graph
requires:
  - phase: 210-backend-normalization-ui
    provides: Fixed ACF contact fields (email_1, email_2, mobile_1, mobile_2, telephone_1, telephone_2) on person records
provides:
  - Phone normalizer for Node.js (normalizePhone, e164ToLocal)
  - Forward sync writing fixed ACF fields instead of contact_info repeater
  - Reverse sync reading from fixed ACF fields
  - E.164 to local format conversion for Sportlink reverse sync
  - SQLite tracking column migration (email->email_1, etc.)
affects: [rondo-sync deployment, reverse-sync re-enable]

# Tech tracking
tech-stack:
  added: []
  patterns: [E.164 normalization in both PHP and Node.js, fixed ACF field pattern for sync]

key-files:
  created:
    - rondo-sync/lib/phone-normalizer.js
  modified:
    - rondo-sync/steps/prepare-rondo-club-members.js
    - rondo-sync/lib/sync-origin.js
    - rondo-sync/lib/detect-rondo-club-changes.js
    - rondo-sync/lib/reverse-sync-sportlink.js
    - rondo-sync/lib/rondo-club-db.js

key-decisions:
  - "Use EmailAlternative/MobileAlternative/TelephoneAlternative Sportlink API field names instead of Email2/Mobile2/Telephone2 which do not exist in raw data"
  - "Add mobile_2 and telephone_2 to TRACKED_FIELDS for complete bidirectional sync"
  - "Keep old SQLite columns after migration (harmless, avoids complex table recreate)"
  - "Export SPORTLINK_FIELD_MAP for testability"

patterns-established:
  - "Phone normalization: E.164 on write to WordPress, local format on write to Sportlink"

requirements-completed: [SYNC-01, SYNC-02]

# Metrics
duration: 3min
completed: 2026-03-08
---

# Phase 211 Plan 01: Sync Update Summary

**Forward and reverse sync updated to use 6 fixed ACF contact fields with E.164 phone normalization, replacing contact_info repeater**

## Performance

- **Duration:** 3 min
- **Started:** 2026-03-08T16:19:12Z
- **Completed:** 2026-03-08T16:22:41Z
- **Tasks:** 2
- **Files modified:** 6

## Accomplishments
- Ported PHP PhoneNormalizer to Node.js with normalizePhone and e164ToLocal functions
- Forward sync now writes email_1, email_2, mobile_1, mobile_2, telephone_1, telephone_2 as direct ACF fields with E.164 normalization
- Reverse sync reads from fixed ACF fields and converts E.164 back to local Dutch format before Sportlink
- SQLite tracking columns migrated from old names (email, email2, mobile, phone) to new names (email_1, email_2, mobile_1, mobile_2, telephone_1, telephone_2)

## Task Commits

Each task was committed atomically:

1. **Task 1: Create phone normalizer and update forward sync** - `133ede7` (feat)
2. **Task 2: Update reverse sync, change detection, field map, and DB migration** - `37013c4` (feat)

## Files Created/Modified
- `rondo-sync/lib/phone-normalizer.js` - E.164 normalization and local format conversion
- `rondo-sync/steps/prepare-rondo-club-members.js` - buildFixedContactFields replaces buildContactInfo
- `rondo-sync/lib/sync-origin.js` - TRACKED_FIELDS with new field names
- `rondo-sync/lib/detect-rondo-club-changes.js` - extractFieldValue reads direct ACF properties
- `rondo-sync/lib/reverse-sync-sportlink.js` - SPORTLINK_FIELD_MAP with new keys, e164ToLocal conversion
- `rondo-sync/lib/rondo-club-db.js` - migrateContactTrackingColumns, updated queries

## Decisions Made
- Used EmailAlternative/MobileAlternative/TelephoneAlternative as the correct Sportlink API field names (Email2/Mobile2/Telephone2 do not exist in raw Sportlink data)
- Added mobile_2 and telephone_2 to TRACKED_FIELDS for complete bidirectional sync coverage
- Old SQLite columns kept after migration to avoid complex table recreate (harmless extra columns)
- Exported SPORTLINK_FIELD_MAP from reverse-sync-sportlink.js for testability

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered
None

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- All sync code updated and pushed to rondo-sync repo
- Ready for server deployment and testing
- Reverse sync can be re-enabled after deploying updated code to rondo-sync server

---
*Phase: 211-sync-update*
*Completed: 2026-03-08*
