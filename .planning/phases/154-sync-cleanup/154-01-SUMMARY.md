---
phase: 154-sync-cleanup
plan: 01
subsystem: sync
tags: [rondo-sync, sqlite, sportlink, role-classification, data-model]

# Dependency graph
requires:
  - phase: 153-wire-up-role-settings
    provides: Rondo Club role settings API and UI for configuring player/staff classification
provides:
  - rondo-sync passes through Sportlink role descriptions without modification or classification
  - Simplified database schema (no member_type column)
  - Skip-and-warn behavior for missing role descriptions
affects: [future sync maintenance, role configuration documentation]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Skip-and-warn pattern for missing data instead of silent fallbacks"
    - "Database migrations using PRAGMA table_info checks before ALTER TABLE"

key-files:
  created: []
  modified:
    - /Users/joostdevalk/Code/rondo/rondo-sync/lib/rondo-club-db.js
    - /Users/joostdevalk/Code/rondo/rondo-sync/steps/download-teams-from-sportlink.js
    - /Users/joostdevalk/Code/rondo/rondo-sync/steps/submit-rondo-club-work-history.js
    - /Users/joostdevalk/Code/rondo/rondo-sync/steps/submit-rondo-club-commissie-work-history.js
    - /Users/joostdevalk/Code/rondo/rondo-sync/pipelines/sync-individual.js
    - /Users/joostdevalk/Code/rondo/rondo-sync/docs/database-schema.md
    - /Users/joostdevalk/Code/rondo/rondo-sync/docs/pipeline-teams.md

key-decisions:
  - "Use skip-and-warn instead of fallback values when role descriptions are missing"
  - "Remove member_type classification entirely from sync layer - Rondo Club handles this via settings"
  - "Add migration to drop member_type column from existing databases"
  - "Delete dead getTeamMemberCounts function that relied on member_type"

patterns-established:
  - "Migration pattern: Check column exists with PRAGMA table_info before DROP COLUMN"
  - "Error handling: Skip entries with missing critical data, log warning, continue sync"

# Metrics
duration: 5min
completed: 2026-02-08
---

# Phase 154 Plan 01: Sync Cleanup Summary

**Removed all hardcoded role fallbacks from rondo-sync, simplified database schema by dropping member_type column, established skip-and-warn pattern for missing role data**

## Performance

- **Duration:** 5 min
- **Started:** 2026-02-08T14:15:46Z
- **Completed:** 2026-02-08T14:20:57Z
- **Tasks:** 2
- **Files modified:** 7

## Accomplishments
- Zero hardcoded role fallback values ('Speler', 'Staflid', 'Lid') remain in JavaScript codebase
- sportlink_team_members table simplified - member_type column removed with migration
- Sync passes through Sportlink-provided role descriptions without modification
- Missing role descriptions trigger skip-and-warn behavior instead of silent fallbacks
- Dead getTeamMemberCounts function removed (relied on removed member_type column)
- Documentation updated to reflect schema changes

## Task Commits

Each task was committed atomically:

1. **Task 1: Clean up database layer** - `fc4b372` (refactor)
   - Remove member_type column from CREATE TABLE
   - Add migration to DROP COLUMN from existing databases
   - Simplify computeTeamMemberHash to 3 params
   - Remove member_type from upsertTeamMembers
   - Simplify getTeamMemberRole to return role_description or null
   - Delete getTeamMemberCounts function and export

2. **Task 2: Clean up sync scripts and docs** - `e73bc91` (refactor)
   - Remove 'Speler'/'Staflid' fallbacks from download-teams-from-sportlink.js
   - Delete determineJobTitleFallback function
   - Simplify getJobTitleForTeam, remove fallbackKernelGameActivities parameter
   - Remove 'Lid' fallbacks from commissie work history
   - Update documentation (database-schema.md, pipeline-teams.md)

## Files Created/Modified
- `lib/rondo-club-db.js` - Simplified database layer, removed member_type classification
- `steps/download-teams-from-sportlink.js` - Skip-and-warn for missing role descriptions
- `steps/submit-rondo-club-work-history.js` - Removed fallback logic, simplified job title lookup
- `steps/submit-rondo-club-commissie-work-history.js` - Removed 'Lid' fallbacks
- `pipelines/sync-individual.js` - Display placeholder for missing roles
- `docs/database-schema.md` - Updated table schema, removed member_type
- `docs/pipeline-teams.md` - Updated field list

## Decisions Made
- **Skip-and-warn over silent fallbacks**: When role_description is missing, log warning and skip entry instead of using hardcoded fallback. Makes data quality issues visible.
- **Complete removal of member_type**: Player/staff classification now happens in Rondo Club via configured role settings (Phase 152/153), not in sync layer.
- **Migration safety**: Check column existence with PRAGMA table_info before DROP COLUMN to avoid errors on already-migrated databases.
- **Dead code removal**: getTeamMemberCounts function was exported but never imported/called - confirmed dead code and removed.

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None - all changes were straightforward refactoring with clear requirements.

## User Setup Required

None - no external service configuration required. Database migration runs automatically on next sync.

## Next Phase Readiness

- v20.0 Configurable Roles is now complete (Phases 152, 153, 154)
- rondo-sync is now club-agnostic - passes through role descriptions without classification
- Rondo Club handles all role classification via configured settings
- Ready for v21.0 phases (155-159) which depend on v20.0 completion
- No blockers or concerns

---
*Phase: 154-sync-cleanup*
*Completed: 2026-02-08*
