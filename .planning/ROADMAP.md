# Roadmap: Rondo Club

## Milestones

- ✅ **v20.0 Configurable Roles** — Phases 151-154 (shipped 2026-02-08) — [Archive](milestones/v20.0-ROADMAP.md)
- ✅ **v21.0 Per-Season Fee Categories** — Phases 155-161 (shipped 2026-02-09) — [Archive](milestones/v21.0-ROADMAP.md)
- ✅ **v22.0 Design Refresh** — Phases 162-165 (shipped 2026-02-09) — [Archive](milestones/v22.0-ROADMAP.md)
- ✅ **v23.0 Former Members** — Phases 166-169 (shipped 2026-02-09) — [Archive](milestones/v23.0-ROADMAP.md)
- ✅ **v24.0 Demo Data** — Phases 170-174 (shipped 2026-02-12) — [Archive](milestones/v24.0-ROADMAP.md)
- ✅ **v24.1 Dead Feature Removal** — Phases 175-177 (shipped 2026-02-13) — [Archive](milestones/v24.1-ROADMAP.md)
- 🚧 **v26.0 Discipline Case Invoicing** — Phases 178-184 (in progress)

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

### v26.0 Discipline Case Invoicing (In Progress)

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
**Plans**: TBD

Plans:
- [ ] 179-01: TBD

#### Phase 180: Invoice Creation Flow
**Goal**: User can select uninvoiced discipline cases on member's Tuchtzaken tab and create a draft invoice that sums case fees.
**Depends on**: Phase 179
**Requirements**: CREATE-01, CREATE-02, CREATE-03
**Success Criteria** (what must be TRUE):
  1. Tuchtzaken tab shows checkboxes for discipline cases that don't have an invoice
  2. User can select one or more uninvoiced cases and click "Maak factuur" button
  3. Invoice created in Draft status with selected cases as line items and sum of Boete fields as total
  4. Invoice visible in member's profile after creation
**Plans**: TBD

Plans:
- [ ] 180-01: TBD

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
**Plans**: TBD

Plans:
- [ ] 181-01: TBD

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
**Plans**: TBD

Plans:
- [ ] 182-01: TBD

#### Phase 183: Email Delivery
**Goal**: Draft invoices can be sent via email with PDF attachment, payment link, and configurable template text.
**Depends on**: Phase 182
**Requirements**: EMAIL-01, EMAIL-02, EMAIL-03
**Success Criteria** (what must be TRUE):
  1. Send invoice action triggers email via wp_mail to member's email address
  2. Email body uses configured template with variable replacement: {naam}, {betaallink}, {factuur_nummer}, {tuchtzaken_lijst}, {totaal_bedrag}
  3. Invoice PDF attached to email as file
  4. Sending invoice transitions status from Draft to Sent and sets sent_date and due_date
**Plans**: TBD

Plans:
- [ ] 183-01: TBD

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
**Plans**: TBD

Plans:
- [ ] 184-01: TBD

## Progress

| Phase | Milestone | Plans Complete | Status | Completed |
|-------|-----------|----------------|--------|-----------|
| 175. Backend Cleanup | v24.1 | 2/2 | ✓ Complete | 2026-02-13 |
| 176. Frontend Cleanup | v24.1 | 2/2 | ✓ Complete | 2026-02-13 |
| 177. Documentation Updates | v24.1 | 2/2 | ✓ Complete | 2026-02-13 |
| 178. Finance Navigation & Settings Backend | v26.0 | 2/2 | ✓ Complete | 2026-02-15 |
| 179. Invoice Data Model & REST API | v26.0 | 0/TBD | Not started | - |
| 180. Invoice Creation Flow | v26.0 | 0/TBD | Not started | - |
| 181. PDF Generation | v26.0 | 0/TBD | Not started | - |
| 182. Rabobank Payment Integration | v26.0 | 0/TBD | Not started | - |
| 183. Email Delivery | v26.0 | 0/TBD | Not started | - |
| 184. Invoice Management UI | v26.0 | 0/TBD | Not started | - |

---
*Roadmap created: 2026-02-09*
*Last updated: 2026-02-15 — Phase 178 completed (2/2 plans)*
