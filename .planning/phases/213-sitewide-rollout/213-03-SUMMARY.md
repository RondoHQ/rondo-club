---
phase: 213-sitewide-rollout
plan: 03
subsystem: frontend/buttons
tags: [button-system, ui, css, datatables]
dependency_graph:
  requires: [212-01]
  provides: [ROLL-06, ROLL-07]
  affects: [src/components/DataTable, src/pages/Feedback, src/pages/VOG, src/pages/Contributie, src/pages/Clothing, src/pages/Todos]
tech_stack:
  added: []
  patterns: [btn-tertiary for utility/filter/export actions, btn-primary for create/send CTAs]
key_files:
  created: []
  modified:
    - src/components/DataTable/DataTableToolbar.jsx
    - src/pages/Feedback/FeedbackDetail.jsx
    - src/pages/VOG/VOGList.jsx
    - src/pages/VOG/VOGUpcoming.jsx
    - src/pages/Contributie/ContributieList.jsx
    - src/pages/Contributie/NogTeFactureren.jsx
    - src/pages/Contributie/SeasonSelector.jsx
    - src/pages/Clothing/ClothingPage.jsx
    - src/components/CustomFieldsSection.jsx
decisions:
  - Retry/error-state buttons (Opnieuw proberen) are utility actions and use btn-tertiary
  - SeasonSelector dropdown hover overrides (hover:translate-y-0 hover:shadow-none) removed since btn-tertiary already has no lift
  - ColumnSettingsPanel Sluiten stays btn-secondary (dismiss action, not utility)
  - ClothingPage Verwijder (delete in items table) uses btn-tertiary (inline row utility, not a danger action)
metrics:
  duration: 3 minutes
  completed_date: "2026-03-11"
  tasks: 2
  files_modified: 9
requirements-completed: [ROLL-06, ROLL-07]
---

# Phase 213 Plan 03: Feedback, VOG, Contributie, Clothing, Todos & DataTable Toolbar Button Tiers Summary

Applied btn-tertiary to all utility/export/filter buttons on Feedback, VOG, Contributie, Clothing pages and the shared DataTable toolbar component.

## What Was Built

All 9 remaining files now use the correct four-tier button hierarchy:
- `btn-primary` — create/send/submit CTAs
- `btn-tertiary` — export, filter, refresh, clear, inline row utilities

## Tasks Completed

| Task | Name | Commit | Files |
|------|------|--------|-------|
| 1 | Apply tier hierarchy to Feedback, VOG, Contributie, Clothing pages | a76df687 | 8 files |
| 2 | Apply tier hierarchy to DataTable toolbar | 3a581263 | 1 file |

## Changes By File

**DataTableToolbar.jsx:** Filter toggle button and column settings cog both changed from btn-secondary to btn-tertiary. Active state conditional styling preserved on filter button.

**FeedbackDetail.jsx:** Edit button (inline utility on header) changed to btn-tertiary. Reply/submit (Send) stays btn-primary.

**VOGList.jsx:** Download CSV button and Opnieuw proberen error-state button changed to btn-tertiary.

**VOGUpcoming.jsx:** Opnieuw proberen error-state button changed to btn-tertiary.

**ContributieList.jsx:** Export CSV button, Opnieuw proberen, and Filters wissen (empty state) changed to btn-tertiary.

**NogTeFactureren.jsx:** Per-row Maak factuur button (text-xs utility) and Opnieuw proberen changed to btn-tertiary. Bulk Maak facturen CTA stays btn-primary.

**SeasonSelector.jsx:** select element changed from btn-secondary to btn-tertiary; removed redundant hover:translate-y-0 hover:shadow-none overrides (btn-tertiary has no lift).

**ClothingPage.jsx:** Nieuw item uitgeven (toggle), Retourneren, Verwijder, and Exporteer CSV changed to btn-tertiary. Uitgeven and Item opslaan submit buttons stay btn-primary.

**CustomFieldsSection.jsx:** Bewerken edit button changed to btn-tertiary.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Error-state retry buttons changed to btn-tertiary**
- **Found during:** Task 1
- **Issue:** VOGList, VOGUpcoming, ContributieList, NogTeFactureren all had "Opnieuw proberen" buttons as btn-secondary. These are utility retry actions.
- **Fix:** Changed to btn-tertiary (utility tier) for consistency
- **Files modified:** VOGList.jsx, VOGUpcoming.jsx, ContributieList.jsx, NogTeFactureren.jsx
- **Commit:** a76df687

No other deviations — plan executed as written.

## Self-Check

**Files exist:**
- [x] src/components/DataTable/DataTableToolbar.jsx
- [x] src/pages/Feedback/FeedbackDetail.jsx
- [x] src/pages/VOG/VOGList.jsx
- [x] src/pages/VOG/VOGUpcoming.jsx
- [x] src/pages/Contributie/ContributieList.jsx
- [x] src/pages/Contributie/NogTeFactureren.jsx
- [x] src/pages/Contributie/SeasonSelector.jsx
- [x] src/pages/Clothing/ClothingPage.jsx
- [x] src/components/CustomFieldsSection.jsx

**Commits exist:**
- [x] a76df687
- [x] 3a581263

**Lint and build:** PASS

## Self-Check: PASSED
