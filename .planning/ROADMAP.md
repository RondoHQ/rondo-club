# Roadmap: Rondo Club

## Milestones

- SHIPPED **v20.0 Configurable Roles** — Phases 151-154 (shipped 2026-02-08) — [Archive](milestones/v20.0-ROADMAP.md)
- **v21.0 Per-Season Fee Categories** — Phases 155-161 (planned)

## Phases

<details>
<summary>v20.0 Configurable Roles (Phases 151-154) — SHIPPED 2026-02-08</summary>

- [x] Phase 151: Dynamic Filters (2/2 plans) — completed 2026-02-07
- [x] Phase 152: Role Settings (0/0 plans, pre-existing) — completed 2026-02-07
- [x] Phase 153: Wire Up Role Settings (1/1 plan) — completed 2026-02-08
- [x] Phase 154: Sync Cleanup (1/1 plan) — completed 2026-02-08

</details>

### v21.0 Per-Season Fee Categories (Planned)

- [x] **Phase 155: Fee Category Data Model** - Store fee category definitions per season in WordPress options with auto-migration — completed 2026-02-08
- [x] **Phase 156: Fee Category Backend Logic** - Replace hardcoded fee logic with config-driven category lookups — completed 2026-02-08
- [x] **Phase 157: Fee Category REST API** - Expose category definitions and CRUD operations through the API — completed 2026-02-09
- [x] **Phase 158: Fee Category Settings UI** - Admin interface for managing per-season fee categories — completed 2026-02-09
- [x] **Phase 159: Fee Category Frontend Display** - Contributie list and export consume dynamic category data from API — completed 2026-02-09
- [x] **Phase 160: Configurable Family Discount** - Make family discount percentages configurable per season instead of hardcoded — completed 2026-02-09
- [x] **Phase 161: Configurable Matching Rules** - Replace hardcoded team name and werkfunctie matching with configurable per-category rules — completed 2026-02-09

## Phase Details

### Phase 155: Fee Category Data Model
**Goal**: Fee category definitions are stored per season in WordPress options with automatic migration from hardcoded values
**Depends on**: Phase 154 (v20.0 must complete first)
**Requirements**: DATA-01, DATA-02, DATA-03
**Success Criteria** (what must be TRUE):
  1. The `rondo_membership_fees_{season}` option for the current season contains full category definitions (slug, label, amount, age_min, age_max, is_youth, sort_order) instead of just amounts
  2. On first load after upgrade, the current season option is automatically enriched with category metadata matching today's hardcoded values (migration is transparent)
  3. Creating a new season copies the full category configuration from the previous season as a starting point
  4. Existing fee calculation continues to work correctly after the data model change (no regression)
**Plans**: 1 plan
Plans:
- [x] 155-01-PLAN.md — Add category helper methods and update copy-forward in MembershipFees + developer docs

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
**Plans**: 2 plans
Plans:
- [x] 156-01-PLAN.md — Rewrite MembershipFees class: replace hardcoded constants, parse_age_group(), and fee lookups with config-driven helpers
- [x] 156-02-PLAN.md — Replace hardcoded category_order arrays in REST API and Google Sheets, update developer documentation

### Phase 157: Fee Category REST API
**Goal**: The REST API exposes full category definitions and supports CRUD operations for managing categories
**Depends on**: Phase 156 (backend logic must read from config for API responses to be meaningful)
**Requirements**: API-01, API-02, API-03, API-04
**Success Criteria** (what must be TRUE):
  1. The fee settings GET endpoint returns full category definitions (slug, label, amount, age_classes, is_youth, sort_order) per season
  2. The fee settings POST endpoint accepts operations to add, edit, remove, and reorder categories
  3. The fee list endpoint includes category metadata (labels, sort order) in its response so the frontend needs no hardcoded mappings
  4. Category validation rejects duplicate slugs and missing required fields, and warns about duplicate age class assignments
**Plans**: 2 plans (complete)
Plans:
- [x] 157-01-PLAN.md — Update settings GET/POST endpoints for full category objects with validation
- [x] 157-02-PLAN.md — Add categories metadata to fee list endpoint and update developer docs

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
**Plans**: 2 plans (complete)
Plans:
- [x] 158-01-PLAN.md — Create FeeCategorySettings component with season selector, DnD category list, CRUD, validation display, and age class coverage
- [x] 158-02-PLAN.md — Wire into Settings.jsx, remove old FeesSubtab, version bump to 21.0.0, deploy

### Phase 159: Fee Category Frontend Display
**Goal**: The contributie list and Google Sheets export derive all category information from the API response
**Depends on**: Phase 157 (API must include category metadata in responses)
**Requirements**: DISPLAY-01, DISPLAY-02, DISPLAY-03
**Success Criteria** (what must be TRUE):
  1. The contributie list page renders category badges (labels, colors) using data from the API response, with no hardcoded `FEE_CATEGORIES` object in the frontend
  2. Category colors are auto-assigned from a fixed palette based on sort order position
  3. Google Sheets export uses dynamic category definitions from the API config, not hardcoded column layouts
**Plans**: 1 plan
Plans:
- [x] 159-01-PLAN.md — Remove all hardcoded category definitions from frontend and export, replace with API-driven data

### Phase 160: Configurable Family Discount
**Goal**: Family discount percentages are configurable per season instead of hardcoded 0%/25%/50%
**Depends on**: Phase 158 (Settings UI must exist to add discount config)
**Requirements**: TBD
**Success Criteria** (what must be TRUE):
  1. Family discount tiers (2nd child %, 3rd+ child %) are stored per season in `rondo_family_discount_{season}` WordPress option
  2. `get_family_discount_rate()` reads from season config instead of hardcoded values
  3. Admin can configure family discount percentages in the fee category settings UI
  4. Changing discount percentages correctly affects fee calculations for the relevant season
**Plans**: 2 plans
Plans:
- [x] 160-01-PLAN.md — Backend: add discount config helpers, update get_family_discount_rate(), extend REST API with validation
- [x] 160-02-PLAN.md — Frontend: add FamilyDiscountSection to settings UI, version bump, docs

### Phase 161: Configurable Matching Rules
**Goal**: Fee category assignment uses configurable matching rules (teams, werkfuncties) instead of hardcoded logic
**Depends on**: Phase 158 (Settings UI must exist for rule configuration)
**Requirements**: TBD
**Success Criteria** (what must be TRUE):
  1. Each category can optionally specify matching teams (multi-select from all teams in system) — replaces hardcoded `is_recreational_team()` string matching
  2. Each category can optionally specify matching werkfuncties (multi-select) — replaces hardcoded `is_donateur()` check
  3. `calculate_fee()` reads matching rules from category config instead of hardcoded slug checks for 'recreant' and 'donateur'
  4. Admin can configure team and werkfunctie matching in the fee category settings UI
  5. Existing fee calculations produce the same results after migration (matching rules pre-populated from current hardcoded values)
**Plans**: 2 plans
Plans:
- [x] 161-01-PLAN.md — Backend: matching rules data model, migration, config-driven calculate_fee(), REST validation and werkfuncties endpoint
- [x] 161-02-PLAN.md — Frontend: matching rules UI in FeeCategorySettings, version bump, deploy

## Progress

**Execution Order:** 155 → 156 → 157 → 158 → 159/160/161
(Note: 159, 160 and 161 are independent and could execute in parallel)

| Phase | Milestone | Plans Complete | Status | Completed |
|-------|-----------|----------------|--------|-----------|
| 155. Fee Category Data Model | v21.0 | 1/1 | ✓ Complete | 2026-02-08 |
| 156. Fee Category Backend Logic | v21.0 | 2/2 | ✓ Complete | 2026-02-08 |
| 157. Fee Category REST API | v21.0 | 2/2 | ✓ Complete | 2026-02-09 |
| 158. Fee Category Settings UI | v21.0 | 2/2 | ✓ Complete | 2026-02-09 |
| 159. Fee Category Frontend Display | v21.0 | 1/1 | ✓ Complete | 2026-02-09 |
| 160. Configurable Family Discount | v21.0 | 2/2 | ✓ Complete | 2026-02-09 |
| 161. Configurable Matching Rules | v21.0 | 2/2 | ✓ Complete | 2026-02-09 |

---
*Roadmap created: 2026-02-06*
*Last updated: 2026-02-09 — Phase 159 completed*
