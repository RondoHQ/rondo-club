# Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-03-08)

**Core value:** Club administrators can manage their members, teams, and club operations through a single integrated system
**Current focus:** v31.0 Editable Contact Fields

## Current Position

Milestone: v31.0 Editable Contact Fields
Phase: Not started (defining requirements)
Plan: —
Status: Defining requirements
Last activity: 2026-03-08 — Milestone v31.0 started

## Performance Metrics

**Velocity:**
- Total plans completed: 214 plans across v1.0-v30.0
- Recent milestones:
  - v30.0: 8 plans, 2 days (2026-02-20 → 2026-02-21)
  - v29.0: 8 plans, 1 day (2026-02-20)
  - v28.0: 9 plans, 2 days (2026-02-18 → 2026-02-19)
  - v27.0: 6 plans, 2 days (2026-02-17 → 2026-02-18)
  - v26.0: 13 plans, 2 days (2026-02-15 → 2026-02-16)

**Recent Trend:**
- Last 5 milestones averaged 1-2 days each
- Velocity: Stable

## Accumulated Context

### Decisions

Decisions logged in PROJECT.md Key Decisions table (780+ entries).

### Pending Todos

5 todo(s) in `.planning/todos/pending/` — 2 consumed by this milestone (contact field alignment, phone normalization)

### Blockers/Concerns

- Phase 195 cron: WP-Cron is visitor-triggered on SiteGround; manually registered event at 2026-02-20 00:00:00 — consider SG Cron integration for reliable daily execution
- Orphaned Google Sheets code: 4 dead client.js methods + 5 unreachable REST routes (tech debt from v29.0)
- Reverse sync currently disabled on rondo-sync server — needs re-enabling as part of v31.0

### Quick Tasks Completed

| # | Description | Date | Commit | Directory |
|---|-------------|------|--------|-----------|
| 110 | People with finance rights should be able to see (and change) the Financiën -> Instellingen | 2026-02-20 | a33f152a | [110-people-with-finance-rights-should-be-abl](./quick/110-people-with-finance-rights-should-be-abl/) |
| 111 | Refactor FunctiesTab mapping UI from CSS grid divs to semantic HTML table | 2026-02-20 | 122829b9 | [111-refactor-functiestab-mapping-ui-to-table](./quick/111-refactor-functiestab-mapping-ui-to-table/) |
| 112 | Update demo export/import code for invoices, finance settings, capability maps | 2026-02-22 | 2bb4e816 | [112-update-demo-export-code-for-new-features](./quick/112-update-demo-export-code-for-new-features/) |
| 113 | Add AWC team column and filter to tuchtzaken list | 2026-02-22 | f5c53c68 | [113-add-awc-team-column-and-filter-to-tuchtz](./quick/113-add-awc-team-column-and-filter-to-tuchtz/) |
| 114 | Prefix numeric team names with speeldag on tuchtzaken | 2026-02-22 | b9a9e6ca | [114-prefix-numeric-team-names-with-speeldag-](./quick/114-prefix-numeric-team-names-with-speeldag-/) |
| 115 | Add exclude team filter on tuchtzaken page | 2026-02-22 | 87eea749 | [115-add-exclude-team-filter-on-tuchtzaken-pa](./quick/115-add-exclude-team-filter-on-tuchtzaken-pa/) |
| 116 | Bulk create invoices from selected tuchtzaken | 2026-02-22 | 2ce6d17e | [116-bulk-create-invoices-from-selected-tucht](./quick/116-bulk-create-invoices-from-selected-tucht/) |
| 117 | Invoice number search in sitewide search modal | 2026-02-22 | ca68558c | [117-in-the-sitewide-search-function-allow-se](./quick/117-in-the-sitewide-search-function-allow-se/) |
| 118 | Add reminder emails for contributie invoices without a payment plan | 2026-02-22 | a577c542 | [118-add-reminder-emails-for-contributie-invo](./quick/118-add-reminder-emails-for-contributie-invo/) |

## Session Continuity

Last session: 2026-03-08
Stopped at: Starting milestone v31.0 Editable Contact Fields
Resume file: None

**Next action:** Define requirements for v31.0

---
*State created: 2026-02-15*
*Last updated: 2026-03-08 — Milestone v31.0 started*
