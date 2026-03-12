---
estimated_steps: 8
estimated_files: 6
---

# T02: Add REST API filter and frontend filter column, deploy to production

**Slice:** S01 — Spelactiviteit field, display, and filter
**Milestone:** M005

## Description

Add the `spelactiviteit_no_team` compound filter to the REST API, wire it through the frontend filter system, bump version, update changelog, and deploy to production. This is the core feature: finding people who have a spelactiviteit but are not assigned to any team.

## Steps

1. Edit `includes/class-rest-people.php` — **route args registration** (~line 340, after `lid_tot_future` param): add `spelactiviteit_no_team` string param with sanitize_callback `sanitize_text_field` and validate_callback accepting `['', '1']`. Follow the exact `include_former` pattern.
2. Edit `includes/class-rest-people.php` — **param extraction** (~line 953, after `$lid_tot_future`): add `$spelactiviteit_no_team = $request->get_param( 'spelactiviteit_no_team' );`
3. Edit `includes/class-rest-people.php` — **SQL filter** (after the `lid_tot_future` filter block, ~line 1178): when `$spelactiviteit_no_team === '1'`, add JOIN `LEFT JOIN {$wpdb->postmeta} sa ON p.ID = sa.post_id AND sa.meta_key = 'spelactiviteit'` and compound WHERE `(sa.meta_value IS NOT NULL AND sa.meta_value != '' AND (tm.meta_value IS NULL OR tm.meta_value = ''))`. The `tm` alias is already joined unconditionally at line ~997.
4. Edit `src/hooks/usePeople.js` — in `useFilteredPeople` params object: add `spelactiviteit_no_team: filters.spelactiviteitNoTeam || null`.
5. Edit `src/pages/People/PeopleList.jsx`:
   - URL param: read `spelactiviteitZonderTeam` from searchParams
   - Setter: add `setSpelactiviteitNoTeam` callback updating URL param `spelactiviteitZonderTeam`
   - useFilteredPeople call: add `spelactiviteitNoTeam: spelactiviteitNoTeam || null`
   - filterColumns: add `createColumn({ id: 'spelactiviteit_no_team', header: 'Spelactiviteit zonder team', filterType: FILTER_TYPES.BOOLEAN, getFilterLabel: () => '' })`
   - filterValues: add `spelactiviteit_no_team: spelactiviteitNoTeam`
   - setFilter switch: add `case 'spelactiviteit_no_team': setSpelactiviteitNoTeam(value); break;`
   - clearSelection useEffect deps: add `spelactiviteitNoTeam`
6. Bump version to 31.12.0 in `style.css` and `package.json`. Add changelog entry under `## [31.12.0]` with Added: spelactiviteit field in Sportlink card, "Spelactiviteit zonder team" filter in People list.
7. Run `npm run lint` and `npm run build` to verify.
8. Deploy to production via `bin/deploy.sh`.

## Must-Haves

- [ ] REST API param `spelactiviteit_no_team` registered with correct validation
- [ ] Param extracted in `get_filtered_people()`
- [ ] SQL compound filter: LEFT JOIN `sa` for `spelactiviteit` + WHERE has value AND `tm` has no value
- [ ] `useFilteredPeople` maps `spelactiviteitNoTeam` → `spelactiviteit_no_team`
- [ ] PeopleList reads URL param, has setter, has filterColumn, has filterValues entry, has setFilter case
- [ ] Version 31.12.0 in style.css and package.json
- [ ] Changelog updated with [31.12.0] entry
- [ ] `npm run lint` and `npm run build` pass
- [ ] Deployed to production

## Verification

- `npm run lint` exits 0
- `npm run build` exits 0
- `grep -c 'spelactiviteit_no_team' includes/class-rest-people.php` returns >= 3 (registration, extraction, filter)
- `grep -c 'spelactiviteitNoTeam\|spelactiviteit_no_team' src/hooks/usePeople.js` returns >= 1
- `grep -c 'spelactiviteit' src/pages/People/PeopleList.jsx` returns >= 5 (param, setter, filter column, filterValues, setFilter)
- Production API: `curl -s "https://rondo.svawc.nl/wp-json/rondo/v1/people/filtered?spelactiviteit_no_team=1&per_page=5" -H "Cookie: ..."` returns valid JSON with people array
- Production UI: /people → filter panel shows "Spelactiviteit zonder team" toggle

## Observability Impact

- Signals added/changed: None — follows existing filter pattern; SQL errors surface via WordPress debug.log
- How a future agent inspects this: `wp post meta get <id> spelactiviteit` on production; API call with `spelactiviteit_no_team=1`
- Failure state exposed: None — empty filter results indicate either no matching data or broken SQL

## Inputs

- T01 output: ACF field exists, SportlinkCard displays it
- `includes/class-rest-people.php` — existing filter registration, extraction, and SQL builder patterns
- `src/hooks/usePeople.js` — existing param mapping pattern
- `src/pages/People/PeopleList.jsx` — existing filter column, URL param, setter, setFilter patterns

## Expected Output

- `includes/class-rest-people.php` — new `spelactiviteit_no_team` param registered, extracted, and compound SQL filter added
- `src/hooks/usePeople.js` — new param mapping for `spelactiviteitNoTeam`
- `src/pages/People/PeopleList.jsx` — new boolean filter column, URL param, setter, filterValues entry, setFilter case
- `style.css` + `package.json` — version 31.12.0
- `CHANGELOG.md` — new [31.12.0] entry
- Production deployment complete
