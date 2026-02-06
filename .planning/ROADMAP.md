# Roadmap: Rondo Club v20.0 Configurable Roles

## Overview

Replace hardcoded club-specific arrays with database-driven settings and dynamic queries so any sports club can use Rondo Club without code changes. This milestone removes the last club-specific assumptions from the codebase by making filter options dynamic, role classifications configurable, and cleaning up default fallbacks from the sync layer.

## Milestones

- 🚧 **v20.0 Configurable Roles** - Phases 151-154 (in progress)

## Phases

- [ ] **Phase 151: Dynamic Filters** - Derive filter options from actual database values instead of hardcoded lists
- [ ] **Phase 152: Role Settings** - Admin UI and backend storage for configurable player roles and excluded roles
- [ ] **Phase 153: Wire Up Role Settings** - Replace hardcoded role arrays with settings lookups in business logic
- [ ] **Phase 154: Sync Cleanup** - Remove default role fallbacks from rondo-sync

## Phase Details

### Phase 151: Dynamic Filters
**Goal**: Filter options on the People list are derived from actual data in the database, not hardcoded arrays
**Depends on**: Nothing (first phase in milestone)
**Requirements**: FILT-01, FILT-02, FILT-03, FILT-04
**Success Criteria** (what must be TRUE):
  1. Age group filter dropdown on People list shows only values that exist in the database
  2. Member type filter dropdown on People list shows only values that exist in the database
  3. When a new age group or member type value arrives via sync, it appears in the filter options without any code change
  4. The REST API provides an endpoint (or extended response) that returns available filter options for both fields
**Plans**: TBD

### Phase 152: Role Settings
**Goal**: Admins can configure which job titles are "player roles" and which are "excluded/honorary roles" via the Settings UI
**Depends on**: Nothing (independent of Phase 151)
**Requirements**: ROLE-01, ROLE-02, ROLE-03, ROLE-04, ROLE-08
**Success Criteria** (what must be TRUE):
  1. Admin can open a Role Settings section in the Settings UI and select which job titles count as player roles
  2. The player role options are populated from actual work_history job titles in the database (not hardcoded)
  3. Admin can select which roles are excluded/honorary, with options populated from actual commissie work_history data
  4. Role settings are stored as WordPress options and persist across sessions
**Plans**: TBD

### Phase 153: Wire Up Role Settings
**Goal**: Business logic uses configured role settings instead of hardcoded arrays
**Depends on**: Phase 152 (settings must exist before they can be consumed)
**Requirements**: ROLE-05, ROLE-06, ROLE-07
**Success Criteria** (what must be TRUE):
  1. Volunteer status calculation reads player roles from settings and correctly identifies volunteers (people without a player role)
  2. Team detail page splits members into players and staff using the configured player roles
  3. Volunteer status calculation respects the excluded/honorary roles setting, excluding those people from volunteer counts
**Plans**: TBD

### Phase 154: Sync Cleanup
**Goal**: Rondo-sync no longer ships with default role fallbacks, relying entirely on Rondo Club settings
**Depends on**: Phase 153 (settings must be wired up before removing fallbacks)
**Requirements**: SYNC-01
**Success Criteria** (what must be TRUE):
  1. The rondo-sync codebase contains no default fallback values for Lid, Speler, or Staflid roles
  2. Rondo-sync continues to function correctly when Rondo Club has role settings configured
**Plans**: TBD

## Progress

**Execution Order:** 151 → 152 → 153 → 154
(Note: 151 and 152 are independent and could execute in parallel)

| Phase | Milestone | Plans Complete | Status | Completed |
|-------|-----------|----------------|--------|-----------|
| 151. Dynamic Filters | v20.0 | 0/TBD | Not started | - |
| 152. Role Settings | v20.0 | 0/TBD | Not started | - |
| 153. Wire Up Role Settings | v20.0 | 0/TBD | Not started | - |
| 154. Sync Cleanup | v20.0 | 0/TBD | Not started | - |

---
*Roadmap created: 2026-02-06*
*Last updated: 2026-02-06*
