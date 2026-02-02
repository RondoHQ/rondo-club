# Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-02-02)

**Core value:** Personal CRM with team collaboration while maintaining relationship-focused experience
**Current focus:** Milestone v12.1 Contributie Forecast - Phase 129 Backend Forecast Calculation

## Current Position

Phase: 129 of 131 (Backend Forecast Calculation)
Plan: 01 of 01 complete
Status: Phase complete
Last activity: 2026-02-02 - Completed 129-01-PLAN.md

Progress: [###-------] 33% (1 of 3 phases in v12.1)

### Recent Milestones

- v12.0 Membership Fees (2026-02-01) - 7 phases, 15 plans
- v10.0 Read-Only UI for Sportlink Data (2026-01-29) - 3 phases, 3 plans
- v9.0 People List Performance & Customization (2026-01-29) - 5 phases, 10 plans

## Accumulated Context

### Decisions

Decisions are logged in PROJECT.md Key Decisions table.
Recent decisions from v12.0 and v12.1:

| Date | Decision | Rationale |
|------|----------|-----------|
| 2026-01-31 | Season key format YYYY-YYYY | Human-readable, standard sports season format |
| 2026-01-31 | Family key format POSTALCODE-HOUSENUMBER | Street name ignored for flexible matching |
| 2026-01-31 | Tiered discount 0%/25%/50% | Position 1/2/3+ per FAM requirements |
| 2026-02-01 | Null vs 0 for missing Nikki data | Distinguishes "no data" from "zero balance" |
| 2026-02-01 | Red/green color coding for saldo | Positive (owes money) = red |
| 2026-02-02 | Forecast ignores season parameter | Always uses next season for consistency |
| 2026-02-02 | 100% pro-rata for forecast | Full year assumption for budget planning |
| 2026-02-02 | Nikki fields omitted from forecast | Future season has no billing data |

### Pending Todos

1 todo(s) in `.planning/todos/pending/`:
- **next-season-contributie-forecast**: Add next season forecast to contributie page (area: ui) - **ACTIVE MILESTONE**

### Blockers/Concerns

None.

## Session Continuity

Last session: 2026-02-02
Stopped at: Completed 129-01-PLAN.md (Backend Forecast Calculation)
Resume file: None
Next: Phase 130 (Frontend Forecast UI)

---
*State updated: 2026-02-02*
