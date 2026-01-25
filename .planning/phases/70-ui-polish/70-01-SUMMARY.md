---
phase: 70-ui-polish
plan: 01
subsystem: ui
tags: [css, tailwind, buttons, dark-mode, react]

# Dependency graph
requires:
  - phase: 69-dashboard-customization
    provides: dashboard customization foundation
provides:
  - btn-danger-outline CSS class for softer delete buttons
  - Updated PersonDetail and TeamDetail with outline delete styling
affects: []

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Outline button variant for secondary destructive actions"

key-files:
  created: []
  modified:
    - src/index.css
    - src/pages/People/PersonDetail.jsx
    - src/pages/Teams/TeamDetail.jsx

key-decisions:
  - "Keep btn-danger solid for critical admin actions (UserApproval)"
  - "Use outline style for entity-level delete buttons"

patterns-established:
  - "btn-danger-outline: Use for secondary destructive actions on detail pages"
  - "btn-danger: Reserve solid red for critical/admin actions"

# Metrics
duration: 5min
completed: 2026-01-16
---

# Phase 70 Plan 01: Soften Delete Button Summary

**Added btn-danger-outline CSS class with red border/text and subtle hover fill for softer delete buttons on PersonDetail and TeamDetail**

## Performance

- **Duration:** 5 min
- **Started:** 2026-01-16T10:00:00Z
- **Completed:** 2026-01-16T10:05:00Z
- **Tasks:** 2
- **Files modified:** 3

## Accomplishments
- Created btn-danger-outline CSS class with transparent background, red border, and red text
- Added hover state that fills with subtle red (red-50 light mode, red-900/30 dark mode)
- Full dark mode support with appropriate color adjustments
- Updated delete buttons on PersonDetail and TeamDetail pages
- Preserved solid btn-danger for critical admin actions in UserApproval

## Task Commits

Each task was committed atomically:

1. **Task 1: Add btn-danger-outline class to CSS** - `b98b9c3` (style)
2. **Task 2: Update delete buttons in PersonDetail and TeamDetail** - `68afea5` (feat)

## Files Created/Modified
- `src/index.css` - Added btn-danger-outline class definition
- `src/pages/People/PersonDetail.jsx` - Changed delete button to use btn-danger-outline
- `src/pages/Teams/TeamDetail.jsx` - Changed delete button to use btn-danger-outline

## Decisions Made
- **Outline for entity deletes:** Using outline style for PersonDetail and TeamDetail delete buttons de-emphasizes these secondary actions while maintaining clear destructive intent
- **Solid for admin actions:** UserApproval.jsx buttons (reject user, deny access) remain solid red because they're critical admin operations that should be prominent

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered
- ESLint configuration file not found when running `npm run lint` - this is a pre-existing issue unrelated to the changes (build succeeded)

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness
- Delete button styling complete
- Phase 70 Plan 01 is the only plan in this phase (UI polish milestone)
- Milestone v4.6 ready for version bump and deployment

---
*Phase: 70-ui-polish*
*Completed: 2026-01-16*
