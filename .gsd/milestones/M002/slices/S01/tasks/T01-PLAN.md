---
estimated_steps: 5
estimated_files: 1
---

# T01: Extract and store Mollie payment details in webhook handler

**Slice:** S01 — Webhook payment detail extraction + REST API + Invoice detail UI
**Milestone:** M002

## Description

Add payment detail extraction to the Mollie webhook handler. When a payment link is confirmed paid, call `$paymentLink->payments()` to get the underlying Payment object, extract method, paidAt, dashboard URL, and consumer details, and store them as flat post meta on the invoice. This must happen BEFORE the status transition to `rondo_paid` so duplicate webhooks (which exit early on the idempotency check) already have details stored.

Two extraction methods are needed:
1. `extract_payment_details()` — stores invoice-level meta (method, paidAt, dashboard URL, consumer name, consumer account, full details JSON blob)
2. `extract_installment_payment_details()` — stores per-installment meta (method, paidAt, dashboard URL) using the existing `_installment_N_*` pattern

Both must be wrapped in try/catch to never block the HTTP 200 response.

## Steps

1. Add private method `extract_payment_details( $payment_link, int $invoice_id )` that:
   - Calls `$payment_link->payments()` to get the PaymentCollection
   - Iterates to find the last payment where `$payment->isPaid() === true`
   - If found, stores: `_mollie_payment_method` (string), `_mollie_paid_at` (ISO 8601 string), `_mollie_dashboard_url` (from `$payment->_links->dashboard->href`, null-checked), `_mollie_consumer_name` (from `$payment->details->consumerName`), `_mollie_consumer_account` (from `$payment->details->consumerAccount`), `_mollie_payment_details` (full `$payment->details` as JSON via `wp_json_encode`)
   - Wraps the entire body in try/catch, logging errors via `error_log` with invoice ID and exception message

2. Add private method `extract_installment_payment_details( $payment_link, int $invoice_id, int $n )` that:
   - Calls `$payment_link->payments()` and finds the last `isPaid()` payment
   - Stores: `_installment_{$n}_mollie_method`, `_installment_{$n}_mollie_paid_at`, `_installment_{$n}_mollie_dashboard_url`
   - Wraps in try/catch with logging

3. In `handle_payment_link_webhook()` (Path 0b), call `$this->extract_payment_details( $payment_link, $invoice_id )` AFTER the `rondo_paid` idempotency check but BEFORE the `wp_update_post()` status transition

4. Change `handle_installment_paid()` signature to accept `$payment_link` as 4th parameter: `private function handle_installment_paid( int $invoice_id, int $n, string $payment_id, $payment_link )`. Call `$this->extract_installment_payment_details( $payment_link, $invoice_id, $n )` AFTER the idempotency check but BEFORE marking installment as `betaald`. Update the caller in `handle_payment_link_webhook()` to pass `$payment_link`.

5. Verify: Run `php -l includes/class-mollie-webhook.php`. Review that all extraction is in try/catch, extraction precedes status transitions, and idempotency guards remain unchanged.

## Must-Haves

- [ ] `extract_payment_details()` stores all 6 invoice-level meta keys
- [ ] `extract_installment_payment_details()` stores 3 per-installment meta keys following `_installment_N_*` pattern
- [ ] All extraction wrapped in try/catch — never blocks webhook 200 response
- [ ] Extraction happens BEFORE status transition in both Path 0a and Path 0b
- [ ] `$payment_link` passed from caller to `handle_installment_paid()`
- [ ] Null-checks on `$payment->_links->dashboard->href`, `$payment->details->consumerName`, `$payment->details->consumerAccount`
- [ ] Existing idempotency guards (rondo_paid check in Path 0b, betaald check in Path 0a) remain unchanged

## Verification

- `php -l includes/class-mollie-webhook.php` exits 0 (no syntax errors)
- Code review: `extract_payment_details()` is called before `wp_update_post` in Path 0b
- Code review: `extract_installment_payment_details()` is called before `update_post_meta(..., 'betaald')` in Path 0a
- Code review: both extraction methods have try/catch around the `$payment_link->payments()` call
- Code review: `handle_installment_paid()` receives `$payment_link` parameter and caller passes it

## Observability Impact

- Signals added/changed: `error_log('Mollie webhook: failed to extract payment details for invoice {id}: {message}')` on extraction failure — structured with invoice ID for grep-ability
- How a future agent inspects this: `wp post meta list <invoice_id> | grep _mollie_` shows stored payment details; absence of meta keys indicates extraction failed or was skipped
- Failure state exposed: error_log entry with invoice ID and exception message; meta keys absent on failure

## Inputs

- `includes/class-mollie-webhook.php` — current 255-line file with `handle_payment_link_webhook()` and `handle_installment_paid()`
- Mollie SDK: `PaymentLink::payments()` returns `PaymentCollection` (iterable), `Payment::$method`, `Payment::$paidAt`, `Payment::$details`, `Payment::$_links`, `Payment::isPaid()`
- Research confirms `_links->dashboard->href` exists on Payment objects

## Expected Output

- `includes/class-mollie-webhook.php` — enhanced with `extract_payment_details()` and `extract_installment_payment_details()` private methods; both webhook paths call extraction before status transition; `handle_installment_paid()` signature includes `$payment_link` parameter
