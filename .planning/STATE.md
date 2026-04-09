---
gsd_state_version: 1.0
milestone: v33.0
milestone_name: Fee Service Decomposition
status: in_progress
stopped_at: phase 217 shipped, settings extracted, option keys + fee snapshot stable
last_updated: "2026-04-09T09:15:00.000Z"
last_activity: 2026-04-09 — phase 217 (MembershipFeeSettings) shipped, triple-clean diff (baseline + pre-phase + option list)
progress:
  total_phases: 5
  completed_phases: 4
  total_plans: 7
  completed_plans: 6
  percent: 86
---

# Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-04-08)

**Core value:** Club administrators can manage their members, teams, and club operations through a single integrated system
**Current focus:** v33.0 Fee Service Decomposition — Phases 214 + 215 + 216 + 217 shipped. MembershipFees down 1,417 lines (66%). Next: Phase 218 (Retire MembershipFees) — final phase.

## Current Position

Milestone: v33.0 Fee Service Decomposition (active)
Phase: 217 (MembershipFeeSettings) — shipped 2026-04-09
Plan: —
Status: Phases 214 + 215 + 216 + 217 shipped direct-style. MembershipFees god class: 2,137 → 720 lines (−1,417, −66%), 45 methods moved. Fee snapshot diff + wp option list diff both clean after each phase. Ready for Phase 218 (Retire MembershipFees) — the final phase.
Last activity: 2026-04-09 — phase 217 (MembershipFeeSettings) extraction, triple-clean diff validation (baseline + pre-phase + 101 option keys)

Progress: [█████████░] 86%

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

Last session: 2026-04-09T09:15:00Z
Stopped at: Phase 217 shipped — MembershipFeeSettings live on prod, 26 methods extracted, 101 rondo_* option keys unchanged, fee snapshot diff clean. MembershipFees is now 720 lines.

**Next action:** Phase 218 — retire MembershipFees. Audit what remains (4 lazy accessors, 5 person-data helpers, 10 cache/snapshot methods, 1 diagnostic = ~20 methods) and decide: (A) delete MembershipFees entirely, moving cache methods to a new `FeeCache` class, person helpers to a `Person` helper, and the diagnostic to a sensible home; OR (B) reduce MembershipFees to a <200-line shell with a single clear purpose (likely the facade / lazy accessors pattern). Per roadmap STRU-01 + QUAL-01/02/03. Before starting: `bin/fee-snapshot.sh --output .planning/phases/218-retire-membershipfees/pre-phase-218.json` and rebuild graphify to verify MembershipFees has already dropped out of the god-node top 10.

---
*State created: 2026-02-15*
*Last updated: 2026-04-09 — phase 217 MembershipFeeSettings extraction shipped, 86% milestone complete*
