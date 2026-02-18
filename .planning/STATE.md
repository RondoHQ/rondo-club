# Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-02-18)

**Core value:** Club administrators can manage their members, teams, and club operations through a single integrated system
**Current focus:** v28.0 Membership Fee Invoicing — Phase 193: Public Payment Landing Page

## Current Position

Phase: 193 of 197 (Public Payment Landing Page)
Plan: 1 of 1 in current phase
Status: Phase 193 complete — ready for Phase 194
Last activity: 2026-02-18 — Phase 193-01 complete: public payment page at /betaling/{token}, Mollie checkout for installments

Progress: [██░░░░░░░░] 33% (v28.0, 2/6 phases)

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

### Pending Todos

1 todo(s) in `.planning/todos/pending/`

### Blockers/Concerns

- Phase 195 (Scheduler): Verify SiteGround real server cron access before finalizing — WP-Cron visitor-trigger unreliable on cached hosting
- Phase 196 (Bulk Creation): Verify SiteGround PHP memory limits for cron execution context before committing to batch size of 50

## Session Continuity

Last session: 2026-02-18
Stopped at: Completed 193-01-PLAN.md — public payment landing page deployed to production
Resume file: None

**Next action:** Execute Phase 194 — Mollie Webhook Handler

---
*State created: 2026-02-15*
*Last updated: 2026-02-18 — Phase 193-01 complete, ready for Phase 194*
