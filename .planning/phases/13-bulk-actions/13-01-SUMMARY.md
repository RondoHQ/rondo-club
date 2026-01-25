---
phase: 13-bulk-actions
plan: 01
subsystem: api
tags: [rest-api, react-query, bulk-operations, tanstack-query]

# Dependency graph
requires:
  - phase: 12-list-view-selection
    provides: Selection infrastructure with selectedIds Set
provides:
  - Bulk update REST endpoint /stadion/v1/people/bulk-update
  - useBulkUpdatePeople React hook
  - bulkUpdatePeople API client method
affects: [13-02-bulk-actions-ui]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - Bulk REST endpoint with per-item permission validation
    - Workspace post ID to term ID conversion for ACF fields

key-files:
  created: []
  modified:
    - includes/class-rest-people.php
    - src/api/client.js
    - src/hooks/usePeople.js

key-decisions:
  - "Used existing workspace ID to term ID conversion pattern from class-access-control.php"
  - "Permission check loops through all IDs to verify ownership before any updates"

patterns-established:
  - "Bulk update endpoints return success/failure per item for partial success handling"

issues-created: []

# Metrics
duration: 8min
completed: 2026-01-13
---

# Phase 13 Plan 01: Bulk Update REST Endpoint Summary

**REST endpoint and React hook for batch updating visibility and workspace assignments on multiple contacts**

## Performance

- **Duration:** 8 min
- **Started:** 2026-01-13T17:30:00Z
- **Completed:** 2026-01-13T17:38:00Z
- **Tasks:** 2
- **Files modified:** 3

## Accomplishments

- Created POST endpoint `/stadion/v1/people/bulk-update` with ownership validation
- Implemented visibility updates using STADION_Visibility helper
- Implemented workspace assignment with post ID to term ID conversion
- Added useBulkUpdatePeople hook with query invalidation

## Task Commits

Each task was committed atomically:

1. **Task 1: Create bulk update REST endpoint** - `5c65c27` (feat)
2. **Task 2: Add bulk update API method and React hook** - `c75405b` (feat)

## Files Created/Modified

- `includes/class-rest-people.php` - Added bulk update endpoint, permission check, and callback
- `src/api/client.js` - Added bulkUpdatePeople API method
- `src/hooks/usePeople.js` - Added useBulkUpdatePeople hook

## Decisions Made

- Used existing workspace ID to term ID conversion pattern from class-access-control.php
- Endpoint validates ownership of ALL posts before making any updates (atomic permission check)

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None

## Next Phase Readiness

- Bulk update API ready for UI integration in 13-02
- Hook exports correctly and invalidates queries on success

---
*Phase: 13-bulk-actions*
*Completed: 2026-01-13*
