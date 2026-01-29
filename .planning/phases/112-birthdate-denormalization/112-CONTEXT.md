# Phase 112: Birthdate Denormalization - Context

**Gathered:** 2026-01-29
**Status:** Ready for planning

<domain>
## Phase Boundary

Denormalize birthdate from important_date posts to person post_meta for fast filtering. Enable birth year filtering on the People list. This phase handles data storage and sync — the filter UI is part of Phase 113 (Frontend Pagination).

</domain>

<decisions>
## Implementation Decisions

### Data Migration
- Backfill all existing people at once via WP-CLI command
- People without birthdays have no `_birthdate` meta key (empty/null)
- Migration triggered manually via `wp stadion migrate-birthdates` command
- Migration is idempotent — safe to re-run, overwrites existing values

### Sync Timing
- Sync happens immediately in same request (save_post hook)
- If person has multiple birthday important_dates, use the first one found
- `_birthdate` only clears on permanent delete, not when trashed
- No logging — sync is silent background operation

### Filter Behavior
- Year range picker UI (from-to selection)
- Year range calculated dynamically from actual birthdays in database
- People without known birthday are excluded from birth year filter results
- Birth year filter combines with other filters using AND logic

### Claude's Discretion
- Exact date format for `_birthdate` meta storage
- Hook priority for sync operations
- Performance optimization for range queries
- Error handling for malformed date data

</decisions>

<specifics>
## Specific Ideas

- WP-CLI command gives control over when migration runs (e.g., during low traffic)
- Dynamic year range means the filter is always relevant to actual data
- Idempotent migration is simpler than tracking progress, acceptable for ~1400 records

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 112-birthdate-denormalization*
*Context gathered: 2026-01-29*
