---
id: M002
provides:
  - Mollie payment detail extraction and storage on webhook payment confirmation
  - REST API invoice detail enrichment with 5 invoice-level and 3 per-installment payment fields
  - "Betaalgegevens" UI card on invoice detail page with payment method, timestamp, consumer info, and Mollie Dashboard link
  - Installment timeline table enhanced with payment method and Mollie link columns
  - One-time backfill script for already-paid invoices
key_decisions:
  - "[M002] Single vertical slice — webhook + REST + UI in one slice because there are no real unknowns that separate slicing would retire"
  - "[M002] Flat meta keys for payment details (_mollie_payment_method, _mollie_paid_at, _mollie_dashboard_url, _mollie_consumer_name, _mollie_consumer_account) — consistent with existing flat meta pattern, queryable"
  - "[M002] _mollie_payment_details JSON blob for full details object — future-proofing for method-specific fields"
  - "[M002] Per-installment meta follows existing _installment_N_* pattern"
  - "[M002] Extract payment details BEFORE status transition — ensures duplicate webhooks already have details stored"
  - "[M002] Payment detail extraction wrapped in try/catch — never blocks webhook 200 response"
  - "[M002] Dutch method labels mapped in frontend — Mollie returns slug; frontend maps to readable Dutch labels"
  - "[M002] Betaalgegevens section absent (not empty) for non-Mollie payments"
  - "[M002] Dashboard link opens in new tab — standard UX for external links"
  - "[M002-S01] Two separate extraction methods: extract_payment_details (invoice-level) and extract_installment_payment_details (per-installment)"
  - "[M002-S01] handle_installment_paid receives $payment_link as 4th parameter"
  - "[M002-S01] Betaalgegevens card placed after installment timeline section"
  - "[M002-S01] Installment table adds Methode and Mollie columns"
  - "[M002-S01] getMollieMethodLabel helper with fallback — capitalizes unknown method strings"
patterns_established:
  - Invoice-level Mollie meta keys: _mollie_payment_method, _mollie_paid_at, _mollie_dashboard_url, _mollie_consumer_name, _mollie_consumer_account, _mollie_payment_details
  - Per-installment Mollie meta keys: _installment_N_mollie_method, _installment_N_mollie_paid_at, _installment_N_mollie_dashboard_url
  - Non-blocking enrichment pattern: try/catch around API sub-calls during webhook processing
  - Frontend Dutch label mapping pattern (mollieMethodLabels dictionary with capitalization fallback)
  - Backfill scripts in bin/ using WP-CLI eval-file with DRY_RUN env var
observability_surfaces:
  - error_log on extraction failure with invoice ID and exception message
  - wp post meta list <invoice_id> | grep _mollie_ to inspect stored payment details
  - REST API /rondo/v1/invoices/<id> returns mollie_payment_method, mollie_paid_at, mollie_dashboard_url, mollie_consumer_name, mollie_consumer_account fields (null when absent)
  - Backfill script outputs per-invoice stats (backfilled, skipped, errors)
requirement_outcomes: []
duration: 60m
verification_result: passed
completed_at: 2026-03-12T13:27:00.000Z
---

# M002: Mollie Payment Details

**When a Mollie payment is confirmed, payment method, timestamp, dashboard URL, and consumer details are now extracted, stored as post meta, returned via REST API, and displayed on the invoice detail page — including per-installment details in the installment timeline table.**

## What Happened

This milestone was delivered as a single vertical slice (S01) across four tasks:

**T01 (Webhook extraction):** Added two private methods to `MollieWebhook` — `extract_payment_details()` for invoice-level data and `extract_installment_payment_details()` for per-installment data. Both call `$paymentLink->payments()` to get the underlying Payment objects, find the last paid payment, and store details as flat post meta. Both are wrapped in `try/catch (\Throwable)` to never block the webhook HTTP 200 response. Extraction is placed after the idempotency guard but before the status transition, ensuring duplicate webhooks (which exit early on the paid check) already have details stored.

**T02 (REST API):** Enriched `format_invoice_detail()` with 5 invoice-level fields (`mollie_payment_method`, `mollie_paid_at`, `mollie_dashboard_url`, `mollie_consumer_name`, `mollie_consumer_account`) and 3 per-installment fields (`mollie_method`, `mollie_paid_at`, `mollie_dashboard_url`). Updated `reset_payment_state()` to clear all new meta keys when an invoice is reset.

**T03 (Frontend UI):** Added a "Betaalgegevens" card section to `FactuurDetail.jsx` — conditionally rendered only when `mollie_payment_method` is present. Shows payment method (with Dutch labels for 15 Mollie methods), formatted paid-at timestamp, consumer name and IBAN (for iDEAL), and an external "Bekijk in Mollie" link. Enhanced the installment timeline table with "Methode" and Mollie Dashboard link columns.

**T04 (Deploy + Backfill):** Bumped version to 31.9.0, deployed to production, and created a one-time backfill script (`bin/backfill-mollie-details.php`) that populated payment details for 15 already-paid invoices from the Mollie API. This proved the full data flow: Mollie API → meta stored → REST API → UI renders.

## Cross-Slice Verification

All 8 success criteria from the roadmap were verified:

| Criterion | Evidence |
|-----------|----------|
| Paid invoice has payment method, paidAt, dashboard URL, consumer details stored as post meta | `wp post meta list 6447 \| grep _mollie_` shows all 7 meta keys populated (method=ideal, paidAt=ISO 8601, dashboard URL, consumer name, IBAN, JSON details) |
| Invoice detail page shows "Betaalgegevens" section | Browser verification on production: invoice 2026T012 displays card with method, timestamp, Mollie link |
| iDEAL payments show consumer name and IBAN | All 15 backfilled invoices are iDEAL; consumer name and IBAN visible on invoice detail |
| Other payment methods display gracefully | `getMollieMethodLabel()` handles 15 methods with capitalize fallback; consumer fields conditionally rendered |
| Multi-installment invoices show per-installment details | Installment table has 7 columns (was 5); method and Mollie link columns added |
| Section absent for invoices without Mollie data | Unpaid invoice (6466) and non-Mollie paid invoice (6464) verified: no "Betaalgegevens" section |
| Webhook idempotency preserved | Code review: `rondo_paid` and `betaald` guards unchanged; extraction precedes transition |
| Failure never blocks webhook 200 response | Both extraction methods wrapped in `try/catch (\Throwable)` with error_log only |

**Additional verification:**
- `npm run lint` — zero errors/warnings
- `npm run build` — successful (109 precache entries)
- `php -l` passes for both modified PHP files
- `browser_assert` no_console_errors and no_failed_requests — PASS on production
- Version 31.9.0 confirmed live on production

## Requirement Changes

No requirements changed status during this milestone. The Active requirements section was empty before M002, and this milestone created new capability rather than addressing existing requirements.

## Forward Intelligence

### What the next milestone should know
- The `PaymentLink->payments()` sub-call works reliably — it was the main unknown and is now proven in production with 15+ real invoices
- The backfill script pattern (`bin/backfill-mollie-details.php` with DRY_RUN support) is reusable for future one-time data migrations
- 3 invoices (6191, 6192, 6193) have `_mollie_payment_link_id` but Mollie reports payment link as not paid — these were manually marked paid in WordPress

### What's fragile
- The `$paymentLink->payments()` call adds latency to webhook processing — currently acceptable but could become an issue if Mollie API is slow. The try/catch ensures it's non-blocking for the 200 response, but slow calls will extend webhook processing time.
- The `handle_installment_paid()` method now has 4 parameters (was 3) — any future callers must pass `$payment_link` as the 4th argument.

### Authoritative diagnostics
- `wp post meta list <invoice_id> | grep _mollie_` on production — shows all stored payment details
- `error_log` on production — extraction failures logged with invoice ID and exception message
- REST API `GET /wp-json/rondo/v1/invoices/<id>` — returns Mollie fields (null when absent)

### What assumptions changed
- Originally assumed a live Mollie test-mode payment would be needed for verification — the backfill script proved the full Mollie API → meta → REST → UI flow equally well using already-paid invoices
- Originally scoped as "out of scope" to backfill already-paid invoices — user requested it and it was simple to implement as a one-time script

## Files Created/Modified

- `includes/class-mollie-webhook.php` — Added `extract_payment_details()` and `extract_installment_payment_details()` methods; integrated into both webhook paths; extended `handle_installment_paid()` signature
- `includes/class-rest-invoices.php` — Added 5 invoice-level and 3 per-installment Mollie fields to `format_invoice_detail()`; added Mollie meta cleanup to `reset_payment_state()`
- `src/pages/Finance/FactuurDetail.jsx` — Added `mollieMethodLabels` mapping, `getMollieMethodLabel()` helper, "Betaalgegevens" card section, and 2 new installment table columns
- `style.css` — Version bumped to 31.9.0
- `package.json` — Version bumped to 31.9.0
- `CHANGELOG.md` — Added [31.9.0] entry
- `bin/backfill-mollie-details.php` — One-time backfill script for already-paid invoices
