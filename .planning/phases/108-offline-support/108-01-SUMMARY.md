---
phase: 108
plan: 01
subsystem: pwa-infrastructure
completed: 2026-01-28
duration: 2m
tags: [pwa, offline, react-hooks, tanstack-query, service-worker]

dependency_graph:
  requires: [107-01, 107-02, 107-03]
  provides:
    - useOnlineStatus hook for React components
    - TanStack Query offline mode integration
    - Service worker offline fallback page
    - Dutch offline.html page with dark mode
  affects: [108-02, 108-03, 108-04]

tech_stack:
  added: []
  patterns:
    - "navigator.onLine event-based detection"
    - "TanStack Query onlineManager integration"
    - "Workbox navigateFallback for uncached routes"
    - "Static offline fallback page (no React)"

key_files:
  created:
    - src/hooks/useOnlineStatus.js
    - public/offline.html
  modified:
    - src/main.jsx
    - vite.config.js

decisions:
  - id: OFF-01
    title: Use navigator.onLine over API ping polling
    rationale: Instant feedback, zero bandwidth/battery cost, TanStack Query handles false positives via automatic retry logic
    alternatives: [API health check polling]
  - id: OFF-02
    title: NetworkFirst strategy for API caching
    rationale: Avoid double-caching with TanStack Query client-side cache; service worker provides offline fallback only
    alternatives: [CacheFirst, StaleWhileRevalidate]
  - id: OFF-03
    title: Static offline.html with no dependencies
    rationale: Must work completely offline, no React/JS bundle needed; self-contained HTML with inline SVG icon
    alternatives: [React component as fallback]
---

# Phase 108 Plan 01: Core Offline Infrastructure Summary

**One-liner:** Created useOnlineStatus hook, integrated TanStack Query onlineManager, and configured Workbox offline fallback with Dutch offline.html page

## What Was Built

### Task 1: useOnlineStatus Hook
Created `src/hooks/useOnlineStatus.js` - React hook that tracks browser online/offline state using navigator.onLine and window events.

**Implementation:**
- Initializes state from `navigator.onLine` with SSR fallback to `true`
- Registers event listeners for `online` and `offline` window events
- Returns boolean `isOnline` state for React components
- Cleans up listeners on unmount

**Files:** `src/hooks/useOnlineStatus.js`
**Commit:** 3c1a1a9

### Task 2: TanStack Query onlineManager Integration
Configured TanStack Query to respect browser online/offline state in `src/main.jsx`.

**Implementation:**
- Imported `onlineManager` from `@tanstack/react-query`
- Called `onlineManager.setEventListener()` before QueryClient initialization
- Registered online/offline event listeners that call `setOnline(true/false)`
- Queries automatically pause when offline (networkMode defaults to 'online')

**Files:** `src/main.jsx`
**Commit:** 4ef9e1e

### Task 3: Workbox Offline Fallback Configuration
Added navigateFallback to serve offline.html for uncached navigation routes, and created static Dutch offline page.

**Implementation:**

**Part A - vite.config.js:**
- Set `navigateFallback: '/offline.html'` in workbox config
- Added `navigateFallbackDenylist` for API, admin, and login routes
- Added `includeAssets: ['offline.html', 'icons/**/*']` to include offline.html in build

**Part B - public/offline.html:**
- Static HTML page with no external dependencies (fully offline)
- Dutch language with heading "Je bent offline"
- WiFi-off icon (inline SVG from lucide-react)
- Retry and Go Back buttons
- Dark mode support via prefers-color-scheme media query
- Stadion accent color (#f97316 orange) for primary button

**Files:** `vite.config.js`, `public/offline.html`
**Commit:** 0dadb11

## Verification

All success criteria met:
- ✅ useOnlineStatus hook created and exported
- ✅ TanStack Query onlineManager configured in main.jsx
- ✅ Workbox navigateFallback set to /offline.html with appropriate denylist
- ✅ public/offline.html exists with Dutch copy, wifi-off icon, Retry/Go back buttons
- ✅ offline.html appears in dist/ after build
- ✅ All lint checks pass (143 pre-existing errors in unrelated files, no new errors)

**Build verification:**
```bash
npm run build
# ✓ built in 2.87s
# PWA v1.2.0 - precache 79 entries
ls dist/offline.html
# File exists in build output
```

## Deviations from Plan

None - plan executed exactly as written.

## Technical Notes

### Online/Offline Detection
- **navigator.onLine limitations:** Can return true even when connected to WiFi without internet (false positive)
- **Mitigation:** TanStack Query's error handling automatically detects failed requests and pauses retries
- **Tradeoff accepted:** Instant detection with occasional false positives is better than polling for battery/bandwidth

### Service Worker Caching Strategy
- **NetworkFirst for API routes:** Configured in Phase 107-01, maintained here
- **Purpose:** Service worker tries network first, falls back to cache only when offline
- **Coordination with TanStack Query:** Query library controls cache freshness (staleTime, refetch); service worker provides offline fallback only
- **Avoids double-caching:** Both layers cache API responses, but NetworkFirst ensures TanStack Query gets fresh data when online

### Offline.html Design
- **No React/JS:** Static HTML works even if service worker can't load JS bundles
- **Inline SVG icon:** No external image dependencies
- **Dark mode:** Uses prefers-color-scheme to match OS/browser theme
- **Dutch copy:** Matches Stadion's language (nl)
- **Buttons:** Reload attempts reconnection; Go Back returns to cached content

## Dependencies

**Requires (built upon):**
- Phase 107-01: Service worker registration via vite-plugin-pwa
- Phase 107-02: PWA manifest with theme colors
- Phase 107-03: Update prompt infrastructure

**Provides (enables):**
- Phase 108-02: OfflineBanner component (uses useOnlineStatus hook)
- Phase 108-03: Form disabling (uses useOnlineStatus hook)
- Phase 108-04: Cache persistence (builds on TanStack Query config)

**Affects:**
- All future components that need online/offline awareness
- Service worker behavior for uncached routes
- TanStack Query behavior during network outages

## Next Phase Readiness

**Ready to proceed to Phase 108-02 (Offline Banner UI):**
- ✅ useOnlineStatus hook available for OfflineBanner component
- ✅ TanStack Query pauses queries when offline
- ✅ Service worker serves offline.html for uncached routes

**No blockers identified.**

## Performance Impact

- **Runtime overhead:** Minimal - two event listeners on window object
- **Bundle size:** +27 lines of code (useOnlineStatus hook), +15 lines in main.jsx
- **Build artifacts:** +4KB (offline.html in dist/)
- **Network impact:** None - no additional requests, navigator.onLine is instant

## Testing Recommendations

1. **Manual offline testing:**
   - Open DevTools → Network tab → Set throttling to "Offline"
   - Verify queries pause (no failed requests in console)
   - Navigate to uncached route → offline.html appears
   - Test dark mode by toggling OS theme preference

2. **Hook testing:**
   - Go offline → useOnlineStatus() returns false
   - Come back online → useOnlineStatus() returns true
   - Verify "online" event fires and components update

3. **Service worker testing:**
   - Clear all caches → go offline → reload app
   - Navigate to /dashboard → should work (cached during PWA install)
   - Navigate to /new-uncached-route → offline.html appears

## Related Files

- `src/hooks/useOnlineStatus.js` - Online/offline detection hook
- `src/main.jsx` - TanStack Query onlineManager configuration
- `vite.config.js` - Workbox navigateFallback configuration
- `public/offline.html` - Static offline fallback page
- `dist/offline.html` - Build output (generated by Vite)

## Metadata

**Execution time:** 2 minutes
**Tasks completed:** 3/3
**Commits:** 3 (one per task)
**Files created:** 2
**Files modified:** 2
**Lines added:** ~180 (hook + config + offline.html)
