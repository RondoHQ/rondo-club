---
gsd_state_version: 1.0
milestone: v34.0
milestone_name: Finance Service Decomposition
status: Roadmap defined, awaiting plan-phase
stopped_at: Completed 220-01-PLAN.md — Extract MollieConfig
last_updated: "2026-04-10T06:21:16.504Z"
last_activity: 2026-04-09 — v34.0 roadmap created (6 phases, 219-224)
progress:
  total_phases: 6
  completed_phases: 1
  total_plans: 4
  completed_plans: 2
  percent: 0
---

# Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-04-09)

**Core value:** Club administrators can manage their members, teams, and club operations through a single integrated system
**Current focus:** v34.0 Finance Service Decomposition — decompose the 1,308-line `FinanceConfig` god class (now the top god node post-v33.0) into focused services using the v33.0 hard-replacement pattern. Strategy Option C: delete FinanceConfig entirely, 18 callers rewired phase-by-phase, `FinanceServices` static locator as the ergonomic entry point.

## Current Position

Milestone: v34.0 Finance Service Decomposition
Phase: 219 — Finance Settings Snapshot Harness (Not started)
Plan: — (TBD)
Status: Roadmap defined, awaiting plan-phase
Last activity: 2026-04-09 — v34.0 roadmap created (6 phases, 219-224)

Progress: [░░░░░░░░░░] 0% (0/6 phases complete)

## Milestone v34.0 Phase Map

| Phase | Name | Requirements | Status |
|---|---|---|---|
| 219 | Finance Settings Snapshot Harness | FIN-01 | Not started |
| 220 | Extract MollieConfig | FIN-02, FIN-03 | Not started |
| 221 | Extract EmailTemplates | FIN-04 | Not started |
| 222 | Extract MembershipPassConfig | FIN-05 | Not started |
| 223 | Extract OrgInfo + PaymentTerms + RabobankConfig | FIN-06, FIN-07, FIN-08 | Not started |
| 224 | Retire FinanceConfig | FIN-09, FIN-10 | Not started |

**Standing requirements** (validated every phase from 219 onward):
- FIN-11: REST API compatibility — `FinanceSettings.jsx` form round-trip clean
- FIN-12: Snapshot diff discipline — `bin/finance-settings-snapshot.sh` clean pre/post diff recorded in every `SUMMARY.md`

## Performance Metrics

**Velocity:**
- Total plans completed: 221 plans across v1.0-v32.0 (216 + 5 in v32.0) + 7 plans across v33.0 (228 lifetime)
- Recent milestones:
  - v33.0 Fee Service Decomposition: 5 phases, 7 plans (2026-04-09, single-day milestone)
  - v32.0 Interface Touch-up: 3 phases, 6 plans (212 + 213 + 213.1 gap closure) — 2026-03-11 to 2026-04-08
  - v31.0 Editable Contact Fields: 7 plans, 3 phases (2026-03-08)
  - v30.0 User Accounts & Profiles: 8 plans, 2 days (2026-02-20 → 2026-02-21)
  - v29.0 Made in Europe: 8 plans, 1 day (2026-02-20)

## Accumulated Context

### Decisions

Decisions logged in PROJECT.md Key Decisions table.

**v34.0 decisions** (milestone scoping):
- Hard replacement (Option C) chosen over thin facade (Option A) — mirrors v33.0 Phase 218 pattern
- Snapshot harness (Phase 219) must exist before any extraction work begins, analogous to v33.0 Phase 214 Plan 01
- Mollie extracted first (Phase 220) to front-load risk — it's the scariest subsystem (encrypted API keys, webhook plumbing) so we want the pattern fresh and it alone can be rolled back cleanly
- OrgInfo + PaymentTerms + RabobankConfig bundled into one phase (223) — each is too small to justify a standalone phase, no interdependencies between them
- Mollie phase gets an extra end-to-end test-mode webhook roundtrip gate on top of the snapshot diff (FIN-03) — snapshot diff alone is not sufficient evidence for the webhook-heavy Mollie subsystem
- `FinanceServices` static locator mirrors `FeeServices` from v33.0 — no DI container, no business logic in the locator
- REST response shape and `FinanceSettings.jsx` form contract are hard compatibility boundaries — any restructuring goes in v35.0 (FINRST-01, FINUI-01)
- Direct-style execution (no formal Nyquist VALIDATION.md per phase) — consistent with v33.0 which shipped cleanly without it

**v33.0 decisions** (now archived — see `.planning/milestones/v33.0-phases/`):
- SeasonKey helper extraction pattern validated before milestone kickoff (commit e25cef7b)
- Lightweight scoping chosen over full `/gsd:new-milestone` ceremony
- WP-CLI fee snapshot script for regression testing, built as Phase 214 Plan 01
- Phase 218 (Retire MembershipFees) decided delete over shrink-to-200-lines → god class deleted in full
- [Phase 219-01]: Namespace is Rondo\Config\FinanceConfig (not Rondo\Finance\FinanceConfig as plan assumed)
- [Phase 220]: Mollie extracted as pure motion refactor: FinanceConfig keeps one-line forwarders (deleted in Plan 02), callers rewired in Plan 02
- [Phase 220]: normalize_accounts_for_storage and build_safe_accounts_from_storage made public on MollieConfig (dropped _mollie_ infix)

### Pending Todos

1 todo(s) in `.planning/todos/pending/`

### Blockers/Concerns

- Orphaned Google Sheets code: 4 dead client.js methods + 5 unreachable REST routes (tech debt from v29.0)
- None specific to v34.0 yet

### Quick Tasks Completed

| # | Description | Date | Commit | Directory |
|---|-------------|------|--------|-----------|
| 125 | Make instapkorting configurable per season | 2026-03-10 | 19076f0e | [125-make-instapkorting-configurable-per-seas](./quick/125-make-instapkorting-configurable-per-seas/) |
| 126 | Add CountryCode next to CountryName in address fields for easier syncing | 2026-03-11 | 371d0597 | [126-add-countrycode-next-to-countryname-in-a](./quick/126-add-countrycode-next-to-countryname-in-a/) |
| 127 | Make person address editable in the frontend | 2026-03-11 | a0671674 | [127-make-person-address-editable-in-the-fron](./quick/127-make-person-address-editable-in-the-fron/) |
| 128 | Remove orphaned Google Sheets subsystem | 2026-04-08 | 68c63652 | [128-remove-orphaned-google-sheets-subsystem](./quick/128-remove-orphaned-google-sheets-subsystem/) |
| Phase 219-finance-settings-snapshot-harness P01 | 30 | 3 tasks | 3 files |
| Phase 220 P01 | 1210 | 3 tasks | 5 files |

## Session Continuity

Last session: 2026-04-10T06:21:16.502Z
Stopped at: Completed 220-01-PLAN.md — Extract MollieConfig

**Next action:** `/gsd:plan-phase 219` to decompose Phase 219 (Finance Settings Snapshot Harness) into plans. Harness must exist and produce a clean baseline snapshot before any extraction work in Phase 220+ can begin.

---
*State created: 2026-02-15*
*Last updated: 2026-04-09 — v34.0 roadmap defined, Phase 219 ready for planning*
