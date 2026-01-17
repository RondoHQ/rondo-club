---
phase: 81-export-to-google
plan: 01
subsystem: api
tags: [google-contacts, people-api, export, field-mapping]

# Dependency graph
requires:
  - phase: 79-google-contacts-oauth
    provides: GoogleContactsConnection for credentials, GoogleOAuth for client
  - phase: 80-import-from-google
    provides: Field mapping patterns to reverse, stored google_contact_id/etag meta
provides:
  - GoogleContactsExport class for exporting Caelis contacts to Google
  - Field mapping from ACF fields to Google Person objects
  - Contact creation via createContact()
  - Contact updates via updateContact() with etag validation
  - Photo uploads via updateContactPhoto()
affects: [81-02-rest-export, 81-03-bulk-export, 82-delta-sync]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Reverse field mapping from Caelis ACF to Google Person objects"
    - "Etag-based optimistic locking for concurrent modification prevention"
    - "Retry logic on 409 conflict by fetching fresh etag"

key-files:
  created:
    - includes/class-google-contacts-export.php
  modified:
    - functions.php

key-decisions:
  - "Mirror GoogleContactsAPI import class structure for consistency"
  - "Check readwrite access mode before export operations"
  - "Auto-retry on etag conflict by fetching fresh contact state"
  - "4MB photo size limit based on Google's undocumented limits"

patterns-established:
  - "export_contact() decides create vs update based on _google_contact_id meta"
  - "build_*() methods create Google API objects from ACF fields"
  - "store_google_ids() updates _google_contact_id, _google_etag, _google_last_export meta"

# Metrics
duration: 18min
completed: 2026-01-17
---

# Phase 81 Plan 01: Core Export Class Summary

**GoogleContactsExport class with field mapping, create/update/photo methods via People API**

## Performance

- **Duration:** 18 min
- **Started:** 2026-01-17T20:56:37Z
- **Completed:** 2026-01-17T21:14:24Z
- **Tasks:** 2
- **Files modified:** 2

## Accomplishments

- Created GoogleContactsExport class (806 lines) with complete field mapping
- Implemented contact creation, update with etag validation, and photo uploads
- Reverse-mapped all ACF fields (names, emails, phones, URLs, addresses, work_history)
- Added retry logic for etag conflicts with fresh fetch and retry
- Registered class with Composer autoloader and backward compatibility alias

## Task Commits

Each task was committed atomically:

1. **Task 1: Create GoogleContactsExport class with field mapping** - `91f94b9` (feat)
2. **Task 2: Register class loading in functions.php** - `f4704b6` (chore)

## Files Created/Modified

- `includes/class-google-contacts-export.php` - Core export class with field mapping and Google API integration
- `functions.php` - Added use statement and backward compatibility class alias

## Decisions Made

| Decision | Rationale |
|----------|-----------|
| Mirror import class structure | Consistency and maintainability |
| Check readwrite access before export | User may only have readonly scope |
| Retry on etag conflict | Google requires etag; conflicts need automatic handling |
| 4MB photo limit | Google's undocumented limit; fail gracefully |

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

- SSH agent had no identities when deploying - resolved by running `ssh-add ~/.ssh/id_rsa`

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- Export class ready for REST endpoint integration (Plan 02)
- All field mapping methods implemented and tested for syntax
- Ready for integration with save_post hooks and bulk export UI (Plan 03)

---
*Phase: 81-export-to-google*
*Completed: 2026-01-17*
