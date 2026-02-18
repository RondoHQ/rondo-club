---
phase: quick-76
plan: 01
subsystem: Finance / Invoices
tags: [invoices, payment, test-mode, mollie, rabobank, rest-api]
dependency_graph:
  requires: [class-rest-invoices.php, FinanceConfig, useInvoices.js, useFinanceSettings.js]
  provides: [POST /rondo/v1/invoices/{id}/reset-payment-state, useResetPaymentState hook, isTestMode guard]
  affects: [FactuurDetail.jsx]
tech_stack:
  added: []
  patterns: [test-mode guard via FinanceConfig::get_all_settings(), IIFE for derived state]
key_files:
  created: []
  modified:
    - includes/class-rest-invoices.php
    - src/api/client.js
    - src/hooks/useInvoices.js
    - src/pages/Finance/FactuurDetail.jsx
decisions:
  - Reset clears both Mollie AND Rabobank meta regardless of active provider (keeps data clean when switching providers)
  - isTestMode computed client-side from financeSettings to avoid extra round-trip
  - Button visibility requires payment state to clear (payment_link OR qr_code_path OR status === 'paid')
  - Returns 403 (not 400) when provider is in live mode — correct semantics for forbidden operation
metrics:
  duration: 180s
  completed: 2026-02-18
  tasks: 2
  files: 4
---

# Quick Task 76: Reset Payment State Interface for Test Mode

REST endpoint + mutation hook + orange "Reset betaalstatus (test)" button on invoice detail, visible only when active payment provider is in test/sandbox mode and the invoice has payment state to clear.

## Tasks Completed

| Task | Name | Commit | Files |
|------|------|--------|-------|
| 1 | Add reset-payment-state REST endpoint | 5b9a19da | includes/class-rest-invoices.php |
| 2 | Add API method, mutation hook, and conditional UI button | 449ae276 | src/api/client.js, src/hooks/useInvoices.js, src/pages/Finance/FactuurDetail.jsx |

## What Was Built

### PHP: REST Endpoint (`POST /rondo/v1/invoices/{id}/reset-payment-state`)

Added to `includes/class-rest-invoices.php`:

- `is_test_mode_active(): bool` — reads `FinanceConfig::get_all_settings()`, returns true for `mollie` + `test` or `rabobank` + `sandbox`, false otherwise
- `reset_payment_state()` — validates invoice, returns 403 if not in test mode, then:
  - Deletes `_mollie_payment_id` and `_rabobank_payment_request_id` post meta
  - Clears `payment_link` ACF field
  - Calls `clear_qr_code()` to remove QR file and field
  - If invoice is `rondo_paid`: transitions to `rondo_sent` and updates ACF `status` field
  - Returns updated invoice via `format_invoice_detail()`

### JavaScript: API + Hook + UI

- `prmApi.resetPaymentState(invoiceId)` in `src/api/client.js`
- `useResetPaymentState()` mutation hook in `src/hooks/useInvoices.js` — invalidates `['invoices']` and `['invoice']` on success
- In `FactuurDetail.jsx`:
  - Imported `useResetPaymentState` and `useFinanceSettings`
  - `isTestMode` IIFE derived from `financeSettings.active_payment_provider` + environment field
  - `handleResetPaymentState` handler with Dutch confirm dialog
  - `resetPaymentState.isPending` included in composite `isPending`
  - Orange-tinted `btn-secondary` button shown conditionally: `isTestMode && (payment_link || qr_code_path || status === 'paid')`

## Deviations from Plan

None — plan executed exactly as written.

## Self-Check: PASSED

- `includes/class-rest-invoices.php` — modified, contains `reset_payment_state` and `is_test_mode_active`
- `src/api/client.js` — modified, contains `resetPaymentState`
- `src/hooks/useInvoices.js` — modified, contains `useResetPaymentState`
- `src/pages/Finance/FactuurDetail.jsx` — modified, contains button with `isTestMode` guard
- Both commits verified: `5b9a19da` and `449ae276`
- `npm run build` exited 0 with no errors
- Deployed to production: https://rondo.svawc.nl/
