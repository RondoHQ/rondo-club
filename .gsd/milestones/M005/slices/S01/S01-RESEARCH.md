# S01: Spelactiviteit field, display, and filter — Research

**Date:** 2026-03-12

## Summary

This slice adds the `spelactiviteit` ACF text field to person records, displays it in the SportlinkCard component, and adds a compound filter ("Spelactiviteit zonder team") to the People list. All three changes follow established, well-documented patterns with zero unknowns.

The ACF field mirrors `leeftijdsgroep` (text, readonly, Sportlink tab). The SportlinkCard display is a single entry in the `fields` array. The filter is a boolean-style compound condition (has spelactiviteit AND no team) using the `tm` alias already joined in every filtered query.

This is a low-risk, pattern-following slice. No new libraries, no new UI patterns, no architectural changes.

## Recommendation

Follow the existing patterns exactly:

1. **ACF field:** Copy `leeftijdsgroep` field structure → name `spelactiviteit`, key `field_spelactiviteit`, readonly=1, insert after `leeftijdsgroep` (index 28 → new index 29) in `acf-json/group_person_fields.json`
2. **SportlinkCard:** Add `spelactiviteit` entry to the `fields` array, after `leeftijdsgroep` row
3. **REST API filter:** Register `spelactiviteit_no_team` boolean param (`'1'` = active), add compound WHERE clause using existing `tm` alias + new `sa` meta join
4. **Frontend filter:** Add boolean filter column in `PeopleList.jsx` filterColumns, wire URL param, pass to `useFilteredPeople`

## Don't Hand-Roll

| Problem | Existing Solution | Why Use It |
|---------|------------------|------------|
| ACF field definition | Copy `leeftijdsgroep` field object in JSON | Exact same type, readonly, wrapper pattern |
| Sportlink card display | `SportlinkCard.jsx` `fields` array | Automatic show/hide for empty values built in |
| REST filter parameter | `class-rest-people.php` route args registration | Consistent validation/sanitization pattern |
| SQL compound filter | Existing `$join_clauses` + `$where_clauses` pattern | `tm` meta for team already joined on every query |
| Frontend filter column | `createColumn` + `FILTER_TYPES.BOOLEAN` | Existing pattern for `foto_missing` etc. |

## Existing Code and Patterns

### ACF Field Definition
- `acf-json/group_person_fields.json` — Sportlink tab starts at index 24. Fields `type-lid` (27) and `leeftijdsgroep` (28) are both `text` type with `readonly: 1` and `wrapper.width: "33"`. New field goes at index 29 (after leeftijdsgroep).

### SportlinkCard Display
- `src/components/SportlinkCard.jsx` — Fields array at line ~37. Each entry: `{ key, label, value, type: 'text' }`. Card renders nothing when no data (`hasData` check). Individual fields with no value are auto-skipped by the rendering loop (line ~105: `if (!field.value && field.type !== 'boolean' && !field.showWhenEmpty) return null`).

### REST API Filter Registration
- `includes/class-rest-people.php` lines 240-345 — Route args registration block. Boolean filters like `include_former` use `validate_callback: in_array(['', '1'])`. The new `spelactiviteit_no_team` param follows this exact pattern.

### REST API Filter SQL
- `includes/class-rest-people.php` line 992 — `tm` alias (team meta) is **already LEFT JOINed** on every filtered query: `LEFT JOIN postmeta tm ON p.ID = tm.post_id AND tm.meta_key = 'team'`. The compound filter needs:
  - New JOIN: `LEFT JOIN postmeta sa ON p.ID = sa.post_id AND sa.meta_key = 'spelactiviteit'`
  - WHERE: `sa.meta_value IS NOT NULL AND sa.meta_value != '' AND (tm.meta_value IS NULL OR tm.meta_value = '')`

### Frontend Filter State
- `src/pages/People/PeopleList.jsx` line 560-625 — URL param parsing and setter functions. Each filter has: URL param read, setter callback, filterValues entry, filterColumns entry, and setFilter switch case.
- `src/hooks/usePeople.js` line 118-165 — `useFilteredPeople` maps camelCase props to snake_case API params. New entry needed.
- `src/api/client.js` line 112 — `getFilteredPeople` passes params through; no change needed (params are passed as-is).

### Dynamic Filter Options
- `includes/class-rest-people.php` lines 1432-1505 — `get_dynamic_filter_config()` and `get_filter_options()`. These power the SELECT-type dynamic filters (age_groups, member_types). The new filter is BOOLEAN (not SELECT), so it does NOT need a filter-options entry — same pattern as `foto_missing`, `include_former`, `lid_tot_future`.

## Constraints

- **Field name must be `spelactiviteit`** — Rondo Sync will write to this exact meta key
- **Field must be readonly=1** — system-managed by sync, not manual user entry (same as `leeftijdsgroep`, `type-lid`)
- **ACF field key must be unique** — use `field_spelactiviteit` (no collision exists)
- **`tm` alias is always available** — joined at line 992 unconditionally, safe to reference in the compound WHERE
- **Boolean filter pattern** — this is NOT a select filter with dynamic options; it's a simple toggle like `foto_missing`

## Common Pitfalls

- **Don't add to `get_dynamic_filter_config()`** — The filter is boolean ("has spelactiviteit without team"), not a select dropdown of values. Adding it there would create a broken filter options endpoint. Follow the `foto_missing` / `include_former` pattern instead.
- **Don't forget `hasData` check in SportlinkCard** — The `spelactiviteit` value must be included in the `hasData` expression (line 24) so the card appears when spelactiviteit is the only populated field.
- **Don't create a separate `tm` JOIN** — The team meta is already joined as `tm` (line 992). A duplicate JOIN would cause SQL errors or incorrect results. Reuse the existing alias.
- **ACF JSON modified timestamp** — After editing the JSON file, the `modified` timestamp at the root level should be updated so ACF recognizes the change on sync.

## Open Risks

- None. All patterns are proven and well-established. The only "new" aspect is the compound condition (meta + no-team), but both parts use existing SQL aliases and patterns already present in the query builder.

## Skills Discovered

| Technology | Skill | Status |
|------------|-------|--------|
| WordPress / ACF | N/A | Not applicable — standard WP/ACF patterns, no skill needed |
| React / TanStack Query | vercel-react-best-practices | installed (available in `<available_skills>`) — not needed for this trivial addition |

## Sources

- `acf-json/group_person_fields.json` — ACF field group structure, Sportlink tab fields (indexes 24-35)
- `src/components/SportlinkCard.jsx` — Full file reviewed, field rendering pattern understood
- `includes/class-rest-people.php` — Route registration (240-350), filter param extraction (930-950), SQL clauses (1075-1175), dynamic filter config (1432-1505)
- `src/pages/People/PeopleList.jsx` — URL params (550-625), filterColumns (777-860), filterValues (882-895), setFilter (938-958), useFilteredPeople call (662-685)
- `src/hooks/usePeople.js` — useFilteredPeople param mapping (118-165), useFilterOptions (171-185)
- `src/api/client.js` — getFilteredPeople endpoint (line 112)
