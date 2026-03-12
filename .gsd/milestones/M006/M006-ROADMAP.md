# M006: Markeer als betaald

**Vision:** When a user manually marks an invoice as paid, an audit trail is visible in the Betaalgegevens card showing who marked it and when.

## Success Criteria

- Manually marking an invoice as paid stores a timestamp and the acting user
- The Betaalgegevens card shows "Handmatig gemarkeerd als betaald" with date/time and user name
- The card renders for manually-paid invoices even without Mollie payment data
- Invoices paid via Mollie continue to show Mollie details as before
- Deployed to production

## Key Risks / Unknowns

None — follows established patterns (`_invoice_sent_by_user_id` for user tracking, Mollie fields for payment detail display).

## Verification Classes

- Contract verification: mark an invoice as paid via the UI, verify meta is stored and card renders
- Integration verification: deployed to production
- Operational verification: none
- UAT / human verification: user verifies on production

## Milestone Definition of Done

This milestone is complete only when all are true:

- Backend stores audit meta on manual paid transition
- REST API returns the new fields
- Betaalgegevens card displays manual-paid info
- Mollie-paid invoices still display correctly
- Deployed to production and verified

## Slices

- [x] **S01: Manual paid audit trail + display** `risk:low` `depends:[]`
  > After this: marking an invoice as paid shows who did it and when in the Betaalgegevens card — deployed to production

## Boundary Map

### S01

Produces:
- Post meta `_manually_marked_paid_at` and `_manually_marked_paid_by` stored on paid transition in `update_invoice_status()`
- Fields `manually_marked_paid_at` and `manually_marked_paid_by` (with user name) in `format_invoice_detail()` response
- Betaalgegevens card renders for both Mollie-paid and manually-paid invoices

Consumes:
- nothing (single slice)
