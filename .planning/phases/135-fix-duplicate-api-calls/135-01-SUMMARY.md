---
phase: 135-fix-duplicate-api-calls
plan: 01
subsystem: api
tags: [react-query, tanstack-query, api-optimization, caching]

# Dependency graph
requires:
  - phase: initial-setup
    provides: QueryClient configuration in main.jsx
provides:
  - Optimized QueryClient defaults preventing unnecessary refetches
  - refetchOnWindowFocus disabled for tab-switch scenarios
  - Documentation of React Query's built-in request deduplication
affects: [all-api-calls, dashboard-performance, user-experience]

# Tech tracking
tech-stack:
  added: []
  patterns: [QueryClient configuration optimization, production-focused caching strategy]

key-files:
  created: []
  modified: [src/main.jsx]

key-decisions:
  - "Disabled refetchOnWindowFocus to prevent unnecessary API calls on tab switches"
  - "Documented that React Query automatically deduplicates concurrent requests with same queryKey"
  - "Clarified that React 18 StrictMode double-mount in dev is expected behavior"

patterns-established:
  - "QueryClient configuration: staleTime + refetchOnWindowFocus for personal CRM use case"

# Metrics
duration: 1min
completed: 2026-02-04
---

# Phase 135 Plan 01: Fix Duplicate API Calls Summary

**QueryClient optimized with refetchOnWindowFocus: false to eliminate tab-switch refetches, leveraging React Query's built-in request deduplication**

## Performance

- **Duration:** 1 min
- **Started:** 2026-02-04T09:58:55Z
- **Completed:** 2026-02-04T09:59:55Z
- **Tasks:** 2
- **Files modified:** 1

## Accomplishments
- Added `refetchOnWindowFocus: false` to QueryClient defaultOptions
- Eliminated unnecessary API refetches when users switch browser tabs
- Deployed to production with optimized configuration
- Documented React Query's built-in concurrent request deduplication (DUP-03)

## Task Commits

Each task was committed atomically:

1. **Task 1: Add refetchOnWindowFocus to QueryClient defaults** - `af7f7c6a` (feat)

**Note:** Task 2 (Deploy and verify) involved production deployment via `bin/deploy.sh`. Build artifacts are gitignored, so no separate commit was created for the build output.

## Files Created/Modified
- `src/main.jsx` - Added `refetchOnWindowFocus: false` to QueryClient defaultOptions

## Decisions Made

**1. Disabled refetchOnWindowFocus**
- Rationale: For a personal CRM where data doesn't change frequently, refetching on every tab switch is unnecessary network overhead
- Combined with existing `staleTime: 5 * 60 * 1000`, ensures data is fresh for 5 minutes without redundant requests

**2. Production-first verification focus**
- Rationale: React 18 StrictMode intentionally double-mounts components in development to detect side effects. This is expected behavior and NOT a bug.
- In production, StrictMode does not double-mount, so duplicate calls from StrictMode are not a production concern
- React Query's built-in request deduplication ensures that even concurrent requests (from StrictMode or legitimate concurrent component mounts) share a single network call

**3. Documented React Query's built-in deduplication (DUP-03)**
- No additional code needed for concurrent request deduplication
- React Query automatically merges requests with identical queryKeys into a single network call
- All existing queryKeys in the codebase are already consistent (['dashboard'], ['currentUser'], etc.)

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

**Ready for production verification:**
1. Production site deployed with optimized QueryClient configuration
2. Verification should focus on production behavior, not development mode
3. Expected outcomes:
   - Single API call per endpoint on page load (production)
   - No refetch on tab switch (production)
   - React Query's deduplication ensures concurrent requests share network calls

**Next phase recommendations:**
- Phase 136: Optimize QuickActivityModal to avoid loading 1400+ people on dashboard
- Phase 137: Consolidate multiple current-user queries into single source
- Phase 138: Optimize VOG count queries on navigation

**Important context for verification:**
- Development mode may show duplicate requests due to StrictMode double-mount - this is EXPECTED
- Production mode does not have StrictMode, so no double-mount duplicates occur
- Tab-switch refetches are now prevented in both environments

---
*Phase: 135-fix-duplicate-api-calls*
*Completed: 2026-02-04*
