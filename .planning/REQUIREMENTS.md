# Requirements: Rondo Club

**Defined:** 2026-02-18
**Core Value:** Club administrators can manage their members, teams, and club operations through a single integrated system

## v28.0 Requirements

Requirements for membership fee invoicing with Mollie payment plans. Each maps to roadmap phases.

### Billing Configuration

- [ ] **BILL-01**: Admin can toggle per-season billing method between Nikki (external) and Rondo invoicing
- [ ] **BILL-02**: Admin can enable/disable 3-installment payment plan per season
- [ ] **BILL-03**: Admin can enable/disable 8-installment payment plan per season
- [ ] **BILL-04**: Admin can configure per-installment administration fee amount
- [ ] **BILL-05**: Admin can configure email template for initial membership invoice
- [ ] **BILL-06**: Admin can configure email template for installment follow-up emails
- [ ] **BILL-07**: Admin can configure email template for overdue payment reminders

### Invoice Creation

- [ ] **INV-01**: Admin can create a concept membership fee invoice for a single member from their calculated fee
- [ ] **INV-02**: Admin can bulk-create concept invoices for all eligible members in one action
- [ ] **INV-03**: Invoices have a type field distinguishing discipline vs membership fee invoices
- [ ] **INV-04**: Bulk creation runs asynchronously via batched WP-Cron to avoid timeouts
- [ ] **INV-05**: Admin can see progress of bulk invoice creation job

### Member Payment Experience

- [ ] **PAY-01**: Member receives email with link to a token-secured public payment page
- [ ] **PAY-02**: Member can view their invoice details on the public payment page without logging in
- [ ] **PAY-03**: Member can choose to pay the full amount at once
- [ ] **PAY-04**: Member can choose to pay in 3 installments (Sep, Nov 25, Feb 25) if enabled
- [ ] **PAY-05**: Member can choose to pay in 8 installments (Sep + Oct-Apr 25th monthly) if enabled
- [ ] **PAY-06**: Administration fee is added per installment and clearly displayed before plan selection
- [ ] **PAY-07**: Member is redirected to Mollie to pay the first installment immediately after selecting a plan
- [ ] **PAY-08**: Public payment page is mobile-friendly (members open from email on phone)

### Installment Management

- [ ] **INST-01**: Each installment has tracked amount, status, due date, and Mollie payment ID
- [ ] **INST-02**: Mollie webhook correctly identifies which installment was paid (reverse-lookup pattern)
- [ ] **INST-03**: Automatic installment emails sent on the 25th of each scheduled month with fresh Mollie payment link
- [ ] **INST-04**: Invoice is marked fully paid only when all installments are completed
- [ ] **INST-05**: Admin can view installment progress/timeline on the invoice detail page

### Overdue & Reminders

- [ ] **REM-01**: Automatic reminder email sent 2 weeks after an unpaid installment due date
- [ ] **REM-02**: Second reminder email sent 3 weeks after unpaid due date with BCC to treasurer
- [ ] **REM-03**: Daily WP-Cron sweeper detects overdue installments and triggers reminders

### Facturen Page Enhancements

- [ ] **FACT-01**: Admin can filter invoices by type (discipline vs membership)
- [ ] **FACT-02**: Admin can filter invoices by payment plan (full / 3 installments / 8 installments)
- [ ] **FACT-03**: Admin can filter invoices by overdue status
- [ ] **FACT-04**: Contributie page hides Nikki columns when Rondo invoicing is active for the season

### Access Control

- [ ] **AUTH-01**: Users with finance capability can manage membership invoicing without being WordPress administrators

## Future Requirements

Deferred to future release. Tracked but not in current roadmap.

### Advanced Payment Options

- **ADV-01**: Mollie Recurring/SEPA Direct Debit for fully automatic installment collection
- **ADV-02**: Rabobank payment plan support (currently Mollie-only for installments)

### Reporting

- **RPT-01**: Payment status dashboard with season-level collection progress
- **RPT-02**: Export of payment status per member to Google Sheets

## Out of Scope

Explicitly excluded. Documented to prevent scope creep.

| Feature | Reason |
|---------|--------|
| Mollie Recurring/Subscriptions | Requires SEPA creditor ID and member mandate; manual payment links are simpler and sufficient |
| Configurable installment schedules | Fixed 3 and 8 plans match Dutch football club norms; custom schedules add complexity without value |
| Member self-service portal (beyond payment) | Payment landing page is single-purpose; full member portal is a separate milestone |
| Partial payment handling | Each installment is a fixed amount via Mollie; partial payments not supported by the payment link model |
| Multi-season invoice management | One season at a time; historical invoices viewable but not editable |
| Bank transfer / manual payment tracking | Mollie handles all payment processing; manual bank transfer reconciliation is out of scope |

## Traceability

Which phases cover which requirements. Updated during roadmap creation.

| Requirement | Phase | Status |
|-------------|-------|--------|
| BILL-01 | TBD | Pending |
| BILL-02 | TBD | Pending |
| BILL-03 | TBD | Pending |
| BILL-04 | TBD | Pending |
| BILL-05 | TBD | Pending |
| BILL-06 | TBD | Pending |
| BILL-07 | TBD | Pending |
| INV-01 | TBD | Pending |
| INV-02 | TBD | Pending |
| INV-03 | TBD | Pending |
| INV-04 | TBD | Pending |
| INV-05 | TBD | Pending |
| PAY-01 | TBD | Pending |
| PAY-02 | TBD | Pending |
| PAY-03 | TBD | Pending |
| PAY-04 | TBD | Pending |
| PAY-05 | TBD | Pending |
| PAY-06 | TBD | Pending |
| PAY-07 | TBD | Pending |
| PAY-08 | TBD | Pending |
| INST-01 | TBD | Pending |
| INST-02 | TBD | Pending |
| INST-03 | TBD | Pending |
| INST-04 | TBD | Pending |
| INST-05 | TBD | Pending |
| REM-01 | TBD | Pending |
| REM-02 | TBD | Pending |
| REM-03 | TBD | Pending |
| FACT-01 | TBD | Pending |
| FACT-02 | TBD | Pending |
| FACT-03 | TBD | Pending |
| FACT-04 | TBD | Pending |
| AUTH-01 | TBD | Pending |

**Coverage:**
- v28.0 requirements: 33 total
- Mapped to phases: 0
- Unmapped: 33

---
*Requirements defined: 2026-02-18*
*Last updated: 2026-02-18 after initial definition*
