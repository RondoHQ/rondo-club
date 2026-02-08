# Roadmap: Rondo Club v20.0 Configurable Roles

## Overview

Replace hardcoded club-specific arrays with database-driven settings and dynamic queries so any sports club can use Rondo Club without code changes. This milestone removes the last club-specific assumptions from the codebase by making filter options dynamic, role classifications configurable, and cleaning up default fallbacks from the sync layer.

## Milestones

- 🚧 **v20.0 Configurable Roles** - Phases 151-154 (in progress)
- 📋 **v21.0 Per-Season Fee Categories** - Phases 155-159 (planned)

## Phases

- [x] **Phase 151: Dynamic Filters** - Derive filter options from actual database values instead of hardcoded lists
- [x] **Phase 152: Role Settings** - Admin UI and backend storage for configurable player roles and excluded roles
- [x] **Phase 153: Wire Up Role Settings** - Replace hardcoded role arrays with settings lookups in business logic
- [ ] **Phase 154: Sync Cleanup** - Remove default role fallbacks from rondo-sync
- [ ] **Phase 155: Fee Category Data Model** - Store fee category definitions per season in WordPress options with auto-migration
- [ ] **Phase 156: Fee Category Backend Logic** - Replace hardcoded fee logic with config-driven category lookups
- [ ] **Phase 157: Fee Category REST API** - Expose category definitions and CRUD operations through the API
- [ ] **Phase 158: Fee Category Settings UI** - Admin interface for managing per-season fee categories
- [ ] **Phase 159: Fee Category Frontend Display** - Contributie list and export consume dynamic category data from API

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
**Plans:** 2 plans
Plans:
  - [x] 151-01-PLAN.md -- Backend REST endpoint for filter options with generic infrastructure
  - [x] 151-02-PLAN.md -- Frontend hook, dynamic dropdowns, documentation

### Phase 152: Role Settings
**Goal**: Admins can configure which job titles are "player roles" and which are "excluded/honorary roles" via the Settings UI
**Depends on**: Nothing (independent of Phase 151)
**Requirements**: ROLE-01, ROLE-02, ROLE-03, ROLE-04, ROLE-08
**Success Criteria** (what must be TRUE):
  1. Admin can open a Role Settings section in the Settings UI and select which job titles count as player roles
  2. The player role options are populated from actual work_history job titles in the database (not hardcoded)
  3. Admin can select which roles are excluded/honorary, with options populated from actual commissie work_history data
  4. Role settings are stored as WordPress options and persist across sessions
**Plans**: 0 plans (pre-existing implementation satisfied all criteria)
**Note**: Role settings UI, REST API, and WordPress options storage were implemented in v19.1.0 as a quick task. All success criteria verified met.

### Phase 153: Wire Up Role Settings
**Goal**: Business logic uses configured role settings instead of hardcoded arrays
**Depends on**: Phase 152 (settings must exist before they can be consumed)
**Requirements**: ROLE-05, ROLE-06, ROLE-07
**Success Criteria** (what must be TRUE):
  1. Volunteer status calculation reads player roles from settings and correctly identifies volunteers (people without a player role)
  2. Team detail page splits members into players and staff using the configured player roles
  3. Volunteer status calculation respects the excluded/honorary roles setting, excluding those people from volunteer counts
**Plans:** 1 plan
Plans:
  - [x] 153-01-PLAN.md -- Fix API permission, create useVolunteerRoleSettings hook, wire TeamDetail

### Phase 154: Sync Cleanup
**Goal**: Rondo-sync no longer ships with default role fallbacks, relying entirely on Rondo Club settings
**Depends on**: Phase 153 (settings must be wired up before removing fallbacks)
**Requirements**: SYNC-01
**Success Criteria** (what must be TRUE):
  1. The rondo-sync codebase contains no default fallback values for Lid, Speler, or Staflid roles
  2. Rondo-sync continues to function correctly when Rondo Club has role settings configured
**Plans**: TBD

---

### Phase 155: Fee Category Data Model
**Goal**: Fee category definitions are stored per season in WordPress options with automatic migration from hardcoded values
**Depends on**: Phase 154 (v20.0 must complete first)
**Requirements**: DATA-01, DATA-02, DATA-03
**Success Criteria** (what must be TRUE):
  1. The `rondo_membership_fees_{season}` option for the current season contains full category definitions (slug, label, amount, age_min, age_max, is_youth, sort_order) instead of just amounts
  2. On first load after upgrade, the current season option is automatically enriched with category metadata matching today's hardcoded values (migration is transparent)
  3. Creating a new season copies the full category configuration from the previous season as a starting point
  4. Existing fee calculation continues to work correctly after the data model change (no regression)
**Plans**: TBD

### Phase 156: Fee Category Backend Logic
**Goal**: All fee calculation logic reads from per-season category config instead of hardcoded constants
**Depends on**: Phase 155 (enriched data model must exist)
**Requirements**: LOGIC-01, LOGIC-02, LOGIC-03, LOGIC-04, LOGIC-05
**Success Criteria** (what must be TRUE):
  1. `parse_age_group()` determines a member's fee category by reading age ranges from the season config, not hardcoded values
  2. The list of valid fee types (VALID_TYPES equivalent) is derived from category slugs in the season config
  3. The `youth_categories` list is derived from the `is_youth` flag on each category in the config
  4. Category sort order comes from a single source (config `sort_order`), removing the duplicated `category_order` arrays from `class-rest-api.php`, `class-rest-google-sheets.php`, and `ContributieList.jsx`
  5. Fee calculation produces correct results for both current season and forecast mode using per-season categories
**Plans**: TBD

### Phase 157: Fee Category REST API
**Goal**: The REST API exposes full category definitions and supports CRUD operations for managing categories
**Depends on**: Phase 156 (backend logic must read from config for API responses to be meaningful)
**Requirements**: API-01, API-02, API-03, API-04
**Success Criteria** (what must be TRUE):
  1. The fee settings GET endpoint returns full category definitions (slug, label, amount, age range, youth flag, sort order) per season
  2. The fee settings POST endpoint accepts operations to add, edit, remove, and reorder categories
  3. The fee list endpoint includes category metadata (labels, sort order) in its response so the frontend needs no hardcoded mappings
  4. Category validation rejects duplicate slugs, missing required fields, and overlapping age ranges
**Plans**: TBD

### Phase 158: Fee Category Settings UI
**Goal**: Admins can manage fee categories per season through the Settings UI
**Depends on**: Phase 157 (API must support category CRUD)
**Requirements**: UI-01, UI-02, UI-03, UI-04, UI-05
**Success Criteria** (what must be TRUE):
  1. Admin can add, edit, and remove fee categories in the fee settings section
  2. Each category's slug, label, amount, age range (min/max), and youth flag are editable
  3. Admin can switch between seasons to manage category configs for different seasons
  4. Admin can drag-and-drop to reorder categories, and the new order persists
  5. A visual display shows age range coverage across categories, highlighting gaps or overlaps
**Plans**: TBD

### Phase 159: Fee Category Frontend Display
**Goal**: The contributie list and Google Sheets export derive all category information from the API response
**Depends on**: Phase 157 (API must include category metadata in responses)
**Requirements**: DISPLAY-01, DISPLAY-02, DISPLAY-03
**Success Criteria** (what must be TRUE):
  1. The contributie list page renders category badges (labels, colors) using data from the API response, with no hardcoded `FEE_CATEGORIES` object in the frontend
  2. Category colors are auto-assigned from a fixed palette based on sort order position
  3. Google Sheets export uses dynamic category definitions from the API config, not hardcoded column layouts
**Plans**: TBD

## Progress

**Execution Order:** 151 → 152 → 153 → 154 → 155 → 156 → 157 → 158/159
(Note: 151 and 152 are independent and could execute in parallel)
(Note: 158 and 159 are independent and could execute in parallel)

| Phase | Milestone | Plans Complete | Status | Completed |
|-------|-----------|----------------|--------|-----------|
| 151. Dynamic Filters | v20.0 | 2/2 | ✓ Complete | 2026-02-07 |
| 152. Role Settings | v20.0 | 0/0 | ✓ Complete (pre-existing) | 2026-02-07 |
| 153. Wire Up Role Settings | v20.0 | 1/1 | ✓ Complete | 2026-02-08 |
| 154. Sync Cleanup | v20.0 | 0/TBD | Not started | - |
| 155. Fee Category Data Model | v21.0 | 0/TBD | Not started | - |
| 156. Fee Category Backend Logic | v21.0 | 0/TBD | Not started | - |
| 157. Fee Category REST API | v21.0 | 0/TBD | Not started | - |
| 158. Fee Category Settings UI | v21.0 | 0/TBD | Not started | - |
| 159. Fee Category Frontend Display | v21.0 | 0/TBD | Not started | - |

---
*Roadmap created: 2026-02-06*
*Last updated: 2026-02-08 -- Phase 153 complete (1/1 plans executed, verified)*
