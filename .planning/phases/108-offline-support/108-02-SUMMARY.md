---
phase: 108
plan: 02
subsystem: ui-components
tags: [offline, ui, banner, react, hooks]

dependencies:
  requires: [108-01]
  provides: ["offline-banner-component", "network-status-feedback"]
  affects: [108-03, 108-04]

tech-stack:
  added: []
  patterns: ["state-management-for-transitions", "persistent-ui-feedback"]

files:
  created:
    - src/components/OfflineBanner.jsx
  modified:
    - src/App.jsx

decisions:
  - id: OFF-05
    context: "Offline banner positioning and behavior"
    decision: "Fixed bottom banner with persistent offline state and brief (2.5s) back-online confirmation"
    rationale: "Bottom avoids conflict with UpdateBanner at top; persistent offline state ensures users understand why features may be limited; brief confirmation provides reassurance when reconnecting"
    alternatives: ["Toast notification (auto-dismisses, less persistent)", "Top banner (conflicts with UpdateBanner)", "No feedback (users unaware of offline state)"]

metrics:
  tasks: 2
  commits: 2
  duration: "~1m"
  completed: 2026-01-28
---

# Phase 108 Plan 02: Offline Banner Summary

**One-liner:** Persistent offline indicator banner with Dutch text, using useOnlineStatus hook for network feedback

## What Was Built

Created OfflineBanner component that provides clear visual feedback about network connectivity:

1. **OfflineBanner Component** (`src/components/OfflineBanner.jsx`):
   - Shows persistent "Je bent offline" banner with WifiOff icon when network disconnected
   - Shows brief "Je bent weer online" confirmation with Wifi icon for 2.5 seconds when reconnecting
   - Returns null when online and no recent state change
   - Uses wasOffline state to track offline→online transitions
   - Uses showBackOnline state for timed confirmation message
   - Fixed bottom positioning (z-50) to appear above content
   - Subtle styling: gray background offline, green when showing confirmation
   - Dark mode support via Tailwind dark: variants
   - Dutch text throughout per project localization

2. **App Integration**:
   - Mounted OfflineBanner in App.jsx after ReloadPrompt
   - Visible globally across all routes including login
   - No UI conflicts due to fixed bottom positioning

## Technical Implementation

### Component Architecture

**State Management:**
- `isOnline` from useOnlineStatus hook (navigator.onLine wrapper)
- `wasOffline` (useState) to detect when user has been offline
- `showBackOnline` (useState) to control 2.5s confirmation display

**Effect Logic:**
```javascript
useEffect(() => {
  if (!isOnline) {
    // Going offline
    setWasOffline(true);
    setShowBackOnline(false);
  } else if (wasOffline) {
    // Coming back online after being offline
    setShowBackOnline(true);
    setWasOffline(false);

    const timer = setTimeout(() => {
      setShowBackOnline(false);
    }, 2500);

    return () => clearTimeout(timer);
  }
}, [isOnline, wasOffline]);
```

**Rendering Logic:**
- Returns null when online and not showing back-online message
- Renders with green background + Wifi icon when showing "Je bent weer online"
- Renders with gray background + WifiOff icon when offline

### Integration Points

- Uses `useOnlineStatus` hook from 108-01
- Coordinates with TanStack Query's onlineManager (configured in 108-01)
- Complements Workbox offline.html fallback (configured in 108-01)

## Files Modified

### Created
- **src/components/OfflineBanner.jsx** (56 lines)
  - Offline indicator banner component
  - Exports: OfflineBanner

### Modified
- **src/App.jsx**
  - Added OfflineBanner import
  - Rendered after ReloadPrompt for global visibility

## Verification Results

- ✓ OfflineBanner.jsx created in src/components/
- ✓ Component uses useOnlineStatus hook
- ✓ Shows persistent banner while offline with WifiOff icon
- ✓ Shows brief (2.5s) "back online" confirmation with Wifi icon
- ✓ Dutch text used throughout ("Je bent offline", "Je bent weer online")
- ✓ Mounted in App.jsx after ReloadPrompt
- ✓ No lint errors in new component
- ✓ Build succeeds

## Deviations from Plan

None - plan executed exactly as written.

## Decisions Made

**OFF-05: Offline Banner Positioning and Behavior**
- **Context:** Need to show network status without interfering with existing UI
- **Decision:** Fixed bottom banner with persistent offline state and brief (2.5s) back-online confirmation
- **Rationale:**
  - Bottom positioning avoids conflict with UpdateBanner at top
  - Persistent offline state ensures users understand why features may be limited
  - Brief confirmation provides reassurance when reconnecting without being intrusive
- **Alternatives Considered:**
  - Toast notification: Would auto-dismiss, less persistent than needed for offline state
  - Top banner: Would conflict with UpdateBanner which also uses top positioning
  - No feedback: Users would be unaware of offline state and confused by non-functional features

## Known Issues

None.

## Next Phase Readiness

**Blockers:** None

**Concerns:** None

**Dependencies Delivered:**
- OfflineBanner component ready for production
- Network status feedback UI complete
- Ready for 108-03 (Form Protection) which will use this feedback when preventing mutations

**Ready for:** 108-03 (Form Protection)

## Testing Notes

**Manual Testing Required:**
1. Start app online - banner should not be visible
2. Disconnect network (airplane mode or disable WiFi)
3. Banner should appear at bottom: "Je bent offline" with WifiOff icon
4. Banner should remain visible while offline (persistent)
5. Reconnect network
6. Banner should change to green: "Je bent weer online" with Wifi icon
7. After 2.5 seconds, banner should disappear

**Expected Behavior:**
- Instant appearance when going offline
- Instant change when coming back online
- Automatic dismissal 2.5s after reconnecting
- No banner shown during normal online operation

## Metrics

- **Tasks Completed:** 2/2
- **Commits:** 2
- **Duration:** ~1 minute
- **Files Created:** 1
- **Files Modified:** 1
- **Test Coverage:** Manual testing required (visual/UX component)

## Commits

- `27e521f` - feat(108-02): create OfflineBanner component
- `74aeef2` - feat(108-02): mount OfflineBanner in App.jsx
