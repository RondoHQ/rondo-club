---
phase: 40-quick-wins
plan: 01
subsystem: ui, api
tags: [dashboard, acf, email, todos]

# Dependency graph
requires:
  - phase: 39
    provides: Dashboard awaiting response section
provides:
  - Awaiting checkbox toggle in Dashboard
  - Email normalization on save for person and team
affects: []

# Tech tracking
tech-stack:
  added: []
  patterns:
    - ACF update_value filter for field normalization

key-files:
  created: []
  modified:
    - src/pages/Dashboard.jsx
    - includes/class-auto-title.php

key-decisions:
  - "Direct completion for awaiting todos (no modal like open todos)"
  - "Use is_email() WordPress function for email validation before lowercasing"

patterns-established:
  - "ACF field value normalization via update_value filter"

# Metrics
duration: 5 min
completed: 2026-01-14
---

# Phase 40 Plan 01: Quick Wins Summary

**Dashboard awaiting checkbox toggle and email address auto-lowercasing on save**

## Performance

- **Duration:** 5 min
- **Started:** 2026-01-14T22:45:00Z
- **Completed:** 2026-01-14T22:50:00Z
- **Tasks:** 2
- **Files modified:** 2

## Accomplishments

- Added clickable checkbox to awaiting todos in Dashboard that marks them as complete
- Email addresses in contact_info are automatically lowercased on save
- Works for both person and team post types

## Task Commits

Each task was committed atomically:

1. **Task 1: Add awaiting checkbox to Dashboard AwaitingTodoCard** - `fbefa9a` (feat)
2. **Task 2: Auto-lowercase email addresses on save** - `813b2f9` (feat)

## Files Created/Modified

- `src/pages/Dashboard.jsx` - Added onToggle prop to AwaitingTodoCard, checkbox button with CheckSquare icon, awaiting status handling in handleToggleTodo
- `includes/class-auto-title.php` - Added ACF update_value filters and maybe_lowercase_email method for email normalization

## Decisions Made

- **Direct completion for awaiting todos:** Unlike open todos which show a modal with options (complete, mark awaiting, convert to activity), awaiting todos are marked complete directly when clicking the checkbox. This provides quick status toggling without extra clicks.
- **Using is_email() for validation:** Rather than checking the contact_type field, we use WordPress's is_email() function to validate if a value is an email before lowercasing. This ensures only actual email addresses are modified regardless of the contact type setting.

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- Phase 40 complete with both quick wins implemented
- Ready for Phase 41 (Bundle Optimization)

---
*Phase: 40-quick-wins*
*Completed: 2026-01-14*
