---
phase: 213-sitewide-rollout
plan: 01
subsystem: ui
tags: [react, tailwind, buttons, finance]

# Dependency graph
requires:
  - phase: 212-button-css-system
    provides: btn-primary, btn-secondary, btn-tertiary, btn-danger CSS classes
provides:
  - Finance pages using correct button tier hierarchy with no inline color overrides
affects: [213-02, 213-03, 213-04]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "btn-primary for main CTA (Send invoice, Save, Connect)"
    - "btn-secondary for important secondary actions (Mark Paid, Cancel)"
    - "btn-tertiary for utility actions (PDF, payment link, resend, toggle)"
    - "btn-danger for destructive actions (Delete invoice, Remove line item, Reset test, Disconnect)"
    - "Remove redundant flex/items-center/px-4/py-2/rounded-lg from btn-* classes"
    - "Use border-b-2 border-current for spinner inside any btn variant"

key-files:
  created: []
  modified:
    - src/pages/Finance/FactuurDetail.jsx
    - src/pages/Finance/Facturen.jsx
    - src/pages/Finance/FinanceSettings.jsx
    - src/components/finance/InvoiceDraftForm.jsx
    - src/components/FinancesCard.jsx
    - src/components/DisciplineCaseTable.jsx

key-decisions:
  - "Spinner color uses border-b-2 border-current for all btn variants (not hardcoded color)"
  - "FinancesCard Maak factuur keeps size overrides (text-xs px-2.5 py-1.5 rounded-md) to stay compact in card context"
  - "FinanceSettings Disconnect Rabobank → btn-danger (destructive external service disconnect)"
  - "FinanceSettings Rekening toevoegen → btn-secondary (adds item to list, secondary importance)"

patterns-established:
  - "btn-* classes include inline-flex/items-center/justify-center/px-4/py-2/rounded-lg — only add gap-2 + size overrides"

requirements-completed: [ROLL-01, ROLL-03]

# Metrics
duration: 6min
completed: 2026-03-11
---

# Phase 213 Plan 01: Finance Button Tier Hierarchy Summary

**All Finance pages and components migrated to 4-tier btn-* system, eliminating inline green/red/orange/deep-midnight color overrides from ~20 buttons across 7 files**

## Performance

- **Duration:** 6 min
- **Started:** 2026-03-11T13:37:26Z
- **Completed:** 2026-03-11T13:43:48Z
- **Tasks:** 2
- **Files modified:** 7

## Accomplishments
- Replaced all inline color overrides (green/red/orange on buttons) in FactuurDetail.jsx with proper tier classes
- Applied btn-primary to all primary CTAs (Send invoice, Save settings, Connect Rabobank, Maak factuur)
- Applied btn-secondary to Mark Paid actions and Cancel buttons
- Applied btn-tertiary to all utility actions (PDF download/generate, payment links, resend, kortingen aanpassen)
- Applied btn-danger to all destructive actions (Verwijder factuur, Reset factuur test, Remove line item, Disconnect Rabobank, Ontkoppelen)
- Removed all redundant flex/items-center/justify-center/px-4/py-2/rounded-lg from btn-* class strings
- Replaced hardcoded spinner border colors with border-current for theme-agnostic behavior

## Task Commits

Each task was committed atomically:

1. **Task 1: Apply tier hierarchy to FactuurDetail.jsx** - `359b36d3` (feat)
2. **Task 2: Apply tier hierarchy to remaining Finance files** - `905a8ad0` (feat)

## Files Created/Modified
- `src/pages/Finance/FactuurDetail.jsx` - ~20 buttons reassigned: send=primary, mark-paid=secondary, PDF/payment-link/resend/utility=tertiary, delete/reset=danger
- `src/pages/Finance/Facturen.jsx` - Nieuwe factuur link: removed redundant btn prefix
- `src/pages/Finance/FinanceSettings.jsx` - Verstuur/Opslaan/Koppelen=primary; Certificaat/Hulp=tertiary; Rekening toevoegen=secondary; Ontkoppelen=danger
- `src/components/finance/InvoiceDraftForm.jsx` - submit=primary, cancel=secondary, add-line=tertiary, remove-line=danger; removed all btn prefixes
- `src/components/FinancesCard.jsx` - Maak factuur: removed inline electric-cyan styling → btn-primary with compact size overrides
- `src/components/DisciplineCaseTable.jsx` - Maak factuur(en): removed bg-deep-midnight/hover:bg-obsidian → btn-primary

## Decisions Made
- Spinner animation color changed from hardcoded `border-green-700`/`border-red-600`/`border-orange-600` to `border-current` — works correctly with any btn variant's text color
- FinancesCard "Maak factuur" keeps explicit size overrides (`text-xs px-2.5 py-1.5 rounded-md`) since it's a compact card action that must stay small

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 2 - Missing Critical] Fixed additional inline-styled buttons in FinanceSettings.jsx**
- **Found during:** Task 2 (remaining Finance files audit)
- **Issue:** Plan specified only the "Verstuur" test email button in FinanceSettings, but audit found 5 more inline-styled buttons (Koppelen met Rabobank, Rekening toevoegen, Certificaat tonen, Hulp bij instellen, Opslaan save button) with bg-electric-cyan or bg-gray-100 inline overrides
- **Fix:** Applied correct tier classes to all 6 buttons in FinanceSettings.jsx
- **Files modified:** src/pages/Finance/FinanceSettings.jsx
- **Verification:** grep finds no remaining inline-flex bg- overrides on buttons; lint and build pass
- **Committed in:** 905a8ad0 (Task 2 commit)

---

**Total deviations:** 1 auto-fixed (Rule 2 - missing critical)
**Impact on plan:** Necessary for complete correctness. All Finance buttons now use tier system, not just the ones called out in the plan.

## Issues Encountered
None - plan executed as expected.

## Self-Check: PASSED

All files exist, both commits verified in git history.

## Next Phase Readiness
- Finance module fully migrated to btn-* tier system
- Ready for remaining sitewide rollout phases (213-02 through 213-04)

---
*Phase: 213-sitewide-rollout*
*Completed: 2026-03-11*
