---
phase: quick
plan: 041
subsystem: ui
tags: [css, tailwind, dark-mode, accessibility, contrast]

# Dependency graph
requires:
  - phase: 17-club-branding
    provides: Dynamic accent colors from club configuration
provides:
  - Dark mode styles using gray backgrounds with accent borders/text for readability
  - Contrast-safe button, navigation, and search selection styles
affects: [ui, accessibility, theming]

# Tech tracking
tech-stack:
  added: []
  patterns: [Dark mode uses neutral backgrounds with accent accents (text/borders) instead of accent backgrounds]

key-files:
  created: []
  modified:
    - src/index.css
    - src/components/layout/Layout.jsx

key-decisions:
  - "Use gray-700 backgrounds in dark mode instead of accent-colored backgrounds"
  - "Apply accent color via text and borders (accent-400/accent-500) for visual tie-in"
  - "Brighter accent shades (400/300) for text visibility against dark backgrounds"

patterns-established:
  - "Dark mode contrast pattern: gray background + accent text/border instead of accent background + white text"

# Metrics
duration: 2min
completed: 2026-02-06
---

# Quick Task 041: Dark Mode Contrast Fixes Summary

**Gray backgrounds with accent borders/text replace accent backgrounds in dark mode, ensuring readability with dark club colors**

## Performance

- **Duration:** 2 min
- **Started:** 2026-02-06T07:20:37Z
- **Completed:** 2026-02-06T07:22:19Z
- **Tasks:** 3
- **Files modified:** 2

## Accomplishments
- Primary buttons readable in dark mode with any accent color
- Active navigation items clearly visible with accent text and border indicator
- Search result selections use gray backgrounds with accent ring for distinction

## Task Commits

Each task was committed atomically:

1. **Task 1: Fix dark mode button and accent background styles** - `5cf5d394` (style)
2. **Task 2: Fix dark mode navigation and search selection styles** - `0e2650cf` (style)
3. **Task 3: Build, deploy, and verify** - Deployment complete (no commit needed)

## Files Created/Modified
- `src/index.css` - Updated `.btn-primary` to use gray-700 background with accent-400 text and accent-500 border in dark mode
- `src/components/layout/Layout.jsx` - Updated active nav items and search selection styles to use gray backgrounds with accent text/rings

## Decisions Made

**Use gray backgrounds instead of accent backgrounds in dark mode**
- Rationale: Accent-colored backgrounds fail when club accent color is dark (e.g., dark green #006935). Gray backgrounds are always readable.
- Implementation: gray-700 background with accent-400/300 text and accent-500 borders for visual distinction

**Brighter accent shades for text in dark mode**
- Rationale: accent-400 and accent-300 are lighter shades that maintain visibility against dark backgrounds
- Applied to: button text, active nav items, search selection text

**Border/ring indicators instead of background color**
- Rationale: Maintains visual accent tie-in without compromising readability
- Applied to: button borders, nav left border, search selection ring

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None - straightforward CSS and className updates.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

All dark mode contrast issues resolved. UI is now readable regardless of configured club accent color. No known blockers for future UI work.

---
*Phase: quick-041*
*Completed: 2026-02-06*
