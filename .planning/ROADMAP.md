# Roadmap: Rondo Club

## Milestones

- ✅ **v20.0 Configurable Roles** — Phases 151-154 (shipped 2026-02-08) — [Archive](milestones/v20.0-ROADMAP.md)
- ✅ **v21.0 Per-Season Fee Categories** — Phases 155-161 (shipped 2026-02-09) — [Archive](milestones/v21.0-ROADMAP.md)
- ✅ **v22.0 Design Refresh** — Phases 162-165 (shipped 2026-02-09) — [Archive](milestones/v22.0-ROADMAP.md)
- ✅ **v23.0 Former Members** — Phases 166-169 (shipped 2026-02-09) — [Archive](milestones/v23.0-ROADMAP.md)
- ✅ **v24.0 Demo Data** — Phases 170-174 (shipped 2026-02-12) — [Archive](milestones/v24.0-ROADMAP.md)
- ✅ **v24.1 Dead Feature Removal** — Phases 175-177 (shipped 2026-02-13) — [Archive](milestones/v24.1-ROADMAP.md)
- ✅ **v26.0 Discipline Case Invoicing** — Phases 178-185 (shipped 2026-02-16) — [Archive](milestones/v26.0-ROADMAP.md)
- 🚧 **v27.0 Mollie** — Phases 186-190 (in progress)

## Phases

<details>
<summary>v20.0 Configurable Roles (Phases 151-154) — SHIPPED 2026-02-08</summary>

- [x] Phase 151: Dynamic Filters (2/2 plans) — completed 2026-02-07
- [x] Phase 152: Role Settings (0/0 plans, pre-existing) — completed 2026-02-07
- [x] Phase 153: Wire Up Role Settings (1/1 plan) — completed 2026-02-08
- [x] Phase 154: Sync Cleanup (1/1 plan) — completed 2026-02-08

</details>

<details>
<summary>v21.0 Per-Season Fee Categories (Phases 155-161) — SHIPPED 2026-02-09</summary>

- [x] Phase 155: Fee Category Data Model (1/1 plan) — completed 2026-02-08
- [x] Phase 156: Fee Category Backend Logic (2/2 plans) — completed 2026-02-08
- [x] Phase 157: Fee Category REST API (2/2 plans) — completed 2026-02-09
- [x] Phase 158: Fee Category Settings UI (2/2 plans) — completed 2026-02-09
- [x] Phase 159: Fee Category Frontend Display (1/1 plan) — completed 2026-02-09
- [x] Phase 160: Configurable Family Discount (2/2 plans) — completed 2026-02-09
- [x] Phase 161: Configurable Matching Rules (2/2 plans) — completed 2026-02-09

</details>

<details>
<summary>v22.0 Design Refresh (Phases 162-165) — SHIPPED 2026-02-09</summary>

- [x] Phase 162: Foundation - Tailwind v4 & Tokens (1/1 plan) — completed 2026-02-09
- [x] Phase 163: Color System Migration (3/3 plans) — completed 2026-02-09
- [x] Phase 164: Component Styling & Dark Mode (2/2 plans) — completed 2026-02-09
- [x] Phase 165: PWA & Backend Cleanup (1/1 plan) — completed 2026-02-09

</details>

<details>
<summary>v23.0 Former Members (Phases 166-169) — SHIPPED 2026-02-09</summary>

- [x] Phase 166: Backend Foundation (1/1 plan) — completed 2026-02-09
- [x] Phase 167: Core Filtering (1/1 plan) — completed 2026-02-09
- [x] Phase 168: Visibility Controls (1/1 plan) — completed 2026-02-09
- [x] Phase 169: Contributie Logic (1/1 plan) — completed 2026-02-09

</details>

<details>
<summary>v24.0 Demo Data (Phases 170-174) — SHIPPED 2026-02-12</summary>

- [x] Phase 170: Fixture Format Design (1/1 plan) — completed 2026-02-11
- [x] Phase 171: Export Command Foundation (4/4 plans) — completed 2026-02-11
- [x] Phase 172: Data Anonymization (3/3 plans) — completed 2026-02-11
- [x] Phase 173: Import Command (3/3 plans) — completed 2026-02-11
- [x] Phase 174: End-to-End Verification (2/2 plans) — completed 2026-02-12

</details>

<details>
<summary>v24.1 Dead Feature Removal (Phases 175-177) — SHIPPED 2026-02-13</summary>

- [x] Phase 175: Backend Cleanup (2/2 plans) — completed 2026-02-13
- [x] Phase 176: Frontend Cleanup (2/2 plans) — completed 2026-02-13
- [x] Phase 177: Documentation Updates (2/2 plans) — completed 2026-02-13

</details>

### v26.0 Discipline Case Invoicing (SHIPPED 2026-02-16)

**Milestone Goal:** Enable the club to invoice members for discipline case fees with PDF generation, Rabobank payment links, and email delivery — tracked through a full invoice lifecycle.

#### Phase 178: Finance Navigation & Settings Backend
**Goal**: Finance section exists in navigation with Instellingen page showing configurable invoice details, bank account, payment terms, email template, and Rabobank API credentials.
**Depends on**: Phase 177 (v24.1 complete)
**Requirements**: NAV-01, NAV-02, SET-01, SET-02, SET-03, SET-04, SET-05, SET-06
**Success Criteria** (what must be TRUE):
  1. Financien section appears in sidebar with Contributie, Facturen (disabled), and Instellingen sub-items
  2. Contributie page accessible from new location, old navigation entry removed
  3. Finance settings page loads with empty form fields at Financien > Instellingen
  4. Admin can save club invoice details (name, address, contact email) and see them persist
  5. Admin can configure bank account (IBAN), payment term days, and payment clause text
  6. Admin can edit email template with variable placeholders and see documentation
  7. Admin can enter Rabobank API credentials (client ID, secret) with sandbox/production toggle
**Plans**: 2 plans

Plans:
- [x] 178-01-PLAN.md — Navigation restructuring + backend FinanceConfig class + REST API
- [x] 178-02-PLAN.md — Finance Settings page UI with all form sections

#### Phase 179: Invoice Data Model & REST API
**Goal**: Invoice CPT exists with ACF fields for lifecycle tracking, automatic invoice numbering, and REST API endpoints for CRUD operations.
**Depends on**: Phase 178
**Requirements**: INV-01, INV-02, INV-03, INV-04, INV-05
**Success Criteria** (what must be TRUE):
  1. Invoice CPT (rondo_invoice) registered with ACF fields: invoice_number, person (relationship), line_items (repeater with discipline_case relationship), total_amount, status, payment_link, pdf_path, sent_date, due_date
  2. Invoice statuses available: Draft, Sent, Paid, Overdue
  3. New invoices auto-generate invoice numbers in format 2026T001 (calendar year + T + sequential)
  4. Invoice REST API endpoints exist: list (/rondo/v1/invoices), get single, create, update status
  5. Overdue status auto-applies when sent invoice passes due date (via backend logic)
**Plans**: 2 plans

Plans:
- [x] 179-01-PLAN.md — Invoice CPT registration, statuses, ACF fields, and numbering service
- [x] 179-02-PLAN.md — Invoice REST API controller, overdue detection, and API client methods

#### Phase 180: Invoice Creation Flow
**Goal**: User can select uninvoiced discipline cases on member's Tuchtzaken tab and create a draft invoice that sums case fees.
**Depends on**: Phase 179
**Requirements**: CREATE-01, CREATE-02, CREATE-03
**Success Criteria** (what must be TRUE):
  1. Tuchtzaken tab shows checkboxes for discipline cases that don't have an invoice
  2. User can select one or more uninvoiced cases and click "Maak factuur" button
  3. Invoice created in Draft status with selected cases as line items and sum of Boete fields as total
  4. Invoice visible in member's profile after creation
**Plans**: 2 plans

Plans:
- [x] 180-01-PLAN.md — Backend invoiced-cases endpoint + Tuchtzaken tab selection UI with invoice creation
- [x] 180-02-PLAN.md — Invoice display on member profile sidebar

#### Phase 181: PDF Generation
**Goal**: Draft invoices can be converted to PDF documents with club branding, member details, case breakdown, and payment instructions.
**Depends on**: Phase 180
**Requirements**: PDF-01, PDF-02, PDF-03, PDF-04, PDF-05
**Success Criteria** (what must be TRUE):
  1. mPDF library installed via Composer
  2. Backend can generate PDF from invoice data with club logo, name, address, and contact email from settings
  3. PDF contains member name, address, and email from person record
  4. PDF lists each discipline case with match description, sanction, and fee amount
  5. PDF shows invoice number, invoice date, due date, total amount, bank account (IBAN), and payment clause
  6. Generated PDF stored in WordPress uploads directory and path saved to invoice
**Plans**: 1 plan

Plans:
- [x] 181-01-PLAN.md -- Install mPDF, create InvoicePdfGenerator class, add REST endpoints for PDF generation and download

#### Phase 182: Rabobank Payment Integration
**Goal**: Invoices can generate Rabobank betaalverzoek payment links via OAuth API integration.
**Depends on**: Phase 181
**Requirements**: PAY-01, PAY-02, PAY-03, PAY-04, PAY-05
**Success Criteria** (what must be TRUE):
  1. OAuth 2.0 Premium flow with Rabobank implemented using stored credentials
  2. Backend can create payment request via Rabobank API with invoice amount and description
  3. Payment link from API response stored on invoice record
  4. API credentials retrieved securely from finance settings (sodium encryption pattern)
  5. Sandbox/production environment toggle works correctly
**Plans**: 2 plans

Plans:
- [x] 182-01-PLAN.md -- RabobankOAuth class (OAuth 2.0 flow, token management) + RabobankPayment service (payment request creation)
- [x] 182-02-PLAN.md -- Frontend: Rabobank connection status UI, connect/disconnect buttons, API client methods

#### Phase 183: Email Delivery
**Goal**: Draft invoices can be sent via email with PDF attachment, payment link, and configurable template text.
**Depends on**: Phase 182
**Requirements**: EMAIL-01, EMAIL-02, EMAIL-03
**Success Criteria** (what must be TRUE):
  1. Send invoice action triggers email via wp_mail to member's email address
  2. Email body uses configured template with variable replacement: {naam}, {betaallink}, {factuur_nummer}, {tuchtzaken_lijst}, {totaal_bedrag}
  3. Invoice PDF attached to email as file
  4. Sending invoice transitions status from Draft to Sent and sets sent_date and due_date
**Plans**: 1 plan

Plans:
- [x] 183-01-PLAN.md -- InvoiceEmailSender service class, send invoice REST endpoint, and frontend API method

#### Phase 184: Invoice Management UI
**Goal**: Facturen page exists with invoice list, detail view, and status management actions.
**Depends on**: Phase 183
**Requirements**: MGMT-01, MGMT-02, MGMT-03, MGMT-04, MGMT-05, MGMT-06
**Success Criteria** (what must be TRUE):
  1. Facturen page accessible from Financien section showing all invoices
  2. Invoice list displays columns: number, member name, amount, status, date sent (sortable)
  3. Clicking invoice row opens detail view with full invoice info, PDF download button, and status actions
  4. User can send draft invoice (generates PDF, creates payment link, sends email, transitions to Sent)
  5. User can mark sent invoice as Paid manually (transitions status to Paid)
  6. User can resend invoice email for sent invoices
  7. Invoice history appears on member's profile page showing linked invoices
**Plans**: 2 plans

Plans:
- [x] 184-01-PLAN.md — Backend resend endpoint, frontend hooks, routes, and navigation enable
- [x] 184-02-PLAN.md — Facturen list page, detail page with actions, FinancesCard links, version bump

#### Phase 185: Invoice PDF Club Branding
**Goal:** Invoice PDF uses the club's own logo and colors instead of Rondo branding — headings and accent colors come from club settings.
**Depends on:** Phase 184
**Plans:** 1 plan

Plans:
- [x] 185-01-PLAN.md — Backend branding settings + PDF generator integration + frontend logo upload and color picker UI

### v27.0 Mollie (In Progress)

**Milestone Goal:** Add Mollie as a second payment provider for discipline case invoices alongside Rabobank — with encrypted API key storage, automatic provider selection, payment link generation, and webhook-driven invoice status updates.

#### Phase 186: SDK Installation + FinanceConfig + MollieClient
**Goal**: Mollie PHP SDK installed via Composer, API key and provider settings persisted in `FinanceConfig`, and a shared `MollieClient` wrapper ready for use by both payment and webhook classes.
**Depends on**: Phase 185
**Requirements**: CONF-01, CONF-02, CONF-03, CONF-04, CONF-05
**Success Criteria** (what must be TRUE):
  1. `mollie/mollie-api-php ^3.9` installed via Composer and autoloaded — `composer install` runs without errors
  2. `FinanceConfig::get_mollie_api_key()`, `update_mollie_api_key()`, `get_active_payment_provider()`, `update_active_payment_provider()` methods exist and store data correctly
  3. Mollie API key stored encrypted using existing `CredentialEncryption` pattern (sodium)
  4. `FinanceConfig::get_all_settings()` returns `mollie_has_api_key` (bool) and `mollie_environment` (`test`/`live`) — never the raw key
  5. `MollieClient::get()` returns a configured `MollieApiClient` instance using the stored key
  6. No user-visible changes on the site — this phase is backend-only
**Plans**: 1 plan

Plans:
- [x] 186-01-PLAN.md — Composer install, FinanceConfig Mollie methods, MollieClient class

#### Phase 187: MolliePayment — Payment Link Creation
**Goal**: `MolliePayment` class creates a Mollie payment via the Payments API, stores the checkout URL in the invoice's ACF `payment_link` field, and stores the Mollie payment ID for later webhook lookup.
**Depends on**: Phase 186
**Requirements**: PYMT-01, PYMT-02, PYMT-03, PYMT-04
**Success Criteria** (what must be TRUE):
  1. `MolliePayment::create_payment_link($invoice_id)` creates a Mollie payment using `$mollie->payments->create()` (Payments API, not Payment Links API)
  2. Amount formatted as string with exactly 2 decimal places (e.g., `"12.50"`) — no floating point formatting errors
  3. Invoice `payment_link` ACF field updated with the Mollie checkout URL (`_links->checkout->href`)
  4. `_mollie_payment_id` post meta stored on the invoice (e.g., `tr_xxx`) for webhook lookup
  5. If `_mollie_payment_id` already exists on the invoice, `create_payment_link()` reuses the existing checkout URL without creating a new Mollie payment
  6. `webhookUrl` is omitted when site URL contains `localhost` or `.local` (local dev safety)
**Plans**: 1 plan

Plans:
- [x] 187-01-PLAN.md — MolliePayment class + functions.php registration

#### Phase 188: MollieWebhook — Automatic Status Update
**Goal**: A dedicated public REST endpoint receives Mollie webhook events and idempotently transitions the matching invoice to `rondo_paid` when payment is confirmed.
**Depends on**: Phase 187
**Requirements**: WHKT-01, WHKT-02, WHKT-03, WHKT-04, WHKT-05
**Success Criteria** (what must be TRUE):
  1. `POST /wp-json/rondo/v1/mollie/webhook` endpoint exists and returns 200 without WordPress authentication (verify with `curl -X POST <url> -d "id=test"`)
  2. Handler extracts payment ID from POST body, re-fetches payment from Mollie API via `MollieClient`
  3. Handler looks up invoice by `_mollie_payment_id` meta using `WP_Query`
  4. When payment status is `paid`, invoice post status transitions to `rondo_paid` and `payment_status` ACF field updates to `paid`
  5. Handler is idempotent — sending the same webhook payload twice does not double-update or cause errors
  6. Any error (unknown payment ID, invoice not found, API failure) is logged but handler still returns HTTP 200
**Plans**: 1 plan

Plans:
- [x] 188-01-PLAN.md — MollieWebhook class + REST endpoint registration

#### Phase 189: RestInvoices — Provider Routing
**Goal**: `RestInvoices::send_invoice()` routes to Mollie or Rabobank based on the configured active provider — existing Rabobank path is completely unchanged.
**Depends on**: Phase 188
**Requirements**: WIRE-01, WIRE-02
**Success Criteria** (what must be TRUE):
  1. `RestInvoices::send_invoice()` reads `FinanceConfig::get_active_payment_provider()` and branches to `MolliePayment::create_payment_link()` when Mollie is selected
  2. When Rabobank is the active provider, invoice sending behavior is byte-for-byte identical to v26.0
  3. Default provider is `rabobank` — if `active_payment_provider` option is not set, Rabobank path executes
  4. Existing Rabobank classes (`RabobankPayment`, `RabobankOAuth`) are not modified
**Plans**: 1 plan

Plans:
- [x] 189-01-PLAN.md — Provider branching in RestInvoices::send_invoice()

#### Phase 190: Finance Settings UI — Mollie Configuration
**Goal**: Finance Settings page includes Mollie API key input, payment provider selector, and test/live mode badge — using existing settings REST endpoint.
**Depends on**: Phase 189
**Requirements**: UI-01, UI-02, UI-03, UI-04
**Success Criteria** (what must be TRUE):
  1. Finance Settings Mollie section shows API key input (masked, save button)
  2. Payment provider selector (Rabobank / Mollie) visible and saves correctly
  3. Test/Live mode badge derived from key prefix displayed in settings
  4. Full API key never returned by REST endpoint — only `mollie_has_api_key` bool and `mollie_environment` string
  5. Version bumped to 27.0.0 in `style.css` and `package.json`
  6. CHANGELOG.md updated with v27.0 Mollie additions
**Plans**: 1 plan

Plans:
- [x] 190-01-PLAN.md — Finance Settings Mollie section UI + version bump + changelog

## Progress

| Phase | Milestone | Plans Complete | Status | Completed |
|-------|-----------|----------------|--------|-----------|
| 175. Backend Cleanup | v24.1 | 2/2 | ✓ Complete | 2026-02-13 |
| 176. Frontend Cleanup | v24.1 | 2/2 | ✓ Complete | 2026-02-13 |
| 177. Documentation Updates | v24.1 | 2/2 | ✓ Complete | 2026-02-13 |
| 178. Finance Navigation & Settings Backend | v26.0 | 2/2 | ✓ Complete | 2026-02-15 |
| 179. Invoice Data Model & REST API | v26.0 | 2/2 | ✓ Complete | 2026-02-15 |
| 180. Invoice Creation Flow | v26.0 | 2/2 | ✓ Complete | 2026-02-15 |
| 181. PDF Generation | v26.0 | 1/1 | ✓ Complete | 2026-02-16 |
| 182. Rabobank Payment Integration | v26.0 | 2/2 | ✓ Complete | 2026-02-16 |
| 183. Email Delivery | v26.0 | 1/1 | ✓ Complete | 2026-02-16 |
| 184. Invoice Management UI | v26.0 | 2/2 | ✓ Complete | 2026-02-16 |
| 185. Invoice PDF Club Branding | v26.0 | 1/1 | ✓ Complete | 2026-02-16 |
| 186. SDK + FinanceConfig + MollieClient | v27.0 | 1/1 | ✓ Complete | 2026-02-17 |
| 187. MolliePayment — Payment Link Creation | v27.0 | 1/1 | ✓ Complete | 2026-02-17 |
| 188. MollieWebhook — Automatic Status Update | v27.0 | 1/1 | ✓ Complete | 2026-02-17 |
| 189. RestInvoices — Provider Routing | v27.0 | 1/1 | ✓ Complete | 2026-02-18 |
| 190. Finance Settings UI | v27.0 | 1/1 | ✓ Complete | 2026-02-18 |

### Phase 191: Administratiekosten

**Goal:** Add a configurable administration fee for discipline-based invoices, included as a separate line item on the invoice and reflected in the PDF, email, and total amount.
**Depends on:** Phase 190
**Plans:** 1 plan

Plans:
- [ ] 191-01-PLAN.md — FinanceConfig admin_fee setting, REST API arg, server-side injection in create_invoice(), and Finance Settings UI field

---
*Roadmap created: 2026-02-09*
*Last updated: 2026-02-18 — Phase 191 planned*
