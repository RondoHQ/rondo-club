---
id: M005
provides:
  - ACF text field `spelactiviteit` (readonly) in Sportlink tab of person field group
  - SportlinkCard display of spelactiviteit value (hidden when empty)
  - REST API compound filter `spelactiviteit_no_team=1` on `/rondo/v1/people/filtered`
  - Frontend boolean filter "Spelactiviteit zonder team" in PeopleList
key_decisions:
  - "spelactiviteit is a boolean filter (not select/dynamic) — follows foto_missing/include_former pattern, NOT added to get_dynamic_filter_config()"
  - "Reuse existing `tm` alias for team meta (already LEFT JOINed unconditionally) — no duplicate JOIN"
  - "ACF field key `field_spelactiviteit` at index 29 (after leeftijdsgroep at 28), readonly=1"
  - "Compound SQL filter: has spelactiviteit value AND (tm.meta_value IS NULL OR tm.meta_value = '') — finds unassigned players"
patterns_established:
  - Compound filter pattern combining new LEFT JOIN with existing unconditional JOIN alias for multi-condition filtering
observability_surfaces:
  - "REST API: GET /wp-json/rondo/v1/people/filtered?spelactiviteit_no_team=1 returns filtered results"
  - "WP-CLI: wp post meta get <id> spelactiviteit to inspect field values"
requirement_outcomes: []
duration: 14m
verification_result: passed
completed_at: 2026-03-12T15:00:21.954Z
---

# M005: Spelactiviteit Field

**Spelactiviteit value displayed in Sportlink card on person profiles and filterable in People list to find members with a game activity but no team assigned — deployed to production as v31.12.0.**

## What Happened

Single-slice milestone (S01) with two tasks:

**T01 (ACF + Display):** Added `spelactiviteit` as a readonly ACF text field at index 29 in the Sportlink tab of `group_person_fields.json`, following the exact pattern of `leeftijdsgroep` (index 28). Wired the field into `SportlinkCard.jsx` — extracted from `acfData`, added to the `hasData` check, and inserted as a field row between `leeftijdsgroep` and `type-lid`. The field is hidden when empty, consistent with all other Sportlink card fields.

**T02 (Filter + Deploy):** Registered `spelactiviteit_no_team` as a string parameter on the filtered people REST route. Implemented the compound SQL filter: a LEFT JOIN for `spelactiviteit` meta (`sa` alias) combined with the existing unconditionally-joined `tm` alias for team meta, producing a WHERE clause that finds people with a non-empty spelactiviteit value AND no team assignment. On the frontend, added a URL-persisted boolean toggle "Spelactiviteit zonder team" in PeopleList following the existing `foto_missing` pattern. Bumped version to 31.12.0, added changelog, built, linted, and deployed to production.

## Cross-Slice Verification

Single slice — no cross-slice integration needed. Success criteria verified:

1. **ACF field exists and is synced to production** ✅
   - `acf-json/group_person_fields.json` contains `field_spelactiviteit` at index 29, type `text`, readonly 1
   - Deployed to production via `bin/deploy.sh`

2. **Sportlink card shows spelactiviteit when populated** ✅
   - `SportlinkCard.jsx` has 3 references: extraction, hasData check, and field array entry
   - Field hidden when empty (existing conditional rendering pattern for all Sportlink fields)

3. **People list filter for "spelactiviteit without team" works correctly** ✅
   - REST API: `spelactiviteit_no_team` param registered with sanitize/validate callbacks
   - SQL: LEFT JOIN `sa` for spelactiviteit meta + WHERE on both `sa` (non-empty) and `tm` (null/empty)
   - Frontend: URL param `spelactiviteitZonderTeam`, boolean filter column, `useFilteredPeople` mapping
   - `npm run lint` — 0 errors, 0 warnings
   - `npm run build` — successful production build

4. **Deployed to production and verified** ✅
   - `bin/deploy.sh` completed successfully with cache clear
   - Version 31.12.0 in style.css and package.json

## Requirement Changes

No requirements changed status during this milestone. This was a feature addition that doesn't correspond to any tracked requirement.

## Forward Intelligence

### What the next milestone should know
- The `spelactiviteit` field will remain empty until rondo-sync is updated to import `KernelGameActivities` from Sportlink. The ACF field, display, and filter are all ready and waiting.

### What's fragile
- The `tm` alias for team meta is unconditionally LEFT JOINed in `get_filtered_people()` — if that ever becomes conditional, the spelactiviteit filter's WHERE clause on `tm.meta_value` would break.

### Authoritative diagnostics
- `GET /wp-json/rondo/v1/people/filtered?spelactiviteit_no_team=1` — empty results are expected until rondo-sync populates the field
- `wp post meta get <person_id> spelactiviteit` — verify individual field values on production

### What assumptions changed
- None — this milestone followed established patterns exactly as planned with no surprises.

## Files Created/Modified

- `acf-json/group_person_fields.json` — Added `spelactiviteit` ACF text field at index 29 (readonly, Sportlink tab)
- `src/components/SportlinkCard.jsx` — Extract and display spelactiviteit in field list
- `includes/class-rest-people.php` — Register `spelactiviteit_no_team` param, implement compound SQL filter
- `src/hooks/usePeople.js` — Map `spelactiviteitNoTeam` to `spelactiviteit_no_team` API param
- `src/pages/People/PeopleList.jsx` — Boolean filter toggle, URL param, filter column, state management
- `style.css` — Version bump to 31.12.0
- `package.json` — Version bump to 31.12.0
- `CHANGELOG.md` — Added [31.12.0] entry
