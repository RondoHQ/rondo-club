# Requirements: Rondo Club

**Defined:** 2026-02-15
**Core Value:** Club administrators can manage their members, teams, and club operations through a single integrated system

## v26.0 Requirements

Requirements for discipline case invoicing. Each maps to roadmap phases.

### Navigation (NAV)

- [ ] **NAV-01**: Financiën section appears in sidebar with Contributie, Facturen, and Instellingen sub-items
- [ ] **NAV-02**: Contributie page moves from current sidebar position to under Financiën

### Invoice Data Model (INV)

- [ ] **INV-01**: Invoice CPT (`rondo_invoice`) stores invoice number, member link, total amount, status, payment link, PDF path
- [ ] **INV-02**: Invoice numbering follows `2026T001` format (calendar year + T + zero-padded sequential number)
- [ ] **INV-03**: Invoice statuses: Draft, Sent, Paid, Overdue — with due date tracking
- [ ] **INV-04**: Each invoice links to one or more discipline cases as line items (ACF repeater or relationship)
- [ ] **INV-05**: Overdue detection when sent invoice passes due date (configurable payment term days)

### Invoice Creation (CREATE)

- [ ] **CREATE-01**: User can select one or more uninvoiced discipline cases on member's Tuchtzaken tab
- [ ] **CREATE-02**: Selected cases' fees (Boete field) are summed into invoice total
- [ ] **CREATE-03**: Invoice created in Draft status with case details as line items

### PDF Generation (PDF)

- [ ] **PDF-01**: Invoice PDF generated using mPDF library with club logo, name, address, and contact email from settings
- [ ] **PDF-02**: Invoice PDF contains member name, address, and email address
- [ ] **PDF-03**: Invoice PDF lists each discipline case with match description, sanction, and fee amount
- [ ] **PDF-04**: Invoice PDF shows total amount, bank account number (IBAN), and payment clause from settings
- [ ] **PDF-05**: Invoice PDF includes invoice number, invoice date, and due date

### Rabobank Payment Integration (PAY)

- [ ] **PAY-01**: OAuth 2.0 Premium flow with Rabobank developer portal for betaalverzoek API
- [ ] **PAY-02**: System creates payment request via Rabobank API with invoice amount and description
- [ ] **PAY-03**: Payment link from Rabobank stored on invoice and included in email
- [ ] **PAY-04**: Rabobank API credentials (client ID, secret) stored securely in finance settings
- [ ] **PAY-05**: Sandbox/production toggle for development vs live usage

### Email Delivery (EMAIL)

- [ ] **EMAIL-01**: Invoice email sent via wp_mail to member's email from person contact_info
- [ ] **EMAIL-02**: Email body uses configurable template with variables ({naam}, {betaallink}, {factuur_nummer}, {tuchtzaken_lijst}, {totaal_bedrag})
- [ ] **EMAIL-03**: Invoice PDF attached to email

### Finance Settings (SET)

- [ ] **SET-01**: Finance settings page at Financiën > Instellingen
- [ ] **SET-02**: Club invoice details: organization name, address (multi-line), contact email
- [ ] **SET-03**: Bank account field (IBAN) and payment clause text
- [ ] **SET-04**: Payment term days (default 14)
- [ ] **SET-05**: Email template editor with variable placeholder documentation
- [ ] **SET-06**: Rabobank API credential fields (client ID, secret, environment toggle)

### Invoice Management (MGMT)

- [ ] **MGMT-01**: Facturen page lists all invoices with columns: number, member name, amount, status, date sent
- [ ] **MGMT-02**: Invoice detail view with full info, PDF download, and status actions
- [ ] **MGMT-03**: User can send invoice (transitions Draft → Sent, generates PDF, creates payment link, sends email)
- [ ] **MGMT-04**: User can mark invoice as Paid manually
- [ ] **MGMT-05**: User can resend invoice email
- [ ] **MGMT-06**: Invoice history visible on member's profile page

## Future Requirements

### Membership Fee Invoicing

- **FEE-INV-01**: Generate invoices from Contributie page for membership fees
- **FEE-INV-02**: Batch invoicing for all members in a season
- **FEE-INV-03**: Automated payment status checking via Rabobank API webhook

## Out of Scope

| Feature | Reason |
|---------|--------|
| Membership fee invoicing | Future extension — architecture supports it via invoice type field |
| Batch invoicing from discipline cases list | One per member from profile is sufficient for now |
| Automated payment status via Rabobank webhook | Manual mark-as-paid sufficient for v1 |
| Credit notes / partial payments | Future enhancement |
| Multiple payment providers | Rabobank only for now |

## Traceability

| Requirement | Phase | Status |
|-------------|-------|--------|
| NAV-01 | Phase 178 | Pending |
| NAV-02 | Phase 178 | Pending |
| SET-01 | Phase 178 | Pending |
| SET-02 | Phase 178 | Pending |
| SET-03 | Phase 178 | Pending |
| SET-04 | Phase 178 | Pending |
| SET-05 | Phase 178 | Pending |
| SET-06 | Phase 178 | Pending |
| INV-01 | Phase 179 | Pending |
| INV-02 | Phase 179 | Pending |
| INV-03 | Phase 179 | Pending |
| INV-04 | Phase 179 | Pending |
| INV-05 | Phase 179 | Pending |
| CREATE-01 | Phase 180 | Pending |
| CREATE-02 | Phase 180 | Pending |
| CREATE-03 | Phase 180 | Pending |
| PDF-01 | Phase 181 | Pending |
| PDF-02 | Phase 181 | Pending |
| PDF-03 | Phase 181 | Pending |
| PDF-04 | Phase 181 | Pending |
| PDF-05 | Phase 181 | Pending |
| PAY-01 | Phase 182 | Pending |
| PAY-02 | Phase 182 | Pending |
| PAY-03 | Phase 182 | Pending |
| PAY-04 | Phase 182 | Pending |
| PAY-05 | Phase 182 | Pending |
| EMAIL-01 | Phase 183 | Pending |
| EMAIL-02 | Phase 183 | Pending |
| EMAIL-03 | Phase 183 | Pending |
| MGMT-01 | Phase 184 | Pending |
| MGMT-02 | Phase 184 | Pending |
| MGMT-03 | Phase 184 | Pending |
| MGMT-04 | Phase 184 | Pending |
| MGMT-05 | Phase 184 | Pending |
| MGMT-06 | Phase 184 | Pending |

**Coverage:**
- v26.0 requirements: 35 total
- Mapped to phases: 35 (100% ✓)
- Unmapped: 0

**Phase Distribution:**
- Phase 178 (Finance Navigation & Settings Backend): 8 requirements
- Phase 179 (Invoice Data Model & REST API): 5 requirements
- Phase 180 (Invoice Creation Flow): 3 requirements
- Phase 181 (PDF Generation): 5 requirements
- Phase 182 (Rabobank Payment Integration): 5 requirements
- Phase 183 (Email Delivery): 3 requirements
- Phase 184 (Invoice Management UI): 6 requirements

---
*Requirements defined: 2026-02-15*
*Last updated: 2026-02-15 after roadmap creation — 100% coverage validated*

## v27.0 Requirements

### Configuration (CONF)

- [ ] **CONF-01**: Admin can save a Mollie API key (test or live) in Finance Settings
- [ ] **CONF-02**: System automatically derives test/live mode from API key prefix (`test_` vs `live_`)
- [ ] **CONF-03**: Admin can select the default payment provider (Rabobank or Mollie) in Finance Settings
- [ ] **CONF-04**: Default payment provider persists and is returned by the Finance Settings REST endpoint
- [ ] **CONF-05**: Mollie API key is stored encrypted at rest using the existing `CredentialEncryption` pattern

### Payment Link Creation (PYMT)

- [ ] **PYMT-01**: `MolliePayment::create_payment_link()` creates a Mollie payment via the Payments API (`$mollie->payments->create()`)
- [ ] **PYMT-02**: Generated Mollie checkout URL is stored in the invoice's ACF `payment_link` field (enabling email delivery via existing `InvoiceEmailSender`)
- [ ] **PYMT-03**: Mollie payment ID (`tr_xxx`) is stored in `_mollie_payment_id` post meta for webhook lookup
- [ ] **PYMT-04**: Payment link is reused — if `_mollie_payment_id` already exists on the invoice, no new payment is created

### Webhook & Status Updates (WHKT)

- [ ] **WHKT-01**: Public REST endpoint exists at `POST /rondo/v1/mollie/webhook` (no WordPress authentication required)
- [ ] **WHKT-02**: Webhook handler re-fetches payment status from Mollie API using the received ID (never trusts POST body alone)
- [ ] **WHKT-03**: When payment status is `paid`, handler transitions the invoice to the `rondo_paid` post status
- [ ] **WHKT-04**: Webhook handler is idempotent — processing the same event twice has no side effects
- [ ] **WHKT-05**: Webhook handler always returns HTTP 200, including on errors (errors logged internally)

### Provider Routing (WIRE)

- [ ] **WIRE-01**: When Mollie is the active provider, `RestInvoices::send_invoice()` uses `MolliePayment` to generate the payment link
- [ ] **WIRE-02**: When Rabobank is the active provider, `RestInvoices::send_invoice()` behavior is completely unchanged from v26.0

### Settings UI (UI)

- [ ] **UI-01**: Finance Settings Mollie section includes an API key input field
- [ ] **UI-02**: Finance Settings includes a payment provider selector (Rabobank / Mollie)
- [ ] **UI-03**: Finance Settings shows a test/live mode badge derived from the stored key prefix
- [ ] **UI-04**: Full Mollie API key is never exposed in REST responses (`mollie_has_api_key` bool + `mollie_environment` string only)

## v28.0+ Requirements (Deferred)

### Membership Fee Payments

- **FEES-01**: Admin can bulk-send Mollie payment requests to members for membership fees
- **FEES-02**: Members can choose to pay in 4 or 10 installments at first payment
- **FEES-03**: Installment plans use Mollie Subscriptions API (member chooses at first payment)
- **FEES-04**: Per-season setting controls whether Nikki or Mollie handles fee collection

### Advanced Invoice Features

- **ADV-01**: Admin can initiate Mollie refunds from the invoice detail page
- **ADV-02**: Payment status polling fallback for webhooks that were not delivered
- **ADV-03**: iDEAL-only payment method restriction at code level

## v27.0 Out of Scope

| Feature | Reason |
|---------|--------|
| Mollie OAuth (multi-merchant) | Single-club app — API key auth is sufficient and simpler |
| Payment Links API | Designed for reusable links; Payments API is correct for per-invoice payments |
| iDEAL enforcement in code | Mollie Dashboard controls enabled payment methods; no code needed for Dutch accounts |
| Refunds UI | Admin handles refunds in Mollie Dashboard; discipline case appeals are infrequent |
| HMAC webhook verification | Standard Mollie webhooks don't send HMAC; security provided by API re-fetch |
| Membership fee integration | Deferred to v28.0 — separate scope |

## v27.0 Traceability

| Requirement | Phase | Status |
|-------------|-------|--------|
| CONF-01 | Phase 186 | Pending |
| CONF-02 | Phase 186 | Pending |
| CONF-03 | Phase 186 | Pending |
| CONF-04 | Phase 186 | Pending |
| CONF-05 | Phase 186 | Pending |
| PYMT-01 | Phase 187 | Pending |
| PYMT-02 | Phase 187 | Pending |
| PYMT-03 | Phase 187 | Pending |
| PYMT-04 | Phase 187 | Pending |
| WHKT-01 | Phase 188 | Pending |
| WHKT-02 | Phase 188 | Pending |
| WHKT-03 | Phase 188 | Pending |
| WHKT-04 | Phase 188 | Pending |
| WHKT-05 | Phase 188 | Pending |
| WIRE-01 | Phase 189 | Pending |
| WIRE-02 | Phase 189 | Pending |
| UI-01 | Phase 190 | Pending |
| UI-02 | Phase 190 | Pending |
| UI-03 | Phase 190 | Pending |
| UI-04 | Phase 190 | Pending |

**Coverage:**
- v27.0 requirements: 20 total
- Mapped to phases: 20 (100% ✓)
- Unmapped: 0

**Phase Distribution:**
- Phase 186 (SDK + FinanceConfig + MollieClient): 5 requirements
- Phase 187 (MolliePayment — Payment Link Creation): 4 requirements
- Phase 188 (MollieWebhook — Automatic Status Update): 5 requirements
- Phase 189 (RestInvoices — Provider Routing): 2 requirements
- Phase 190 (Finance Settings UI): 4 requirements

---
*v27.0 requirements defined: 2026-02-17*
*Last updated: 2026-02-17 after initial definition*
