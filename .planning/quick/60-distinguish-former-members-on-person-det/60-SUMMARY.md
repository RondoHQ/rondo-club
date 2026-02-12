---
phase: quick-60
plan: 01
subsystem: ui
tags: [react, former-members, person-detail, badges]

# Dependency graph
requires:
  - phase: quick-59
    provides: Former member search weighting
provides:
  - Former member visual indicators on PersonDetail page
  - "Oud-lid" badge matching PeopleList styling
  - Lid-tot date display in Dutch format
affects: [person-detail, ui-consistency, former-members]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - Conditional badge rendering in profile headers
    - Dutch date formatting for lid-tot dates
    - Subtle background styling for former members

key-files:
  created: []
  modified:
    - src/pages/People/PersonDetail.jsx

key-decisions:
  - "Matched PeopleList badge styling for consistency across UI"
  - "Used subtle gray background for former members (red reserved for financiële blokkade)"
  - "Imported parseISO from dateFormat utility for ISO date parsing"

patterns-established:
  - "Former member indicators: badge + optional lid-tot date + subtle background"
  - "Conditional background priority: financiële blokkade > former_member > default"

# Metrics
duration: 8min
completed: 2026-02-12
---

# Quick Task 60: Distinguish Former Members on PersonDetail

**Former members now show "Oud-lid" badge, lid-tot date, and subtle gray background on person detail pages**

## Performance

- **Duration:** 8 minutes
- **Started:** 2026-02-12T14:32:00Z
- **Completed:** 2026-02-12T14:40:00Z
- **Tasks:** 1
- **Files modified:** 1

## Accomplishments
- Added "Oud-lid" badge next to person name in profile header
- Display lid-tot date in Dutch format when available ("Lid tot: 15 januari 2024")
- Applied subtle gray background to former member profile cards
- Maintained styling consistency with PeopleList

## Task Commits

1. **Task 1: Add former member visual indicators to PersonDetail** - `000124c4` (feat)

## Files Created/Modified
- `src/pages/People/PersonDetail.jsx` - Added former member badge, lid-tot date display, and conditional background styling

## Decisions Made
- **Badge placement:** Added after deceased indicator (†) in the name heading for clear visibility
- **Background priority:** Financiële blokkade (red) takes precedence over former member (gray) when both conditions exist
- **Date formatting:** Used Dutch date format (d MMMM yyyy) consistent with rest of application via dateFormat utility
- **Badge styling:** Matched exact PeopleList styling (bg-gray-200/dark:bg-gray-600) for UI consistency

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

Former member visual distinction complete on both list and detail views. System provides consistent visual indicators for membership status across the application.

---
*Quick Task: 60*
*Completed: 2026-02-12*
