# Requirements: Rondo Club v21.0 Per-Season Fee Categories

**Defined:** 2026-02-07
**Core Value:** Fee categories are fully configurable per season, removing all hardcoded category definitions from the codebase.

## v21.0 Requirements

### Data Model

- [ ] **DATA-01**: Fee category definitions (slug, label, amount, age_min, age_max, is_youth, sort_order) are stored per season in the existing `rondo_membership_fees_{season}` WordPress option
- [ ] **DATA-02**: On first load, current season option is auto-enriched with today's hardcoded category values (migration)
- [ ] **DATA-03**: Creating a new season auto-copies the full category configuration from the previous season

### Backend Logic

- [ ] **LOGIC-01**: `parse_age_group()` reads age ranges from season category config instead of hardcoded values
- [ ] **LOGIC-02**: `VALID_TYPES` is derived from season category config (no hardcoded constant)
- [ ] **LOGIC-03**: `youth_categories` is derived from `is_youth` flag in category config (no hardcoded arrays)
- [ ] **LOGIC-04**: Category sort order is derived from config (removing duplicated `category_order` arrays from `class-rest-api.php`, `class-rest-google-sheets.php`, and `ContributieList.jsx`)
- [ ] **LOGIC-05**: Fee calculation correctly uses per-season categories for both current and forecast modes

### REST API

- [ ] **API-01**: Fee settings GET endpoint returns full category definitions per season (not just amounts)
- [ ] **API-02**: Fee settings POST endpoint accepts category add, edit, remove, and reorder operations
- [ ] **API-03**: Fee list endpoint includes category metadata (labels, sort order) in response so frontend does not need hardcoded mappings
- [ ] **API-04**: Category validation: no duplicate slugs, required fields (slug, label, amount), age ranges don't overlap

### Settings UI

- [ ] **UI-01**: Admin can manage fee categories in the fee settings section (add, edit, remove categories)
- [ ] **UI-02**: Per-category fields editable: slug, label, amount, age range (min/max), youth flag
- [ ] **UI-03**: Season selector for managing category configs of different seasons
- [ ] **UI-04**: Drag-and-drop reordering of categories in the settings UI
- [ ] **UI-05**: Visual age range coverage display showing gaps or overlaps between categories

### Frontend Display

- [ ] **DISPLAY-01**: Fee list category badges (labels, colors) are derived from API response, not hardcoded `FEE_CATEGORIES`
- [ ] **DISPLAY-02**: Category colors are auto-assigned from a fixed palette based on sort order
- [ ] **DISPLAY-03**: Google Sheets export uses dynamic category definitions from config

## Future Requirements

- Configurable category colors per category — deferred, auto-assigned palette sufficient for now
- Per-category discount rules — currently family discount is universal, could be per-category in future

## Out of Scope

| Feature | Reason |
|---------|--------|
| Custom category colors | Auto-assigned palette is simpler and consistent |
| Category archival/history | Seasons already provide temporal separation |
| Category import/export | WordPress options are portable enough |
| Sportlink age class auto-mapping | Age ranges are set manually per category; auto-detect would need Sportlink API investigation |

## Traceability

| Requirement | Phase | Status |
|-------------|-------|--------|
| DATA-01 | TBD | Pending |
| DATA-02 | TBD | Pending |
| DATA-03 | TBD | Pending |
| LOGIC-01 | TBD | Pending |
| LOGIC-02 | TBD | Pending |
| LOGIC-03 | TBD | Pending |
| LOGIC-04 | TBD | Pending |
| LOGIC-05 | TBD | Pending |
| API-01 | TBD | Pending |
| API-02 | TBD | Pending |
| API-03 | TBD | Pending |
| API-04 | TBD | Pending |
| UI-01 | TBD | Pending |
| UI-02 | TBD | Pending |
| UI-03 | TBD | Pending |
| UI-04 | TBD | Pending |
| UI-05 | TBD | Pending |
| DISPLAY-01 | TBD | Pending |
| DISPLAY-02 | TBD | Pending |
| DISPLAY-03 | TBD | Pending |

**Coverage:**
- v21.0 requirements: 20 total
- Mapped to phases: 0
- Unmapped: 20

---
*Requirements defined: 2026-02-07*
*Last updated: 2026-02-07 after initial definition*
