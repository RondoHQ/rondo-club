---
phase: 110-install-polish
plan: 02
subsystem: pwa
tags: [pwa, install-ui, android-banner, ios-modal, user-engagement, dutch-localization, react-components]

# Dependency graph
requires:
  - phase: 110-01
    provides: useInstallPrompt, useEngagementTracking, installTracking utilities
provides:
  - InstallPrompt component for Android install banner
  - IOSInstallModal component for iOS install instructions
  - Integrated install prompts in App.jsx
affects: [110-03]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Bottom banner positioning (bottom-20) to avoid ReloadPrompt overlap"
    - "iOS modal sheet style on mobile (rounded-t-2xl), centered on desktop"
    - "Self-managing component visibility based on platform detection"
    - "Dutch localization with HTML entities for quotes (&ldquo;/&rdquo;)"

key-files:
  created:
    - src/components/InstallPrompt.jsx
    - src/components/IOSInstallModal.jsx
  modified:
    - src/App.jsx

key-decisions:
  - "Android banner shows after 2 page views OR 1 note (via useEngagementTracking)"
  - "iOS modal shows after 3 page views (higher threshold for less intrusion)"
  - "Install prompts positioned at bottom-20 to avoid ReloadPrompt at bottom-4"
  - "Components self-manage visibility, no conditional rendering in App.jsx"
  - "Z-index stacking: UpdateBanner (100) > ReloadPrompt/IOSModal (50) > InstallPrompt (40)"

patterns-established:
  - "Pattern 1: Platform-specific install UI with shared engagement tracking"
  - "Pattern 2: Component z-index layering for notification coexistence"
  - "Pattern 3: Dutch text localization with proper HTML entity escaping"

# Metrics
duration: 1min
completed: 2026-01-28
---

# Phase 110 Plan 02: Install Prompt UI Components Summary

**Android banner and iOS modal with Dutch text, engagement-based visibility, and non-overlapping positioning**

## Performance

- **Duration:** 1 min
- **Started:** 2026-01-28T14:35:55Z
- **Completed:** 2026-01-28T14:37:49Z
- **Tasks:** 3
- **Files created:** 2
- **Files modified:** 1

## Accomplishments
- Created InstallPrompt component for Android with engagement-based visibility
- Built IOSInstallModal with step-by-step visual instructions for iOS Safari
- Integrated both components into App.jsx with proper z-index layering
- All UI text in Dutch with proper HTML entity escaping for quotes
- Components positioned to avoid overlapping with existing prompts

## Task Commits

Each task was committed atomically:

1. **Task 1: Create InstallPrompt component** - `6477f0d` (feat)
2. **Task 2: Create IOSInstallModal component** - `c256b66` (feat)
3. **Task 3: Integrate into App.jsx** - `e11a28c` (feat)
4. **Lint fix: Escape quotes** - `e63071f` (fix)

## Files Created/Modified

- `src/components/InstallPrompt.jsx` - Android PWA install banner with Dutch text
- `src/components/IOSInstallModal.jsx` - iOS Safari install instructions modal
- `src/App.jsx` - Added InstallPrompt and IOSInstallModal components

## Decisions Made

- **Android engagement threshold:** Show after 2 page views OR 1 note added (configurable via useEngagementTracking)
- **iOS engagement threshold:** Show after 3 page views (higher to reduce intrusion on iOS)
- **Component positioning:** InstallPrompt at bottom-20 to avoid ReloadPrompt at bottom-4
- **Z-index layering:** UpdateBanner (100) > ReloadPrompt/IOSModal (50) > InstallPrompt (40) > OfflineBanner (no z-index)
- **Self-managed visibility:** Components handle their own show/hide logic, App.jsx just mounts them
- **Quote escaping:** Use &ldquo; and &rdquo; HTML entities for Dutch quotes to satisfy ESLint

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Fixed ESLint unescaped quotes warning**
- **Found during:** Task 2 verification (npm run build)
- **Issue:** IOSInstallModal had unescaped quotes in JSX text causing react/no-unescaped-entities lint errors
- **Fix:** Replaced straight quotes with &ldquo; and &rdquo; HTML entities
- **Files modified:** src/components/IOSInstallModal.jsx
- **Commit:** e63071f

## Issues Encountered

None

## User Setup Required

None - components are fully integrated and will show automatically based on platform detection and engagement tracking.

## Next Phase Readiness

**Ready for Plan 110-03:** Install prompt UI components are complete and integrated.

Components available:
- `InstallPrompt` - Shows for Android users after engagement threshold met
- `IOSInstallModal` - Shows for iOS Safari users after 3 page views
- Both respect dismissal tracking and standalone mode detection

**For Plan 110-03:** trackNoteAdded() function from 110-01 needs to be integrated into note/activity creation success handlers to properly track engagement for install prompts.

No blockers or concerns.

---
*Phase: 110-install-polish*
*Completed: 2026-01-28*
