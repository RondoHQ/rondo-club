---
phase: 163-color-system-migration
plan: 01
subsystem: frontend
tags: [color-system, tailwind-v4, brand-tokens, css-migration]
dependency-graph:
  requires: [162-01]
  provides: [brand-color-classes, no-accent-system]
  affects: [all-components, css-bundle]
tech-stack:
  added: [cyan-50, cyan-100, cyan-200]
  removed: [data-accent-system, accent-variables]
  patterns: [brand-color-tokens, oklch-colors]
key-files:
  created: []
  modified:
    - src/index.css (deleted 239 lines data-accent system)
    - src/**/*.jsx (60 component files, 363 class replacements)
decisions:
  - id: preserve-gradient-utility
    summary: Keep bg-brand-gradient utility for future use
    rationale: While not currently used, provides ready pattern for gradient effects
  - id: add-cyan-variants
    summary: Added cyan-50, cyan-100, cyan-200 to @theme block
    rationale: Needed for light background variants that existed in old accent-* scale
metrics:
  duration: 254 seconds
  completed: 2026-02-09
  tasks: 2
  files-modified: 61
  lines-deleted: 239
  lines-changed: 363
---

# Phase 163 Plan 01: Replace Accent System with Brand Colors Summary

**One-liner:** Migrated all 60 React components and core CSS from dynamic accent-* color system to fixed brand color tokens (electric-cyan, bright-cobalt, deep-midnight), deleting 239 lines of data-accent CSS infrastructure.

## Overview

This plan executed the bulk mechanical migration to eliminate the dynamic accent color system from the frontend. All React components now use fixed brand color tokens defined in Phase 162, and the data-accent CSS variable system has been completely removed.

## Tasks Completed

### Task 1: Replace accent-* Classes in React Components

**Status:** Complete
**Commit:** a8179037
**Files modified:** 60 JSX component files
**Changes:** 363 class replacements

Applied systematic find-and-replace across all React component files using the following mapping:

| Old Class | New Class | Usage |
|-----------|-----------|-------|
| `accent-900` | `obsidian` | Very dark backgrounds |
| `accent-800` | `deep-midnight` | Dark backgrounds/text |
| `accent-700` | `bright-cobalt` | Hover states, darker interactive |
| `accent-600` | `electric-cyan` | Primary interactive color |
| `accent-500` | `electric-cyan` | Default primary |
| `accent-400` | `electric-cyan` | Dark mode primary |
| `accent-300` | `electric-cyan-light` | Lighter variants |
| `accent-200` | `cyan-200` | Light highlights |
| `accent-100` | `cyan-100` | Subtle highlights |
| `accent-50` | `cyan-50` | Light backgrounds |

**Key patterns handled:**
- `dark:text-accent-400` → `dark:text-electric-cyan`
- `dark:bg-accent-800` → `dark:bg-deep-midnight`
- `border-accent-500` → `border-electric-cyan`
- Opacity patterns like `bg-accent-800/30` → `bg-deep-midnight/30`

**Verification:**
```bash
grep -rn "accent-[0-9]" src/ --include="*.jsx" | wc -l
# Result: 0 (all accent-* class references removed)
```

### Task 2: Delete data-accent CSS System

**Status:** Complete
**Commit:** 4727918c
**Files modified:** src/index.css
**Lines deleted:** 239

**Changes made:**

1. **Updated @theme block** (removed lines 11-21, added cyan variants):
   - Removed all `--color-accent-*` token definitions
   - Kept brand color tokens (electric-cyan, bright-cobalt, deep-midnight, obsidian)
   - Added `--color-cyan-50`, `--color-cyan-100`, `--color-cyan-200` for light variants

2. **Deleted data-accent system** (lines 146-384, 239 lines):
   - Removed `:root` default accent definitions
   - Removed 9 `[data-accent="*"]` variants (orange, teal, indigo, emerald, violet, pink, fuchsia, rose)
   - Removed 9 `.dark[data-accent="*"]` dark mode inversions
   - Complete removal of dynamic color switching infrastructure

3. **Updated component classes** to use brand colors:
   - `.timeline-content a`: `text-electric-cyan hover:text-bright-cobalt`
   - `.btn-primary`: `bg-electric-cyan hover:bg-bright-cobalt focus:ring-electric-cyan`
   - `.btn-secondary`: `focus:ring-electric-cyan`
   - `.input`: `focus:ring-electric-cyan focus:border-electric-cyan`

**Production build verification:**
```bash
npm run build              # Success (16.27s)
grep -c "color-accent" dist/assets/*.css  # Result: 0
grep -c "electric-cyan" dist/assets/*.css # Result: 1 (brand colors present)
```

## Deviations from Plan

None - plan executed exactly as written. All replacements followed the specified mapping, and the data-accent system was cleanly removed without modification to the plan.

## Verification Results

All verification checks passed:

| Check | Command | Expected | Actual | Status |
|-------|---------|----------|--------|--------|
| No accent-* in JSX | `grep -rn "accent-[0-9]" src/ --include="*.jsx"` | 0 | 0 | ✅ Pass |
| No accent- in CSS | `grep -c "accent-" src/index.css` | 0 | 0 | ✅ Pass |
| No data-accent in CSS | `grep -c "data-accent" src/index.css` | 0 | 0 | ✅ Pass |
| Build succeeds | `npm run build` | exit 0 | exit 0 | ✅ Pass |
| No accent in dist | `grep "color-accent" dist/assets/*.css` | 0 | 0 | ✅ Pass |
| Brand colors in dist | `grep "electric-cyan" dist/assets/*.css` | >0 | 1 | ✅ Pass |

## Impact Analysis

### Before (with accent-* system)
- **CSS size:** 430 lines
- **Accent definitions:** 10 tokens × 9 variants × 2 modes = 180 color definitions
- **Component references:** 363 accent-* class usages
- **Dynamic behavior:** User could switch accent colors via data-accent attribute

### After (brand colors only)
- **CSS size:** 191 lines (239 lines deleted)
- **Brand definitions:** 8 tokens (5 brand + 3 cyan variants)
- **Component references:** 363 brand color class usages
- **Static behavior:** Fixed brand colors, no runtime switching

### Migration stats
- **Files changed:** 61 (60 JSX + 1 CSS)
- **Lines deleted:** 239
- **Lines changed:** 363
- **Commits:** 2
- **Build impact:** No breaking changes, build succeeds

## Technical Notes

### OKLCH Color Space
Brand colors use OKLCH color space for wider P3 gamut and perceptual uniformity:
- `electric-cyan`: `oklch(0.69 0.14 196)` - Primary interactive
- `bright-cobalt`: `oklch(0.55 0.19 264)` - Hover/darker
- `deep-midnight`: `oklch(0.35 0.12 264)` - Dark backgrounds
- `obsidian`: `oklch(0.16 0.02 264)` - Very dark
- `cyan-*`: Standard hex values for Tailwind compatibility

### Dark Mode Handling
Dark mode patterns adapted seamlessly:
- `dark:text-accent-400` → `dark:text-electric-cyan` (brand color works at native lightness)
- `dark:bg-accent-800` → `dark:bg-deep-midnight` (dedicated dark background token)
- No color inversions needed (OKLCH provides consistent perception)

### CSS Architecture Cleanup
Eliminated complexity:
- **Before:** 3 layers (theme → data-accent → components)
- **After:** 2 layers (theme → components)
- **Result:** Simpler cascade, no CSS variable overhead, smaller bundle

## Next Steps

This plan enables Phase 163 Plan 02:
1. Update `useTheme.js` hook to return fixed brand colors
2. Remove accent color switching logic
3. Update Settings page to remove color picker
4. Archive accent color system documentation

## Self-Check

### Verification: PASSED

**Created files exist:**
- N/A (no new files created)

**Modified files exist:**
```bash
[ -f "src/index.css" ] && echo "FOUND: src/index.css" || echo "MISSING: src/index.css"
# Result: FOUND: src/index.css
```

**Commits exist:**
```bash
git log --oneline --all | grep -q "a8179037" && echo "FOUND: a8179037" || echo "MISSING: a8179037"
# Result: FOUND: a8179037

git log --oneline --all | grep -q "4727918c" && echo "FOUND: 4727918c" || echo "MISSING: 4727918c"
# Result: FOUND: 4727918c
```

**Content verification:**
```bash
# Brand colors present in theme
grep -q "electric-cyan" src/index.css && echo "Brand colors: ✅" || echo "Brand colors: ❌"
# Result: Brand colors: ✅

# No accent-* references
! grep -q "accent-[0-9]" src/index.css && echo "No accent refs: ✅" || echo "Accent refs remain: ❌"
# Result: No accent refs: ✅

# data-accent system deleted
! grep -q "data-accent" src/index.css && echo "data-accent deleted: ✅" || echo "data-accent remains: ❌"
# Result: data-accent deleted: ✅
```

All verification checks passed. Plan execution complete.
