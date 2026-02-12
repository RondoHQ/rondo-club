---
phase: quick-62
plan: 01
subsystem: ui-people
tags: [ui, badges, volunteers, design]

dependency_graph:
  requires: []
  provides:
    - prominent-vrijwilliger-badge
  affects:
    - PersonDetail

tech_stack:
  added: []
  patterns:
    - solid-brand-color-badges

key_files:
  created: []
  modified:
    - src/pages/People/PersonDetail.jsx

decisions: []

metrics:
  duration_seconds: 76
  task_count: 1
  commit_count: 1
  completed_at: "2026-02-12T15:36:44Z"
---

# Quick Task 62: Make Vrijwilliger Badge More Prominent

**One-liner:** Vrijwilliger badge now uses solid electric-cyan background (#0891b2) with white text for immediate visual prominence.

## What Changed

Changed the Vrijwilliger (volunteer) badge styling on PersonDetail from subtle translucent background (`bg-electric-cyan/10`) to solid brand-colored background (`bg-electric-cyan`) with white text.

**Visual impact:**
- **Before:** Light cyan tint, cyan text (subtle, easily missed)
- **After:** Solid cyan badge, white text (eye-catching, immediately noticeable)

The badge now provides strong visual distinction for volunteers while the Oud-lid (former member) badge remains subdued in gray.

## Implementation Details

### Badge Styling Update

**File:** `src/pages/People/PersonDetail.jsx` (line 1043)

**Old styling:**
```jsx
bg-electric-cyan/10 text-electric-cyan dark:bg-electric-cyan/20 dark:text-electric-cyan-light
```

**New styling:**
```jsx
bg-electric-cyan text-white dark:bg-electric-cyan dark:text-white
```

**Design rationale:**
- Uses brand color (electric-cyan #0891b2) at full opacity for prominence
- White text provides strong contrast and readability
- Consistent appearance in both light and dark modes
- Maintains same spacing/sizing as other badges (px-1.5, py-0.5, rounded, text-xs)

### Badge Hierarchy

The badge system now has clear visual hierarchy:

1. **Vrijwilliger badge** (solid cyan) — most prominent
2. **Oud-lid badge** (gray) — subdued, informational

This aligns with the importance of quickly identifying active volunteers.

## Tasks Completed

| Task | Name | Type | Commit | Duration |
|------|------|------|--------|----------|
| 1 | Update Vrijwilliger badge styling | auto | cb0d13f1 | ~1 min |

## Deviations from Plan

None - plan executed exactly as written.

## Verification Results

**Automated checks:**
- Build completed successfully
- Badge styling confirmed in source code
- No linting errors introduced

**Deployment:**
- Deployed to production at https://rondo.svawc.nl/
- Caches cleared (WordPress object cache, SiteGround optimizer, dynamic cache)

**Visual verification:**
The badge now appears with a solid electric-cyan background and white text on PersonDetail pages for anyone with `huidig-vrijwilliger = true`.

## Self-Check

**Created files:** None (styling change only)

**Modified files:**
```bash
[✓] FOUND: src/pages/People/PersonDetail.jsx
```

**Commits:**
```bash
[✓] FOUND: cb0d13f1 - feat(quick-62): make Vrijwilliger badge more prominent
```

## Self-Check: PASSED

All claimed artifacts exist and changes are deployed to production.

## Impact Assessment

**User impact:**
- Volunteers are now immediately identifiable on PersonDetail pages
- Improved visual hierarchy helps users scan for volunteer status quickly
- No functional changes — purely visual enhancement

**Technical impact:**
- Single file change (PersonDetail.jsx)
- No breaking changes
- No database or API changes
- No dependencies affected

**Performance impact:**
- None (CSS-only change)

## Future Considerations

This badge styling could be extracted into a reusable badge component if additional badge types are needed in the future, but current implementation is sufficient for the two badge types in use (Vrijwilliger, Oud-lid).

---

**Completed by:** Claude Sonnet 4.5 (execute-phase)
**Execution time:** 76 seconds
**Status:** Complete and deployed to production
