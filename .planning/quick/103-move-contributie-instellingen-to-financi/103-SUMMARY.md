---
phase: quick-103
plan: 01
subsystem: ui
tags: [react, finance, settings, contributie, tabs]

requires:
  - phase: quick-85
    provides: FeeCategorySettings component used in Contributie page
  - phase: quick-95
    provides: email templates for membership and installment invoices in FinanceSettings

provides:
  - FinanceSettings with Contributie tab rendering FeeCategorySettings
  - FinanceSettings E-mail tab with 4 pill sub-tabs (Boetes, Contributie, Termijnen, Herinneringen)
  - Contributie page without Instellingen tab

affects: [FinanceSettings, Contributie, settings-consolidation]

tech-stack:
  added: []
  patterns:
    - "Pill sub-tabs pattern for secondary navigation within a tab"
    - "Self-contained component tab: hide form save button when active tab manages its own saves"

key-files:
  created: []
  modified:
    - src/pages/Finance/FinanceSettings.jsx
    - src/pages/Contributie/Contributie.jsx

key-decisions:
  - "Hide main save button when Contributie tab is active — FeeCategorySettings manages its own save"
  - "EMAIL_SUB_TABS defined outside component (constant) — not inside, consistent with TABS pattern"
  - "emailSubTab defaults to 'boetes' — first tab shown by default"

duration: 8min
completed: 2026-02-19
---

# Quick Task 103: Move Contributie Instellingen to Financien Summary

**Consolidated all financial settings: FeeCategorySettings moved to Financien -> Instellingen Contributie tab; E-mail tab split into 4 pill sub-tabs (Boetes, Contributie, Termijnen, Herinneringen)**

## Performance

- **Duration:** ~8 min
- **Completed:** 2026-02-19
- **Tasks:** 3
- **Files modified:** 2

## Accomplishments
- FeeCategorySettings is now accessible via Financien -> Instellingen -> Contributie tab (6th tab)
- E-mail tab replaced 4 stacked cards with pill sub-tab navigation showing one template at a time
- Contributie page simplified: Instellingen tab removed, unused isAdmin variable cleaned up
- Main form save button hidden on Contributie tab to avoid confusion with FeeCategorySettings own save

## Task Commits

1. **Tasks 1+2: Add Contributie tab and E-mail sub-tabs to FinanceSettings** - `3d3c2d9b` (feat)
2. **Task 3: Remove Instellingen tab from Contributie page** - `1f0dc272` (feat)

## Files Created/Modified
- `src/pages/Finance/FinanceSettings.jsx` - Added FeeCategorySettings import, Contributie tab, EMAIL_SUB_TABS constant, emailSubTab state, pill sub-tab navigation for E-mail tab, hides save button on contributie tab
- `src/pages/Contributie/Contributie.jsx` - Removed FeeCategorySettings import, Instellingen tab from TABS, adminOnly redirect guard, isAdmin variable, adminOnly filter

## Decisions Made
- Hide the main form save button when the Contributie tab is active, since FeeCategorySettings renders its own save button and submits independently from the finance settings form
- EMAIL_SUB_TABS constant placed outside the component, consistent with the TABS constant pattern already established in the file
- emailSubTab state defaults to 'boetes' (first tab) — most common template admins edit

## Deviations from Plan

None — plan executed exactly as written, with one additional quality improvement: hiding the main save button on the Contributie tab (not explicitly specified in plan, but necessary to avoid a confusing double-save UI where the form button would submit unchanged settings while FeeCategorySettings already has its own save).

## Issues Encountered
None.

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- All financial settings consolidated in Financien -> Instellingen
- Contributie page is now simpler with 2-3 tabs only
- Ready for further finance/contributie work

---
*Phase: quick-103*
*Completed: 2026-02-19*
