# S01: Spelactiviteit field, display, and filter

**Goal:** Spelactiviteit value is visible in the Sportlink card on person profiles and filterable via "Spelactiviteit zonder team" in the People list.
**Demo:** Set `spelactiviteit` on a person via WP-CLI on production, see it appear in their Sportlink card, then use the "Spelactiviteit zonder team" filter on /people to find people with a spelactiviteit but no team.

## Must-Haves

- ACF text field `spelactiviteit` exists in the Sportlink tab (readonly, after leeftijdsgroep)
- SportlinkCard shows spelactiviteit when populated, hides it when empty
- SportlinkCard `hasData` check includes spelactiviteit
- REST API accepts `spelactiviteit_no_team=1` parameter on `/rondo/v1/people/filtered`
- Filter SQL: compound condition — has spelactiviteit AND no team (using existing `tm` alias)
- PeopleList has a boolean filter column "Spelactiviteit zonder team"
- Frontend passes the filter through to the API via useFilteredPeople
- Version bumped to 31.12.0, changelog updated
- Deployed to production

## Proof Level

- This slice proves: integration
- Real runtime required: yes (production WordPress)
- Human/UAT required: yes (user verifies display and filter on production)

## Verification

- `npm run build` succeeds with no errors
- `npm run lint` passes with no warnings
- WP-CLI on production: `wp post meta update <person_id> spelactiviteit "JO15"` → Sportlink card on that person shows "Spelactiviteit: JO15"
- Production API: `GET /wp-json/rondo/v1/people/filtered?spelactiviteit_no_team=1` returns only people with spelactiviteit who have no team
- Production UI: /people filter panel shows "Spelactiviteit zonder team" toggle, activating it filters the list

## Observability / Diagnostics

- Runtime signals: None new — follows existing filter pattern; SQL errors surface via WordPress debug.log
- Inspection surfaces: `wp post meta get <id> spelactiviteit` to check field value; REST API endpoint with `spelactiviteit_no_team=1` to verify filter
- Failure visibility: Empty results on filter activation indicate missing meta or broken SQL join
- Redaction constraints: None

## Integration Closure

- Upstream surfaces consumed: `acf-json/group_person_fields.json` (field group), `class-rest-people.php` (filter SQL builder with `tm` alias), `SportlinkCard.jsx` (field rendering), `PeopleList.jsx` (filter columns), `usePeople.js` (param mapping)
- New wiring introduced in this slice: ACF field → REST param → SQL compound filter → frontend boolean toggle
- What remains before the milestone is truly usable end-to-end: nothing — Rondo Sync will write to the `spelactiviteit` meta key in a future update, but the field and filter work as soon as meta is populated (via sync or WP-CLI)

## Tasks

- [ ] **T01: Add ACF field and wire SportlinkCard display** `est:20m`
  - Why: The spelactiviteit field must exist in ACF and render in the Sportlink card — this is the data foundation and the simplest user-visible change
  - Files: `acf-json/group_person_fields.json`, `src/components/SportlinkCard.jsx`
  - Do: Add `spelactiviteit` text field at index 29 in ACF JSON (copy leeftijdsgroep pattern, readonly=1, wrapper width 33). In SportlinkCard, extract `acfData?.spelactiviteit`, add to `hasData` check, add entry to `fields` array after leeftijdsgroep. Update ACF JSON `modified` timestamp.
  - Verify: `npm run lint` passes, `npm run build` succeeds, visual inspection of SportlinkCard code confirms field entry
  - Done when: ACF JSON has spelactiviteit field at index 29, SportlinkCard renders it when populated and hides when empty

- [ ] **T02: Add REST API filter and frontend filter column, deploy to production** `est:30m`
  - Why: The compound filter (has spelactiviteit + no team) is the core feature for finding unassigned players, and the frontend must expose it as a toggle
  - Files: `includes/class-rest-people.php`, `src/hooks/usePeople.js`, `src/pages/People/PeopleList.jsx`, `style.css`, `package.json`, `CHANGELOG.md`
  - Do: Register `spelactiviteit_no_team` boolean param in route args (validate: `['', '1']`). Extract param in `get_filtered_people()`. Add compound SQL filter: JOIN `sa` alias for `spelactiviteit` meta, WHERE `sa.meta_value IS NOT NULL AND sa.meta_value != '' AND (tm.meta_value IS NULL OR tm.meta_value = '')`. In `usePeople.js`, map `spelactiviteitNoTeam` → `spelactiviteit_no_team`. In `PeopleList.jsx`: read URL param `spelactiviteitZonderTeam`, add setter, add boolean filterColumn, add to filterValues, add setFilter case. Bump version to 31.12.0, update changelog. Build and deploy to production.
  - Verify: `npm run build` succeeds, `npm run lint` passes, deployed to production, API call with `spelactiviteit_no_team=1` returns filtered results, UI toggle works
  - Done when: Filter works end-to-end on production — toggling "Spelactiviteit zonder team" shows only people with spelactiviteit value and no team

## Files Likely Touched

- `acf-json/group_person_fields.json`
- `src/components/SportlinkCard.jsx`
- `includes/class-rest-people.php`
- `src/hooks/usePeople.js`
- `src/pages/People/PeopleList.jsx`
- `style.css`
- `package.json`
- `CHANGELOG.md`
