---
phase: 135-fix-duplicate-api-calls
verified: 2026-02-04T10:54:53Z
status: human_needed
score: 3/3 must-haves verified
re_verification: 
  previous_status: gaps_found
  previous_score: 1/3
  gaps_closed:
    - "React Router uses data router pattern (createBrowserRouter)"
  gaps_remaining: []
  regressions: []
human_verification:
  - test: "Production page load single API call check"
    expected: "Dashboard shows one request per endpoint in network panel (not 2x with ~1 second gap)"
    why_human: "Requires runtime verification with browser DevTools network panel to confirm double-mount is fixed"
  - test: "Tab switching does not trigger refetches"
    expected: "Switch away and back to tab - no new API requests appear"
    why_human: "Requires runtime verification with browser DevTools network panel"
  - test: "Page transitions single API call check"
    expected: "Navigate People -> Dashboard -> Teams - single API call per endpoint"
    why_human: "Requires runtime verification with browser DevTools network panel"
---

# Phase 135: Fix Duplicate API Calls Verification Report

**Phase Goal:** All page loads make single API call per endpoint (not 2x)
**Verified:** 2026-02-04T10:54:53Z
**Status:** human_needed
**Re-verification:** Yes - after gap closure plan 135-02

## Re-Verification Context

**Previous verification (2026-02-04T11:30:00Z):**
- Status: gaps_found
- Score: 1/3 truths verified
- Gap: Entire React tree mounting twice ~1 second apart
- Root cause hypothesis: BrowserRouter JSX-based routing triggering remounts

**Gap closure plan 135-02:**
- Migrated from BrowserRouter to createBrowserRouter data router pattern
- Moved route configuration to module scope in router.jsx
- Updated main.jsx to use RouterProvider with pre-created router
- Simplified App.jsx to root layout component with Outlet

**This verification:**
- All code structure gaps closed
- All artifacts verified (exists, substantive, wired)
- Runtime behavior requires human verification on production

## Goal Achievement

### Observable Truths

| #   | Truth                                                            | Status        | Evidence                                                                                    |
| --- | ---------------------------------------------------------------- | ------------- | ------------------------------------------------------------------------------------------- |
| 1   | Dashboard page load shows single API call per endpoint          | ✓ CODE_READY  | createBrowserRouter at module scope prevents router recreation; runtime verification needed |
| 2   | Page transitions make single API call per endpoint              | ✓ CODE_READY  | QueryClient staleTime and refetch options configured correctly                              |
| 3   | React Router uses data router pattern (createBrowserRouter)     | ✓ VERIFIED    | router.jsx line 178 creates router at module scope, exported to main.jsx                   |

**Score:** 3/3 code structure verified (runtime behavior requires human verification)

### Code Structure Verification (All Passed)

All architectural changes from plan 135-02 successfully implemented:

1. **router.jsx created** (256 lines) - ✓ VERIFIED
   - createBrowserRouter at module scope (line 178)
   - 22 route paths defined as data objects (not JSX)
   - All protection components included (ProtectedRoute, ApprovalCheck, FairplayRoute)
   - Dashboard direct import, 25+ lazy-loaded page components
   - ProtectedLayout uses Outlet for nested routes

2. **main.jsx updated** (47 lines) - ✓ VERIFIED
   - RouterProvider replaces BrowserRouter (line 41)
   - Imports router from ./router.jsx (line 6)
   - QueryClientProvider wraps RouterProvider
   - No BrowserRouter import anywhere in codebase

3. **App.jsx simplified** (47 lines) - ✓ VERIFIED
   - Uses Outlet for child routes (line 42)
   - No Routes/Route JSX (removed entirely)
   - Only global UI components (UpdateBanner, OfflineBanner, InstallPrompt, IOSInstallModal)
   - No lazy imports or routing logic

### Required Artifacts

| Artifact          | Expected                                     | Status     | Details                                                                        |
| ----------------- | -------------------------------------------- | ---------- | ------------------------------------------------------------------------------ |
| `src/router.jsx`  | Route configuration at module scope          | ✓ VERIFIED | 256 lines, createBrowserRouter at line 178, 22 routes, all components wired   |
| `src/main.jsx`    | App entry point with RouterProvider          | ✓ VERIFIED | 47 lines, RouterProvider with router prop (line 41), imports from router.jsx  |
| `src/App.jsx`     | Root layout component (no Routes JSX)        | ✓ VERIFIED | 47 lines, Outlet at line 42, no routing logic, only global UI components      |

**Artifact Verification Details:**

**src/router.jsx** (256 lines):
- **Level 1 (Exists):** ✓ EXISTS
- **Level 2 (Substantive):** ✓ SUBSTANTIVE
  - 256 lines (well above minimum)
  - No stub patterns (only "Todos" in legitimate route definitions)
  - Proper exports: `export default router` (line 256)
  - Complete implementations:
    - 4 protection components: ApprovalCheck (lines 43-93), FairplayRoute (95-144), ProtectedRoute (146-162), ProtectedLayout (165-175)
    - 26 component imports (1 direct, 25 lazy)
    - PageLoader component (lines 37-41)
    - 22 route paths covering all app sections
- **Level 3 (Wired):** ✓ WIRED
  - Imported by main.jsx: `import router from './router'` (line 6)
  - Used in RouterProvider: `<RouterProvider router={router} />` (line 41)
  - All lazy components properly imported and used in routes
  - ProtectedRoute wraps ApprovalCheck (line 161)
  - ProtectedLayout uses Layout component with Outlet (lines 168-172)

**src/main.jsx** (47 lines):
- **Level 1 (Exists):** ✓ EXISTS
- **Level 2 (Substantive):** ✓ SUBSTANTIVE
  - 47 lines (substantive)
  - No stub patterns
  - Proper exports: ReactDOM.createRoot().render() (lines 39-43)
  - QueryClient configuration with all required defaults (lines 23-33)
- **Level 3 (Wired):** ✓ WIRED
  - Imports router from ./router.jsx (line 6)
  - RouterProvider receives router prop (line 41)
  - QueryClientProvider wraps RouterProvider (lines 40-42)
  - No BrowserRouter anywhere in codebase

**src/App.jsx** (47 lines):
- **Level 1 (Exists):** ✓ EXISTS
- **Level 2 (Substantive):** ✓ SUBSTANTIVE
  - 47 lines (above 30-line minimum)
  - No stub patterns
  - Proper exports: `export default App` (line 47)
  - Real implementations:
    - UpdateBanner component with version check and reload (lines 9-30)
    - useTheme() hook call (line 34)
    - Global UI components (lines 38-41)
    - Outlet for child routes (line 42)
- **Level 3 (Wired):** ✓ WIRED
  - Imported by router.jsx: `import App from './App'` (line 8)
  - Used as root route element in router config (line 181)
  - Outlet component renders child routes from router.jsx
  - All global UI components properly imported and rendered

### Key Link Verification

| From            | To                   | Via                  | Status     | Details                                                                                |
| --------------- | -------------------- | -------------------- | ---------- | -------------------------------------------------------------------------------------- |
| src/main.jsx    | src/router.jsx       | import router        | ✓ WIRED    | Line 6: `import router from './router'`, used in RouterProvider (line 41)             |
| src/main.jsx    | RouterProvider       | router prop          | ✓ WIRED    | Line 41: `<RouterProvider router={router} />` receives module-scoped router           |
| src/router.jsx  | src/App.jsx          | root route element   | ✓ WIRED    | Line 181: `element: <App />` as root route, App renders Outlet for child routes       |
| src/App.jsx     | router children      | Outlet component     | ✓ WIRED    | Line 42: `<Outlet />` renders child routes from router.jsx configuration              |
| router.jsx      | ProtectedLayout      | nested routes        | ✓ WIRED    | Line 194: ProtectedLayout wraps all protected routes, uses Layout + Outlet (line 170) |
| ProtectedLayout | all page components  | Outlet + Suspense    | ✓ WIRED    | Lines 169-171: Suspense wraps Outlet, all lazy components load through this path      |

**Key Link Details:**

1. **main.jsx → router.jsx → RouterProvider:**
   - router created at module scope in router.jsx (line 178) - prevents recreation on renders
   - Exported as default (line 256)
   - Imported by main.jsx (line 6)
   - Passed to RouterProvider (line 41)
   - **Critical:** Router is created once at module load, not recreated during component renders

2. **App.jsx as root layout:**
   - App used as root route element in router config (line 181 of router.jsx)
   - App renders global UI + Outlet (line 42 of App.jsx)
   - Outlet receives children from router configuration
   - No routing logic in App.jsx - purely presentational layout

3. **ProtectedLayout → all protected pages:**
   - ProtectedLayout wraps all authenticated routes (line 194 of router.jsx)
   - Applies ProtectedRoute → ApprovalCheck → Layout → Suspense → Outlet chain
   - All page components (Dashboard, PeopleList, Teams, etc.) render through this path
   - Dashboard is direct import (line 11), others are lazy (lines 14-34)

4. **Route nesting structure:**
   ```
   Root route (App) [line 180]
   ├── Login (public) [line 185]
   └── ProtectedLayout [line 194]
       ├── Dashboard (index) [line 197]
       ├── People routes [lines 200-202]
       ├── VOG, Contributie [lines 205, 208]
       ├── Tuchtzaken (FairplayRoute) [lines 211-218]
       ├── Teams, Commissies [lines 221-226]
       ├── Dates, Todos, Feedback [lines 229-236]
       ├── Settings (multi-level) [lines 239-246]
       └── Fallback redirect [line 249]
   ```

### Gap Closure Analysis

**Previous gap: "React Router uses data router pattern (createBrowserRouter)"**

Status: ✓ CLOSED

Evidence:
1. **Module-scoped router:** router.jsx line 178 creates router at module scope (outside any function)
2. **Data-based routes:** 22 routes defined as configuration objects, not JSX
3. **RouterProvider pattern:** main.jsx line 41 uses RouterProvider with pre-created router
4. **No BrowserRouter:** Grep confirms no BrowserRouter imports in entire src/ directory
5. **Outlet pattern:** App.jsx uses Outlet (line 42) instead of Routes/Route JSX

**Why this should fix the double-mount:**
- BrowserRouter creates routes during render, potentially causing reconciliation issues
- createBrowserRouter creates routes once at module load
- Router configuration is immutable - cannot be recreated on component renders
- This eliminates the primary suspected cause of double-mounting

**Regression check:**
- All route paths preserved from original App.jsx routing
- Protection logic maintained (ProtectedRoute, ApprovalCheck, FairplayRoute)
- Layout structure identical (Layout component with Suspense for lazy routes)
- No functionality removed or changed

### Requirements Coverage

| Requirement | Status       | Blocking Issue |
| ----------- | ------------ | -------------- |
| DUP-01: Dashboard page load makes single API call per endpoint | ✓ CODE_READY | Code structure verified, runtime requires human testing |
| DUP-02: All page transitions make single API call per endpoint | ✓ CODE_READY | QueryClient config + router pattern verified, runtime requires human testing |
| DUP-03: React Query properly deduplicates concurrent requests | ✓ VERIFIED | Built-in behavior, defaultOptions in main.jsx (lines 24-32) |

### Anti-Patterns Found

| File                                     | Line | Pattern             | Severity   | Impact                                                                    |
| ---------------------------------------- | ---- | ------------------- | ---------- | ------------------------------------------------------------------------- |
| src/components/TeamEditModal.jsx         | 235  | staleTime: 0        | ℹ️ INFO    | Modal-specific, intentional per comments                                  |
| src/components/CommissieEditModal.jsx    | 235  | staleTime: 0        | ℹ️ INFO    | Modal-specific, intentional per comments                                  |

**Anti-pattern Analysis:**

No blocker anti-patterns found. The `staleTime: 0` overrides in modals are intentional and documented. They only execute when modals are open and do not affect page load performance (phase goal).

**Router implementation quality:**
- Clean separation of concerns (routing in router.jsx, layout in App.jsx)
- Proper protection layers (ProtectedRoute → ApprovalCheck → Layout)
- Code-splitting via lazy() for all non-critical routes
- Dashboard direct import for immediate initial render

### Human Verification Required

All automated structural checks passed. The createBrowserRouter migration is correctly implemented. However, the phase goal requires runtime verification that the double-mount issue is resolved.

#### 1. Production Dashboard Page Load - CRITICAL

**Test:** 
1. Deploy to production (MUST be production - not localhost dev server)
2. Open production site in Chrome browser
3. Open DevTools Network tab, filter by "Fetch/XHR"
4. Hard refresh Dashboard (Cmd/Ctrl+Shift+R to bypass all cache)
5. Observe network request timing and count

**Expected:** 
- Single API call to /stadion/v1/dashboard
- Single API call to /wp/v2/users/me
- Single API call to /stadion/v1/todos
- Single API call to /stadion/v1/settings
- NO duplicate requests with ~1 second gap
- NO duplicate request IDs (previously saw reqid 81-87, then 111-117)

**Why human:** 
The double-mount issue is a runtime behavior that cannot be detected through static code analysis. Previous production testing (2026-02-04 initial verification) showed ALL endpoints called 2x with ~270ms-1000ms gap. This test confirms whether the createBrowserRouter migration resolved the issue.

**How to verify success:**
- Open Network panel, clear it, hard refresh
- Count requests to each endpoint
- Check timing between any duplicates (should be none)
- Service worker registration should log only once in console (was logging 2x before)

**Previous behavior (before fix):**
```
Request Timeline:
T+0ms:    First batch (reqid 81-87): dashboard, user/me, todos, settings, meetings, VOG count
T+270ms:  (gap)
T+1000ms: Second batch (reqid 111-117): EXACT SAME endpoints repeated
```

**Expected behavior (after fix):**
```
Request Timeline:
T+0ms:    Single batch: dashboard, user/me, todos, settings, meetings, VOG count
T+1000ms: (nothing - no second batch)
```

#### 2. Console Service Worker Logging Check

**Test:**
1. In production, open browser console
2. Hard refresh page
3. Count how many times "Service Worker registered" appears

**Expected:**
- Message appears exactly once
- Previously appeared twice (proving entire App tree mounted twice)

**Why human:**
Service worker registration logging is a reliable indicator of App component mounting. Two logs = two mounts = double API calls.

#### 3. Tab Switching Refetch Prevention

**Test:**
1. Load Dashboard page in production
2. Note network requests (should now be single calls per endpoint)
3. Switch to another browser tab for 10+ seconds
4. Return to Dashboard tab
5. Check Network tab for new requests

**Expected:**
- No new API requests when returning to tab
- Existing data remains displayed (from staleTime cache)
- refetchOnWindowFocus: false prevents refetch (main.jsx line 27)

**Why human:** Requires runtime verification of refetch behavior across tab focus events

#### 4. Page Transitions Single API Call

**Test:**
1. In production, start on Dashboard
2. Navigate: Dashboard → People → Teams → Dashboard
3. Monitor Network tab during each navigation

**Expected:**
- Dashboard (initial): /stadion/v1/dashboard (once)
- People: /wp/v2/people (once)
- Teams: /wp/v2/teams (once)
- Dashboard (return): No new /stadion/v1/dashboard call (cache still fresh from staleTime: 5min)

**Why human:** Requires runtime verification of navigation flow and cache behavior

### Verification Context

**What changed in gap closure:**

1. **Routing architecture:**
   - Before: BrowserRouter with JSX Routes/Route in App.jsx render
   - After: createBrowserRouter with data routes at module scope in router.jsx

2. **Router creation:**
   - Before: Routes created during App component render (potential for recreation)
   - After: Router created once at module load (immutable, never recreated)

3. **Component structure:**
   - Before: App.jsx contained all routing logic (281 lines)
   - After: router.jsx owns routing (256 lines), App.jsx is pure layout (47 lines)

**Why this should fix the issue:**

The BrowserRouter JSX pattern creates route elements during render, which can trigger React reconciliation and potential remounts during navigation or state updates. The createBrowserRouter data pattern creates the router once at module load, making it immutable. This eliminates the suspected cause of double-mounting.

**What's configured correctly:**

1. ✓ Router at module scope (prevents recreation)
2. ✓ QueryClient with staleTime: 5min (prevents remount refetches)
3. ✓ refetchOnWindowFocus: false (prevents tab switch refetches)
4. ✓ refetchOnMount: false (prevents component remount refetches)
5. ✓ React Query deduplication (built-in for concurrent requests)

**Production deployment:**

- Build completed successfully (2.73s, no errors)
- dist/ assets generated (81 entries, 2202.28 KiB)
- Service worker precached
- Ready for deployment: `bin/deploy.sh`

**Next steps:**

1. Deploy to production: `bin/deploy.sh`
2. Run human verification tests above
3. If verification passes: Phase 135 complete, proceed to Phase 136
4. If verification fails: Double-mount cause is deeper than routing pattern, investigate React 18 concurrent rendering or QueryClient provider interaction

---

_Verified: 2026-02-04T10:54:53Z_
_Verifier: Claude (gsd-verifier)_
_Re-verification: Yes - after plan 135-02 gap closure_
