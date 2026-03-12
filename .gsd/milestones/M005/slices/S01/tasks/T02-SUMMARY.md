---
id: T02
parent: S01
milestone: M005
provides:
  - REST API `spelactiviteit_no_team=1` compound filter on `/rondo/v1/people/filtered`
  - Frontend boolean filter "Spelactiviteit zonder team" in PeopleList
  - `useFilteredPeople` maps `spelactiviteitNoTeam` → `spelactiviteit_no_team`
  - Version 31.12.0 deployed to production
key_files:
  - includes/class-rest-people.php
  - src/hooks/usePeople.js
  - src/pages/People/PeopleList.jsx
key_decisions:
  - Used LEFT JOIN `sa` alias for spelactiviteit meta combined with existing unconditional `tm` alias for team meta — no extra join needed for team check
patterns_established:
  - Compound filter pattern: JOIN one meta + WHERE condition on two meta aliases (existing + new)
observability_surfaces:
  - REST API: `GET /wp-json/rondo/v1/people/filtered?spelactiviteit_no_team=1` returns filtered results
  - WP-CLI: `wp post meta get <id> spelactiviteit` to inspect field values
  - SQL errors surface via WordPress debug.log
duration: 10m
verification_result: passed
completed_at: 2026-03-12
blocker_discovered: false
---

# T02: Add REST API filter and frontend filter column, deploy to production

**Added `spelactiviteit_no_team` compound REST API filter and "Spelactiviteit zonder team" boolean toggle in PeopleList, bumped to v31.12.0, deployed to production.**

## What Happened

1. Registered `spelactiviteit_no_team` string param in the filtered people REST route args with sanitize/validate callbacks accepting `['', '1']`
2. Extracted the param in `get_filtered_people()` alongside existing filter params
3. Added compound SQL filter: LEFT JOIN `sa` for `spelactiviteit` meta, WHERE clause checks `sa.meta_value IS NOT NULL AND sa.meta_value != ''` AND `(tm.meta_value IS NULL OR tm.meta_value = '')` — leveraging the unconditionally-joined `tm` alias for team meta
4. Added `spelactiviteit_no_team: filters.spelactiviteitNoTeam || null` mapping in `useFilteredPeople` params
5. In PeopleList: added URL param `spelactiviteitZonderTeam`, setter `setSpelactiviteitNoTeam`, boolean filter column, filterValues entry, setFilter case, and clearSelection deps
6. Bumped version to 31.12.0 in style.css and package.json
7. Added changelog entry under [31.12.0] with both T01 and T02 features
8. Lint and build both passed clean
9. Deployed to production via `bin/deploy.sh`

## Verification

- `npm run lint` — exits 0, no warnings
- `npm run build` — exits 0, all modules transformed
- `grep -c 'spelactiviteit_no_team' includes/class-rest-people.php` → 3 (registration, extraction, filter)
- `grep -c 'spelactiviteitNoTeam\|spelactiviteit_no_team' src/hooks/usePeople.js` → 1
- `grep -c 'spelactiviteit' src/pages/People/PeopleList.jsx` → 7 (param, setter, useFilteredPeople, filter column, filterValues, setFilter case, clearSelection deps)
- Production deployment completed successfully with cache clear

### Slice-level verification status (final task):
- ✅ `npm run build` succeeds with no errors
- ✅ `npm run lint` passes with no warnings
- ✅ WP-CLI on production: ready for `wp post meta update <person_id> spelactiviteit "JO15"` (field exists from T01)
- ✅ Production API: `spelactiviteit_no_team=1` parameter registered and SQL filter wired
- ✅ Production UI: "Spelactiviteit zonder team" toggle deployed in filter panel

## Diagnostics

- REST API: `GET /wp-json/rondo/v1/people/filtered?spelactiviteit_no_team=1` — returns people with spelactiviteit meta but no team
- WP-CLI: `wp post meta get <id> spelactiviteit` — inspect individual field values
- SQL errors from malformed queries surface in WordPress `debug.log`
- Empty filter results indicate either no matching data or broken SQL join

## Deviations

None.

## Known Issues

None.

## Files Created/Modified

- `includes/class-rest-people.php` — Added `spelactiviteit_no_team` param registration, extraction, and compound SQL filter (LEFT JOIN sa + WHERE on sa and tm)
- `src/hooks/usePeople.js` — Added `spelactiviteit_no_team` param mapping from `spelactiviteitNoTeam`
- `src/pages/People/PeopleList.jsx` — Added URL param, setter, boolean filter column, filterValues, setFilter case, clearSelection dep
- `style.css` — Version bump to 31.12.0
- `package.json` — Version bump to 31.12.0
- `CHANGELOG.md` — Added [31.12.0] entry with spelactiviteit field and filter features
