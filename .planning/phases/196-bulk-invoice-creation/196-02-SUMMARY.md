---
phase: 196-bulk-invoice-creation
plan: 02
subsystem: payments
tags: [react, tanstack-query, bulk-invoices, billing-settings, membership-fees, ui]

# Dependency graph
requires:
  - phase: 196-bulk-invoice-creation
    plan: 01
    provides: BulkInvoiceCreator REST endpoints, billing-settings endpoint, billing_method in fee responses

provides:
  - Billing method toggle (Nikki/Rondo) and installment plan checkboxes in FeeCategorySettings
  - useBulkInvoiceJob hook with 2-second TanStack Query polling while job is running
  - useBillingSettings hook for per-season billing config
  - ContributieOverzicht bulk creation button + progress indicator for admins
  - ContributieList conditional Nikki column visibility based on billing_method
  - FinancesCard single-member membership invoice creation button
  - Version 28.0.0 deployed to production

affects:
  - Future phases touching Contributie or FinancesCard components

# Tech tracking
tech-stack:
  added: []
  patterns:
    - TanStack Query refetchInterval as function for conditional polling (running -> 2s, else false)
    - billingMethod derived from REST response data with ?? 'nikki' safe default
    - showNikkiColumns flag as single derived bool to guard all Nikki UI simultaneously

key-files:
  created: []
  modified:
    - src/api/client.js
    - src/hooks/useFees.js
    - src/pages/Settings/FeeCategorySettings.jsx
    - src/pages/Contributie/ContributieList.jsx
    - src/pages/Contributie/ContributieOverzicht.jsx
    - src/components/FinancesCard.jsx
    - style.css
    - package.json
    - CHANGELOG.md

key-decisions:
  - "showNikkiColumns = billingMethod === 'nikki' && !isForecast — single flag guards all Nikki UI including FeeRow prop, header, footer, filter buttons, summary line"
  - "FeeRow refactored from isForecast prop to showNikkiColumns prop — cleaner single responsibility"
  - "Billing settings section placed between season selector and family discount section in FeeCategorySettings"
  - "Maak factuur button uses inline Tailwind rather than btn-primary-sm (class does not exist in codebase)"
  - "billingMethod and hasMembershipInvoice computed from feeData and invoices — no extra API call needed"

patterns-established:
  - "Conditional column visibility: derive show{Feature}Columns flag at top of component, use consistently throughout"
  - "TanStack Query polling: refetchInterval as function checking query.state.data?.status"

# Metrics
duration: 6min
completed: 2026-02-19
---

# Phase 196 Plan 02: Bulk Invoice Frontend Summary

**React frontend for v28.0 membership fee invoicing: billing method toggle, installment plan checkboxes, conditional Nikki column visibility, bulk creation button with live progress polling, and single-member invoice button on person detail**

## Performance

- **Duration:** 6 min
- **Started:** 2026-02-19T10:42:51Z
- **Completed:** 2026-02-19T10:48:58Z
- **Tasks:** 3
- **Files modified:** 9

## Accomplishments

- Added five API methods to `prmApi` (startBulkInvoiceJob, getBulkInvoiceJobStatus, createMembershipInvoice, getBillingSettings, updateBillingSettings)
- Added `useBulkInvoiceJob` polling hook and `useBillingSettings` hook to `useFees.js`
- Built billing method and installment plan toggles in `FeeCategorySettings` with instant-save mutations
- Replaced all `!isForecast` Nikki guards in `ContributieList` with `showNikkiColumns` flag that also respects billing method
- Added "Maak facturen" button + progress indicator to `ContributieOverzicht` (admin + rondo billing only)
- Added "Maak factuur" single-member button to `FinancesCard` (rondo billing + no existing membership invoice)
- Bumped version to 28.0.0 and deployed to production at https://rondo.svawc.nl/

## Task Commits

Each task was committed atomically:

1. **Task 1: API methods, bulk job hooks, billing settings UI** - `d7a42429` (feat)
2. **Task 2: ContributieList Nikki column conditional visibility** - `8477de7f` (feat)
3. **Task 3: Bulk creation UI, single-member invoice button, version bump, deploy** - `dd13adfe` (feat)

## Files Created/Modified

- `src/api/client.js` — 5 new API methods: startBulkInvoiceJob, getBulkInvoiceJobStatus, createMembershipInvoice, getBillingSettings, updateBillingSettings
- `src/hooks/useFees.js` — Added bulkJob and billingSettings query keys; new useBulkInvoiceJob and useBillingSettings hooks
- `src/pages/Settings/FeeCategorySettings.jsx` — Facturatie-instellingen card with billing method radio buttons and installment plan checkboxes
- `src/pages/Contributie/ContributieList.jsx` — showNikkiColumns flag derived from billing_method; replaces all !isForecast Nikki guards
- `src/pages/Contributie/ContributieOverzicht.jsx` — Maak facturen button, bulk job progress card (running/done/error)
- `src/components/FinancesCard.jsx` — Maak factuur button for rondo billing mode when no membership invoice exists
- `style.css` — Version bumped to 28.0.0
- `package.json` — Version bumped to 28.0.0
- `CHANGELOG.md` — [28.0.0] entry added

## Decisions Made

- `showNikkiColumns = billingMethod === 'nikki' && !isForecast` — single derived boolean guards all Nikki UI elements (FeeRow, headers, footer, filter buttons, "Nog te ontvangen" line)
- `FeeRow` component refactored from `isForecast` prop to `showNikkiColumns` prop — cleaner, single responsibility
- `Maak factuur` button uses inline Tailwind classes (btn-primary-sm does not exist in the codebase)
- Billing settings section positioned between season selector and FamilyDiscountSection — logical grouping above fee categories

## Deviations from Plan

None — plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

None — no external service configuration required.

## Next Phase Readiness

- v28.0 Membership Fee Invoicing frontend is complete and deployed
- Admin can configure billing method and installment plans per season
- Admin can trigger bulk invoice creation and monitor progress
- Users with financieel access can create single-member invoices from person detail
- All REST endpoints from Phase 196-01 are fully wired up in the frontend

## Self-Check: PASSED

All 9 modified files confirmed present. All 3 task commits (d7a42429, 8477de7f, dd13adfe) confirmed in git log.

---
*Phase: 196-bulk-invoice-creation*
*Completed: 2026-02-19*
