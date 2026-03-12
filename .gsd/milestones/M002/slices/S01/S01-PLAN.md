# S01: Webhook payment detail extraction + REST API + Invoice detail UI

**Goal:** When a Mollie payment is confirmed via webhook, extract and store payment details (method, paidAt, dashboard URL, consumer info); expose them through the REST API; and render them on the invoice detail page — for both full-payment and installment flows.
**Demo:** A paid invoice shows a "Betaalgegevens" card with payment method, paid-at timestamp, and a clickable "Bekijk in Mollie" link. For iDEAL payments, consumer name and IBAN are shown. Multi-installment invoices show per-installment method, paid-at, and Mollie link in the timeline table. Invoices without Mollie data show no section (absent, not empty).

## Must-Haves

- `extract_payment_details()` private method in MollieWebhook calls `$paymentLink->payments()`, finds the paid payment, and stores flat meta keys
- Payment details extracted BEFORE status transition (idempotency-safe)
- Extraction wrapped in try/catch — never blocks HTTP 200 or status transition
- Per-installment meta stored using `_installment_N_mollie_*` pattern
- `$payment_link` object passed to `handle_installment_paid()` for detail extraction
- `format_invoice_detail()` returns `mollie_payment_method`, `mollie_paid_at`, `mollie_dashboard_url`, `mollie_consumer_name`, `mollie_consumer_account` at invoice level
- Per-installment objects gain `mollie_method`, `mollie_paid_at`, `mollie_dashboard_url`
- `reset_payment_state()` clears all new meta keys
- "Betaalgegevens" card section rendered only when `mollie_payment_method` is truthy
- Installment timeline table enhanced with Methode and Mollie link columns for paid installments
- Dutch method labels for all common Mollie payment methods
- No ESLint errors introduced
- Existing webhook idempotency preserved

## Proof Level

- This slice proves: integration
- Real runtime required: yes (real Mollie test-mode payment on production)
- Human/UAT required: yes (admin views paid invoice on production)

## Verification

- `npm run build` — frontend compiles without errors
- `npm run lint` — zero ESLint warnings/errors
- PHP syntax check: `php -l includes/class-mollie-webhook.php && php -l includes/class-rest-invoices.php`
- Deploy to production and trigger a Mollie test-mode payment
- After payment: `wp post meta get <invoice_id> _mollie_payment_method` returns non-empty value
- After payment: `wp post meta get <invoice_id> _mollie_paid_at` returns ISO 8601 timestamp
- After payment: `wp post meta get <invoice_id> _mollie_dashboard_url` returns Mollie dashboard URL
- REST API: `GET /wp-json/rondo/v1/invoices/<id>` response contains `mollie_payment_method`, `mollie_paid_at`, `mollie_dashboard_url` fields
- Browser: invoice detail page shows "Betaalgegevens" card with method, timestamp, and Mollie link
- Browser: unpaid invoice detail page does NOT show "Betaalgegevens" card
- Duplicate webhook for same payment link returns 200 without errors

## Observability / Diagnostics

- Runtime signals: `error_log('Mollie webhook: failed to extract payment details...')` on extraction failure with exception message; successful extraction produces no log (silent success pattern, matching existing webhook)
- Inspection surfaces: `wp post meta list <invoice_id>` to inspect stored Mollie payment details; REST API `/rondo/v1/invoices/<id>` includes payment detail fields
- Failure visibility: `error_log` entries contain invoice ID, payment link ID, and exception message; extraction failures leave meta keys absent (easily detectable via `wp post meta get`)
- Redaction constraints: Mollie API keys never logged; consumer IBAN stored as-is (not PII-sensitive in this admin context)

## Integration Closure

- Upstream surfaces consumed: `MollieWebhook::handle_payment_link_webhook()`, `MollieWebhook::handle_installment_paid()`, `RONDO_REST_Invoices::format_invoice_detail()`, `RONDO_REST_Invoices::reset_payment_state()`, `FactuurDetail.jsx` card sections and installment table
- New wiring introduced in this slice: `extract_payment_details()` method wired into both webhook paths; REST response enriched with new meta reads; frontend consumes new API fields
- What remains before the milestone is truly usable end-to-end: nothing — this is the only slice; a real Mollie test-mode payment on production proves the full flow

## Tasks

- [x] **T01: Extract and store Mollie payment details in webhook handler** `est:45m`
  - Why: The webhook must fetch payment details from Mollie's Payment object and persist them as post meta before the status transition, for both full-payment (Path 0b) and installment (Path 0a) flows
  - Files: `includes/class-mollie-webhook.php`
  - Do: Add `extract_payment_details($payment_link, $invoice_id)` private method that calls `$payment_link->payments()`, finds the last `isPaid()` payment, stores `_mollie_payment_method`, `_mollie_paid_at`, `_mollie_dashboard_url`, `_mollie_consumer_name`, `_mollie_consumer_account`, `_mollie_payment_details` as flat meta. Add `extract_installment_payment_details($payment_link, $invoice_id, $n)` that stores per-installment `_installment_N_mollie_method`, `_installment_N_mollie_paid_at`, `_installment_N_mollie_dashboard_url`. Call invoice-level extraction in Path 0b before `wp_update_post`. Pass `$payment_link` to `handle_installment_paid()` and call installment extraction before marking `betaald`. Wrap all extraction in try/catch.
  - Verify: `php -l includes/class-mollie-webhook.php` passes; code review confirms try/catch wrapping, extraction before status transition, and `$payment_link` passed to installment handler
  - Done when: Webhook handler stores payment details for both full and installment paths, extraction never blocks 200 response, idempotency preserved

- [x] **T02: Expose payment details via REST API and clear on reset** `est:30m`
  - Why: The frontend needs payment detail data from the API, and test-mode reset must clean up all new meta keys
  - Files: `includes/class-rest-invoices.php`
  - Do: In `format_invoice_detail()`, add invoice-level reads: `mollie_payment_method`, `mollie_paid_at`, `mollie_dashboard_url`, `mollie_consumer_name`, `mollie_consumer_account` from post meta. In the installment loop, add `mollie_method`, `mollie_paid_at`, `mollie_dashboard_url` per installment. In `reset_payment_state()`, add `delete_post_meta()` calls for all 6 invoice-level meta keys and loop through installments to clear per-installment Mollie meta.
  - Verify: `php -l includes/class-rest-invoices.php` passes; code review confirms all 5 invoice-level fields added, 3 per-installment fields added, and reset clears all keys
  - Done when: REST response includes payment details at both levels; reset_payment_state clears all Mollie payment detail meta

- [ ] **T03: Render payment details UI on invoice detail page** `est:45m`
  - Why: Admins need to see how an invoice was paid, with a direct link to the Mollie Dashboard — both in the "Betaalgegevens" card and the installment timeline table
  - Files: `src/pages/Finance/FactuurDetail.jsx`
  - Do: Add a Dutch method label mapping object (`ideal` → `iDEAL`, `creditcard` → `Creditcard`, etc.) with fallback to capitalized raw string. Add a "Betaalgegevens" card section (conditionally rendered when `invoice.mollie_payment_method` is truthy) showing: payment method label, paid-at timestamp (formatted with `format(new Date(...), 'd MMM yyyy HH:mm')`), consumer name and IBAN when available, and a "Bekijk in Mollie" external link. Enhance the installment timeline table with two new columns: "Methode" (method label for paid installments) and "Mollie" (external link icon linking to dashboard URL). Place the Betaalgegevens card after the installment timeline section.
  - Verify: `npm run lint` passes with zero errors; `npm run build` succeeds; browser verification on production confirms card appears for paid Mollie invoices and is absent for non-Mollie invoices
  - Done when: "Betaalgegevens" card renders correctly for paid invoices with Mollie data; installment table shows method and dashboard link per paid installment; section absent for non-Mollie invoices; no ESLint errors

- [ ] **T04: Deploy, verify with Mollie test payment, version bump** `est:30m`
  - Why: The full integration must be proven with a real Mollie test-mode payment on production; version and changelog must be updated per project rules
  - Files: `style.css`, `package.json`, `CHANGELOG.md`
  - Do: Bump version (patch increment). Add changelog entry under new version. Build, deploy via `bin/deploy.sh`. On production, trigger a Mollie test-mode payment. Verify meta stored via WP-CLI. Verify REST API returns payment details. Verify invoice detail page shows "Betaalgegevens" card. Verify existing paid invoices without Mollie data show no section. Verify duplicate webhook is a silent no-op.
  - Verify: Production invoice detail page shows payment details after test payment; `wp post meta get <id> _mollie_payment_method` returns value; REST API response includes new fields; no errors in error log from extraction
  - Done when: End-to-end flow proven on production: webhook → meta stored → API returns → UI renders; version bumped and changelog updated; committed and pushed

## Files Likely Touched

- `includes/class-mollie-webhook.php`
- `includes/class-rest-invoices.php`
- `src/pages/Finance/FactuurDetail.jsx`
- `style.css`
- `package.json`
- `CHANGELOG.md`
