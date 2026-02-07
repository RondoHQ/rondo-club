# Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-02-07)

**Core value:** Club administrators can manage their members, teams, and important dates through a single integrated system
**Current focus:** v21.0 Per-Season Fee Categories

## Current Position

Phase: Not started (defining requirements)
Plan: —
Status: Defining requirements
Last activity: 2026-02-07 — Milestone v21.0 started

Progress: [░░░░░░░░░░] 0%

## Accumulated Context

### Decisions

Decisions are logged in PROJECT.md Key Decisions table.

- Season key format YYYY-YYYY already established (v12.0)
- Per-season fee amounts already stored in WordPress options (`rondo_membership_fees_{season}`)
- Season detection (July 1 boundary) and next-season support already implemented
- Forecast mode already works for next season
- User chose: WordPress options storage, copy-previous for new seasons, fully configurable age ranges

### Pending Todos

3 todo(s) in `.planning/todos/pending/`:
- **public-vog-upload-and-validation**: Public VOG Upload and Validation (area: api)
- **soft-delete-inactive-members**: Soft-delete inactive members instead of hard delete (area: data-model)
- **store-sync-field-mappings-in-json**: Store Sportlink sync field mappings in ACF JSON (area: data-model)

### Blockers/Concerns

- v20.0 Configurable Roles (phases 151-154) not yet executed — independent, can be done before or after v21.0

## Session Continuity

Last session: 2026-02-07
Stopped at: Defining requirements for v21.0
Resume file: None

---
*State updated: 2026-02-07*
