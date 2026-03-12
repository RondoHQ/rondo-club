# M006: Markeer als betaald — Context

**Gathered:** 2026-03-12
**Status:** Ready for planning

## Project Description

When a user manually marks an invoice as paid ("Markeer als betaald"), record who did it and when. Display this information in the Betaalgegevens card on the invoice detail page.

## Why This Milestone

Currently, when a user manually marks an invoice as paid via `update_invoice_status`, the status changes to `rondo_paid` but no audit trail is kept. There's no way to see who marked it or when. For invoices paid via Mollie, this info comes from the webhook (`mollie_paid_at`, `mollie_consumer_name`), but manual payments have no equivalent.

## User-Visible Outcome

### When this milestone is complete, the user can:

- Mark an invoice as paid and see "Handmatig gemarkeerd als betaald" in the Betaalgegevens card
- See the date/time and the name of the user who marked it as paid

### Entry point / environment

- Entry point: https://rondo.svawc.nl/financien/facturen/{id}
- Environment: production WordPress site
- Live dependencies involved: none

## Completion Class

- Contract complete means: meta is stored on status transition, REST API returns it, frontend renders it
- Integration complete means: deployed to production and verified
- Operational complete means: none

## Final Integrated Acceptance

To call this milestone complete, we must prove:

- Manually marking an invoice as paid stores the timestamp and user ID
- The Betaalgegevens card shows this info for manually-paid invoices (even when no Mollie data exists)
- Invoices paid via Mollie still show Mollie payment details as before
- Deployed to production and verified

## Risks and Unknowns

- None — straightforward meta storage + UI display

## Existing Codebase / Prior Art

- `includes/class-rest-invoices.php` line ~1044: `update_invoice_status()` transitions to `paid` but stores no audit meta. Pattern to follow: the `sent` transition already stores `_invoice_sent_by_user_id` and `_invoice_last_sent_by_user_id` (line ~1030).
- `src/pages/Finance/FactuurDetail.jsx` line ~830: Betaalgegevens card currently only renders when `invoice.mollie_payment_method` is set. Needs to also render for manually-paid invoices.
- `includes/class-rest-invoices.php` `format_invoice_detail()`: already returns Mollie payment fields; needs to also return manual-paid fields.

## Scope

### In Scope

- Store `_manually_marked_paid_at` (datetime) and `_manually_marked_paid_by` (user ID) when transitioning to paid via `update_invoice_status`
- Return these fields in `format_invoice_detail()` REST response
- Show in Betaalgegevens card: "Handmatig gemarkeerd als betaald op {date} door {user name}"
- Betaalgegevens card should render when either Mollie data OR manual-paid data exists
- Deploy to production

### Out of Scope / Non-Goals

- Changing the Mollie webhook flow (it already records its own payment details)
- Adding manual-paid tracking to installment-level payments (only invoice-level for now)
- Retroactively populating data for already-paid invoices

## Technical Constraints

- Use post meta (not ACF fields) for the audit data — consistent with `_invoice_sent_by_user_id` pattern
- `get_current_user_id()` provides the user who made the REST call
- `current_time('mysql')` for the timestamp

## Integration Points

- **REST API** — `format_invoice_detail()` must include the new fields
- **Frontend** — Betaalgegevens card condition and display
