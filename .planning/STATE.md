# Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-02-08)

**Core value:** Club administrators can manage their members, teams, and important dates through a single integrated system
**Current focus:** v21.0 Per-Season Fee Categories (next milestone)

## Current Position

Phase: 155 of 159 (Fee Category Data Model) — first phase of v21.0
Plan: 01 of 01 complete
Status: Phase complete
Last activity: 2026-02-08 — Completed 155-01-PLAN.md (category data model foundation)

Progress: [██░░░░░░░░] 20% (1/5 v21.0 phases complete)

## Accumulated Context

### Decisions

Decisions are logged in PROJECT.md Key Decisions table.

- Season key format YYYY-YYYY already established (v12.0)
- Per-season fee amounts already stored in WordPress options (`rondo_membership_fees_{season}`)
- Season detection (July 1 boundary) and next-season support already implemented
- Forecast mode already works for next season
- User chose: WordPress options storage, copy-previous for new seasons, fully configurable age ranges
- **Phase 155-01:** Category data structure is slug-keyed objects with label, amount, age ranges, youth flag, sort order
- **Phase 155-01:** Copy-forward clones entire category configuration from previous season
- **Phase 155-01:** No backward compatibility layer - clean break from flat amount format

### Pending Todos

7 todo(s) in `.planning/todos/pending/`:
- **public-vog-upload-and-validation**: Public VOG Upload and Validation (area: api)
- **remove-contact-import-feature**: Remove contact import feature (area: ui)
- **remove-how-we-met-and-met-date-fields**: Remove how_we_met and met_date fields (area: data-model)
- **remove-user-approval-system**: Remove user approval system (area: auth)
- **soft-delete-inactive-members**: Soft-delete inactive members instead of hard delete (area: data-model)
- **store-sync-field-mappings-in-json**: Store Sportlink sync field mappings in ACF JSON (area: data-model)
- **switch-to-new-website-design-style**: Switch to new website design style (area: ui)

### Blockers/Concerns

- **Phase 155 deployment blocker:** Do not deploy Phase 155 alone. Must deploy together with Phase 156 (or after) to avoid breaking existing fee calculations. Phase 155 changes the data structure; Phase 156 updates the code that reads it.

## Session Continuity

Last session: 2026-02-08 22:51
Stopped at: Completed Phase 155 Plan 01 (category data model foundation)
Resume file: None

---
*State updated: 2026-02-08*
