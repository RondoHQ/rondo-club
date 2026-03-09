---
phase: quick-121
plan: 01
subsystem: frontend-routing
tags: [url-routing, tabs, settings, clothing]
key-files:
  created: []
  modified:
    - src/pages/Finance/FinanceSettings.jsx
    - src/pages/Settings/Settings.jsx
    - src/pages/Clothing/ClothingPage.jsx
    - src/router.jsx
decisions:
  - FinanceSettings uses controlled/uncontrolled pattern to support both URL-driven (Settings page) and standalone (payment-providers, wallets) usage
  - ClothingPage tab validation placed after all hooks to satisfy Rules of Hooks
  - CLOTHING_TABS extracted to module-level constant for DRY tab rendering
metrics:
  duration: 3m52s
  completed: 2026-03-09
---

# Quick Task 121: Add Sub-Routes for Settings Tabs Summary

URL-driven tab navigation for FinanceSettings (via Settings subtab param) and ClothingPage (via route param), enabling bookmarkable URLs and browser back/forward for all tabbed settings pages.

## Tasks Completed

| Task | Name | Commit | Files |
|------|------|--------|-------|
| 1 | Wire FinanceSettings tabs to URL subtab parameter | d8e219f5 | FinanceSettings.jsx, Settings.jsx |
| 2 | Add URL-based tabs to ClothingPage | 9e805101 | ClothingPage.jsx, router.jsx |

## Changes Made

### Task 1: FinanceSettings URL Routing

- Added `activeTab` (prop) and `onTabChange` callback props to FinanceSettings
- Implemented controlled/uncontrolled dual-mode: when `propActiveTab` and `onTabChange` are provided (from Settings.jsx), tabs are URL-driven; when omitted (payment-providers, wallets subtabs), internal state is used
- Updated Settings.jsx to default financieel subtab to `organization` and pass activeSubtab + navigate callback to FinanceSettings
- Removed the useEffect that validated activeTab (now handled by controlled/uncontrolled logic)

### Task 2: ClothingPage URL Routing

- Added `/kleding/:tab` route to router.jsx (before the existing `/kleding` route)
- Replaced `useState('overview')` with `useParams()` + `useNavigate()` for URL-driven tabs
- Extracted `CLOTHING_TABS` array to module-level constant
- Added `Navigate` redirect for unknown tabs to `/kleding/overview`
- Tab validation placed after all hooks to comply with React Rules of Hooks

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] React Rules of Hooks violation in ClothingPage**
- **Found during:** Task 2
- **Issue:** Early return with `<Navigate>` before useState/useEffect hooks violated React Rules of Hooks, causing lint errors
- **Fix:** Moved the unknown-tab validation and early return to after all hook calls
- **Files modified:** src/pages/Clothing/ClothingPage.jsx
- **Commit:** 9e805101

## Verification

- `npm run build` passes
- `npm run lint` passes with 0 warnings
- Deployed to production: https://rondo.svawc.nl/
