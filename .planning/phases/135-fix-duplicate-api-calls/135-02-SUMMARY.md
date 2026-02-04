---
phase: 135-fix-duplicate-api-calls
plan: 02
subsystem: ui
tags: [react-router, createBrowserRouter, data-router, react, routing]

# Dependency graph
requires:
  - phase: 135-01
    provides: QueryClient configuration with refetch prevention
provides:
  - createBrowserRouter pattern with module-scoped route configuration
  - RouterProvider replacing BrowserRouter
  - Simplified App.jsx root layout component
  - Single route mount preventing double-mount issues
affects: [all future routing changes, performance optimization phases]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Module-scoped router configuration using createBrowserRouter"
    - "Data router pattern instead of JSX-based routing"
    - "App.jsx as root layout with Outlet for child routes"

key-files:
  created:
    - src/router.jsx
  modified:
    - src/main.jsx
    - src/App.jsx

key-decisions:
  - "Migrated from BrowserRouter (JSX routes) to createBrowserRouter (data routes) to prevent double-mount"
  - "Moved all route configuration to module scope in router.jsx"
  - "Simplified App.jsx to pure root layout component with global UI elements"
  - "Kept Dashboard as non-lazy import for immediate rendering"

patterns-established:
  - "Route configuration at module scope prevents router recreation on renders"
  - "RouterProvider receives pre-created router instead of creating routes during render"
  - "ProtectedRoute, ApprovalCheck, FairplayRoute components live in router.jsx"
  - "App.jsx handles only global UI (UpdateBanner, OfflineBanner, InstallPrompt, IOSInstallModal) and Outlet"

# Metrics
duration: 3m 22s
completed: 2026-02-04
---

# Phase 135 Plan 02: createBrowserRouter Migration Summary

**Migrated from BrowserRouter to createBrowserRouter data router pattern with module-scoped configuration to eliminate double-mount causing 2x API calls**

## Performance

- **Duration:** 3 minutes 22 seconds
- **Started:** 2026-02-04T10:48:24Z
- **Completed:** 2026-02-04T10:51:46Z
- **Tasks:** 4
- **Files modified:** 2 (plus 1 created)

## Accomplishments
- Created router.jsx with createBrowserRouter defining all routes at module scope
- Updated main.jsx to use RouterProvider with imported router configuration
- Simplified App.jsx to root layout component (removed 239 lines of routing code)
- Deployed to production with new router pattern for verification

## Task Commits

Each task was committed atomically:

1. **Task 1: Create router.jsx with route configuration at module scope** - `13b4ae29` (feat)
2. **Task 2: Update main.jsx to use RouterProvider** - `a164dd16` (feat)
3. **Task 3: Simplify App.jsx to root layout component** - `8bd1e496` (refactor)
4. **Task 4: Build, deploy, and verify single API calls** - deployed (no commit - dist/ gitignored)

## Files Created/Modified

- `src/router.jsx` - New file with createBrowserRouter configuration at module scope, includes ProtectedRoute, ApprovalCheck, FairplayRoute, ProtectedLayout components
- `src/main.jsx` - Replaced BrowserRouter with RouterProvider, imports router from router.jsx
- `src/App.jsx` - Simplified to root layout with UpdateBanner, OfflineBanner, InstallPrompt, IOSInstallModal, and Outlet (removed all Routes/Route JSX and lazy imports)

## Decisions Made

**Router pattern migration:**
- Chose createBrowserRouter over BrowserRouter because the data router pattern defines routes at module scope, preventing router recreation on renders
- Routes are created once at module load, not recreated during component renders
- This eliminates the potential for double-mounts caused by router/route reconciliation

**Component organization:**
- Kept ProtectedRoute, ApprovalCheck, FairplayRoute in router.jsx (they're routing concerns)
- Kept global UI components (UpdateBanner, OfflineBanner, InstallPrompt, IOSInstallModal) in App.jsx (they appear on all pages regardless of route)
- Dashboard remains directly imported (not lazy) for immediate rendering on initial page load

**Deployment strategy:**
- Deployed immediately to production for verification testing
- dist/ folder remains gitignored as it's a build artifact

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None - migration completed smoothly. All three files updated successfully, build passed, deployment completed without errors.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

**Ready for verification:**
- Production deployed at https://stadion.svawc.nl/
- Network panel testing required to confirm single API calls per endpoint
- Key endpoints to verify:
  - /stadion/v1/dashboard
  - /wp/v2/users/me
  - /stadion/v1/todos
  - /stadion/v1/settings

**Expected outcome:**
If the double-mount hypothesis is correct, each endpoint should now appear only once in the network panel on initial page load (previously appeared 2x with ~270ms gap).

**If verification fails:**
- createBrowserRouter migration ruled out as fix
- Double-mount cause is deeper than routing pattern
- May need to investigate React 18 concurrent rendering or QueryClient provider interaction

**If verification succeeds:**
- Double API call issue resolved
- Can proceed with Phase 136 (Optimize QuickActivityModal) performance improvements
- Root cause confirmed: BrowserRouter JSX routing pattern caused double-mount

---
*Phase: 135-fix-duplicate-api-calls*
*Completed: 2026-02-04*
