---
phase: quick-93
plan: 01
subsystem: payments
tags: [invoices, mollie, installments, membership, rest-api, react]

requires:
  - phase: quick-85
    provides: contributie exclusion option on person detail
  - phase: 196
    provides: BulkInvoiceCreator, installment plan toggles, PublicPaymentPage

provides:
  - Per-invoice _disable_installments post meta flag
  - POST /rondo/v1/invoices/{id}/toggle-installments REST endpoint
  - disable_installments boolean in format_invoice_detail response
  - PublicPaymentPage enforces flag in render and POST validation
  - BulkInvoiceCreator defaults to disable_installments=1 for new membership invoices
  - FactuurDetail checkbox for membership draft invoices

affects:
  - invoices
  - betaling-public-page
  - bulk-invoice-creator

tech-stack:
  added: []
  patterns:
    - Per-invoice meta override pattern for payment options
    - Toggle via REST endpoint returning updated invoice detail

key-files:
  created: []
  modified:
    - includes/class-rest-invoices.php
    - includes/class-public-payment-page.php
    - includes/class-bulk-invoice-creator.php
    - src/api/client.js
    - src/hooks/useInvoices.js
    - src/pages/Finance/FactuurDetail.jsx

key-decisions:
  - "BulkInvoiceCreator sets _disable_installments=1 by default — Nikki-year invoices never need installments"
  - "toggle-installments endpoint uses boolean 'disabled' param and returns full invoice detail for immediate UI refresh"
  - "delete_post_meta used for disabled=false (absence = enabled) rather than storing '0' — cleaner meta state"
  - "Installment plans hidden in render_page and rejected in handle_plan_selection for defense-in-depth"

duration: 8min
completed: 2026-02-19
---

# Quick Task 93: Enable Sending and Disable Installments Summary

**Per-invoice installments toggle: REST endpoint, BulkInvoiceCreator default, public page enforcement, and FactuurDetail checkbox for membership draft invoices**

## Performance

- **Duration:** ~8 min
- **Started:** 2026-02-19T00:00:00Z
- **Completed:** 2026-02-19T00:08:00Z
- **Tasks:** 2
- **Files modified:** 6

## Accomplishments

- Added `POST /rondo/v1/invoices/{id}/toggle-installments` REST endpoint that sets/deletes `_disable_installments` post meta
- BulkInvoiceCreator now defaults all new membership invoices to `_disable_installments=1` (Nikki-year model)
- PublicPaymentPage hides quarterly_3 and monthly_8 plan options when flag is set (render) and rejects POST selection (handle)
- FactuurDetail shows "Termijnbetaling uitschakelen" checkbox for membership draft invoices, reflecting live `disable_installments` state

## Task Commits

1. **Task 1: PHP — REST endpoint, public page enforcement, bulk creator default** - `f984ffdb` (feat)
2. **Task 2: Frontend — useToggleInstallments hook + FactuurDetail toggle UI** - `bcf41e92` (feat)

## Files Created/Modified

- `includes/class-rest-invoices.php` — Added toggle-installments route, toggle_installments() method, disable_installments in format_invoice_detail
- `includes/class-public-payment-page.php` — Per-invoice override in render_page() and handle_plan_selection()
- `includes/class-bulk-invoice-creator.php` — Sets _disable_installments=1 after generating token for new membership invoices
- `src/api/client.js` — Added toggleInstallments API method
- `src/hooks/useInvoices.js` — Added useToggleInstallments mutation hook
- `src/pages/Finance/FactuurDetail.jsx` — Added toggle UI for membership draft invoices

## Decisions Made

- BulkInvoiceCreator sets `_disable_installments=1` by default — Nikki-year invoices are never paid via installments
- `toggle-installments` endpoint accepts boolean `disabled` param and returns full updated invoice detail for immediate UI refresh without a second fetch
- `delete_post_meta` is used when `disabled=false` (flag absence = installments enabled) rather than storing `'0'` — keeps meta state clean
- Installment plans are blocked at two levels in the public page: hidden in render (UI) and rejected in handle (POST validation) for defense-in-depth

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- Toggle is live on production at https://rondo.svawc.nl/
- Verification: open a draft membership invoice, check the "Termijnbetaling uitschakelen" checkbox is visible and pre-checked (for bulk-created ones), toggle it, and verify the betaling page hides installment options when flag is set

---
*Phase: quick-93*
*Completed: 2026-02-19*
