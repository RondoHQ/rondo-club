# Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-02-20)

**Core value:** Club administrators can manage their members, teams, and club operations through a single integrated system
**Current focus:** v29.0 Made in Europe

## Current Position

Milestone: v29.0 Made in Europe
Status: Defining requirements
Last activity: 2026-02-20 — Milestone v29.0 started

Progress: [░░░░░░░░░░] 0%

## Performance Metrics

**Velocity:**
- Total plans completed: 206 plans across v1.0-v28.0 (in progress)
- Recent milestones:
  - v27.0: 6 plans, 2 days (2026-02-17 → 2026-02-18)
  - v26.0: 13 plans, 2 days (2026-02-15 → 2026-02-16)
  - v24.1: 6 plans, 1 day (2026-02-13)
  - v24.0: 13 plans, 2 days (2026-02-11 → 2026-02-12)
  - v23.0: 4 plans, 1 day (2026-02-09)

**Recent Trend:**
- Last 5 milestones averaged 1-2 days each
- Velocity: Stable

| Phase | Plan | Duration | Tasks | Files |
|-------|------|----------|-------|-------|
| 192 | 01 | 3min | 2 | 4 |
| 193 | 01 | 5min | 2 | 5 |
| 194 | 01 | 4min | 2 | 6 |
| 195 | 01 | 5min | 2 | 4 |
| 195 | 02 | 10min | 2 | 5 |
| 196 | 01 | 5min | 2 | 5 |
| 196 | 02 | 6min | 3 | 9 |
| 197 | 01 | 3min | 2 | 2 |
| 197 | 02 | 3min | 2 | 5 |

## Accumulated Context

### Decisions

Decisions logged in PROJECT.md Key Decisions table (700+ entries).

Key decisions for v28.0 (from research):
- Flat numbered post meta for installments (`_installment_N_*`) — not ACF repeater, not separate CPT
- Reverse-lookup meta pattern (`_mollie_pid_{payment_id} = installment_number`) for webhook O(1) lookup
- `template_redirect` priority 0 for public landing page — before SPA catch-all at priority 1
- PHP-rendered public page (not React) — no WP nonce available for unauthenticated users
- Single daily cron sweeper for scheduler — not per-invoice scheduled events
- 50 invoices per cron batch for bulk creation — avoids PHP timeout at 500+ members

Phase 192-01 decisions:
- `invoice_type` ACF field has `allow_null=1` and `required=0` so existing invoices pass validation before backfill
- Installment admin fee is separate option from discipline invoice admin fee (different constants, different purposes)
- Billing method stored per-season via WordPress options (`rondo_billing_method_{season}`)
- [Phase 193]: PublicPaymentPage uses template_redirect priority 0 — fires before SPA catch-all at priority 1
- [Phase 193]: generate_token() sets both _payment_token meta and payment_link ACF field for InvoiceEmailSender {betaallink} variable
- [Phase 193]: All three plan options shown unconditionally — plan-enable toggles deferred to Phase 196
- [Phase 194]: InstallmentPaymentService reads amount from _installment_N_amount + _installment_N_admin_fee; falls back to ACF total_amount for full plan
- [Phase 194]: handle_installment_paid writes betaald before all-paid loop so full plan (count=1) works in one pass
- [Phase 194]: N+1 creation wrapped in try/catch — never propagates; idempotency guard on empty _installment_{next}_mollie_payment_id
- [Phase 195-01]: Due dates hardcoded as 25th of each month (Sep-Apr) — derived from season via MembershipFees::get_season_key(post_date)
- [Phase 195-01]: quarterly_3 due dates: Sep 25, Nov 25, Feb 25; monthly_8: Sep 25 through Apr 25
- [Phase 195-01]: installment_admin_fee REST arg was missing — auto-fixed alongside three new template args
- [Phase 195-01]: Three email templates (installment, reminder_1, reminder_2) stored as WordPress options via FinanceConfig
- [Phase 195]: Status written before wp_mail for idempotency — prevents duplicate sends if cron re-runs; failed Mollie create_payment aborts early without writing status so sweeper retries next day
- [Phase 195]: Reminder 2 checked before reminder 1 in sweeper decision tree — 21-day threshold also satisfies 14-day check; checking reminder 2 first ensures exactly one reminder per overdue period
- [Phase 195]: Fresh Mollie payment link created per email call — links expire, initial send + both reminders each call InstallmentPaymentService::create_payment independently
- [Phase 196]: BulkInvoiceCreator uses WP-Cron chained single-events (50/batch) — avoid PHP timeout for 500+ members
- [Phase 196]: Installment plan toggles (plan_3, plan_8) stored per-season as WP options, default true — existing deployments unaffected
- [Phase 196]: person_ids array stripped from REST responses — stored in WP option only for cron batch processor, keeps API payloads small
- [Phase 196]: showNikkiColumns = billingMethod === 'nikki' && !isForecast — single flag guards all Nikki UI simultaneously
- [Phase 196]: Maak factuur button uses inline Tailwind classes — btn-primary-sm does not exist in the codebase
- [Phase 197-01]: Discipline type filter uses OR clause to include legacy invoices (null/empty invoice_type) — ensures existing invoices visible before backfill
- [Phase 197-01]: URL param 'plan' maps to API param 'payment_plan' — keeps URL query strings clean
- [Phase 197-01]: Generic updateFilter(key, value) replaces single-purpose updateStatusFilter — DRY pattern for all filter keys
- [Phase 197-01]: meta_query initialized as [] before all filter blocks, unset if empty — avoids WP_Query warnings
- [Phase 197-02]: Installment data added to format_invoice_detail only (not format_invoice list) — list response stays lean, per-installment data only needed on detail page
- [Phase 197-02]: Loop guard count >= 1 && plan && plan !== 'full' ensures discipline (no plan) and full-plan invoices produce empty installments[]
- [Phase 197-02]: InstallmentStatusBadge uses smaller padding (py-0.5) vs StatusBadge (py-1) — suits table cell density

### Pending Todos

4 todo(s) in `.planning/todos/pending/`

### Blockers/Concerns

- Phase 195 cron: WP-Cron is visitor-triggered on SiteGround; manually registered event at 2026-02-20 00:00:00 — consider SG Cron integration for reliable daily execution

### Quick Tasks Completed

| # | Description | Date | Commit | Directory |
|---|-------------|------|--------|-----------|
| 85 | Add contributie exclusion option to person detail screen | 2026-02-19 | 28fabfa3 | [85-add-contributie-exclusion-option-to-pers](./quick/85-add-contributie-exclusion-option-to-pers/) |
| 86 | Add pro-rata and family discount columns to Nog te factureren page | 2026-02-19 | 817c57f2 | [86-add-pro-rata-and-family-discount-columns](./quick/86-add-pro-rata-and-family-discount-columns/) |
| 87 | Redirect to invoice page after creating invoice | 2026-02-19 | be3d9f86 | [87-redirect-to-invoice-page-after-creating-](./quick/87-redirect-to-invoice-page-after-creating-/) |
| 88 | Show Doorbelast as n.v.t. when boete is €0 | 2026-02-19 | c9945a37 | [88-show-doorbelast-as-n-v-t-when-boete-is-z](./quick/88-show-doorbelast-as-n-v-t-when-boete-is-z/) |
| 89 | Add cursor pointer to tabs and buttons | 2026-02-19 | 31c5043f | [89-add-cursor-pointer-to-tabs-and-buttons](./quick/89-add-cursor-pointer-to-tabs-and-buttons/) |
| 90 | Show club name and logo on betaling page | 2026-02-19 | cff97229 | [90-show-club-name-and-logo-on-betaling-page](./quick/90-show-club-name-and-logo-on-betaling-page/) |
| 91 | Remove admin fee note and use accent color on betaling page | 2026-02-19 | 46d7bf06 | [91-remove-admin-fee-note-and-use-accent-col](./quick/91-remove-admin-fee-note-and-use-accent-col/) |
| 92 | Set club logo as favicon on betaling page | 2026-02-19 | 2852213c | [92-set-club-logo-as-favicon-on-betaling-pag](./quick/92-set-club-logo-as-favicon-on-betaling-pag/) |
| 93 | Enable sending and disable installments per invoice | 2026-02-19 | bcf41e92 | [93-enable-sending-and-disable-installments-](./quick/93-enable-sending-and-disable-installments-/) |
| 94 | Use direct Mollie payment link for membership invoices with installments disabled | 2026-02-19 | f7486ab5 | [94-use-direct-mollie-payment-link-for-membe](./quick/94-use-direct-mollie-payment-link-for-membe/) |
| 95 | Add separate email template for contributie invoices | 2026-02-19 | 9f98284e | [95-add-separate-email-template-for-contribu](./quick/95-add-separate-email-template-for-contribu/) |
| 96 | Separate invoice numbering for contributie (C prefix) | 2026-02-19 | b2e78ca6 | [96-separate-invoice-numbering-for-contribut](./quick/96-separate-invoice-numbering-for-contribut/) |
| 97 | Show family discount and pro-rata discount line items on membership invoices | 2026-02-19 | 00bbeed5 | [97-show-family-discount-and-pro-rata-discou](./quick/97-show-family-discount-and-pro-rata-discou/) |
| 98 | Improve membership invoice PDF: category name, remove discipline columns, separate payment section | 2026-02-19 | 46765a91 | [98-improve-membership-invoice-pdf-category-](./quick/98-improve-membership-invoice-pdf-category-/) |
| 99 | Update contributie invoice line item descriptions: add season and instapkorting explanation | 2026-02-19 | 9307ff7c | [99-update-contributie-invoice-line-descript](./quick/99-update-contributie-invoice-line-descript/) |
| 100 | Add separate betalingsclausule for contributie invoices | 2026-02-19 | 3a560c97 | [100-add-separate-betalingsclausule-for-contr](./quick/100-add-separate-betalingsclausule-for-contr/) |
| 101 | Replace hardcoded membership invoice payment text with betalingsclausule setting | 2026-02-19 | 6cb4e297 | [101-replace-hardcoded-membership-invoice-pay](./quick/101-replace-hardcoded-membership-invoice-pay/) |
| 102 | Fix invoice status lookup on Nog te factureren page | 2026-02-19 | ab7a1d2a | [102-fix-invoice-status-on-nog-te-factureren](./quick/102-fix-invoice-status-on-nog-te-factureren/) |
| 103 | Move Contributie Instellingen to Financien settings and add E-mail sub-tabs | 2026-02-19 | 1f0dc272 | [103-move-contributie-instellingen-to-financi](./quick/103-move-contributie-instellingen-to-financi/) |
| 104 | Fix amounts on Person Detail Financieel tab to use two decimals | 2026-02-19 | de41aa73 | [104-fix-amounts-on-person-detail-financieel-](./quick/104-fix-amounts-on-person-detail-financieel-/) |
| 105 | Rename Administratiekosten to Administratiekosten tuchtzaken | 2026-02-19 | c710ab51 | [105-rename-administratiekosten-to-administra](./quick/105-rename-administratiekosten-to-administra/) |
| 106 | Reorder Finance Settings tabs and mark inactive payment provider | 2026-02-19 | 3678ea0f | [106-reorder-finance-settings-tabs-and-mark-i](./quick/106-reorder-finance-settings-tabs-and-mark-i/) |
| 107 | Fix Nog te factureren page not updating after Maak factuur | 2026-02-19 | 9a104640 | [107-fix-nog-te-factureren-page-not-updating-](./quick/107-fix-nog-te-factureren-page-not-updating-/) |
| 108 | Fix Termijnbetaling uitschakelen default (installments enabled by default for bulk invoices) | 2026-02-19 | f2b55241 | [108-fix-termijnbetaling-uitschakelen-default](./quick/108-fix-termijnbetaling-uitschakelen-default/) |
| 109 | Replace Financien with Financiën in sidebar and page title display strings | 2026-02-19 | e0afe59e | [109-replace-financien-with-financi-n-everywh](./quick/109-replace-financien-with-financi-n-everywh/) |

## Session Continuity

Last session: 2026-02-20
Stopped at: Completed v28.0 milestone archival
Resume file: None

**Next action:** Define requirements and create roadmap

---
*State created: 2026-02-15*
*Last updated: 2026-02-20 — v29.0 Made in Europe milestone started*
