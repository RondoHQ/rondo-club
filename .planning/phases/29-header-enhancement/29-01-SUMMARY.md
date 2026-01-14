---
phase: 29-header-enhancement
plan: 01
subsystem: ui
tags: [react, persondetail, header, work-history]

# Dependency graph
requires:
  - phase: 28-filters-polish
    provides: completed milestone v3.1
provides:
  - Current position display in PersonDetail header
  - Job title + company link format
affects: [person-profile-enhancements]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - useMemo for filtered derived state
    - Conditional rendering with null return

key-files:
  created: []
  modified:
    - src/pages/People/PersonDetail.jsx

key-decisions:
  - "Filter is_current from sortedWorkHistory using useMemo (reuses existing sorted data)"
  - "Show 'Works at Company' when job title is missing but company exists"
  - "Skip positions that have neither title nor company"

patterns-established:
  - "Header enhancement pattern: derive display data from existing state"

issues-created: []

# Metrics
duration: 5 min
completed: 2026-01-14
---

# Phase 29 Plan 01: Add Role/Job Display to Person Header

**Display current position (job title + company) in PersonDetail header with clickable company link**

## Performance

- **Duration:** 5 min
- **Started:** 2026-01-14T15:55:00Z
- **Completed:** 2026-01-14T16:00:00Z
- **Tasks:** 3
- **Files modified:** 1

## Accomplishments

- Added `currentPositions` useMemo to filter jobs where `is_current` is true
- Display current position below name, before nickname/demographics
- Format: "Job Title at Company" with Company as clickable link
- Handles edge cases: multiple positions (comma-separated), title-only, company-only

## Task Commits

1. **Tasks 1-3: Add role/job display to person header** - `205eb05` (feat)

## Files Modified

- `src/pages/People/PersonDetail.jsx` (lines 1090-1095, 1306-1333)
  - Added `currentPositions` useMemo filtering from sortedWorkHistory
  - Added JSX rendering current positions in header section

## Decisions Made

1. **Reuse sortedWorkHistory** - Instead of creating a separate filter, derive currentPositions from the existing sorted work history. Efficient and keeps data consistent.

2. **"Works at Company" fallback** - When job title is missing but company exists, show "Works at Company" instead of just the company name. Provides better context.

3. **Skip empty positions** - If a position has neither title nor linked company, skip it entirely rather than showing empty content.

## Deviations from Plan

None - implemented exactly as specified.

## Issues Encountered

None - straightforward UI enhancement.

## Next Phase Readiness

- Phase 29 complete
- Ready for Phase 30: Todos Sidebar

---
*Phase: 29-header-enhancement*
*Completed: 2026-01-14*
