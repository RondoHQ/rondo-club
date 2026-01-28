---
phase: 109-mobile-ux
plan: 02
subsystem: mobile-ux
tags: [pwa, mobile, pull-to-refresh, ux]

requires:
  - 109-01  # PullToRefreshWrapper component

provides:
  - Pull-to-refresh on all list views
  - Pull-to-refresh on all detail views
  - Pull-to-refresh on Dashboard
  - Consistent refresh UX across app

affects:
  - Future mobile UX enhancements will follow this pattern

tech-stack:
  added: []
  patterns:
    - Pull-to-refresh wrapper pattern for views
    - TanStack Query cache invalidation on refresh

key-files:
  created: []
  modified:
    - src/pages/People/PeopleList.jsx
    - src/pages/Teams/TeamsList.jsx
    - src/pages/Commissies/CommissiesList.jsx
    - src/pages/Dates/DatesList.jsx
    - src/pages/Todos/TodosList.jsx
    - src/pages/Feedback/FeedbackList.jsx
    - src/pages/People/PersonDetail.jsx
    - src/pages/Teams/TeamDetail.jsx
    - src/pages/Commissies/CommissieDetail.jsx
    - src/pages/Dashboard.jsx

decisions: []

metrics:
  duration: 341s
  completed: 2026-01-28
---

# Phase 109 Plan 02: Pull-to-Refresh Integration Summary

**One-liner:** Integrated pull-to-refresh across all list views, detail views, and Dashboard using PullToRefreshWrapper

## Execution Summary

Successfully added pull-to-refresh functionality to all major views in the application. Each view now wraps content with `PullToRefreshWrapper` and invalidates its relevant TanStack Query cache on refresh, triggering background data fetches.

**Tasks Completed:**
1. ✅ Added pull-to-refresh to 6 list views (People, Teams, Commissies, Dates, Todos, Feedback)
2. ✅ Added pull-to-refresh to 3 detail views (PersonDetail, TeamDetail, CommissieDetail) and Dashboard

**Build Status:** ✅ Build completed successfully without errors

## Implementation Details

### List Views Pattern

Each list view follows this pattern:

1. Import `useQueryClient` from `@tanstack/react-query`
2. Import `PullToRefreshWrapper` component
3. Get `queryClient` instance
4. Create `handleRefresh` function that invalidates the appropriate query
5. Wrap entire returned JSX with `<PullToRefreshWrapper onRefresh={handleRefresh}>`

**Query Keys Used:**
- **PeopleList**: `['people', 'list']` (matches peopleKeys.lists())
- **TeamsList**: `['teams']`
- **CommissiesList**: `['commissies']`
- **DatesList**: `['reminders']` (dates use reminders endpoint)
- **TodosList**: `['todos']`
- **FeedbackList**: `['feedback']`

### Detail Views Pattern

Detail views invalidate multiple related queries:

**PersonDetail:**
```javascript
await Promise.all([
  queryClient.invalidateQueries({ queryKey: ['people', 'detail', id] }),
  queryClient.invalidateQueries({ queryKey: ['people', id, 'timeline'] }),
]);
```

**TeamDetail/CommissieDetail:**
```javascript
await queryClient.invalidateQueries({ queryKey: ['teams', parseInt(id, 10)] });
await queryClient.invalidateQueries({ queryKey: ['commissies', parseInt(id, 10)] });
```

**Dashboard:**
```javascript
await Promise.all([
  queryClient.invalidateQueries({ queryKey: ['dashboard'] }),
  queryClient.invalidateQueries({ queryKey: ['reminders'] }),
  queryClient.invalidateQueries({ queryKey: ['todos'] }),
]);
```

## Technical Approach

### Why Cache Invalidation

Rather than manually refetching data, we use TanStack Query's `invalidateQueries()` which:
- Marks cached data as stale
- Triggers background refetch automatically
- Handles loading states via existing query hooks
- Ensures spinner stays visible until new data arrives (returns Promise)

### Mobile-First UX

- Pull gesture works on all touchscreen devices
- Visual feedback with spinner at top of view
- Overscroll prevention via CSS already in place (from 109-01)
- Works alongside existing data fetching mechanisms

## Deviations from Plan

None - plan executed exactly as written.

## Next Phase Readiness

**Blockers:** None

**Concerns:** None

**Dependencies Met:**
- PullToRefreshWrapper component available (109-01)
- TanStack Query properly configured
- Query keys consistent across app

**Ready for Next Plan:** Yes

## Commits

1. `2090c26` - feat(109-02): add pull-to-refresh to all list views
2. `f74ba39` - feat(109-02): add pull-to-refresh to detail views and Dashboard

## Files Modified

**List Views (6 files):**
- src/pages/People/PeopleList.jsx - Added import, handleRefresh, wrapper
- src/pages/Teams/TeamsList.jsx - Added import, handleRefresh, wrapper
- src/pages/Commissies/CommissiesList.jsx - Added import, handleRefresh, wrapper
- src/pages/Dates/DatesList.jsx - Added import, handleRefresh, wrapper
- src/pages/Todos/TodosList.jsx - Added import, handleRefresh, wrapper
- src/pages/Feedback/FeedbackList.jsx - Added import, handleRefresh, wrapper

**Detail Views + Dashboard (4 files):**
- src/pages/People/PersonDetail.jsx - Added import, handleRefresh (detail + timeline), wrapper
- src/pages/Teams/TeamDetail.jsx - Added import, handleRefresh, wrapper
- src/pages/Commissies/CommissieDetail.jsx - Added import, handleRefresh, wrapper
- src/pages/Dashboard.jsx - Added import, handleRefresh (dashboard + reminders + todos), wrapper

## Verification

✅ All 10 files import PullToRefreshWrapper
✅ All files have handleRefresh functions with correct query keys
✅ All files wrap content with PullToRefreshWrapper
✅ Build completes successfully
✅ No TypeScript or linting errors introduced

## Testing Checklist

When manually testing on mobile:

- [ ] Pull down on People list refreshes data
- [ ] Pull down on Teams list refreshes data
- [ ] Pull down on Commissies list refreshes data
- [ ] Pull down on Dates list refreshes data
- [ ] Pull down on Todos list refreshes data
- [ ] Pull down on Feedback list refreshes data
- [ ] Pull down on PersonDetail refreshes person and timeline
- [ ] Pull down on TeamDetail refreshes team data
- [ ] Pull down on CommissieDetail refreshes commissie data
- [ ] Pull down on Dashboard refreshes all widgets
- [ ] Spinner appears at top during refresh
- [ ] Spinner disappears when data loads
- [ ] Works on both iOS and Android
