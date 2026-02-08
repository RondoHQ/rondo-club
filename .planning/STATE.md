# Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-02-07)

**Core value:** Club administrators can manage their members, teams, and important dates through a single integrated system
**Current focus:** v20.0 Configurable Roles (complete) → v21.0 Per-Season Fee Categories (next)

## Current Position

Phase: 154 of 159 (Sync Cleanup) — fourth phase of v20.0
Plan: 1 of 1
Status: Phase complete
Last activity: 2026-02-08 — Completed 154-01-PLAN.md (remove hardcoded role fallbacks from rondo-sync)

Progress: [████████░░] 100% (4/4 v20.0 phases complete)

## Accumulated Context

### Decisions

Decisions are logged in PROJECT.md Key Decisions table.

- Season key format YYYY-YYYY already established (v12.0)
- Per-season fee amounts already stored in WordPress options (`rondo_membership_fees_{season}`)
- Season detection (July 1 boundary) and next-season support already implemented
- Forecast mode already works for next season
- User chose: WordPress options storage, copy-previous for new seasons, fully configurable age ranges
- Generic filter config pattern established (151-01): map filter key → meta_key + sort_method
- Smart age group sorting uses numeric extraction + gender variant detection (151-01)
- Member types use priority array with unknown types at end (151-01)
- Filter options cached 5 minutes in frontend (151-02) - changes only on sync
- Stale URL params cleared silently (151-02) for better UX
- GET /rondo/v1/volunteer-roles/settings accessible to all authenticated users, POST admin-only (153-01)
- Settings hooks use 5-minute staleTime for rarely-changing data (153-01)
- Team detail player/staff split driven by configured role settings (153-01)
- Skip-and-warn pattern for missing data instead of silent fallbacks (154-01)
- rondo-sync passes through Sportlink role descriptions without modification (154-01)
- Database migrations use PRAGMA table_info checks before ALTER TABLE (154-01)

### Pending Todos

4 todo(s) in `.planning/todos/pending/`:
- **public-vog-upload-and-validation**: Public VOG Upload and Validation (area: api)
- **remove-user-approval-system**: Remove user approval system (area: auth)
- **soft-delete-inactive-members**: Soft-delete inactive members instead of hard delete (area: data-model)
- **store-sync-field-mappings-in-json**: Store Sportlink sync field mappings in ACF JSON (area: data-model)

### Blockers/Concerns

None - v20.0 Configurable Roles is complete (phases 152-154 done)

## Session Continuity

Last session: 2026-02-08
Stopped at: Completed 154-01-PLAN.md
Resume file: None

---
*State updated: 2026-02-08*
