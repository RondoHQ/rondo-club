---
id: T01
parent: S01
milestone: M002
provides:
  - extract_payment_details() private method storing 6 invoice-level meta keys from Mollie Payment object
  - extract_installment_payment_details() private method storing 3 per-installment meta keys
  - Both webhook paths (0a installment, 0b full payment) extract details before status transition
key_files:
  - includes/class-mollie-webhook.php
key_decisions:
  - Used \Throwable (not just \Exception) in catch blocks to handle any PHP error during extraction
  - Iterate all payments and take the last isPaid() one (not first) to match the most recent successful payment
  - Store method and paidAt unconditionally (empty string fallback via ??); store dashboard_url, consumer_name, consumer_account only when non-null
patterns_established:
  - Invoice-level meta keys: _mollie_payment_method, _mollie_paid_at, _mollie_dashboard_url, _mollie_consumer_name, _mollie_consumer_account, _mollie_payment_details
  - Per-installment meta keys: _installment_N_mollie_method, _installment_N_mollie_paid_at, _installment_N_mollie_dashboard_url
  - Extraction always precedes status transition for idempotency safety
observability_surfaces:
  - error_log('Mollie webhook: failed to extract payment details for invoice {id}: {message}') on invoice-level extraction failure
  - error_log('Mollie webhook: failed to extract installment {n} payment details for invoice {id}: {message}') on installment extraction failure
  - Absence of meta keys on a paid invoice indicates extraction failed or was skipped
  - wp post meta list <invoice_id> | grep _mollie_ to inspect stored details
duration: 10m
verification_result: passed
completed_at: 2026-03-12
blocker_discovered: false
---

# T01: Extract and store Mollie payment details in webhook handler

**Added two private extraction methods to MollieWebhook that fetch payment details from Mollie's Payment object and store them as flat post meta before status transitions.**

## What Happened

Added `extract_payment_details()` and `extract_installment_payment_details()` private methods to the `MollieWebhook` class. Both methods call `$payment_link->payments()` to get the PaymentCollection, iterate to find the last `isPaid()` payment, and store relevant details as post meta.

For Path 0b (full payment / discipline invoices): `extract_payment_details()` is called after the `rondo_paid` idempotency check but before `wp_update_post()`. It stores 6 meta keys: `_mollie_payment_method`, `_mollie_paid_at`, `_mollie_dashboard_url`, `_mollie_consumer_name`, `_mollie_consumer_account`, and `_mollie_payment_details` (full JSON blob).

For Path 0a (installment payments): `handle_installment_paid()` signature was extended with a 4th `$payment_link` parameter. `extract_installment_payment_details()` is called after the `betaald` idempotency check but before marking the installment as paid. It stores 3 per-installment meta keys following the existing `_installment_N_*` pattern.

Both methods are wrapped in `try/catch (\Throwable)` to ensure extraction failures never block the HTTP 200 response or the payment status transition.

## Verification

- `php -l includes/class-mollie-webhook.php` — exits 0, no syntax errors
- Code review: `extract_payment_details()` called at line 175, before `wp_update_post()` at line 178 (Path 0b) ✅
- Code review: `extract_installment_payment_details()` called at line 205, before `update_post_meta(..., 'betaald')` at line 208 (Path 0a) ✅
- Code review: both extraction methods have `try/catch (\Throwable)` wrapping ✅
- Code review: `handle_installment_paid()` receives `$payment_link` as 4th parameter and caller passes it ✅
- Code review: null-checks on `_links->dashboard->href`, `details->consumerName`, `details->consumerAccount` ✅
- Code review: idempotency guards (rondo_paid check in Path 0b, betaald check in Path 0a) unchanged ✅

### Slice-level checks (partial — T01 is intermediate):
- ✅ `php -l includes/class-mollie-webhook.php` passes
- ⏳ `php -l includes/class-rest-invoices.php` — T02
- ⏳ `npm run build` / `npm run lint` — T03
- ⏳ Deploy + Mollie test payment — T04
- ⏳ REST API verification — T02
- ⏳ Browser verification — T03

## Diagnostics

- On extraction failure: `error_log` entry with invoice ID and exception message
- On success: no log output (silent success, matching existing webhook pattern)
- Inspect stored data: `wp post meta list <invoice_id> | grep _mollie_`
- Absence of `_mollie_payment_method` on a `rondo_paid` invoice means extraction failed or the invoice was paid before this code was deployed

## Deviations

None.

## Known Issues

None.

## Files Created/Modified

- `includes/class-mollie-webhook.php` — Added `extract_payment_details()` and `extract_installment_payment_details()` private methods; integrated extraction calls into both webhook paths before status transitions; extended `handle_installment_paid()` signature with `$payment_link` parameter
