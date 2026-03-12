# M002: Mollie Payment Details

**Vision:** When a Mollie payment is confirmed, store the payment method, timestamp, dashboard URL, and consumer details on the invoice — and display them on the invoice detail page so admins can see *how* an invoice was paid and jump directly to the Mollie Dashboard.

## Success Criteria

- A paid invoice (via Mollie webhook) has payment method, paidAt timestamp, Mollie dashboard URL, and consumer details stored as post meta
- The invoice detail page shows a "Betaalgegevens" section with payment method, paid-at time, and a clickable "Bekijk in Mollie" link
- For iDEAL payments, the consumer name and IBAN are displayed
- For other payment methods, available details are displayed gracefully (no empty/broken state)
- Multi-installment invoices show per-installment payment details (method, paid-at, Mollie link) in the installment timeline table
- The section is absent (not empty) for invoices without Mollie payment data (e.g., manually marked paid, Rabobank)
- Existing webhook idempotency is preserved — duplicate webhooks remain silent no-ops
- Failure to fetch payment details never blocks the HTTP 200 webhook response or the status transition

## Key Risks / Unknowns

- **PaymentLink→payments() sub-call** — Payment links (`pl_xxx`) don't carry payment details directly. We must call `$paymentLink->payments()` to get underlying Payment objects. If this returns empty or fails, we get no details. This is the only real unknown.

## Proof Strategy

- PaymentLink→payments() sub-call → retire in S01 by shipping the webhook enhancement to production and verifying that a real Mollie test-mode payment stores method + paidAt + dashboard URL in post meta

## Verification Classes

- Contract verification: `wp-cli` meta inspection on production after a test payment confirms data is stored; browser verification of REST API response containing new fields; visual verification of FactuurDetail page
- Integration verification: Real Mollie test-mode payment triggers webhook → data stored → REST returns it → UI renders it
- Operational verification: Deploy to production, verify webhook still returns 200 on duplicate calls, verify no regressions on existing paid invoices (they simply lack the new section)
- UAT / human verification: Admin views a newly-paid invoice on production and sees payment details with working Mollie Dashboard link

## Milestone Definition of Done

This milestone is complete only when all are true:

- Webhook extracts and stores payment details from Mollie Payment objects for both full-payment and installment flows
- REST API `format_invoice_detail()` returns payment detail fields at invoice level and per-installment level
- FactuurDetail page renders "Betaalgegevens" section for paid invoices with Mollie data
- Installment timeline table includes payment method, paid-at, and Mollie Dashboard link per installment
- A real Mollie test-mode payment on production triggers the full flow: webhook → meta stored → API returns data → UI displays it
- Existing paid invoices display normally (no "Betaalgegevens" section, no errors)
- No ESLint errors introduced

## Requirement Coverage

- Covers: No active requirements (Active section is empty in REQUIREMENTS.md)
- This milestone creates new capability; requirements will be validated during execution
- Orphan risks: none

## Slices

- [x] **S01: Webhook payment detail extraction + REST API + Invoice detail UI** `risk:medium` `depends:[]`
  > After this: A Mollie payment triggers the webhook, payment details (method, paidAt, dashboard URL, consumer details) are stored as post meta, returned by the REST API, and displayed in a "Betaalgegevens" section on the invoice detail page — including per-installment details in the installment timeline table. Verified on production with a real Mollie test-mode payment.

## Boundary Map

### S01 (single vertical slice)

Produces:
- `MollieWebhook::extract_payment_details()` private method that calls `$paymentLink->payments()`, finds the paid payment, and stores flat meta keys: `_mollie_payment_method`, `_mollie_paid_at`, `_mollie_dashboard_url`, `_mollie_consumer_name`, `_mollie_consumer_account`, `_mollie_payment_details` (JSON blob)
- Per-installment meta: `_installment_N_mollie_method`, `_installment_N_mollie_paid_at`, `_installment_N_mollie_dashboard_url`
- REST `format_invoice_detail()` returns new fields: `mollie_payment_method`, `mollie_paid_at`, `mollie_dashboard_url`, `mollie_consumer_name`, `mollie_consumer_account` at invoice level; per-installment objects gain `mollie_method`, `mollie_paid_at`, `mollie_dashboard_url`
- FactuurDetail.jsx "Betaalgegevens" card section rendering payment details with Dutch method labels, formatted timestamp, and external Mollie Dashboard link
- Installment timeline table enhanced with method + dashboard link columns for paid installments

Consumes:
- Existing `MollieWebhook::handle_payment_link_webhook()` and `handle_installment_paid()` methods (extended)
- Existing `format_invoice_detail()` in `class-rest-invoices.php` (extended)
- Existing `FactuurDetail.jsx` installment table and card layout patterns (extended)
- Mollie PHP SDK `PaymentLink::payments()` and `Payment` resource properties
