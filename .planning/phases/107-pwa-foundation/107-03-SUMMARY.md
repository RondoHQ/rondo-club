---
phase: 107
plan: 03
subsystem: pwa
tags: [pwa, service-worker, react, theme]
dependency-graph:
  requires: ["107-01", "107-02"]
  provides: ["ReloadPrompt component", "dynamic theme-color meta tags"]
  affects: ["107-04"]
tech-stack:
  added: []
  patterns: ["useRegisterSW hook pattern", "dynamic meta tag updates"]
key-files:
  created:
    - src/components/ReloadPrompt.jsx
  modified:
    - src/App.jsx
    - src/hooks/useTheme.js
decisions:
  - "ReloadPrompt coexists with UpdateBanner: UpdateBanner checks server version, ReloadPrompt handles SW lifecycle"
  - "ACCENT_HEX_DARK uses Tailwind -600 values for dark mode theme-color contrast"
metrics:
  duration: ~5m
  completed: 2026-01-28
---

# Phase 107 Plan 03: ReloadPrompt & Dynamic Theme Color Summary

**One-liner:** SW update prompt UI using vite-plugin-pwa useRegisterSW hook with dynamic theme-color meta tag updates matching user accent preference.

## What Was Built

### ReloadPrompt Component (src/components/ReloadPrompt.jsx)
- Created component using `useRegisterSW` hook from `virtual:pwa-register/react`
- Two notification states:
  - **Offline ready**: Shows when SW has cached app for offline use (dismissable)
  - **Update available**: Shows when new SW version detected (reload button)
- Styled to match existing app design with dark mode support
- Fixed bottom-right positioning (z-50) to avoid content interference

### App.jsx Integration
- Imported and rendered ReloadPrompt after UpdateBanner
- Both update mechanisms coexist for different scenarios:
  - UpdateBanner: Server-side version check via manifest.json build time
  - ReloadPrompt: Service worker lifecycle events

### Dynamic Theme Color (src/hooks/useTheme.js)
- Added `ACCENT_HEX_DARK` object with Tailwind -600 values for dark mode
- Created `updateThemeColorMeta()` function to update PWA theme-color meta tags
- Integrated into `applyTheme()` function alongside favicon updates
- Browser chrome color now dynamically matches user's accent preference

## Technical Decisions

1. **Dual update notifications**: Keep both UpdateBanner and ReloadPrompt since they serve different purposes (server version vs SW lifecycle)
2. **Dark mode hex values**: Used Tailwind -600 values for ACCENT_HEX_DARK to ensure sufficient contrast in dark browser chrome

## Task Commits

| Task | Description | Commit | Files |
|------|-------------|--------|-------|
| 1 | Create ReloadPrompt component | 41155f8 | src/components/ReloadPrompt.jsx |
| 2 | Integrate into App.jsx | c0aaba9 | src/App.jsx |
| 3 | Add dynamic theme-color | 6e2acfc | src/hooks/useTheme.js |

## Verification Results

- ReloadPrompt component created with useRegisterSW hook
- App.jsx imports and renders ReloadPrompt
- useTheme.js has updateThemeColorMeta function integrated into applyTheme
- Build succeeds (76 precache entries)
- Lint status unchanged (143 pre-existing errors in unrelated files)

## Deviations from Plan

None - plan executed exactly as written.

## Next Phase Readiness

Plan 107-04 can proceed. This plan provides:
- Working ReloadPrompt component for SW update notifications
- Dynamic theme-color meta tags that respond to accent color changes
- Complete integration into App.jsx

Remaining for PWA foundation:
- Plan 04: Testing & documentation
