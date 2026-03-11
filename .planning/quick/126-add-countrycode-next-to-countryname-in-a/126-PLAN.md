---
phase: quick-126
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - acf-json/group_person_fields.json
  - src/components/AddressEditModal.jsx
  - src/pages/People/PersonDetail.jsx
autonomous: true
requirements: [QK-126]
must_haves:
  truths:
    - "country_code ACF field exists in the addresses repeater"
    - "AddressEditModal includes a country_code text input"
    - "country_code is saved and loaded when editing addresses"
    - "PersonDetail displays country_code next to country when present"
  artifacts:
    - path: "acf-json/group_person_fields.json"
      provides: "country_code field in addresses repeater"
      contains: "field_address_country_code"
    - path: "src/components/AddressEditModal.jsx"
      provides: "country_code input in address form"
      contains: "country_code"
    - path: "src/pages/People/PersonDetail.jsx"
      provides: "country_code display in address card"
      contains: "country_code"
  key_links:
    - from: "src/components/AddressEditModal.jsx"
      to: "acf-json/group_person_fields.json"
      via: "form field name matches ACF field name"
      pattern: "country_code"
---

<objective>
Add a `country_code` text field to the ACF addresses repeater and expose it in the React frontend (edit modal + detail display). Rondo-sync already sends `country_code` in address data but the field does not exist yet in rondo-club, so values are silently dropped.

Purpose: Enable country code syncing between Sportlink and rondo-club via rondo-sync.
Output: ACF field added, React edit modal updated, detail page shows country code.
</objective>

<execution_context>
@/Users/joostdevoak/.claude/get-shit-done/workflows/execute-plan.md
@/Users/joostdevoak/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@acf-json/group_person_fields.json (lines 275-290 — country field ends the addresses sub_fields array)
@src/components/AddressEditModal.jsx (full file — address form with SearchableCountrySelector)
@src/pages/People/PersonDetail.jsx (line ~1356-1365 — address display block)
</context>

<tasks>

<task type="auto">
  <name>Task 1: Add country_code ACF field to addresses repeater</name>
  <files>acf-json/group_person_fields.json</files>
  <action>
In `acf-json/group_person_fields.json`, find the `field_address_country` entry (the last item in the addresses repeater `sub_fields` array, around line 281-289). Add a new field AFTER it as the new last sub_field:

```json
{
    "key": "field_address_country_code",
    "label": "Country Code",
    "name": "country_code",
    "type": "text",
    "maxlength": 3,
    "placeholder": "e.g. NL",
    "wrapper": {
        "width": "25"
    }
}
```

Also update the `country` field wrapper width from `"25"` to `"25"` (keep it the same — both fit in the row with state at 25% too). The four fields state/country/country_code will be 25/25/25 which leaves 25% for potential future use or they just wrap neatly.

After editing, verify the JSON is valid.
  </action>
  <verify>
    <automated>node -e "JSON.parse(require('fs').readFileSync('acf-json/group_person_fields.json','utf8')); console.log('Valid JSON')" && grep -c "field_address_country_code" acf-json/group_person_fields.json</automated>
  </verify>
  <done>ACF JSON contains `field_address_country_code` field as last sub_field in addresses repeater, JSON is valid.</done>
</task>

<task type="auto">
  <name>Task 2: Add country_code to AddressEditModal and PersonDetail display</name>
  <files>src/components/AddressEditModal.jsx, src/pages/People/PersonDetail.jsx</files>
  <action>
**AddressEditModal.jsx:**

1. Add `country_code: ''` to defaultValues in useForm (line ~172, after `country`).
2. In the reset call for editing (line ~187), add: `country_code: address.country_code || ''`
3. In the reset call for new address (line ~198), add: `country_code: ''`
4. In handleFormSubmit (line ~215), add: `country_code: data.country_code || ''`
5. In the form JSX, change the "State and Country row" grid from `grid-cols-2` to `grid-cols-3` and add a third column for country_code AFTER the country field:

```jsx
<div>
  <label className="label">Landcode</label>
  <input
    {...register('country_code')}
    className="input"
    placeholder="bijv. NL"
    maxLength={3}
    disabled={isLoading}
  />
</div>
```

The row will now show: Provincie | Land | Landcode in a 3-column grid.

**PersonDetail.jsx:**

In the address display block (~line 1358-1362), after `address.country` in the addressLines array, add the country_code in parentheses when present. Change:
```js
address.country
```
to:
```js
[address.country, address.country_code ? `(${address.country_code})` : null].filter(Boolean).join(' ')
```

This displays e.g. "Netherlands (NL)" or just "Netherlands" if no code is set.
  </action>
  <verify>
    <automated>cd /Users/joostdevalk/Code/rondo/rondo-club && npx eslint src/components/AddressEditModal.jsx src/pages/People/PersonDetail.jsx --max-warnings=0 && grep -c "country_code" src/components/AddressEditModal.jsx && grep -c "country_code" src/pages/People/PersonDetail.jsx</automated>
  </verify>
  <done>AddressEditModal has country_code input field in a 3-column row with Provincie and Land. PersonDetail shows country code in parentheses after country name. ESLint passes with zero warnings.</done>
</task>

</tasks>

<verification>
1. ACF JSON is valid and contains `field_address_country_code`
2. ESLint passes on both modified React files
3. `npm run build` succeeds
4. After deploy: edit an address in the UI, country_code field appears and saves correctly
</verification>

<success_criteria>
- `country_code` ACF field exists in addresses repeater
- AddressEditModal shows Landcode input after Land
- PersonDetail displays country code in parentheses when present
- Frontend builds without errors
- Rondo-sync's existing `country_code` data will now be stored and displayed
</success_criteria>

<output>
After completion, create `.planning/quick/126-add-countrycode-next-to-countryname-in-a/126-SUMMARY.md`
</output>
