# Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-02-20)

**Core value:** Club administrators can manage their members, teams, and club operations through a single integrated system
**Current focus:** v30.0 User Accounts & Profiles — Phase 206 (next)

## Current Position

Milestone: v30.0 User Accounts & Profiles
Phase: 205 of 208 (User Provisioning) — COMPLETE
Plan: 2 of 2 complete in phase 205
Status: Phase 205 complete — UserProvisioning backend + frontend UI both deployed to production
Last activity: 2026-02-20 — Phase 205 Plan 02 (AccountCard + WelkomstmailTab frontend) complete

Progress: [████░░░░░░] 50% (3/6 phases complete)

## Performance Metrics

**Velocity:**
- Total plans completed: 214 plans across v1.0-v29.0
- Recent milestones:
  - v29.0: 8 plans, 1 day (2026-02-20)
  - v28.0: 9 plans, 2 days (2026-02-18 → 2026-02-19)
  - v27.0: 6 plans, 2 days (2026-02-17 → 2026-02-18)
  - v26.0: 13 plans, 2 days (2026-02-15 → 2026-02-16)
  - v24.1: 6 plans, 1 day (2026-02-13)

**Recent Trend:**
- Last 5 milestones averaged 1-2 days each
- Velocity: Stable

## Accumulated Context

### Decisions

Decisions logged in PROJECT.md Key Decisions table (750+ entries).

Recent decisions affecting v30.0:
- Capability sync (CAPS-04-08) gets its own phase (206) separate from provisioning (205) — sync reconciliation logic is distinct from account creation and crosses repos (rondo-sync step)
- Phase 208 (Avatar) depends on Phase 205 bidirectional link, not Phase 207 — avatar can ship before profile page if needed
- Phase 203: Used admin_init hook (not init/template_redirect) — fires only inside wp-admin, implicit is_admin(), clean redirect before output
- Phase 203: No REST API exemption needed — REST uses rest_api_init, not admin_init
- Phase 204: FunctieCapabilityMap is a pure static class — no constructor, no hooks, no functions.php entry needed
- Phase 204: GET /rondo/v1/functie-capability-map returns { map, roles } in one response (no separate roles endpoint needed)
- Phase 204: Row list = union(availableFuncties, keys(functieMapState)) — stale Functies remain visible with "(niet meer actief)" label until admin cleans up
- Phase 205 Plan 01: UserProvisioning is a pure service class with no hooks — no functions.php entry needed (same pattern as VOGEmail)
- Phase 205 Plan 01: Welcome email failure is non-blocking — user created successfully even if wp_mail() fails, failure logged
- Phase 205 Plan 01: 7-day password reset expiry via scoped add/remove filter on password_reset_expiration
- Phase 205 Plan 02: AccountCard reads personData from parent (no separate fetch) — same pattern as VOGCard
- Phase 205 Plan 02: WelkomstmailTab lazy-fetches settings only when first opened (useEffect with !welcomeSettings guard)
- Phase 205 Plan 02: No re-provision button when account already exists — backend is idempotent, UI prevents confusion

### Pending Todos

3 todo(s) in `.planning/todos/pending/`

### Blockers/Concerns

- Phase 195 cron: WP-Cron is visitor-triggered on SiteGround; manually registered event at 2026-02-20 00:00:00 — consider SG Cron integration for reliable daily execution
- Orphaned Google Sheets code: 4 dead client.js methods + 5 unreachable REST routes (tech debt from v29.0)
- v30.0 Phase 205: Verify rondo-sync service account is an administrator WordPress user before provisioning endpoint runs (manage_options required)
- v30.0 Phase 205: Lettermint from-address must be verified in dashboard before sending welcome emails to real members

### Quick Tasks Completed

| # | Description | Date | Commit | Directory |
|---|-------------|------|--------|-----------|
| 110 | People with finance rights should be able to see (and change) the Financiën -> Instellingen | 2026-02-20 | a33f152a | [110-people-with-finance-rights-should-be-abl](./quick/110-people-with-finance-rights-should-be-abl/) |

## Session Continuity

Last session: 2026-02-20
Stopped at: Completed 205-02-PLAN.md — AccountCard + WelkomstmailTab frontend UI, version 29.2.0, deployed to production
Resume file: None

**Next action:** Run `/gsd:plan-phase 206` to plan the next phase

---
*State created: 2026-02-15*
*Last updated: 2026-02-20 — Phase 205 complete (backend + frontend), v29.2.0 deployed to production*
