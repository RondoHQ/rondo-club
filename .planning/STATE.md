# Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-02-07)

**Core value:** Club administrators can manage their members, teams, and important dates through a single integrated system
**Current focus:** v20.0 Configurable Roles

## Current Position

Phase: 152 of 159 (Role Settings) — second phase of v20.0
Plan: —
Status: Ready to plan
Last activity: 2026-02-07 — Phase 151 (Dynamic Filters) complete and verified

Progress: [██░░░░░░░░] 25% (1/4 v20.0 phases)

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

### Pending Todos

3 todo(s) in `.planning/todos/pending/`:
- **public-vog-upload-and-validation**: Public VOG Upload and Validation (area: api)
- **soft-delete-inactive-members**: Soft-delete inactive members instead of hard delete (area: data-model)
- **store-sync-field-mappings-in-json**: Store Sportlink sync field mappings in ACF JSON (area: data-model)

### Blockers/Concerns

- v20.0 Configurable Roles (phases 152-154 remaining) — v21.0 phases 155-159 depend on v20.0 completing first

## Session Continuity

Last session: 2026-02-07
Stopped at: Phase 151 complete and verified, ready for Phase 152
Resume file: None

---
*State updated: 2026-02-07*
