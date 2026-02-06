# Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-02-06)

**Core value:** Add workspaces and sharing to enable team collaboration while maintaining the personal, relationship-focused experience that makes Stadion unique.
**Current focus:** v19.0 Birthdate Simplification - Gap closure (Phases 149-150)

## Current Position

Phase: 149 of 150 (Fix vCard Birthday Export)
Plan: 1 of 1 in current phase
Status: Phase 149 complete
Last activity: 2026-02-06 - Completed 149-01-PLAN.md

Progress: [#########-] 83% (5/6 plans complete)

## Performance Metrics

**Velocity:**
- Total plans completed: 5
- Average duration: 21 min
- Total execution time: 1.75 hours

**By Phase:**

| Phase | Plans | Total | Avg/Plan |
|-------|-------|-------|----------|
| 147   | 1     | 5min  | 5min     |
| 148   | 2     | 80min | 40min    |
| 149   | 1     | 5min  | 5min     |

**Recent Trend:**
- Last 3 plans: 45min, 35min, 5min
- Trend: Gap closure phases are quick (5min each), infrastructure removal takes longer

*Updated after each plan completion*

## Accumulated Context

### Decisions

Decisions are logged in PROJECT.md Key Decisions table.

Recent decisions from v19.0:
- **Store birthdate directly on person records** - Not as Important Date CPT, simpler data model
- **Display format: age + birthdate** - "43 jaar (6 feb 1982)" includes full birth year for clarity
- **Dashboard widget only shows birthdays** - Other Important Dates removed, Phase 148 removes infrastructure
- **Delete production data before code removal** - Data must be deleted while CPT is still registered
- **is_deceased always returns false** - Death date feature removed with Important Dates
- **Version 19.0.0 major release** - Removing Important Dates is a breaking change

Recent decisions from v17.0:
- **Individual WordPress option keys for club config** - Separate options allow independent updates
- **REST partial updates via null-checking** - Flexible API that only updates provided fields
- **Dynamic CSS variable injection** - Runtime color changes without CSS rebuilds

### Pending Todos

3 todo(s) in `.planning/todos/pending/`:
- **public-vog-upload-and-validation**: Public VOG Upload and Validation (area: api)
- **soft-delete-inactive-members**: Soft-delete inactive members instead of hard delete (area: data-model)
- **store-sync-field-mappings-in-json**: Store Sportlink sync field mappings in ACF JSON (area: data-model)

### Blockers/Concerns

None.

## Session Continuity

Last session: 2026-02-06T12:20:38Z
Stopped at: Completed 149-01-PLAN.md
Resume file: None

---
*State updated: 2026-02-06*
