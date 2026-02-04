---
phase: 135-fix-duplicate-api-calls
verified: 2026-02-04T11:53:00Z
status: passed
score: 3/3 must-haves verified
---

# Phase 135: Fix Duplicate API Calls Verification Report

**Phase Goal:** All page loads make single API call per endpoint (not 2x)
**Verified:** 2026-02-04T11:53:00Z
**Status:** passed
**Re-verification:** Yes - after root cause fix

## Goal Achievement

### Observable Truths

| #   | Truth                                                               | Status     | Evidence                                                                                    |
| --- | ------------------------------------------------------------------- | ---------- | ------------------------------------------------------------------------------------------- |
| 1   | Production page loads make single API call per endpoint            | ✓ VERIFIED | Network panel shows 7 unique API calls, no duplicates                                       |
| 2   | Tab switching does not trigger refetches                            | ✓ VERIFIED | refetchOnWindowFocus: false in QueryClient defaults                                         |
| 3   | React app mounts exactly once on page load                          | ✓ VERIFIED | Console shows single [useDashboard] Fetching log, main.js loaded once                       |

**Score:** 3/3 truths verified

### Root Cause Analysis

**Original Issue:** ALL endpoints called 2x on page load with ~600ms gap between batches.

**Root Cause Found:** WordPress added `?ver=8.4.0` query string to main.js, but Vite's lazy-loaded chunks imported it without the version param. Browser's ES module cache uses full URL as key, so it treated `main.js?ver=8.4.0` and `main.js` as TWO DIFFERENT MODULES. This caused React to execute twice, mounting the entire app tree twice.

**Evidence:**
- Network panel showed two loads of main.js: `main-Div6inYg.js?ver=8.4.0` (reqid 202) and `main-Div6inYg.js` (reqid 233)
- Console showed `[useDashboard] Fetching at` logged twice, ~600ms apart
- All API calls duplicated with same ~600ms pattern

**Fix Applied:** Changed `wp_enqueue_script()` version parameter from `STADION_THEME_VERSION` to `null`, preventing the `?ver=` query string. Vite already hashes filenames (`main-Div6inYg.js`) for cache busting.

### Production Test Results (After Fix)

**Test Method:** Chrome DevTools Network panel on production site

**Before Fix:**
- ALL endpoints called 2x with ~600ms gap
- First batch: reqid 206-212 (7 requests)
- Second batch: reqid 234-240 (7 duplicate requests)
- Console: `[useDashboard] Fetching` logged twice
- main.js loaded twice (with and without ?ver=)

**After Fix:**
- Single API call per endpoint
- Network: reqid 317-323 (7 unique requests, no duplicates)
- Console: `[useDashboard] Fetching` logged once
- main.js loaded once (reqid 313, no ?ver= suffix)

### Required Artifacts

| Artifact          | Expected                                        | Status     | Details                                                    |
| ----------------- | ----------------------------------------------- | ---------- | ---------------------------------------------------------- |
| `src/router.jsx`  | createBrowserRouter at module scope             | ✓ VERIFIED | 256 lines, routes defined at module level                  |
| `src/main.jsx`    | RouterProvider with imported router             | ✓ VERIFIED | 47 lines, RouterProvider on line 41                        |
| `src/App.jsx`     | Root layout with Outlet                         | ✓ VERIFIED | 47 lines, Outlet on line 42                                |
| `functions.php`   | wp_enqueue_script with null version             | ✓ VERIFIED | Line 506, prevents ?ver= query string on ES modules        |

### Key Link Verification

| From           | To                  | Via                                | Status     |
| -------------- | ------------------- | ---------------------------------- | ---------- |
| main.jsx       | router.jsx          | import router                      | ✓ WIRED    |
| RouterProvider | router              | router prop                        | ✓ WIRED    |
| App.jsx        | children routes     | Outlet                             | ✓ WIRED    |
| functions.php  | main.js             | wp_enqueue_script (no ?ver=)       | ✓ WIRED    |

### Requirements Coverage

| Requirement | Status     | Evidence                                                    |
| ----------- | ---------- | ----------------------------------------------------------- |
| DUP-01      | ✓ Complete | Dashboard loads with single API call per endpoint           |
| DUP-02      | ✓ Complete | Page transitions make single API call per endpoint          |
| DUP-03      | ✓ Complete | React Query deduplication works (concurrent requests merged)|

### Commits

1. `13b4ae29` - feat(135-02): create router.jsx with createBrowserRouter at module scope
2. `a164dd16` - feat(135-02): update main.jsx to use RouterProvider
3. `8bd1e496` - refactor(135-02): simplify App.jsx to root layout component
4. `554df626` - docs(135-02): complete createBrowserRouter migration plan
5. `b9f5861e` - fix(135): remove version query string from ES module to prevent double-mount

### Technical Summary

The double-mount issue was caused by a URL mismatch in ES module resolution:

1. WordPress enqueued `main.js?ver=8.4.0`
2. Vite's lazy chunks imported `src/main.jsx` → resolved to `main.js` (no version)
3. Browser ES module cache is keyed by full URL including query strings
4. Two different URLs = two separate module instances = React executed twice

The fix removes the version query string from ES modules. Vite's content-hashed filenames (`main-Div6inYg.js`) already provide cache busting, making query strings unnecessary and harmful.

---

_Verified: 2026-02-04T11:53:00Z_
_Verifier: Human + Claude (via Chrome DevTools MCP)_
