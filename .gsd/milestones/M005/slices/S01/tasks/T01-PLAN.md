---
estimated_steps: 4
estimated_files: 2
---

# T01: Add ACF field and wire SportlinkCard display

**Slice:** S01 — Spelactiviteit field, display, and filter
**Milestone:** M005

## Description

Add the `spelactiviteit` ACF text field to the person field group (Sportlink tab) and wire it into the SportlinkCard React component. This establishes the data model and the first user-visible feature: seeing spelactiviteit on person profiles.

## Steps

1. Edit `acf-json/group_person_fields.json`: insert a new field object at index 29 (after `leeftijdsgroep` at index 28) following the exact structure of `leeftijdsgroep` — key: `field_spelactiviteit`, label: `Spelactiviteit`, name: `spelactiviteit`, type: `text`, readonly: 1, wrapper width: `"33"`. Update the root `modified` timestamp to current Unix time.
2. Edit `src/components/SportlinkCard.jsx`: extract `spelactiviteit` from `acfData?.spelactiviteit`. Add it to the `hasData` expression (alongside `knvbId`, `lidSinds`, etc.). Add a field entry `{ key: 'spelactiviteit', label: 'Spelactiviteit', value: spelactiviteit, type: 'text' }` in the `fields` array after the `leeftijdsgroep` entry and before `datum-foto`.
3. Run `npm run lint` to verify no warnings/errors.
4. Run `npm run build` to verify frontend compiles.

## Must-Haves

- [ ] ACF field `spelactiviteit` at index 29 with key `field_spelactiviteit`, readonly=1, type text, wrapper width 33
- [ ] ACF JSON `modified` timestamp updated
- [ ] SportlinkCard extracts `spelactiviteit` value from acfData
- [ ] `hasData` check includes `spelactiviteit` so card appears when only spelactiviteit is populated
- [ ] Field entry in `fields` array renders spelactiviteit with label "Spelactiviteit" and type "text"
- [ ] `npm run lint` passes
- [ ] `npm run build` succeeds

## Verification

- `npm run lint` exits 0
- `npm run build` exits 0
- `python3 -c "import json; d=json.load(open('acf-json/group_person_fields.json')); f=d['fields'][29]; assert f['name']=='spelactiviteit' and f['readonly']==1"` exits 0
- `grep -c 'spelactiviteit' src/components/SportlinkCard.jsx` returns >= 3 (extraction, hasData, fields array)

## Observability Impact

- Signals added/changed: None — display-only change, no runtime logging
- How a future agent inspects this: Read `acf-json/group_person_fields.json` index 29, check SportlinkCard.jsx fields array
- Failure state exposed: None — if field is empty, it's simply hidden (existing pattern)

## Inputs

- `acf-json/group_person_fields.json` — current field group structure with leeftijdsgroep at index 28
- `src/components/SportlinkCard.jsx` — current field rendering pattern

## Expected Output

- `acf-json/group_person_fields.json` — new field at index 29 for spelactiviteit
- `src/components/SportlinkCard.jsx` — spelactiviteit extracted, in hasData, and in fields array
