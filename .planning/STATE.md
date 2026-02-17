# Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-02-15)

**Core value:** Club administrators can manage their members, teams, and club operations through a single integrated system
**Current focus:** v27.0 Mollie — Executing (Phase 186)

## Current Position

Phase: Phase 186 — SDK Installation + FinanceConfig + MollieClient
Plan: 01 complete
Status: In progress
Last activity: 2026-02-17 — Completed 186-01 (SDK + FinanceConfig + MollieClient)

## Performance Metrics

**Velocity:**
- Total plans completed: 199 plans across v1.0-v26.0
- Recent milestones:
  - v24.1: 6 plans, 1 day (2026-02-13)
  - v24.0: 13 plans, 2 days (2026-02-11 → 2026-02-12)
  - v23.0: 4 plans, 1 day (2026-02-09)
  - v22.0: 7 plans, 1 day (2026-02-09)
  - v21.0: 12 plans, 2 days (2026-02-08 → 2026-02-09)

**Phase 179 Progress:**
- Plan 179-01: 114s, 2 tasks, 3 files (2026-02-15)
- Plan 179-02: 179s, 2 tasks, 3 files (2026-02-15)

**Phase 180 Progress:**
- Plan 180-01: 375s, 2 tasks, 5 files (2026-02-15)
- Plan 180-02: 137s, 2 tasks, 2 files (2026-02-15)

**Phase 181 Progress:**
- Plan 181-01: 201s, 2 tasks, 5 files (2026-02-16)

**Phase 182 Progress:**
- Plan 182-01: 195s, 2 tasks, 2 files (2026-02-16)
- Plan 182-02: 180s, 2 tasks, 3 files (2026-02-16)

**Phase 183 Progress:**
- Plan 183-01: 172s, 2 tasks, 3 files (2026-02-16)

**Phase 184 Progress:**
- Plan 184-01: 162s, 2 tasks, 7 files (2026-02-16)
- Plan 184-02: 232s, 2 tasks, 6 files (2026-02-16)

**Phase 185 Progress:**
- Plan 185-01: 336s, 2 tasks, 6 files (2026-02-16)

**Recent Trend:**
- Last 5 milestones averaged 1-2 days each
- Velocity: Stable

## Accumulated Context

### Decisions

Decisions are logged in PROJECT.md Key Decisions table (658 entries).

Recent decisions for v26.0:
- Invoice system follows existing patterns (CPT, ACF, REST API)
- mPDF library for PDF generation (HTML/CSS workflow, ~15-20MB)
- Rabobank betaalverzoek OAuth API for payment links
- Sodium encryption for API credentials (existing pattern)
- Navigation section headers use type='section' property (178-01)
- Disabled navigation items show grayed out with disabled property (178-01)
- Conditional credential submission preserves existing values when fields empty (178-02)
- IBAN auto-formatting on blur for consistent storage (178-02)
- Invoiced cases show FileText icon with 60% opacity instead of checkbox (180-01)
- Selection state managed via Set for O(1) lookup performance (180-01)
- Both fairplay AND financieel capabilities required to create invoices (180-01)
- Invoice display uses Dutch status labels: Concept/Verstuurd/Betaald/Verlopen (180-02)
- Invoice section hidden when no invoices exist (no empty state UI) (180-02)
- [Phase 181]: mPDF library for PDF generation (HTML/CSS workflow)
- [Phase 181]: Store PDFs in wp-content/uploads/invoices/ (WordPress convention)
- [Phase 182-01]: OAuth 2.0 Premium with browser redirect callback (Rabobank requirement)
- [Phase 182-01]: 5-minute token refresh buffer (prevent mid-operation expiry)
- [Phase 182-01]: Separate RabobankOAuth and RabobankPayment classes (SRP)
- [Phase 182-02]: Connection status card above credentials form (user needs to see state first)
- [Phase 182-02]: Disable connect button when no credentials saved (prevent OAuth failure)
- [Phase 182-02]: URL parameter cleanup after OAuth callback (prevent re-display on refresh)
- [Phase 183-01]: wp_mail for email delivery (WordPress native, supports HTML and attachments)
- [Phase 183-01]: Template variable replacement using str_replace (6 variables)
- [Phase 183-01]: Payment link creation is non-blocking (logs error if fails, email still sent)
- [Phase 183-01]: Only draft invoices can be sent (400 error prevents re-sending)
- [Phase 184-01]: Resend endpoint only allows sent/overdue invoices (400 error for others)
- [Phase 184-01]: useResendInvoice only invalidates ['invoice'] query (targeted performance)
- [Phase 184-01]: Placeholder page components created for build compilation (full UI in Plan 02)
- [Phase 184-02]: Client-side sorting for Facturen list (small datasets, instant feedback)
- [Phase 184-02]: Success messages auto-hide after 3s using setTimeout (FeedbackDetail pattern)
- [Phase 184-02]: Status-driven button rendering for invoice actions (draft/sent/paid each different)
- [Phase 185-01]: Logo storage via WordPress media library (reuse existing infrastructure)
- [Phase 185-01]: Accent color stored as hex string with sanitize_hex_color() validation
- [Phase 185-01]: PDF fallback to Rondo branding ensures PDFs always render
- [Phase 186]: Sodium encryption for Mollie API key (same pattern as Rabobank credentials)
- [Phase 186]: MollieClient is not a singleton — reads fresh key from FinanceConfig on each instantiation
- [Phase 186]: Active payment provider defaults to 'rabobank' — no behavioral change for existing sites
- [Phase 186]: Boolean mollie_has_api_key in get_all_settings() — raw key never exposed via REST

### Roadmap Evolution

None — phase 185 was the final planned enhancement.

### Pending Todos

1 todo(s) in `.planning/todos/pending/`

### Blockers/Concerns

**Pre-existing Code Quality Issues:**
- 140 lint problems (113 errors, 27 warnings) in JSX files
- Should be addressed in a future cleanup milestone

### Quick Tasks Completed

| # | Description | Date | Commit | Directory |
|---|-------------|------|--------|-----------|
| 65 | Add BCC email for invoice sending | 2026-02-16 | 6af9f77 | [65-add-bcc-email-for-invoice-sending](./quick/65-add-bcc-email-for-invoice-sending/) |
| 66 | Add regenerate invoice button | 2026-02-16 | 34ed6bc | [66-add-regenerate-invoice-button](./quick/66-add-regenerate-invoice-button/) |
| 67 | Invoice PDF - Card type and suspension columns | 2026-02-16 | adb1910 | [67-invoice-columns-card-type-instead-of-san](./quick/67-invoice-columns-card-type-instead-of-san/) |
| 68 | Center QR code scan text in invoice PDF | 2026-02-16 | 12cc679 | [68-center-qr-code-scan-text-in-invoice-pdf](./quick/68-center-qr-code-scan-text-in-invoice-pdf/) |
| 69 | Invoice PDF colored Geel/Rood card type | 2026-02-16 | 5503ea8 | [69-invoice-pdf-show-geel-rood-colored-text-](./quick/69-invoice-pdf-show-geel-rood-colored-text-/) |
| 70 | Fix invoice PDF column widths | 2026-02-16 | 4836a3d | [70-fix-invoice-pdf-column-widths-for-kaart-](./quick/70-fix-invoice-pdf-column-widths-for-kaart-/) |
| 71 | Move club logo to right side of invoice header | 2026-02-16 | d69396f | [71-move-club-logo-to-right-side-of-invoice-](./quick/71-move-club-logo-to-right-side-of-invoice-/) |
| 72 | Add tab navigation to Finance Settings | 2026-02-16 | d31e152 | [72-finance-settings-tabs](./quick/72-finance-settings-tabs/) |
| 73 | Set doorbelast to Rondo when invoice sent | 2026-02-16 | dd59a57 | [73-set-discipline-case-doorbelast-to-ja-ron](./quick/73-set-discipline-case-doorbelast-to-ja-ron/) |
| 74 | Filter Tuchtzaken page by Doorbelast and Sanctie | 2026-02-16 | 5694ebc | [74-filter-tuchtzaken-page-by-doorbelast-and](./quick/74-filter-tuchtzaken-page-by-doorbelast-and/) |
| Phase 186 P01 | 164 | 2 tasks | 9 files |

## Session Continuity

Last session: 2026-02-17
Stopped at: Completed 186-01-PLAN.md (SDK + FinanceConfig + MollieClient)
Resume file: None

**Next action:** Execute Phase 186 Plan 02 (if exists) or next phase

---
*State created: 2026-02-15*
*Last updated: 2026-02-17 after starting milestone v27.0 Mollie*
