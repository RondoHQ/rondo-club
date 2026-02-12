---
phase: 61-add-vrijwilliger-badge-to-volunteers-sim
plan: 01
subsystem: person-detail
tags: [ui, volunteers, badges]
dependency_graph:
  requires: [class-volunteer-status]
  provides: [vrijwilliger-badge]
  affects: [person-detail-ui]
tech_stack:
  added: []
  patterns: [badge-pattern, field-exposure]
key_files:
  created: []
  modified:
    - includes/class-rest-base.php
    - src/pages/People/PersonDetail.jsx
decisions: []
metrics:
  duration: 100
  completed_at: 2026-02-12T15:17:54Z
---

# Quick Task 61: Add Vrijwilliger Badge to Volunteers

**Volunteers now display an electric-cyan "Vrijwilliger" badge on their PersonDetail page, making active volunteer status visually prominent.**

## Tasks Completed

| Task | Name | Commit | Files |
|------|------|--------|-------|
| 1 | Add huidig_vrijwilliger to REST API person summary | 98ab0b46 | includes/class-rest-base.php |
| 2 | Add Vrijwilliger badge to PersonDetail | 4f496a3c | src/pages/People/PersonDetail.jsx |
| 3 | Build production assets | (dist/ gitignored) | dist/ |

## Implementation Details

**REST API Enhancement:**
- Added `huidig_vrijwilliger` field to `format_person_summary()` method in `class-rest-base.php`
- Follows same boolean pattern as `former_member` field
- Field uses ACF key `huidig-vrijwilliger` (with hyphens) but REST API key uses underscores for JavaScript compatibility

**Frontend Badge:**
- Added Vrijwilliger badge to PersonDetail.jsx after existing Oud-lid badge
- Uses electric-cyan brand color (`bg-electric-cyan/10 text-electric-cyan`) to distinguish from gray former member badge
- Dark mode support with `dark:bg-electric-cyan/20 dark:text-electric-cyan-light`
- Badge only renders when `acf.huidig_vrijwilliger` is true

**Build:**
- Production assets rebuilt with Vite (16.87s build time)
- 89 precache entries (3089.28 KiB)
- dist/ folder is gitignored per project conventions

## Deviations from Plan

None - plan executed exactly as written.

## Technical Notes

- The `huidig-vrijwilliger` field is already calculated server-side by `class-volunteer-status.php`
- Field is automatically updated when person's commissie roles change
- Badge pattern matches existing Oud-lid implementation for consistency
- Electric-cyan brand color creates visual hierarchy: gray for former status, brand color for active volunteer status

## Self-Check: PASSED

**Created files:** None (as expected)

**Modified files:**
- ✓ includes/class-rest-base.php exists and contains huidig_vrijwilliger
- ✓ src/pages/People/PersonDetail.jsx exists and contains Vrijwilliger badge

**Commits:**
- ✓ 98ab0b46 (REST API update)
- ✓ 4f496a3c (Vrijwilliger badge)

All verification checks passed.
