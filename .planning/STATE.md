# Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-02-07)

**Core value:** Club administrators can manage their members, teams, and important dates through a single integrated system
**Current focus:** v21.0 Per-Season Fee Categories

## Current Position

Phase: 151 of 159 (Dynamic Filters) — first plan of v20.0
Plan: 01 of 02
Status: In progress
Last activity: 2026-02-07 — Completed 151-01-PLAN.md

Progress: [█░░░░░░░░░] 0.6%

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

### Pending Todos

3 todo(s) in `.planning/todos/pending/`:
- **public-vog-upload-and-validation**: Public VOG Upload and Validation (area: api)
- **soft-delete-inactive-members**: Soft-delete inactive members instead of hard delete (area: data-model)
- **store-sync-field-mappings-in-json**: Store Sportlink sync field mappings in ACF JSON (area: data-model)

### Blockers/Concerns

- v20.0 Configurable Roles (phases 151-154) not yet executed — v21.0 phases 155-159 depend on v20.0 completing first

## Session Continuity

Last session: 2026-02-07T20:42:00Z
Stopped at: Completed 151-01-PLAN.md (Dynamic filter options endpoint)
Resume file: None

---
*State updated: 2026-02-07*
