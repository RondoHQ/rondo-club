# Phase 132 Plan 01: Data Foundation Summary

**One-liner:** Registered discipline_case CPT with 11 ACF fields, seizoen taxonomy with current season support, and unique dossier_id validation via ACF filter

---

## Frontmatter

```yaml
phase: 132-data-foundation
plan: 01
subsystem: backend-data
tags: [wordpress, cpt, acf, taxonomy, rest-api, sportlink-integration]
requires: [acf-pro, wordpress-rest-api]
provides: [discipline_case-cpt, seizoen-taxonomy, discipline-case-rest-api]
affects: [133-business-logic, 134-ui-implementation]
tech-stack:
  added: []
  patterns: [wordpress-cpt-registration, acf-field-groups, taxonomy-term-meta, acf-validation-filters]
key-files:
  created:
    - acf-json/group_discipline_case_fields.json
  modified:
    - includes/class-post-types.php
    - includes/class-taxonomies.php
decisions: [use-post-object-for-person-link, store-single-charge-code, seizoen-as-shared-taxonomy, dossier-id-uniqueness-via-acf-filter]
metrics:
  duration: 2m 33s
  completed: 2026-02-03
```

---

## What Was Built

### Custom Post Type: discipline_case
- **Labels:** Dutch labels (Tuchtzaak singular, Tuchtzaken plural)
- **REST endpoint:** `wp/v2/discipline-cases` (show_in_rest: true)
- **Menu position:** 9 (after Todos)
- **Menu icon:** dashicons-warning
- **Supports:** title, author (minimal - ACF holds all data)
- **Hierarchical:** false (cases are flat, no parent-child)

### Taxonomy: seizoen
- **Type:** Non-hierarchical (like tags, not categories)
- **Applied to:** discipline_case CPT (designed for future expansion)
- **REST-enabled:** show_in_rest: true
- **Shows in admin column:** Filterable in discipline case list
- **Current season support:** Term meta `is_current_season` flag with helper methods

### ACF Field Group: Tuchtzaak Details
**11 fields for discipline case data:**

1. **dossier_id** (text, required)
   - Unique identifier from Sportlink
   - Validated for uniqueness via ACF filter
   - Primary sync key for Sportlink integration

2. **person** (post_object)
   - Optional link to person CPT
   - Single selection (multiple: 0)
   - Returns integer post ID
   - Allow null: cases can exist without person link

3. **match_date** (date_picker)
   - Date of incident/match
   - Display: d-m-Y, Return: Ymd

4. **processing_date** (date_picker)
   - When case was processed
   - Display: d-m-Y, Return: Ymd

5. **match_description** (text)
   - Description of match/event

6. **team_name** (text)
   - Team involved (stored as text, not relationship)

7. **charge_codes** (text)
   - Single charge code per case (not multi-select)

8. **charge_description** (textarea)
   - Description of the charge/violation

9. **sanction_description** (textarea)
   - Description of imposed sanction

10. **administrative_fee** (number)
    - Min: 0, Step: 0.01
    - Prepend: € symbol
    - Stored in euros

11. **is_charged** (true_false)
    - "Is doorbelast" - whether costs were charged to person
    - UI toggle, default: false

**Field group settings:**
- Location: discipline_case post type
- show_in_rest: 1 (exposes all fields via REST API)
- hide_on_screen: excerpt, discussion, comments, slug

### Validation & Helper Methods

**Unique dossier_id validation:**
- ACF filter: `acf/validate_value/name=dossier_id`
- Queries existing discipline cases
- Excludes current post when editing
- Returns Dutch error message on duplicate

**Season management helpers:**
- `set_current_season($slug)` - Sets one season as current, clears previous
- `get_current_season()` - Returns current season term or null
- Uses term meta `is_current_season` flag

---

## Technical Implementation

### Pattern 1: CPT Registration for Sportlink-Synced Data
Followed existing pattern from team/commissie CPTs:
- `public: false` - Not publicly accessible via WordPress URLs
- `publicly_queryable: false` - No front-end queries
- `show_in_rest: true` - Enables REST API and Gutenberg
- `rewrite: false` - React Router handles routing

### Pattern 2: ACF Post Object vs Relationship Field
**Chose Post Object for person link because:**
- Returns single integer (not array) for single selection
- Cleaner REST API response structure
- Matches user requirement: "single person per case"
- Relationship field always returns array (even for single value)

### Pattern 3: Shared Taxonomy Design
**seizoen registered for discipline_case only, but designed for expansion:**
- Non-hierarchical (seasons are flat, not parent-child)
- show_admin_column: true (filterable in post lists)
- Can later add to other post types via `register_taxonomy_for_object_type()`
- Term meta allows "current season" concept for default filtering

### Pattern 4: ACF Validation Filter
**Unique field validation without custom database checks:**
- Filter runs on admin save and potentially REST API creates
- Checks both `$_POST['post_ID']` and `$_POST['post_id']` sources
- Excludes current post from duplicate check
- Returns translatable error message string

---

## REST API Response Format

**GET wp/v2/discipline-cases/{id}** returns:

```json
{
  "id": 123,
  "title": {"rendered": "Auto-generated title"},
  "acf": {
    "dossier_id": "2024-001",
    "person": 456,
    "match_date": "20240915",
    "processing_date": "20241001",
    "match_description": "Team A - Team B",
    "team_name": "Senioren 1",
    "charge_codes": "A123",
    "charge_description": "Rood kaart (ernstig)",
    "sanction_description": "3 wedstrijden schorsing",
    "administrative_fee": "25.00",
    "is_charged": true
  },
  "seizoen": [12]
}
```

**Key REST behaviors:**
- Post Object field returns integer (person ID), not full object
- Date pickers return Ymd format (20240915), not formatted strings
- Number field returns string ("25.00")
- True/false field returns boolean
- Taxonomy returns array of term IDs

---

## Decisions Made

### 1. Use Post Object field for person link (not Relationship field)
**Context:** Person field needs to link one discipline case to one person
**Decision:** ACF Post Object field with `multiple: 0`
**Rationale:**
- Returns single integer (cleaner REST response)
- Relationship field always returns array
- Matches semantic intent: "single person per case"
- Allows `allow_null: 1` for optional linking

**Affects:** Phase 133 (Sportlink sync) - sync code can set person ID directly

### 2. Store single charge code (not multi-select)
**Context:** Research showed one charge code per case in Sportlink data
**Decision:** Text field for charge_codes (not repeater or multi-select)
**Rationale:**
- Simplifies data model
- Matches Sportlink API structure
- Easier querying and filtering
- Can change to repeater later if needed

**Affects:** Phase 134 (UI) - simpler display logic

### 3. seizoen as shared taxonomy (not CPT-specific meta)
**Context:** Seasons may apply to multiple features (discipline, fees, events)
**Decision:** Register as taxonomy, not as select field or meta
**Rationale:**
- Enables shared use across post types
- Supports term metadata (current season flag)
- Provides REST endpoints automatically
- Allows filtering/faceting in UI

**Affects:** Future features can reuse seizoen taxonomy

### 4. dossier_id uniqueness via ACF filter (not database constraint)
**Context:** Need to prevent duplicate dossier IDs from Sportlink
**Decision:** ACF validation filter, not custom pre_save check
**Rationale:**
- Works in admin UI and potentially REST API
- Provides user-facing error message
- No need for custom database constraints
- Follows WordPress plugin ecosystem patterns

**Affects:** Phase 133 (Sportlink sync) - sync must handle validation errors

---

## Deviations from Plan

None - plan executed exactly as written. All three tasks completed without modifications.

---

## Files Modified

### Created
- `acf-json/group_discipline_case_fields.json` (186 lines)
  - Complete ACF field group with 11 fields
  - All field types: text, post_object, date_picker, textarea, number, true_false
  - show_in_rest: 1 for REST API exposure

### Modified
- `includes/class-post-types.php` (+89 lines)
  - Added `register_discipline_case_post_type()` method
  - Added call in `register_post_types()` method

- `includes/class-taxonomies.php` (+102 lines)
  - Added `register_seizoen_taxonomy()` method
  - Added `set_current_season()` helper method
  - Added `get_current_season()` helper method
  - Added `validate_unique_dossier_id()` ACF filter
  - Added validation filter registration in constructor

---

## Testing Verification

**Recommended verification steps (for Phase 133 or manual testing):**

1. **CPT exists in admin:**
   - Visit WordPress admin
   - Confirm "Tuchtzaken" menu item appears with warning icon
   - Menu position 9 (after Todos)

2. **REST endpoint works:**
   ```bash
   curl -s "https://[site]/wp-json/wp/v2/discipline-cases" -H "Cookie: [auth]"
   ```
   - Should return 200 with empty array (no cases yet)

3. **Taxonomy appears:**
   - Create discipline case in admin
   - Confirm "Seizoenen" appears in sidebar
   - Seizoen column appears in case list

4. **ACF fields appear:**
   - Edit discipline case
   - All 11 fields visible with correct labels
   - Field order: dossier_id, person, dates, descriptions, fee, is_charged

5. **REST includes ACF:**
   - Create test case with data
   - GET wp/v2/discipline-cases/{id}
   - Confirm `acf` object contains all field values

6. **Unique validation works:**
   - Create case with dossier_id "TEST-001"
   - Create second case with same dossier_id
   - Should show error: "Dit dossier-ID bestaat al. Elk dossier moet een uniek ID hebben."
   - Edit first case with same dossier_id
   - Should save successfully (not flagged as duplicate of itself)

---

## Next Phase Readiness

### Phase 133 (Business Logic) Blocked By
Nothing - all data layer components ready.

### Phase 133 Can Now Implement
- Sportlink API sync for discipline cases
- Auto-title generation for cases
- Person linking logic (match by name/ID)
- Season term creation during sync
- Current season setting

### Phase 134 (UI Implementation) Blocked By
Nothing from data layer - Phase 133 business logic needed first.

### Phase 134 Will Use
- REST endpoint wp/v2/discipline-cases
- ACF fields via acf object in REST response
- seizoen taxonomy for filtering
- get_current_season() for default filter

---

## Accumulated Debt

None introduced. Code follows existing Stadion patterns for CPT/ACF/taxonomy registration.

---

## Performance Notes

- **Query impact:** seizoen taxonomy adds one relationship table join when querying discipline cases by season
- **REST payload:** 11 ACF fields add ~500 bytes per case to REST response
- **Validation query:** Unique dossier_id check adds one meta_query on save (negligible impact)
- **Term meta queries:** get_current_season() uses meta_query (consider caching if called frequently)

**Optimization recommendations for Phase 133+:**
- Cache current season term in transient (60 minute expiry)
- Use `fields: 'ids'` in unique validation query (already implemented)
- Consider indexing dossier_id meta key if large datasets (>1000 cases)

---

## Open Questions for Phase 133

1. **Auto-title format:** What template for discipline case titles?
   - Suggestion: "{dossier_id} - {person_name} - {match_date}"
   - Or: "{dossier_id} - {charge_codes} - {team_name}"

2. **Season auto-creation:** Should sync automatically create missing season terms?
   - Recommendation: Yes - auto-create with format validation (YYYY-YYYY pattern)

3. **Person matching logic:** How to link cases to persons during sync?
   - By Sportlink person ID (if person table has it)?
   - By name matching (fuzzy or exact)?
   - Leave unlinked for manual resolution?

4. **Sync frequency:** How often to pull discipline cases from Sportlink?
   - Daily cron? Weekly? Manual trigger?

---

*Phase: 132-data-foundation*
*Plan: 01*
*Completed: 2026-02-03*
*Duration: 2m 33s*
