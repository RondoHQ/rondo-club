# M002: Mollie Payment Details — Research

**Date:** 2026-03-12

## Summary

This milestone enriches invoice records with payment details from Mollie (payment method, paid-at timestamp, dashboard URL, consumer details) when webhooks confirm payment. The work is straightforward and well-scoped: modify the webhook handler to make one extra API call (`$paymentLink->payments()`), store the results as flat post meta, expose them via the existing REST endpoint (`format_invoice_detail`), and render them on the FactuurDetail page.

The primary technical challenge is that Payment Link objects (`pl_xxx`) don't carry payment details directly — the webhook must call `$paymentLink->payments()` to get the underlying Payment object(s), which have `method`, `paidAt`, `details`, and `_links->dashboard->href`. This is one additional HTTP call to Mollie's API per webhook invocation, which is acceptable since webhooks are infrequent (one per payment event) and the HTTP 200 response should be returned regardless of whether detail extraction succeeds.

**Primary recommendation:** Implement in 3 slices — (1) backend webhook enhancement + meta storage, (2) REST API response enrichment, (3) frontend rendering. Prove the webhook→store flow first since it's the riskiest part (depends on Mollie API behavior). The frontend rendering is low-risk and follows established patterns in FactuurDetail.jsx.

## Recommendation

Use flat post meta keys for invoice-level payment details (`_mollie_payment_method`, `_mollie_paid_at`, `_mollie_dashboard_url`, `_mollie_consumer_name`, `_mollie_consumer_account`) and extend the existing `_installment_N_*` pattern for per-installment details (`_installment_N_mollie_method`, `_installment_N_mollie_paid_at`, `_installment_N_mollie_dashboard_url`). Store the full `details` object as a JSON blob in `_mollie_payment_details` for future-proofing, but render only the known fields (consumerName, consumerAccount for iDEAL).

Extract payment details in a try/catch wrapper that never blocks the webhook's HTTP 200 response. If the `$paymentLink->payments()` call fails, log the error and proceed with the status transition — payment details are enrichment, not a blocking requirement.

## Don't Hand-Roll

| Problem | Existing Solution | Why Use It |
|---------|------------------|------------|
| Fetching payments for a payment link | `$paymentLink->payments()` (Mollie PHP SDK) | SDK method already exists on `PaymentLink` resource; returns `PaymentCollection` with full `Payment` objects |
| Payment method labels (iDEAL → readable name) | Mollie's `method` field is already a readable slug (`ideal`, `creditcard`, `banktransfer`) | Map to Dutch labels in frontend; no need to call Methods API |
| Dashboard URL construction | `$payment->_links->dashboard->href` | SDK returns full URL directly; no need to construct org ID URLs manually |
| Installment meta pattern | Existing `_installment_N_*` flat meta keys | Consistent with `_installment_N_amount`, `_installment_N_status`, etc. |

## Existing Code and Patterns

- `includes/class-mollie-webhook.php` — **Primary modification target.** After `isPaid()` check, add `$paymentLink->payments()` call to extract payment details. Both `handle_payment_link_webhook()` (Path 0b, full payment) and `handle_installment_paid()` (Path 0a, installments) need enrichment. The MollieClient is already instantiated and the `$payment_link` object is already fetched — just need to call `->payments()` on it.

- `includes/class-installment-payment-service.php` — Creates payment links for installments. No changes needed here; payment details are extracted at webhook time, not at creation time.

- `includes/class-mollie-payment.php` — Creates payment links for discipline invoices. No changes needed; same reasoning as above.

- `includes/class-rest-invoices.php` → `format_invoice_detail()` — **Must add payment detail fields.** Currently returns installment data with `status`, `due_date`, `paid_at`, `sent_at`. Add `mollie_method`, `mollie_paid_at`, `mollie_dashboard_url`, `mollie_consumer_name`, `mollie_consumer_account` at invoice level, and per-installment `mollie_method`, `mollie_paid_at`, `mollie_dashboard_url`.

- `src/pages/Finance/FactuurDetail.jsx` — **Must add "Betaalgegevens" section.** Currently 1084 lines with installment timeline table already rendered. Add a new card section after the installment timeline for payment details. The installment table already has a "Betaald op" column that could be enhanced with method + dashboard link.

- `vendor/mollie/mollie-api-php/src/Resources/Payment.php` — Payment object has `method` (string), `paidAt` (ISO 8601), `details` (stdClass|null with `consumerName`, `consumerAccount`, `consumerBic` for iDEAL), and `_links` (stdClass with `dashboard->href`).

- `vendor/mollie/mollie-api-php/src/Resources/PaymentLink.php` → `payments()` — Returns `PaymentCollection` via `paymentLinkPayments->pageFor()`. Each item is a full `Payment` resource.

- `includes/class-mollie-client.php` — Thin wrapper; already used in webhook handler. No changes needed.

## Constraints

- **Webhook must always return HTTP 200** — Existing decision (Mollie retry storms). Payment detail extraction must be wrapped in try/catch and never prevent the 200 response.
- **Webhook idempotency must be preserved** — Existing `rondo_paid` status check and installment `betaald` check must remain. Payment details should be stored before the status transition (so they're available even if a duplicate webhook arrives and exits early).
- **Payment details are method-specific** — `details` varies by payment method: iDEAL has `consumerName`/`consumerAccount`/`consumerBic`, credit card has `cardHolder`/`cardNumber`, PayPal has `consumerName`/`consumerAccount` (email). Frontend must handle null/missing fields gracefully.
- **Dashboard URL only on Payment objects** — `_links->dashboard` exists on `Payment` resources but NOT on `PaymentLink` resources. This is why we need the `->payments()` sub-call.
- **Multiple payments per payment link** — A payment link can have multiple payment attempts (failed + succeeded). We want the *last paid* payment's details. Filter by `isPaid()` or take the last one in the collection.
- **Flat meta keys required** — Project constraint: no custom tables, use WordPress post meta. Follow existing `_installment_N_*` pattern.
- **No backfill** — Per M002-CONTEXT.md, existing paid invoices won't have payment details. This is acceptable.
- **Multi-account support** — Webhook already handles multi-account via `_payment_account_id` meta and `get_mollie_api_key_for_account()`. Payment details extraction uses the same MollieClient instance.

## Common Pitfalls

- **`$paymentLink->payments()` returns a paginated collection, not a single Payment** — Must iterate or take the first/last item. For payment links, there's typically one payment (the successful one), but failed attempts may also appear. Filter for `isPaid()` status or take the last item with `paidAt` set.

- **`_links->dashboard` may be null in test mode** — Dashboard links might not be present for test payments. Always null-check before accessing `->href`. Store empty string if unavailable.

- **`details` is null until payment is completed** — The `details` property (consumerName etc.) is only populated after successful payment. For iDEAL, `consumerName` and `consumerAccount` are available immediately. For bank transfer, they appear "one banking day after." Since we're processing the webhook at payment confirmation time, details should be available for iDEAL but might not be for all methods.

- **Storing the extra API call result after status transition** — If we transition to `rondo_paid` first and then fail to fetch payment details, we'd need to re-process. Better to fetch details first, store meta, then do the status transition. This way duplicate webhooks (which exit early on `rondo_paid` check) still have the details already stored.

- **`paidAt` on Payment vs PaymentLink** — PaymentLink has its own `paidAt` field, but that's the PaymentLink's paid timestamp. The underlying Payment object's `paidAt` is more precise (includes time). Use the Payment object's value.

- **JSON encoding for `details` blob** — Use `wp_json_encode()` (WordPress wrapper) not `json_encode()` to ensure proper escaping. Store as string in `_mollie_payment_details` meta.

## Open Risks

- **Mollie API rate limits during webhook processing** — The extra `$paymentLink->payments()` call adds one API request per webhook. Mollie's rate limit is ~250 requests per 10 seconds. For normal operation (one payment at a time), this is not a concern. For bulk payment confirmations (e.g., multiple members paying simultaneously), the sequential webhook processing should naturally rate-limit.

- **Payment details timing for non-iDEAL methods** — For bank transfer payments, consumer details may not be available at webhook time (available "one banking day after"). The webhook should store whatever is available; no retry mechanism needed since bank transfer is rare for this use case.

- **Test mode dashboard links** — In test mode, dashboard links point to the test dashboard (`https://www.mollie.com/dashboard/org_xxx/payments/tr_xxx`). This should work correctly but should be verified during testing.

## Candidate Requirements

Based on the research, these should be surfaced during planning:

1. **Payment details must not block webhook processing** — Table stakes; failure to fetch details should degrade gracefully.
2. **Method-specific details displayed conditionally** — iDEAL shows consumer name/IBAN; credit card shows card holder/last 4 digits; others show what's available.
3. **Dashboard link opens in new tab** — Standard UX for external links.
4. **Payment details section only shown for Mollie-paid invoices** — Rabobank-paid invoices won't have this data; section should be absent, not empty.
5. **Per-installment details in installment table** — Extend existing table columns rather than creating a separate section.

## Skills Discovered

| Technology | Skill | Status |
|------------|-------|--------|
| Mollie PHP SDK | No specific agent skill | none found |
| WordPress REST API | No specific agent skill | none found |
| React/Tailwind UI | frontend-design | installed (available in `<available_skills>`) |

No additional skills needed — this is a standard WordPress/React feature addition following established patterns.

## Sources

- Mollie Payment object properties: `vendor/mollie/mollie-api-php/src/Resources/Payment.php` — `method`, `paidAt`, `details` (stdClass), `_links` (stdClass with `dashboard->href`)
- Mollie PaymentLink `->payments()` method: `vendor/mollie/mollie-api-php/src/Resources/PaymentLink.php:168-176`
- PaymentLinkPaymentEndpointCollection: `vendor/mollie/mollie-api-php/src/EndpointCollection/PaymentLinkPaymentEndpointCollection.php` — `pageFor()` and `pageForId()` methods
- Mollie v1 API docs (v2 similar structure): `details` object varies by payment method — iDEAL provides `consumerName`, `consumerAccount`, `consumerBic`
- Dashboard URL format confirmed via SDK test fixture: `vendor/mollie/mollie-api-php/src/Fake/Responses/payment-list.json` — `"dashboard": {"href": "https://www.mollie.com/dashboard/org_12345678/payments/tr_7UhSN1zuXS"}`
- Existing webhook handler: `includes/class-mollie-webhook.php` — 4-path routing, `isPaid()` check, installment tracking
- Existing REST response: `includes/class-rest-invoices.php` → `format_invoice_detail()` — installment data with `status`, `due_date`, `paid_at`, `sent_at`
- Existing frontend: `src/pages/Finance/FactuurDetail.jsx` — installment timeline table at lines 746-790

## Proving Order

1. **Prove first: Webhook → payment detail extraction** — This is the riskiest part. Can we call `$paymentLink->payments()` successfully and get a Payment object with `method`, `paidAt`, `_links->dashboard`? Test with a real Mollie test-mode payment.
2. **Then: Meta storage + REST response** — Standard WordPress meta + REST field addition. Low risk.
3. **Last: Frontend rendering** — Pure React UI work following FactuurDetail.jsx patterns. Lowest risk.

## Existing Pattern Reuse

- **Installment meta pattern** (`_installment_N_*`) for per-installment payment details
- **Webhook try/catch with error_log** for non-blocking enrichment
- **`format_invoice_detail()` field addition** for REST API response
- **FactuurDetail.jsx card section pattern** for new "Betaalgegevens" UI section
- **`InstallmentStatusBadge` component pattern** for payment method badge
- **External link pattern** (`target="_blank" rel="noopener noreferrer"`) for Mollie Dashboard link
