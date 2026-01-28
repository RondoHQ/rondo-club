---
phase: 110-install-polish
plan: 01
subsystem: pwa
tags: [pwa, install-prompt, beforeinstallprompt, engagement-tracking, localStorage, sessionStorage, react-hooks]

# Dependency graph
requires:
  - phase: 107-pwa-foundation
    provides: vite-plugin-pwa service worker infrastructure
  - phase: 108-offline-handling
    provides: Patterns for React hooks (useOnlineStatus)
provides:
  - PWA install prompt event management for Android
  - Engagement-based prompt timing hooks
  - localStorage utilities for dismissal tracking
affects: [110-02, 110-03]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "beforeinstallprompt event capture and storage"
    - "Engagement heuristics via sessionStorage"
    - "localStorage dismissal tracking with cooldown periods"
    - "Standalone mode detection for installed apps"

key-files:
  created:
    - src/utils/installTracking.js
    - src/hooks/useInstallPrompt.js
    - src/hooks/useEngagementTracking.js
  modified: []

key-decisions:
  - "useInstallPrompt checks standalone mode to avoid showing prompts in installed apps"
  - "Dismissal tracking allows up to 3 dismissals with 7-day cooldown between attempts"
  - "Engagement tracking uses sessionStorage for per-session metrics (page views)"
  - "trackNoteAdded() exported as standalone function for integration in Plan 110-03"

patterns-established:
  - "Pattern 1: localStorage utilities exported as object with methods (installTracking)"
  - "Pattern 2: Engagement hooks increment sessionStorage counters on mount"
  - "Pattern 3: Install prompt hooks follow useOnlineStatus pattern (event listeners with cleanup)"

# Metrics
duration: 1min
completed: 2026-01-28
---

# Phase 110 Plan 01: Install Prompt Foundation Summary

**React hooks for Android beforeinstallprompt management, engagement-based timing with sessionStorage, and localStorage dismissal tracking**

## Performance

- **Duration:** 1 min
- **Started:** 2026-01-28T15:31:30Z
- **Completed:** 2026-01-28T15:32:51Z
- **Tasks:** 3
- **Files modified:** 3

## Accomplishments
- Created installTracking localStorage utility with 7-day cooldown and 3-dismissal limit
- Built useInstallPrompt hook capturing beforeinstallprompt for Android install prompts
- Implemented useEngagementTracking hook with configurable page view and note thresholds
- All hooks detect standalone mode to avoid showing prompts in already-installed apps

## Task Commits

Each task was committed atomically:

1. **Task 1: Create installTracking utility** - `aa32d3d` (feat)
2. **Task 2: Create useInstallPrompt hook** - `d947a5a` (feat)
3. **Task 3: Create useEngagementTracking hook** - `1681149` (feat)

## Files Created/Modified

- `src/utils/installTracking.js` - localStorage helpers for dismissal tracking and installation status
- `src/hooks/useInstallPrompt.js` - Android beforeinstallprompt event capture with prompt/hide functions
- `src/hooks/useEngagementTracking.js` - Session-based engagement tracking for smart prompt timing

## Decisions Made

- **Dismissal policy:** Allow up to 3 dismissals with 7-day cooldown between re-prompts, honoring user choice while allowing reconsideration
- **Engagement thresholds:** Default to 2 page views OR 1 note added, configurable via options parameter
- **Standalone detection:** Check both `matchMedia('(display-mode: standalone)')` for Android and `navigator.standalone` for iOS
- **Session vs persistent storage:** sessionStorage for per-session engagement (page views, notes), localStorage for cross-session state (dismissals, installed status)

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

**Ready for Plan 110-02:** All foundation hooks created and ready for UI integration.

Hooks available:
- `useInstallPrompt()` - Ready to use in Android install banner component
- `useEngagementTracking()` - Ready to gate prompt visibility based on engagement
- `installTracking` utility - Ready for iOS modal dismissal tracking

**For Plan 110-03:** `trackNoteAdded()` function exported and ready to integrate into note/activity creation success handlers.

No blockers or concerns.

---
*Phase: 110-install-polish*
*Completed: 2026-01-28*
