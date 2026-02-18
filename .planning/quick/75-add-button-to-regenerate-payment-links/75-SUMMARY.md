---
phase: 75-add-button-to-regenerate-payment-links
plan: 01
subsystem: finance/invoices
tags: [invoices, payment-links, mollie, rabobank, rest-api]
dependency_graph:
  requires: [class-rest-invoices.php, useInvoices.js, FactuurDetail.jsx]
  provides: [POST /rondo/v1/invoices/{id}/regenerate-payment-link, useRegeneratePaymentLink, Betaallink opnieuw aanmaken button]
  affects: [FactuurDetail, invoice payment link workflow]
tech_stack:
  added: []
  patterns: [mutation hook pattern matching useResendInvoice, provider routing matching send_invoice]
key_files:
  created: []
  modified:
    - includes/class-rest-invoices.php
    - src/api/client.js
    - src/hooks/useInvoices.js
    - src/pages/Finance/FactuurDetail.jsx
decisions:
  - Paid invoices blocked at API level (400 error) — no reason to regenerate a link on a paid invoice
  - Mollie regeneration clears _mollie_payment_id meta AND payment_link field to fully bypass idempotency
  - Rabobank regeneration checks is_connected() first and returns 400 if not, consistent with existing patterns
  - Button uses window.confirm() for user confirmation before replacing the existing link
  - regeneratePaymentLink.isPending included in combined isPending to disable all buttons during request
metrics:
  duration: 133s
  completed: 2026-02-18
  tasks: 2
  files: 4
---

# Quick Task 75: Add Button to Regenerate Payment Links Summary

**One-liner:** REST endpoint + React button to regenerate payment links on unpaid invoices for both Mollie and Rabobank providers, clearing old payment data before creating new link.

## Tasks Completed

| Task | Name | Commit | Files |
|------|------|--------|-------|
| 1 | Add regenerate-payment-link REST endpoint | cce49934 | includes/class-rest-invoices.php |
| 2 | Add frontend hook and button | 2deebd8e | src/api/client.js, src/hooks/useInvoices.js, src/pages/Finance/FactuurDetail.jsx |

## What Was Built

### Backend (Task 1)

Added `POST /rondo/v1/invoices/{id}/regenerate-payment-link` endpoint to `class-rest-invoices.php`:

- Validates invoice exists (404 if not)
- Blocks paid invoices with a 400 error ("Betaalde facturen kunnen geen nieuwe betaallink krijgen.")
- For Mollie: deletes `_mollie_payment_id` post meta and clears `payment_link` ACF field before calling `MolliePayment->create_payment_link()` — this bypasses Mollie's idempotency check so a new payment URL is generated
- For Rabobank: checks OAuth connection, returns 400 if not connected, then calls `RabobankPayment->create_payment_request()`
- Returns updated invoice via `format_invoice_detail()`
- All existing `use` statements (MolliePayment, RabobankOAuth, RabobankPayment, FinanceConfig) were already present

### Frontend (Task 2)

Three file changes:

**`src/api/client.js`** — Added `regeneratePaymentLink` method alongside `createPaymentLink`:
```js
regeneratePaymentLink: (invoiceId) => api.post(`/rondo/v1/invoices/${invoiceId}/regenerate-payment-link`),
```

**`src/hooks/useInvoices.js`** — Added `useRegeneratePaymentLink` export following the same pattern as `useResendInvoice`:
- Invalidates both `['invoices']` and `['invoice']` queries on success

**`src/pages/Finance/FactuurDetail.jsx`**:
- Added `useRegeneratePaymentLink` to imports and instantiated it
- Added `handleRegeneratePaymentLink` async handler with `window.confirm()` guard
- Updated `isPending` to include `regeneratePaymentLink.isPending`
- Added "Betaallink opnieuw aanmaken" button shown when `invoice.status !== 'paid' && invoice.payment_link` — placed directly after the existing "Betaallink aanmaken" block (which shows when no payment link exists)

## Deviations from Plan

None — plan executed exactly as written.

## Self-Check

### Files exist
- includes/class-rest-invoices.php: EXISTS
- src/api/client.js: EXISTS
- src/hooks/useInvoices.js: EXISTS
- src/pages/Finance/FactuurDetail.jsx: EXISTS

### Commits exist
- cce49934: EXISTS
- 2deebd8e: EXISTS

## Self-Check: PASSED
