# M002: Mollie Payment Details — Context

**Gathered:** 2026-03-12
**Status:** Ready for planning

## Project Description

Enrich invoice records with payment details from Mollie when payments are confirmed. Currently the webhook only transitions invoices to "paid" status — it discards all the rich data Mollie provides about the actual payment (method, dashboard link, paidAt timestamp, consumer details).

## Why This Milestone

When an invoice is paid via Mollie, the admin has no visibility into *how* it was paid. They can't see the payment method (iDEAL, credit card, etc.), when exactly the payment happened, or quickly jump to the Mollie Dashboard to inspect the transaction. This information is already available from the Mollie API but is currently ignored by the webhook handler.

## User-Visible Outcome

### When this milestone is complete, the user can:

- See payment details on the invoice detail page: payment method, paid-at timestamp, and (for iDEAL) consumer name/IBAN
- Click a "Bekijk in Mollie" link to go directly to the payment in the Mollie Dashboard
- See per-installment payment details (method, paid-at, Mollie link) for multi-installment invoices

### Entry point / environment

- Entry point: Invoice detail page at `/financien/facturen/:id`
- Environment: Production (https://rondo.svawc.nl)
- Live dependencies involved: Mollie API (webhook + payment link payments endpoint)

## Completion Class

- Contract complete means: Webhook stores payment metadata; REST API returns it; frontend renders it
- Integration complete means: A real Mollie test-mode payment triggers the webhook, data is stored and displayed
- Operational complete means: Existing paid invoices won't have the new data (that's expected), new payments going forward will

## Final Integrated Acceptance

To call this milestone complete, we must prove:

- A Mollie webhook for a payment link results in payment method, paidAt, dashboard URL, and consumer details being stored on the invoice
- The invoice detail page displays a "Betaalgegevens" section with method, timestamp, and a clickable Mollie Dashboard link
- Installment invoices show per-installment payment details in the installment timeline

## Risks and Unknowns

- **Payment Link → Payments sub-call** — Payment links (`pl_xxx`) don't directly carry payment details. We must call `$paymentLink->payments()` to get the underlying Payment objects which have `method`, `details`, `paidAt`, and `_links.dashboard`. This is an extra API call during webhook processing.
- **Dashboard link availability** — The `_links.dashboard` property on Payment objects uses the format `https://www.mollie.com/dashboard/org_{orgId}/payments/{tr_xxx}`. This is present on Payment objects but NOT on PaymentLink objects. We need the underlying payment ID.
- **iDEAL-specific details** — The `details` property on Payment varies by method. iDEAL provides `consumerName` and `consumerAccount` (IBAN). Other methods provide different or no details. Frontend must handle this gracefully.

## Existing Codebase / Prior Art

- `includes/class-mollie-webhook.php` — Current webhook handler. Fetches payment link, checks `isPaid()`, transitions status. This is where we add the payment detail extraction.
- `includes/class-mollie-payment.php` — Creates payment links via `$mollie->paymentLinks->create()`. Stores `_mollie_payment_link_id` meta.
- `includes/class-installment-payment-service.php` — Creates installment payment links. Stores `_installment_N_mollie_payment_id` meta.
- `includes/class-rest-invoices.php` — `format_invoice_detail()` builds the REST response. Needs new payment detail fields.
- `src/pages/Finance/FactuurDetail.jsx` — Invoice detail page. Needs "Betaalgegevens" section.
- `vendor/mollie/mollie-api-php/src/Resources/Payment.php` — Payment object has `method`, `details`, `paidAt`, `_links->dashboard`.
- `vendor/mollie/mollie-api-php/src/Resources/PaymentLink.php` — PaymentLink has `payments()` method to fetch associated payments.

> See `.gsd/DECISIONS.md` for all architectural and pattern decisions — it is an append-only register; read it during planning, append to it during execution.

## Relevant Requirements

- Invoice detail page should show payment information — enriches existing Facturen feature
- Mollie integration should provide admin visibility into payment transactions — operational transparency

## Scope

### In Scope

- Store payment details on webhook: payment method, paid-at, Mollie payment ID, dashboard URL, consumer details
- Expose payment details via REST API (`format_invoice_detail`)
- Display payment details on invoice detail page ("Betaalgegevens" section)
- Per-installment payment details for multi-installment invoices
- Handle method-specific `details` gracefully (iDEAL shows consumer info, others show what's available)

### Out of Scope / Non-Goals

- Backfilling payment data for already-paid invoices (would require re-querying Mollie for each)
- Mollie refund management from within Rondo Club
- Payment status polling or real-time updates
- Changing the payment link creation flow (that works fine)

## Technical Constraints

- Must not break existing webhook idempotency (paid invoices remain no-op on duplicate webhooks)
- Extra API call (`$paymentLink->payments()`) adds latency to webhook — must be non-blocking for the 200 response
- Payment details are method-specific (`details` varies per payment method) — store as JSON blob
- Dashboard URL only available on Payment objects, not PaymentLink objects

## Integration Points

- **Mollie Payment Links API** — `$paymentLink->payments()` to get associated Payment objects after payment confirmation
- **Mollie Webhook** — Receives `pl_xxx` ID, fetches payment link, now also fetches underlying payment for details
- **REST API** — `format_invoice_detail()` returns new payment fields
- **React frontend** — FactuurDetail.jsx renders new payment details section

## Open Questions

- **Store details as JSON or flat meta?** — Leaning toward flat meta keys (`_mollie_payment_method`, `_mollie_paid_at`, `_mollie_dashboard_url`, `_mollie_consumer_name`, `_mollie_consumer_account`) for simplicity and queryability, plus a `_mollie_payment_details` JSON blob for the full `details` object.
- **Installment details storage** — For installments, prefix with installment number: `_installment_N_mollie_method`, `_installment_N_mollie_paid_at`, etc. Or store on the existing installment meta pattern.
