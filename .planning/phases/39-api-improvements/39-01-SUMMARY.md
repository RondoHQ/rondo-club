---
phase: 39-api-improvements
plan: 01
subsystem: api
tags: [php, react, cache-invalidation, search, auto-title]

# Dependency graph
requires:
  - phase: 38-quick-ui-fixes
    provides: baseline UI fixes and stability
provides:
  - improved search relevance with first name prioritization
  - user-editable important date titles that persist
  - dashboard cache sync across all pages
affects: [dashboard, search, important-dates]

# Tech tracking
tech-stack:
  added: []
  patterns: [scoring-based-search, ref-based-state-tracking]

key-files:
  created: []
  modified:
    - includes/class-auto-title.php
    - includes/class-rest-api.php
    - src/components/ImportantDateModal.jsx
    - src/hooks/usePeople.js

key-decisions:
  - "Used scoring system for search (100/80/60/40/20) to rank results by match quality"
  - "Backend detects and persists custom titles to custom_label ACF field automatically"
  - "Used useRef for tracking user title edits to avoid re-render loops"

patterns-established:
  - "Multi-query search with scoring: run separate queries for different match types, merge and sort by score"
  - "Ref-based edit tracking: use useRef to track user intent without triggering re-renders"

issues-created: []

# Metrics
duration: 12 min
completed: 2026-01-14
---

# Phase 39 Plan 01: API Improvements Summary

**Fixed three API/data bugs: important date auto-title respects user edits, search prioritizes first name matches, dashboard cache invalidates on todo changes from PersonDetail.**

## Performance

- **Duration:** 12 min
- **Started:** 2026-01-14T13:45:00Z
- **Completed:** 2026-01-14T13:57:00Z
- **Tasks:** 3
- **Files modified:** 4

## Accomplishments

- Important date titles now persist user edits through saves (backend detects changes and saves to custom_label)
- Search results rank first name matches highest (score 100 for exact, 80 for starts with, 60 for contains)
- Dashboard stats (open_todos_count, awaiting_todos_count) update immediately when todos modified from PersonDetail

## Task Commits

Each task was committed atomically:

1. **Task 1: Fix important date auto-title overwriting custom names** - `308c8b6` (fix)
2. **Task 2: Prioritize first name in search results** - `3396fbe` (feat)
3. **Task 3: Invalidate dashboard cache on todo mutations** - `520454c` (fix)

## Files Created/Modified

- `includes/class-auto-title.php` - Added detection of custom titles before auto-generation
- `includes/class-rest-api.php` - Implemented scored search with first/last name and general queries
- `src/components/ImportantDateModal.jsx` - Added useRef tracking for manual title edits
- `src/hooks/usePeople.js` - Added dashboard and todos query invalidation to todo mutations

## Decisions Made

1. **Backend custom title detection:** Compare current title with would-be auto-generated title; if different and no custom_label set, persist current title as custom_label. This preserves backward compatibility.

2. **Search scoring system:** Used weighted scores (100/80/60/40/20) to ensure first name exact matches always rank highest, followed by partial first name, then last name, then general content.

3. **Frontend edit tracking:** Used useRef instead of useState to track whether user manually edited title, avoiding unnecessary re-renders and effect cycles.

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None.

## Next Phase Readiness

- All three bugs fixed and verified with successful build
- Ready for deployment to production
- Phase 39 complete (single plan phase)

---
*Phase: 39-api-improvements*
*Completed: 2026-01-14*
