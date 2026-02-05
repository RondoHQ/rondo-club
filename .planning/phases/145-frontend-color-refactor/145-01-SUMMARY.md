---
phase: 145-frontend-color-refactor
plan: 01
subsystem: ui
tags: [tailwind, css, react, theme, color-system]
requires:
  - phase: 144-backend-configuration-system
    provides: "ClubConfig REST API with accent_color"
provides:
  - "Renamed color system (awc→club) across frontend"
  - "Dynamic club color support via window.stadionConfig"
  - "Auto-migration from awc to club in localStorage"
affects: [145-02, 146]
tech-stack:
  added: []
  patterns: ["Dynamic CSS variable injection for custom colors"]
key-files:
  created: []
  modified:
    - tailwind.config.js
    - src/index.css
    - src/hooks/useTheme.js
    - src/pages/Settings/Settings.jsx
    - functions.php
key-decisions: []
patterns-established:
  - "Club color dynamic hex from stadionConfig"
  - "CSS variable injection for runtime color changes"
  - "Auto-migration pattern for breaking localStorage changes"
duration: 3min
completed: 2026-02-05
---

# Phase 145 Plan 01: AWC-to-Club Rename Summary

**One-liner:** Renamed AWC color references to "club" throughout frontend with dynamic hex support from window.stadionConfig

## What Was Delivered

### Core Rename (Task 1)
- **Tailwind config:** Renamed `awc` palette to `club` in tailwind.config.js
- **CSS custom properties:** Updated comments (AWC → Club) in src/index.css
- **PHP comments:** Updated theme-color and login page comments in functions.php
- **Settings UI:** Updated accent picker to use `bg-club-600` and `ring-club-600` classes
- **Dynamic swatch:** Club color swatch uses inline style with dynamic hex from stadionConfig
- **User-facing labels:** All "AWC" strings changed to "Club" in Settings page

### Dynamic Color System (Task 2)
- **Dynamic hex lookup:** `getClubHex()` reads from `window.stadionConfig.accentColor` (fallback: #006935)
- **Dark mode support:** `getClubHexDark()` lightens custom colors by 40% for visibility
- **Color utilities:** `lightenHex()` and `darkenHex()` functions for generating color scales
- **CSS variable injection:** When club color differs from default, injects full 50-900 scale as inline styles on `:root`
- **Favicon updates:** Dynamic favicon uses club hex when club accent selected
- **PWA theme-color:** Meta tags update to match club color for mobile browser chrome
- **Auto-migration:** Existing users with 'awc' in localStorage automatically migrated to 'club' on load

## Technical Implementation

### Pattern: Runtime CSS Variable Injection

When the club accent color is selected AND differs from the default green (#006935), useTheme.js injects a full color scale (50-900) as inline CSS custom properties on the `:root` element. This enables admin-configured club colors to work without rebuilding CSS.

**Light mode injection:**
- 50-500: Progressively lighter from base hex
- 600: Base hex (primary accent color)
- 700-900: Progressively darker

**Dark mode injection:**
- 50-200: Darker variants for backgrounds
- 300: Base hex
- 400-900: Progressively lighter for contrast

### Pattern: Auto-Migration

The `loadPreferences()` function in useTheme.js checks for the old 'awc' value and automatically replaces it with 'club' before returning preferences. This ensures zero user disruption from the breaking change.

```javascript
// Auto-migrate 'awc' to 'club' for existing users
if (parsed.accentColor === 'awc') {
  parsed.accentColor = 'club';
}
```

### Files Modified

| File | Changes | Lines |
|------|---------|-------|
| tailwind.config.js | Renamed awc palette key to club | 2 |
| src/index.css | Updated CSS comments (AWC → Club) | 2 |
| functions.php | Updated PHP comments for theme-color and login | 2 |
| src/pages/Settings/Settings.jsx | Updated accent picker classes, labels, and dynamic swatch | 15 |
| src/hooks/useTheme.js | Dynamic club color, auto-migration, CSS injection | 128 |

## Test Results

### Verification Passed
- ✓ Zero AWC/awc references in src/ (except svawc.nl URL in PersonDetail.jsx, addressed in Phase 146)
- ✓ Zero AWC Tailwind classes anywhere in src/
- ✓ No 'awc' string literals in JS files (except auto-migration code)
- ✓ `npm run build` succeeded without errors
- ✓ Club palette exists in Tailwind config
- ✓ Settings accent picker shows "Club" as first option
- ✓ Dynamic club color swatch uses stadionConfig.accentColor

### Pre-existing Lint Warnings
Lint produced 152 errors/warnings (unchanged from before). These are pre-existing issues not introduced by this work and tracked separately.

## Deviations from Plan

None - plan executed exactly as written.

## Decisions Made

No new decisions required. This plan implements the existing **RENAME-AWC-TO-CLUB** decision from Phase 144.

## Next Phase Readiness

### Blockers
None.

### Concerns
None.

### Dependencies Met
This plan depended on Phase 144 providing the ClubConfig REST API with `accent_color` field. That API exists and returns the color in `window.stadionConfig.accentColor`.

### Artifacts Ready for Next Phase
- **145-02 (Login Page Club Color):** Can now reference club color system and stadionConfig pattern
- **Phase 146 (FreeScout Integration):** Club color system ready for FreeScout UI elements

## Performance Impact

### Build Time
No change - club color palette same size as AWC palette.

### Runtime Performance
Minimal impact:
- CSS variable injection only occurs when club color differs from default
- Injection happens once on mount and when accent changes
- ~10 style.setProperty() calls per color scheme change

### Bundle Size
No change to bundle size. Dynamic color logic added ~120 lines but removed no code.

## What's Next

**Immediate follow-up (145-02):**
Update login page to use club color instead of hardcoded AWC green gradients (PHP-side).

**Future phases:**
- Phase 146: FreeScout integration will use club color for consistency
- Any new UI components should use `bg-accent-*` CSS variable classes, not `bg-club-*` Tailwind classes

## Lessons Learned

### What Went Well
- Auto-migration pattern cleanly handles breaking localStorage changes
- Dynamic CSS variable injection enables runtime color changes without CSS rebuilds
- Inline style for club swatch shows actual configured color instead of static fallback

### What Could Be Improved
- Consider extracting color utilities (lightenHex, darkenHex) to separate utils file for reuse
- Could add visual feedback in Settings when club color differs from default green

## Context for Future Sessions

**Key concept:** The club color system has TWO layers:
1. **Static fallback:** Tailwind `club` palette and CSS `:root` variables provide green defaults
2. **Dynamic override:** When `window.stadionConfig.accentColor` differs from #006935, useTheme.js injects CSS variable overrides

**Important files:**
- **tailwind.config.js:** Defines `club` palette (static fallback)
- **src/index.css:** Defines `:root` CSS variables (static defaults)
- **src/hooks/useTheme.js:** Manages dynamic color injection and theme state
- **src/pages/Settings/Settings.jsx:** Accent picker UI with dynamic club swatch

**Search patterns:**
- To find club color usage: `grep -r "bg-club-\|text-club-\|ring-club-"`
- To find accent variable usage: `grep -r "bg-accent-\|text-accent-\|ring-accent-"`
- Most UI should use `bg-accent-*` (CSS variables), not `bg-club-*` (Tailwind classes)

**Migration complete:** All users with 'awc' saved in localStorage will be automatically migrated to 'club' on next page load.
