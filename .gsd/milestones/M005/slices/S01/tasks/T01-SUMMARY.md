---
id: T01
parent: S01
milestone: M005
provides:
  - ACF field `spelactiviteit` in person field group at index 29
  - SportlinkCard renders spelactiviteit when populated
key_files:
  - acf-json/group_person_fields.json
  - src/components/SportlinkCard.jsx
key_decisions: []
patterns_established: []
observability_surfaces:
  - none — display-only change; empty field is simply hidden (existing pattern)
duration: 4m
verification_result: passed
completed_at: 2026-03-12
blocker_discovered: false
---

# T01: Add ACF field and wire SportlinkCard display

**Added `spelactiviteit` ACF text field at index 29 (readonly, Sportlink tab) and wired it into SportlinkCard for display on person profiles.**

## What Happened

1. Inserted `spelactiviteit` field object at index 29 in `acf-json/group_person_fields.json`, copying the exact structure of `leeftijdsgroep` (index 28): key `field_spelactiviteit`, type `text`, readonly 1, wrapper width `"33"`. Added `modified` timestamp to the root object.
2. In `SportlinkCard.jsx`: extracted `spelactiviteit` from `acfData?.spelactiviteit`, added it to the `hasData` check, and added a field entry `{ key: 'spelactiviteit', label: 'Spelactiviteit', value: spelactiviteit, type: 'text' }` in the fields array after `leeftijdsgroep` and before `type-lid`.

## Verification

- `npm run lint` — exits 0, no warnings
- `npm run build` — exits 0, production build successful (15.99s)
- `python3 -c "import json; d=json.load(open('acf-json/group_person_fields.json')); f=d['fields'][29]; assert f['name']=='spelactiviteit' and f['readonly']==1"` — exits 0
- `grep -c 'spelactiviteit' src/components/SportlinkCard.jsx` — returns 3 (extraction, hasData, fields array)

**Slice-level checks (partial — T01 is intermediate):**
- ✅ `npm run build` succeeds
- ✅ `npm run lint` passes
- ⏳ WP-CLI production display — requires deploy (T02)
- ⏳ Production API filter — T02 scope
- ⏳ Production UI filter — T02 scope

## Diagnostics

None — display-only change. If field is empty it's hidden (existing pattern). Inspect ACF JSON index 29 or SportlinkCard fields array to verify wiring.

## Deviations

None.

## Known Issues

None.

## Files Created/Modified

- `acf-json/group_person_fields.json` — inserted `spelactiviteit` field at index 29 with readonly text config, added `modified` timestamp
- `src/components/SportlinkCard.jsx` — extract spelactiviteit, add to hasData, add to fields array
