---
id: T02
parent: S01
milestone: M002
provides:
  - REST API invoice detail response enriched with 5 invoice-level and 3 per-installment Mollie payment detail fields
  - reset_payment_state() clears all 6 invoice-level + 3×N per-installment Mollie meta keys
key_files:
  - includes/class-rest-invoices.php
key_decisions:
  - Used same `(string) ... ?: null` pattern as existing fields for null-when-empty consistency
  - Placed Mollie fields after `disable_installments` at end of format_invoice_detail() to avoid disrupting existing field order
  - Per-installment Mollie cleanup reads _installment_count before the loop (same pattern used in format_invoice_detail)
patterns_established:
  - Invoice-level API fields: mollie_payment_method, mollie_paid_at, mollie_dashboard_url, mollie_consumer_name, mollie_consumer_account
  - Per-installment API fields: mollie_method, mollie_paid_at, mollie_dashboard_url
observability_surfaces:
  - REST API `/rondo/v1/invoices/<id>` response contains Mollie payment detail fields (null when absent)
duration: 10m
verification_result: passed
completed_at: 2026-03-12
blocker_discovered: false
---

# T02: Expose payment details via REST API and clear on reset

**Enriched `format_invoice_detail()` REST response with 5 invoice-level and 3 per-installment Mollie payment detail fields, and updated `reset_payment_state()` to clear all new meta keys.**

## What Happened

Added read-only Mollie payment detail fields to the invoice detail REST endpoint at two levels:

1. **Invoice-level** (5 fields): `mollie_payment_method`, `mollie_paid_at`, `mollie_dashboard_url`, `mollie_consumer_name`, `mollie_consumer_account` — added after the existing `disable_installments` field, before `return $invoice`.

2. **Per-installment** (3 fields per installment): `mollie_method`, `mollie_paid_at`, `mollie_dashboard_url` — added to each installment object in the existing loop.

3. **Reset cleanup**: Added deletion of all 6 invoice-level Mollie meta keys (including `_mollie_payment_details`) in `reset_payment_state()`, plus a loop through installments to delete the 3 per-installment Mollie meta keys per installment.

All fields return `null` when no meta is stored, matching the existing pattern used throughout the method.

## Verification

- `php -l includes/class-rest-invoices.php` — exits 0, no syntax errors
- `php -l includes/class-mollie-webhook.php` — exits 0 (slice-level check)
- `npm run build` — succeeds, 109 precache entries
- `npm run lint` — zero warnings/errors
- Code review: `format_invoice_detail()` returns 5 new invoice-level keys (lines 2466-2470)
- Code review: each installment object includes 3 new keys (lines 2453-2455)
- Code review: `reset_payment_state()` deletes 6 invoice-level meta keys (lines 1951-1956) + loops 3×N per-installment keys (lines 1959-1963)
- No existing fields or behavior modified

### Slice-level checks (intermediate — partial pass expected)

| Check | Status |
|-------|--------|
| `npm run build` | ✅ pass |
| `npm run lint` | ✅ pass |
| PHP syntax: `class-mollie-webhook.php` | ✅ pass |
| PHP syntax: `class-rest-invoices.php` | ✅ pass |
| Deploy + Mollie test-mode payment | ⏳ T04 |
| REST API returns new fields | ⏳ after deploy |
| Browser: Betaalgegevens card | ⏳ T03 |
| Browser: unpaid invoice no card | ⏳ T03 |

## Diagnostics

- Inspect REST response: `curl` the invoice detail endpoint at `/wp-json/rondo/v1/invoices/<id>` and check for `mollie_payment_method` in response
- Fields return `null` when no Mollie payment data exists (not empty string)
- After a reset, all Mollie meta keys are deleted — verifiable via `wp post meta list <invoice_id> | grep _mollie_`

## Deviations

None.

## Known Issues

None.

## Files Created/Modified

- `includes/class-rest-invoices.php` — Added 5 invoice-level Mollie fields to `format_invoice_detail()`, 3 per-installment Mollie fields to installment loop, and full Mollie meta cleanup to `reset_payment_state()`
