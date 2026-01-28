---
phase: 110-install-polish
plan: 03
subsystem: pwa-updates
completed: 2026-01-28
duration: 1m
status: complete

requires:
  - phase: 107
    provides: ["vite-plugin-pwa", "useRegisterSW hook", "ReloadPrompt component"]
  - phase: 110-01
    provides: ["localStorage utilities", "engagement tracking patterns"]

provides:
  - artifact: "ReloadPrompt with periodic update checking"
    exports: ["ReloadPrompt"]
  - artifact: "Dutch PWA update notifications"
    exports: ["localized update/offline messages"]

affects:
  - phase: 110-polish
    reason: "Update notifications now Dutch, consistent with install prompts"

tech-stack:
  added: []
  patterns:
    - "Periodic service worker update checking (1-hour interval)"
    - "Dutch localization for PWA notifications"

key-files:
  created: []
  modified:
    - path: "src/components/ReloadPrompt.jsx"
      changes:
        - "Added periodic SW update check (every hour)"
        - "Localized all text to Dutch"

decisions:
  - id: "periodic-check-interval"
    choice: "1 hour (60 * 60 * 1000ms)"
    rationale: "Balance between catching updates during long sessions and avoiding excessive network/battery usage"
    alternatives: ["30 minutes (too aggressive)", "2 hours (too slow)"]

  - id: "online-only-check"
    choice: "Only check updates when navigator.onLine is true"
    rationale: "Avoid failed fetch attempts when offline, save battery"
    alternatives: ["Check regardless of online status (wasteful)"]

  - id: "dutch-localization"
    choice: "Full Dutch translation for all notifications"
    rationale: "Maintain consistency with app's Dutch UI, improve user experience"
    alternatives: ["Keep English (inconsistent with rest of app)"]

tags: ["pwa", "service-worker", "updates", "localization", "dutch", "react", "vite-plugin-pwa"]
---

# Phase 110 Plan 03: ReloadPrompt Update Checking & Dutch Localization Summary

**One-liner:** Periodic service worker update checking (hourly) with Dutch notifications for offline ready and update prompts

## Overview

Enhanced the existing ReloadPrompt component with periodic service worker update checking and localized all user-facing text to Dutch. This ensures users are notified of new versions even during extended sessions, and maintains UI consistency with the app's Dutch localization.

**Duration:** 1 minute
**Tasks completed:** 2/2
**Status:** ✅ Complete

## What Was Built

### Core Implementation

**Periodic Update Checking:**
- Added `intervalMS = 60 * 60 * 1000` (1 hour) constant
- Replaced `onRegistered` callback with enhanced `onRegisteredSW(swUrl, registration)`
- Implemented `setInterval` to check for updates every hour
- Only checks when `navigator.onLine` is true (saves battery when offline)
- Uses cache-busting headers (`cache: no-store`, `cache-control: no-cache`)
- Calls `registration.update()` on successful fetch
- Catches and logs errors with `console.debug` to avoid noise

**Dutch Localization:**
- Offline ready notification:
  - Title: "Klaar voor offline gebruik"
  - Message: "Stadion werkt nu ook zonder internet"
  - Dismiss button: aria-label "Sluiten"
- Update available notification:
  - Title: "Update beschikbaar"
  - Message: "Een nieuwe versie van Stadion is beschikbaar"
  - Later button: "Later" (same in Dutch)
  - Reload button: "Nu herladen"

### Architecture Decisions

**1. One-Hour Interval**
Based on vite-plugin-pwa best practices and battery/network considerations. Balances:
- Catching updates during long sessions (e.g., 8-hour workday)
- Avoiding excessive network requests
- Minimizing battery drain on mobile devices

**2. Online-Only Checking**
The `navigator.onLine` check prevents:
- Failed fetch attempts when offline
- Unnecessary battery usage
- Console error noise

**3. Cache-Busting Headers**
Using both `cache: 'no-store'` and `cache-control: 'no-cache'` ensures:
- Fresh service worker script is fetched
- No false negatives from cached SW file
- Reliable update detection

**4. Full Dutch Localization**
Maintains consistency with:
- OfflineBanner (Dutch, from 108-02)
- Install prompts (Dutch, from 110-02)
- Rest of Stadion UI (Dutch)

## Files Modified

### src/components/ReloadPrompt.jsx
- **Task 1 (dfec048):** Added periodic SW update checking
  - Defined `intervalMS` constant
  - Enhanced `useRegisterSW` with `onRegisteredSW` callback
  - Implemented `setInterval` with online check and cache-busting fetch
  - Updated JSDoc comment to mention periodic checking

- **Task 2 (1bc2f61):** Localized all text to Dutch
  - Offline ready notification text
  - Update available notification text
  - Button labels and aria-labels

## Testing & Verification

### Build Verification
✅ `npm run build` succeeds
- Build completed in 3.02s
- PWA v1.2.0 generated with 80 precached entries
- Service worker files generated: `dist/sw.js`, `dist/workbox-c456f5df.js`

### Code Verification
✅ Periodic checking implemented:
- `intervalMS = 60 * 60 * 1000` constant defined
- `setInterval` with async update check
- `registration.update()` called on success

✅ Dutch localization verified:
- All notification text in Dutch
- Consistent with existing Dutch UI patterns
- "Later" button correctly kept as "Later" (same in Dutch)

### Functional Behavior
The periodic update check:
1. Fires every hour after service worker registration
2. Only runs when user is online
3. Fetches SW script with cache-busting headers
4. Updates registration if SW file changed (status 200)
5. Triggers existing `needRefresh` state when new version detected
6. Shows Dutch "Update beschikbaar" notification
7. User clicks "Nu herladen" to apply update

## Deviations from Plan

None - plan executed exactly as written.

## Performance Impact

**Positive:**
- Users notified of updates during long sessions (no page reload needed)
- Dutch text improves UX for Dutch-speaking users

**Negligible:**
- 1-hour interval minimizes network/battery impact
- Online-only check avoids wasteful offline attempts
- Update check is lightweight (single HEAD-like fetch)

## Integration Points

### Existing Systems
**ReloadPrompt (107-03):**
- Base component already existed
- Added periodic checking to existing update detection
- Maintained all existing functionality (offlineReady, needRefresh states)

**vite-plugin-pwa (107-01):**
- Uses existing `useRegisterSW` hook
- Enhanced with `onRegisteredSW` callback
- No config changes needed in vite.config.js

**OfflineBanner (108-02):**
- Already uses Dutch text ("Offline", "Online - data wordt gesynchroniseerd")
- ReloadPrompt now consistent with same localization approach

### Future Plans
**110-02 (Install Prompts):**
- Android/iOS install prompts also use Dutch
- Consistent notification styling and positioning
- All PWA-related UI now fully localized

## Next Phase Readiness

**Ready for:** Plan 110-04 (Production testing and Lighthouse audit)
- ReloadPrompt ready for testing in standalone mode
- Update notifications ready for long-session testing
- Dutch localization ready for user acceptance

**No blockers or concerns.**

## Technical Debt & Future Work

### Potential Enhancements
1. **Configurable interval:** Could make `intervalMS` configurable via settings (not needed now)
2. **Visibility API integration:** Pause checks when tab backgrounded (optimization for future)
3. **Update timing:** Could delay update check until user idle (not needed, current approach works)

### Monitoring Recommendations
1. Track update notification acceptance rate (analytics)
2. Monitor battery impact on mobile devices (user feedback)
3. Verify update detection works reliably in production

## Lessons Learned

### What Worked Well
1. **Existing component structure:** ReloadPrompt was well-designed for enhancement
2. **Clear patterns:** vite-plugin-pwa documentation provided exact pattern
3. **Atomic commits:** Separating periodic checking from localization kept changes clear

### What Could Improve
- None - straightforward implementation following established patterns

## Commits

| Task | Description | Commit | Files |
|------|-------------|--------|-------|
| 1 | Add periodic SW update checking | dfec048 | src/components/ReloadPrompt.jsx |
| 2 | Localize ReloadPrompt text to Dutch | 1bc2f61 | src/components/ReloadPrompt.jsx |

## Key Learnings

1. **vite-plugin-pwa patterns:** `onRegisteredSW` callback is the correct hook for periodic updates (not `onRegistered`)
2. **Cache busting:** Both `cache: 'no-store'` and `cache-control: 'no-cache'` headers needed for reliable SW update detection
3. **Online check:** `navigator.onLine` is essential for battery/network efficiency
4. **Localization consistency:** Small details like aria-labels matter for full accessibility

## References

- Pattern from: `.planning/phases/110-install-polish/110-RESEARCH.md` (Pattern 4: Periodic Service Worker Update Checking)
- Source: [Vite PWA - Periodic SW Updates](https://vite-pwa-org.netlify.app/guide/periodic-sw-updates)
- Related: Phase 107 (PWA foundation), Phase 108 (offline handling)
