---
gsd_state_version: 1.0
milestone: v33.0
milestone_name: Fee Service Decomposition
status: in_progress
stopped_at: phase 214 shipped, fee snapshot baseline + post-phase diff clean
last_updated: "2026-04-09T06:55:00.000Z"
last_activity: 2026-04-09 — phase 214 (FeeCategoryResolver + snapshot infra) shipped to prod, diff clean
progress:
  total_phases: 5
  completed_phases: 1
  total_plans: 7
  completed_plans: 2
  percent: 29
---

# Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-04-08)

**Core value:** Club administrators can manage their members, teams, and club operations through a single integrated system
**Current focus:** v33.0 Fee Service Decomposition — Phase 214 shipped (FeeCategoryResolver + fee snapshot infrastructure). Next: Phase 215 (FamilyGroupingService).

## Current Position

Milestone: v33.0 Fee Service Decomposition (active)
Phase: 214 (FeeCategoryResolver + Snapshot Infrastructure) — shipped 2026-04-09
Plan: —
Status: Phase 214 deployed to prod, post-phase snapshot diff clean against baseline. Ready for Phase 215 (FamilyGroupingService).
Last activity: 2026-04-09 — phase 214 direct-style execution (no /gsd:plan-phase), 2 atomic commits, 4,021-row fee snapshot diff passed

Progress: [███░░░░░░░] 29%

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

Last session: 2026-04-09T06:55:00Z
Stopped at: Phase 214 shipped — FeeCategoryResolver live on prod, baseline + post-phase snapshots in `.planning/phases/214-feecategoryresolver/`, fee diff clean

**Next action:** Phase 215 — extract `FamilyGroupingService` from MembershipFees (7 methods: `build_family_groups`, `get_family_key`, `recalculate_all_family_positions`, `recalculate_family_positions_for_person`, `clear_all_family_discount_meta`, `normalize_postal_code`, `extract_house_number`). Clean up the `FeeCacheInvalidator` coupling smell so it depends on `FamilyGroupingService` directly instead of reaching into MembershipFees. Before starting: run `bin/fee-snapshot.sh --output .planning/phases/215-familygrouping/pre-phase-215.json` for the pre/post diff.

---
*State created: 2026-02-15*
*Last updated: 2026-04-09 — phase 214 FeeCategoryResolver extraction shipped, baseline + diff clean*
