---
phase: 137-query-deduplication
plan: 01
subsystem: api
tags: [react-query, hooks, caching, deduplication, performance]

# Dependency graph
requires:
  - phase: 135-react-double-mount
    provides: QueryClient configuration with disabled refetchOnWindowFocus
  - phase: 136-modal-lazy-loading
    provides: usePeople options pattern for enabled flag
provides:
  - useCurrentUser centralized hook for current-user queries
  - useFilteredPeople with options parameter support
  - useVOGCount with 5-minute staleTime caching
affects: [138-backend-optimization]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Centralized hooks for shared queries (useCurrentUser pattern)"
    - "Options parameter for query configuration (staleTime, enabled)"

key-files:
  created:
    - src/hooks/useCurrentUser.js
  modified:
    - src/hooks/usePeople.js
    - src/hooks/useVOGCount.js
    - src/router.jsx
    - src/components/layout/Layout.jsx
    - src/components/FinancesCard.jsx
    - src/pages/People/PersonDetail.jsx

key-decisions:
  - "Used 5-minute staleTime for current-user to prevent refetching while keeping data reasonably fresh"
  - "Added options spread to useFilteredPeople to allow callers to configure staleTime, enabled, etc."

patterns-established:
  - "Shared query pattern: Create centralized hook (useCurrentUser) instead of inline useQuery definitions"
  - "Query options pattern: Pass TanStack Query options as second parameter to custom hooks"

# Metrics
duration: 2m 48s
completed: 2026-02-04
---

# Phase 137 Plan 01: Query Deduplication Summary

**Centralized useCurrentUser hook with 5-minute staleTime eliminates duplicate current-user API calls; VOG count now cached between navigations**

## Performance

- **Duration:** 2 min 48 sec
- **Started:** 2026-02-04T12:27:28Z
- **Completed:** 2026-02-04T12:30:16Z
- **Tasks:** 3
- **Files modified:** 7

## Accomplishments

- Created useCurrentUser hook used by 6 components (ApprovalCheck, FairplayRoute, Sidebar, UserMenu, FinancesCard, PersonDetail)
- Added options parameter to useFilteredPeople enabling staleTime configuration
- VOG count now cached for 5 minutes, preventing refetch on every navigation
- Single cache entry shared by all current-user consumers

## Task Commits

Each task was committed atomically:

1. **Task 1: Create useCurrentUser hook and update consumers** - `32820979` (feat)
2. **Task 2: Add staleTime support to useFilteredPeople and configure useVOGCount** - `27c76fc5` (feat)
3. **Task 3: Deploy and verify deduplication** - Deployment only, no commit

## Files Created/Modified

- `src/hooks/useCurrentUser.js` - New centralized hook for current-user queries with 5-minute staleTime
- `src/hooks/usePeople.js` - Added options parameter to useFilteredPeople
- `src/hooks/useVOGCount.js` - Added 5-minute staleTime to prevent navigation refetches
- `src/router.jsx` - Updated ApprovalCheck and FairplayRoute to use useCurrentUser
- `src/components/layout/Layout.jsx` - Updated Sidebar and UserMenu to use useCurrentUser
- `src/components/FinancesCard.jsx` - Updated to use useCurrentUser
- `src/pages/People/PersonDetail.jsx` - Updated to use useCurrentUser

## Decisions Made

- **5-minute staleTime for current-user:** User data rarely changes during a session, so 5 minutes provides good balance between freshness and avoiding duplicate requests
- **Options spread pattern:** Using `...options` in useQuery allows maximum flexibility for callers while preserving default behavior

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- QRY-01 and QRY-02 requirements fulfilled
- Ready for Phase 138 (Backend Optimization) to address remaining performance issues:
  - Backend count queries using posts_per_page=-1
  - Database query optimization

---
*Phase: 137-query-deduplication*
*Completed: 2026-02-04*
