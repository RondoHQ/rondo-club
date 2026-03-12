# S01 (Webhook payment detail extraction + REST API + Invoice detail UI) — Research

**Date:** 2026-03-12

## Summary

This slice adds Mollie payment detail extraction, storage, REST API exposure, and frontend rendering in a single vertical pass. The work touches three files: `class-mollie-webhook.php` (extract + store payment details from Mollie Payment objects), `class-rest-invoices.php` (expose stored details via `format_invoice_detail()`), and `FactuurDetail.jsx` (render "Betaalgegevens" section and enrich the installment timeline table).

The primary technical risk — whether `$paymentLink->payments()` returns usable Payment objects with `method`, `paidAt`, `details`, and `_links->dashboard` — is addressed by the SDK's `PaymentLinkPaymentEndpointCollection::pageFor()` which returns a `PaymentCollection` (extends `ArrayObject`) of full `Payment` resource objects. The fixture at `vendor/mollie/mollie-api-php/src/Fake/Responses/payment-list.json` confirms Payment objects carry `_links.dashboard.href`, `method`, and `details`. This will be proven definitively with a real Mollie test-mode payment on production.

**Primary recommendation:** Implement in order: (1) webhook extraction + meta storage, (2) REST API enrichment, (3) frontend rendering. All three changes are small and well-scoped — a single implementation pass is appropriate.

## Recommendation

### Webhook changes (`class-mollie-webhook.php`)

Add a private `extract_payment_details()` method that:
1. Calls `$payment_link->payments()` to get the `PaymentCollection`
2. Iterates to find the last payment with `isPaid() === true`
3. Extracts `method`, `paidAt`, `details`, and `_links->dashboard->href`
4. Stores as flat post meta on the invoice

Call this method in two places:
- **Path 0b** (full payment / discipline): In `handle_payment_link_webhook()`, after `isPaid()` check but **before** the status transition to `rondo_paid`. This ensures duplicate webhooks (which exit early on `rondo_paid` check) already have details stored.
- **Path 0a** (installments): In `handle_installment_paid()`, after the idempotency check but before marking the installment as `betaald`. Store per-installment details using the `_installment_N_mollie_*` pattern.

The extraction must be wrapped in `try/catch` — if `$payment_link->payments()` fails, log the error and continue. Payment details are enrichment, not a blocking requirement.

### Meta storage pattern

**Invoice-level** (full payment / discipline invoices):
- `_mollie_payment_method` — string slug (`ideal`, `creditcard`, `banktransfer`)
- `_mollie_paid_at` — ISO 8601 timestamp from Payment object
- `_mollie_dashboard_url` — full URL from `$payment->_links->dashboard->href`
- `_mollie_consumer_name` — from `$payment->details->consumerName` (iDEAL)
- `_mollie_consumer_account` — from `$payment->details->consumerAccount` (iDEAL IBAN)
- `_mollie_payment_details` — JSON blob via `wp_json_encode($payment->details)`

**Per-installment** (follows existing `_installment_N_*` pattern):
- `_installment_N_mollie_method` — string slug
- `_installment_N_mollie_paid_at` — ISO 8601 timestamp
- `_installment_N_mollie_dashboard_url` — full URL

Consumer details are stored at invoice level only (the last installment's consumer info represents the payer for the whole invoice — or they can be overridden per-installment if needed in the future). For now, only store method/paid_at/dashboard_url per installment.

### REST API changes (`class-rest-invoices.php`)

In `format_invoice_detail()`, add new fields:
- At invoice level: `mollie_payment_method`, `mollie_paid_at`, `mollie_dashboard_url`, `mollie_consumer_name`, `mollie_consumer_account`
- Per-installment: extend each installment object with `mollie_method`, `mollie_paid_at`, `mollie_dashboard_url`

These are simple `get_post_meta()` reads — no performance concern.

### Frontend changes (`FactuurDetail.jsx`)

1. **"Betaalgegevens" card section** — Shown only when `invoice.mollie_payment_method` is truthy. Displays: payment method (Dutch label), paid-at timestamp, consumer name/IBAN (when available), and a "Bekijk in Mollie" external link to the dashboard URL.

2. **Installment timeline table** — Add a "Methode" column and a "Mollie" column to paid installments. The "Mollie" column shows a small external link icon linking to the dashboard URL for that installment.

### Reset payment state

The `reset_payment_state()` method must also clear the new meta keys (`_mollie_payment_method`, `_mollie_paid_at`, `_mollie_dashboard_url`, `_mollie_consumer_name`, `_mollie_consumer_account`, `_mollie_payment_details`) so test-mode resets are clean.

## Don't Hand-Roll

| Problem | Existing Solution | Why Use It |
|---------|------------------|------------|
| Fetching payments for a payment link | `$paymentLink->payments()` → `PaymentCollection` (Mollie PHP SDK) | SDK method returns full Payment objects; returns `PaymentCollection` extending `ArrayObject`, directly iterable |
| Payment method labels in Dutch | Frontend mapping object from Mollie's `method` string slugs | Mollie returns `ideal`, `creditcard`, `banktransfer`, etc. — static map to Dutch labels in JS, no API call needed |
| Dashboard URL | `$payment->_links->dashboard->href` | SDK returns full URL; confirmed via test fixture JSON |
| Installment meta pattern | Existing `_installment_N_*` flat meta keys | Consistent with `_installment_N_amount`, `_installment_N_status`, `_installment_N_due_date` |
| ISO 8601 date formatting in frontend | `format()` from `@/utils/dateFormat` | Already used throughout FactuurDetail for date display |
| External link pattern | `target="_blank" rel="noopener noreferrer"` with `ExternalLink` icon | Already imported and used in FactuurDetail.jsx for betaallink |
| Card section pattern | `<div className="card p-6">` with `<h2>` header | Used 6 times in FactuurDetail.jsx |
| Currency formatting | `formatCurrency()` from `@/utils/formatters` | Already imported in FactuurDetail |

## Existing Code and Patterns

- **`includes/class-mollie-webhook.php`** — Primary modification target. Two methods need enrichment:
  - `handle_payment_link_webhook()` (Path 0b, lines ~96-168): After `isPaid()` check, before `wp_update_post()`. Already has `$payment_link` object and `$mollie_client` available.
  - `handle_installment_paid()` (Path 0a, lines ~183-255): After idempotency check on `_installment_N_status`, before `update_post_meta()` for 'betaald'. **Note:** This method does NOT currently have the `$payment_link` object — it receives `$invoice_id`, `$n`, and `$payment_id`. We need to pass the `$payment_link` object (or the `$mollie_client`) from the caller.

- **`includes/class-rest-invoices.php` → `format_invoice_detail()`** (lines ~2396-2453) — Builds the full invoice response. The installment loop (lines ~2420-2439) builds each installment object with `number`, `amount`, `status`, `due_date`, `paid_at`, `sent_at`. Add `mollie_method`, `mollie_paid_at`, `mollie_dashboard_url` here. Invoice-level fields added after the installment block.

- **`src/pages/Finance/FactuurDetail.jsx`** — 1000+ lines. Key insertion points:
  - New "Betaalgegevens" card section: after the line items table (`<div className="card p-6">` at line 656) and before the installment timeline (line 748). Or after the installment timeline.
  - Installment timeline table (lines 748-790): Add columns for Methode and Mollie link to the existing table.
  - `ExternalLink` icon already imported (line 3), used for betaallink (line 573).

- **`includes/class-mollie-client.php`** — Thin wrapper, already used in webhook handler. No changes needed.

- **`vendor/mollie/mollie-api-php/src/Resources/Payment.php`** — Confirms: `$method` (string|null, line 93), `$paidAt` (string|null, line 128), `$details` (stdClass|null, line 289), `$_links` (stdClass, line 301), `isPaid()` (bool, line 465).

- **`vendor/mollie/mollie-api-php/src/Resources/PaymentLink.php` → `payments()`** (line 168) — Returns `PaymentCollection` via `paymentLinkPayments->pageFor()`. No parameters needed for our use case.

## Constraints

- **Webhook must always return HTTP 200** — Mollie retry storms if it gets non-200. Payment detail extraction must be in try/catch, never prevent the 200 response. This is an existing pattern.
- **Idempotency must be preserved** — Existing `rondo_paid` status check (Path 0b) and `betaald` installment check (Path 0a) must remain unchanged. Payment details stored **before** status transition means duplicate webhooks see details already present.
- **`$payment_link` not available in `handle_installment_paid()`** — The installment handler receives `$invoice_id`, `$n`, `$payment_id` but not the Mollie PaymentLink object. Two options: (a) pass `$payment_link` as a 4th parameter, or (b) re-fetch using the payment link ID. Option (a) is cleaner since the object is already available in the caller.
- **Payment details are method-specific** — `$payment->details` varies: iDEAL has `consumerName`/`consumerAccount`/`consumerBic`; credit card has `cardHolder`/`cardNumber`; PayPal has `consumerName`/`consumerAccount` (email). Frontend must handle null/missing fields gracefully.
- **No custom database tables** (Rule 0) — All storage via WordPress post meta.
- **No ESLint errors** — Lint is currently clean; must stay clean.
- **Reset payment state must clear new meta** — `reset_payment_state()` in `class-rest-invoices.php` must delete the new meta keys for clean test-mode resets.

## Common Pitfalls

- **`$paymentLink->payments()` returns a paginated collection** — Must iterate; for payment links, there's typically one successful payment, but failed attempts may also appear. Filter for `isPaid() === true` and take the last one. If none found, skip detail extraction.

- **`_links->dashboard` may be null** — Always null-check `$payment->_links->dashboard->href` before storing. In test mode, dashboard links should be present but it's not guaranteed. Store empty string if unavailable.

- **`details` is null until payment is completed** — For iDEAL, `consumerName` and `consumerAccount` are available at payment time. For bank transfer, details arrive "one banking day after." Since we're in the webhook callback at payment confirmation, iDEAL details should be available. Store null/empty for missing fields.

- **Storing details AFTER status transition could lose data on duplicate webhooks** — If we transition to `rondo_paid` first and then crash before storing details, a duplicate webhook would exit early (idempotency check). Solution: store details BEFORE the transition, as specified in DECISIONS.md.

- **`handle_installment_paid` signature change** — Adding `$payment_link` as a 4th parameter changes the method signature. Since it's a `private` method called only from `handle_payment_link_webhook()`, this is safe and doesn't affect external contracts.

- **Method label mapping must cover all Mollie methods** — Mollie has ~15 payment methods (ideal, creditcard, bancontact, sofort, banktransfer, eps, giropay, przelewy24, etc.). The frontend map should handle unknowns gracefully (show the raw method string capitalized).

- **ISO 8601 date parsing in frontend** — Mollie's `paidAt` is ISO 8601 (e.g., `"2024-02-12T11:58:35+00:00"`). JavaScript's `new Date()` handles this natively. Use `format(new Date(paidAt), 'd MMM yyyy HH:mm')` for display.

## Open Risks

- **`$paymentLink->payments()` could be empty** — If Mollie hasn't yet associated the payment with the payment link when the webhook fires (race condition), the collection could be empty. This is unlikely (Mollie sends the webhook after payment confirmation), but if it happens, we simply skip detail extraction. The invoice still transitions to paid correctly.

- **Rate limiting during bulk payment confirmation** — Each webhook now makes one additional API call (`$paymentLink->payments()`). For normal operation (one payment at a time), Mollie's rate limit (~250 req/10s) is not a concern. For simultaneous bulk confirmations, sequential webhook processing naturally rate-limits.

- **Dashboard links in test mode** — Test dashboard links point to the same URL format (`https://www.mollie.com/dashboard/org_xxx/payments/tr_xxx`). Should work but needs verification during the real test-mode payment proof.

- **Future: consumer details for non-iDEAL methods** — We store the full `details` blob in `_mollie_payment_details` for future-proofing. Currently, the frontend only renders `consumerName` and `consumerAccount` (iDEAL). For credit card, we could show `cardHolder` and masked `cardNumber` — this is a future enhancement, not blocked by this slice.

## Skills Discovered

| Technology | Skill | Status |
|------------|-------|--------|
| Mollie PHP SDK | none | `npx skills find "mollie"` returned no results |
| WordPress REST API | `wordpress/agent-skills@wp-rest-api` | available (374 installs) — not needed for this work; patterns are well-established in codebase |
| React/Tailwind UI | `frontend-design` | installed (in `<available_skills>`) — not needed; simple card section following existing patterns |

No additional skills recommended — this is standard WordPress + React work following established codebase patterns.

## Sources

- Mollie Payment resource properties: `vendor/mollie/mollie-api-php/src/Resources/Payment.php` — `$method` (line 93), `$paidAt` (line 128), `$details` (line 289, stdClass|null), `$_links` (line 301), `isPaid()` (line 465)
- Mollie PaymentLink `->payments()`: `vendor/mollie/mollie-api-php/src/Resources/PaymentLink.php:168-176` — returns `PaymentCollection` via `paymentLinkPayments->pageFor()`
- `PaymentCollection` is iterable: `vendor/mollie/mollie-api-php/src/Resources/PaymentCollection.php` extends `CursorCollection` → `ResourceCollection` → `BaseCollection` → `ArrayObject`
- Dashboard URL format confirmed: `vendor/mollie/mollie-api-php/src/Fake/Responses/payment-list.json` — `"dashboard": {"href": "https://www.mollie.com/dashboard/org_12345678/payments/tr_7UhSN1zuXS"}`
- iDEAL details structure confirmed: Payment.php docblock (line 285) — `$details->consumerName` and `$details->consumerAccount`
- Existing webhook handler: `includes/class-mollie-webhook.php` — 255 lines, 3 methods, 2 paths for payment links
- Existing REST response: `includes/class-rest-invoices.php` → `format_invoice_detail()` (lines 2396-2453) — installment loop builds objects with status/amount/due_date/paid_at/sent_at
- Existing frontend: `src/pages/Finance/FactuurDetail.jsx` — 1000+ lines, card sections at 6 points, installment table at lines 748-790
- MollieClient wrapper: `includes/class-mollie-client.php` — thin wrapper, already used in webhook
- Current ESLint status: clean (zero warnings, confirmed via `npm run lint`)

## File Change Summary

| File | Change | Risk |
|------|--------|------|
| `includes/class-mollie-webhook.php` | Add `extract_payment_details()` private method; call in both Path 0a and 0b; pass `$payment_link` to `handle_installment_paid()` | medium (extra API call, needs try/catch) |
| `includes/class-rest-invoices.php` | Add payment detail fields to `format_invoice_detail()`; clear new meta in `reset_payment_state()` | low (read-only meta additions + meta deletion) |
| `src/pages/Finance/FactuurDetail.jsx` | Add "Betaalgegevens" card section; extend installment table columns | low (pure UI, follows existing patterns) |
