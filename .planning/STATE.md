# Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-02-20)

**Core value:** Club administrators can manage their members, teams, and club operations through a single integrated system
**Current focus:** v29.0 Made in Europe — Phase 199: OAuth + Frontend Cleanup

## Current Position

Milestone: v29.0 Made in Europe
Phase: 199 of 202 (OAuth + Frontend Cleanup)
Plan: 1 of 2 in current phase
Status: In progress
Last activity: 2026-02-20 — Phase 199 Plan 01 complete: GoogleOAuth namespace to Rondo\Sheets, Gravatar endpoint removed

Progress: [██░░░░░░░░] 20%

## Performance Metrics

**Velocity:**
- Total plans completed: 206 plans across v1.0-v28.0
- Recent milestones:
  - v28.0: 9 plans, 2 days (2026-02-18 → 2026-02-19)
  - v27.0: 6 plans, 2 days (2026-02-17 → 2026-02-18)
  - v26.0: 13 plans, 2 days (2026-02-15 → 2026-02-16)
  - v24.1: 6 plans, 1 day (2026-02-13)
  - v24.0: 13 plans, 2 days (2026-02-11 → 2026-02-12)

**Recent Trend:**
- Last 5 milestones averaged 1-2 days each
- Velocity: Stable

| Phase | Plan | Duration | Tasks | Files |
|-------|------|----------|-------|-------|
| 197 | 01 | 3min | 2 | 2 |
| 197 | 02 | 3min | 2 | 5 |
| 198 | 01 | 3min | 2 | 7 |
| 198 | 02 | 4min | 2 | 6 |
| 199 | 01 | 2min | 2 | 4 |

## Accumulated Context

### Decisions

Decisions logged in PROJECT.md Key Decisions table (700+ entries).

Key decisions for v29.0 planning:
- Use Lettermint WordPress plugin (not PHP SDK) — zero code changes to wp_mail() callers; SDK requires PHP 8.2+, project is PHP 8.0+
- Google OAuth kept (not removed) — scoped down to Sheets only after Contacts/Calendar sync removal
- Gravatar removal bundled with frontend cleanup phase (Phase 199) — small enough to not warrant own phase
- CSV export is independent additive work (Phase 200) — can execute in parallel with Lettermint phases
- [Phase 198]: Added wp_clear_scheduled_hook('rondo_google_contacts_sync') in functions.php top level for automatic cleanup of orphaned cron event on existing installs
- [Phase 198]: Kept RONDO_Calendar_CLI_Command class but removed sync/status/auto_log methods — rematch() survived since it uses only Matcher (kept)
- [Phase 199-01]: GoogleOAuth namespace changed from Rondo\Calendar to Rondo\Sheets — namespace should reflect sole responsibility
- [Phase 199-01]: refresh_token() inlines client creation instead of calling deleted get_client() — scopes not needed for token refresh
- [Phase 199-01]: Gravatar endpoint removed — Gravatar.com is a US service (Made in Europe cleanup)

### Pending Todos

4 todo(s) in `.planning/todos/pending/`

### Blockers/Concerns

- Phase 201 (Lettermint Setup) requires SSH access to production for plugin install and DNS changes at the registrar
- PDF attachment passthrough via Lettermint plugin is MEDIUM confidence — must be explicitly tested before marking EMAIL-03 complete (see research/LETTERMINT.md)
- Phase 195 cron: WP-Cron is visitor-triggered on SiteGround; manually registered event at 2026-02-20 00:00:00 — consider SG Cron integration for reliable daily execution

## Session Continuity

Last session: 2026-02-20
Stopped at: Phase 199 Plan 01 complete (199-01-SUMMARY.md)
Resume file: None

**Next action:** Execute Phase 199 Plan 02 — Frontend cleanup

---
*State created: 2026-02-15*
*Last updated: 2026-02-20 — Phase 198 Backend Sync Removal complete*
