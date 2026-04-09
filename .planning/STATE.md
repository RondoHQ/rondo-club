---
gsd_state_version: 1.0
milestone: v33.0
milestone_name: Fee Service Decomposition
status: in_progress
stopped_at: phase 215 shipped, FamilyGroupingService extracted, FeeCacheInvalidator coupling fixed
last_updated: "2026-04-09T07:30:00.000Z"
last_activity: 2026-04-09 — phase 215 (FamilyGroupingService) shipped, fee diff clean against baseline + pre-phase
progress:
  total_phases: 5
  completed_phases: 2
  total_plans: 7
  completed_plans: 3
  percent: 43
---

# Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-04-08)

**Core value:** Club administrators can manage their members, teams, and club operations through a single integrated system
**Current focus:** v33.0 Fee Service Decomposition — Phases 214 + 215 shipped. Next: Phase 216 (FeeCalculator) — the most sensitive extraction in the milestone.

## Current Position

Milestone: v33.0 Fee Service Decomposition (active)
Phase: 215 (FamilyGroupingService) — shipped 2026-04-09
Plan: —
Status: Phases 214 + 215 shipped direct-style. MembershipFees god class has shed 617 lines (29%) and 15 methods. Fee snapshot diff clean against baseline after each phase. Ready for Phase 216 (FeeCalculator).
Last activity: 2026-04-09 — phase 215 extraction + FeeCacheInvalidator coupling fix, 4,021-row diff passed

Progress: [████░░░░░░] 43%

## Performance Metrics

**Velocity:**
- Total plans completed: 221 plans across v1.0-v32.0 (216 + 5 in v32.0)
- Recent milestones:
  - v32.0 Interface Touch-up: 3 phases, 6 plans (212 + 213 + 213.1 gap closure) — 2026-03-11 to 2026-04-08
  - v31.0 Editable Contact Fields: 7 plans, 3 phases (2026-03-08)
  - v30.0 User Accounts & Profiles: 8 plans, 2 days (2026-02-20 → 2026-02-21)
  - v29.0 Made in Europe: 8 plans, 1 day (2026-02-20)

## Accumulated Context

### Decisions

Decisions logged in PROJECT.md Key Decisions table.

**v32.0 decisions** (now archived — see `.planning/milestones/v32.0-phases/`):
- Four-tier button hierarchy (btn-primary/secondary/tertiary/danger)
- DRY btn-* via CSS selector lists (Tailwind v4 compat)
- Audit-driven gap closure via decimal phases (213.1)
- No React Button component wrapper (CSS classes sufficient)

**v33.0 decisions** (pre-kickoff):
- SeasonKey helper extraction pattern validated before milestone kickoff (commit e25cef7b)
- Lightweight scoping chosen over full `/gsd:new-milestone` ceremony
- WP-CLI fee snapshot script for regression testing, built as Phase 214 Plan 01
- Phase 218 (Retire MembershipFees) decides between delete vs shrink-to-200-lines

### Pending Todos

7 todo(s) in `.planning/todos/pending/`

### Blockers/Concerns

- Orphaned Google Sheets code: 4 dead client.js methods + 5 unreachable REST routes (tech debt from v29.0)

### Quick Tasks Completed

| # | Description | Date | Commit | Directory |
|---|-------------|------|--------|-----------|
| 125 | Make instapkorting configurable per season | 2026-03-10 | 19076f0e | [125-make-instapkorting-configurable-per-seas](./quick/125-make-instapkorting-configurable-per-seas/) |
| 126 | Add CountryCode next to CountryName in address fields for easier syncing | 2026-03-11 | 371d0597 | [126-add-countrycode-next-to-countryname-in-a](./quick/126-add-countrycode-next-to-countryname-in-a/) |
| 127 | Make person address editable in the frontend | 2026-03-11 | a0671674 | [127-make-person-address-editable-in-the-fron](./quick/127-make-person-address-editable-in-the-fron/) |
| Phase 212-button-css-system P01 | 15 | 2 tasks | 1 files |
| Phase 213-sitewide-rollout P02 | 15 | 1 tasks | 5 files |
| Phase 213-sitewide-rollout P03 | 3 | 2 tasks | 9 files |
| Phase 213-sitewide-rollout P04 | 5 | 2 tasks | 14 files |
| Phase 213-sitewide-rollout P01 | 6 | 2 tasks | 7 files |

## Session Continuity

Last session: 2026-04-09T07:30:00Z
Stopped at: Phase 215 shipped — FamilyGroupingService live on prod, `FeeCacheInvalidator` coupling fixed (STRU-04), fee diff clean against baseline + pre-phase snapshots

**Next action:** Phase 216 — extract `FeeCalculator` (4 methods: `calculate_fee`, `calculate_full_fee`, `calculate_fee_with_family_discount`, `get_prorata_percentage`). Per roadmap: `FeeCalculator` takes `FeeCategoryResolver` and `FamilyGroupingService` as explicit collaborators (constructor injection). This is the most sensitive extraction in the milestone — the actual fee math. External callers to update: `class-rest-fees.php`, `class-rest-google-sheets.php`, `class-bulk-invoice-creator.php`, `class-fee-cache-invalidator.php`. Before starting: run `bin/fee-snapshot.sh --output .planning/phases/216-feecalculator/pre-phase-216.json`.

---
*State created: 2026-02-15*
*Last updated: 2026-04-09 — phase 215 FamilyGroupingService + FeeCacheInvalidator coupling fix shipped*
