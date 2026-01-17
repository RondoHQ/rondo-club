---
phase: 76-add-email-to-existing-person
plan: FIX
subsystem: ui
tags: [react, tanstack-query, acf, api]

# Dependency graph
requires:
  - phase: 76-01
    provides: AddAttendeePopup and useAddEmailToPerson hook
provides:
  - Working add-email-to-existing-person flow
  - Taller search popup for better UX
affects: []

# Tech tracking
tech-stack:
  added: []
  patterns: []

key-files:
  created: []
  modified:
    - src/hooks/usePeople.js
    - src/components/AddAttendeePopup.jsx

key-decisions:
  - "Preserve required ACF fields (first_name, last_name) when updating contact_info"

patterns-established: []

# Metrics
duration: 5min
completed: 2026-01-17
---

# Phase 76-FIX: Add Email to Existing Person Bug Fixes

**Fixed ACF validation error when adding email to existing person, and increased search popup height for better usability**

## Performance

- **Duration:** 5 min
- **Started:** 2026-01-17T12:30:00Z
- **Completed:** 2026-01-17T12:35:00Z
- **Tasks:** 2
- **Files modified:** 2

## Accomplishments
- Fixed blocker: Selecting person in search now adds email without API validation error
- Fixed minor: Search popup height increased from 192px to 288px to show more results

## Task Commits

Each task was committed atomically:

1. **Task 1: Fix useAddEmailToPerson to preserve existing ACF fields** - `c768b49` (fix)
2. **Task 2: Increase popup height in search mode** - `0b432ed` (fix)

## Files Created/Modified
- `src/hooks/usePeople.js` - Include first_name and last_name in update payload
- `src/components/AddAttendeePopup.jsx` - Changed max-h-48 to max-h-72

## Decisions Made
- **Preserve required ACF fields:** The WordPress REST API requires first_name field when updating ACF data. Rather than changing the API validation, the hook now includes the existing first_name and last_name values when updating contact_info. This is consistent with how useUpdatePerson works.

## Deviations from Plan
None - plan executed exactly as written.

## Issues Encountered
None

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- Phase 76 UAT issues resolved
- Ready for re-verification with /gsd:verify-work 76

---
*Phase: 76-add-email-to-existing-person*
*Completed: 2026-01-17*
