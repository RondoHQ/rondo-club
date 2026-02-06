---
phase: 130-frontend-season-selector
plan: 01
subsystem: ui
tags: [react, lucide-react, tanstack-query, season-toggle, conditional-rendering]

# Dependency graph
requires:
  - phase: 129-backend-forecast-calculation
    provides: GET /rondo/v1/contributie?forecast=true endpoint
provides:
  - Season selector dropdown toggling between current and forecast views
  - Conditional column rendering hiding Nikki/Saldo in forecast mode
  - Forecast indicator badge with visual distinction
  - Automatic sort reset when entering forecast while sorting by Nikki columns
affects: [131-forecast-ux-enhancements, future-multi-season-comparison]

# Tech tracking
tech-stack:
  added: []
  patterns: [conditional-column-rendering, forecast-state-management, select-controlled-component]

key-files:
  created: []
  modified: [src/pages/Contributie/ContributieList.jsx]

key-decisions:
  - "Native select element for season dropdown (consistent with existing UI)"
  - "Instant column hiding (no animation, immediate table reflow)"
  - "Forecast indicator includes TrendingUp icon for visual clarity"
  - "Filter buttons hidden in forecast mode (Geen Nikki, Afwijking)"
  - "Auto-reset sort when entering forecast if sorting by Nikki columns"

patterns-established:
  - "isForecast state drives all conditional UI changes (columns, filters, indicator)"
  - "Conditional rendering via {!isForecast && ...} for table cells"
  - "getNextSeasonLabel() helper calculates forecast season label from current"

# Metrics
duration: 35min
completed: 2026-02-02
---

# Phase 130 Plan 01: Add Season Dropdown with Forecast Toggle Summary

**Season selector dropdown with instant column hiding and forecast indicator for contributie budget planning**

## Performance

- **Duration:** 35 min
- **Started:** 2026-02-02T16:00:00Z (approximately)
- **Completed:** 2026-02-02T16:35:00Z (approximately)
- **Tasks:** 3 (2 auto + 1 checkpoint)
- **Files modified:** 1

## Accomplishments
- Season selector dropdown toggles between current (2025-2026 huidig) and forecast (2026-2027 prognose) views
- Nikki and Saldo columns instantly hide in forecast mode with automatic table reflow
- Blue forecast indicator badge with TrendingUp icon and explanatory text
- Filter buttons (Geen Nikki, Afwijking) automatically hidden in forecast mode
- Sort field auto-resets to last_name when entering forecast while sorting by Nikki columns

## Task Commits

Each task was committed atomically:

1. **Task 1: Add season selector dropdown with isForecast state** - `5f51926f` (feat)
2. **Task 2: Add conditional column rendering and forecast indicator** - `fd29eeff` (feat)
3. **Task 3: Human verification checkpoint** - Approved by user

## Files Created/Modified
- `src/pages/Contributie/ContributieList.jsx` - Added season dropdown, isForecast state, conditional column rendering, forecast indicator badge, filter button hiding, sort reset logic

## Decisions Made

**1. Native select element for season dropdown**
- Rationale: Consistent with existing UI patterns (team/date selectors), accessible, no extra dependencies

**2. Instant column hiding without animation**
- Rationale: Per 130-CONTEXT.md decision - columns should disappear immediately with table reflow, not slide/fade

**3. Forecast indicator includes TrendingUp icon**
- Rationale: Visual distinction beyond text, icon conveys "projection" concept clearly

**4. Filter buttons hidden in forecast mode**
- Rationale: Geen Nikki and Afwijking filters rely on Nikki data which doesn't exist in forecast

**5. Auto-reset sort when entering forecast while sorting by Nikki**
- Rationale: Prevents empty/broken state, resets to sensible default (last_name) automatically

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None - all tasks completed without technical problems. User verification approved on first attempt.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- Season selector and forecast toggle fully functional
- Ready for Phase 131 (Forecast UX Enhancements) if additional polish needed
- Forecast view provides clear visual distinction and correct data presentation
- No blockers for continued work on contributie forecast features

---
*Phase: 130-frontend-season-selector*
*Completed: 2026-02-02*
