---
phase: quick-123
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - acf-json/group_person_fields.json
  - src/components/AddressEditModal.jsx
  - src/pages/People/PersonDetail.jsx
  - src/utils/vcard.js
  - includes/class-vcard-export.php
  - includes/class-invoice-pdf-generator.php
  - includes/class-demo-export.php
  - includes/class-demo-anonymizer.php
  - includes/class-membership-fees.php
  - includes/class-wp-cli.php
  - fixtures/demo-fixture.json
autonomous: true
requirements: [QUICK-123]

must_haves:
  truths:
    - "Address form shows separate fields for street name, house number, and house number addition"
    - "Saved addresses store street_name, house_number, house_number_addition as separate ACF sub-fields"
    - "All PHP code reading addresses uses new field names instead of street"
    - "Address display concatenates street_name + house_number + house_number_addition correctly"
  artifacts:
    - path: "acf-json/group_person_fields.json"
      provides: "New ACF sub-fields: street_name, house_number, house_number_addition replacing street"
    - path: "src/components/AddressEditModal.jsx"
      provides: "Three separate input fields for street_name, house_number, house_number_addition"
  key_links:
    - from: "src/components/AddressEditModal.jsx"
      to: "acf-json/group_person_fields.json"
      via: "form field names match ACF sub-field names"
      pattern: "street_name|house_number|house_number_addition"
---

<objective>
Replace the single `street` ACF sub-field on person addresses with three separate fields: `street_name`, `house_number`, and `house_number_addition`. Update all PHP and React code that references `street` to use the new fields.

Purpose: Enable structured address data (required by sync) instead of a single freeform street string.
Output: Updated ACF field definition, updated React form and display, updated PHP consumers.
</objective>

<execution_context>
@/Users/joostdevalk/.claude/get-shit-done/workflows/execute-plan.md
@/Users/joostdevalk/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@acf-json/group_person_fields.json
@src/components/AddressEditModal.jsx
@src/pages/People/PersonDetail.jsx
@src/utils/vcard.js
@includes/class-vcard-export.php
@includes/class-invoice-pdf-generator.php
@includes/class-demo-export.php
@includes/class-demo-anonymizer.php
@includes/class-membership-fees.php
@includes/class-wp-cli.php
@fixtures/demo-fixture.json
</context>

<tasks>

<task type="auto">
  <name>Task 1: Replace ACF street sub-field with street_name, house_number, house_number_addition</name>
  <files>acf-json/group_person_fields.json, fixtures/demo-fixture.json</files>
  <action>
In `acf-json/group_person_fields.json`, inside the `addresses` repeater sub_fields array, replace the single `field_address_street` sub-field with three new sub-fields:

1. `field_address_street_name` — name: `street_name`, label: "Straatnaam", type: text, wrapper width: 50
2. `field_address_house_number` — name: `house_number`, label: "Huisnummer", type: text, wrapper width: 25
3. `field_address_house_number_addition` — name: `house_number_addition`, label: "Toevoeging", type: text, wrapper width: 25

Remove the old `field_address_street` entry entirely.

In `fixtures/demo-fixture.json`, update all address entries: split the current `"street": "Kloosterstraat 66"` into `"street_name": "Kloosterstraat"`, `"house_number": "66"`, `"house_number_addition": ""`. Remove the `street` key from each address object.
  </action>
  <verify>
    <automated>node -e "const j=require('./acf-json/group_person_fields.json'); const subs=j.fields.find(f=>f.key==='field_addresses').sub_fields; const names=subs.map(s=>s.name); console.log('has street_name:', names.includes('street_name')); console.log('has house_number:', names.includes('house_number')); console.log('has house_number_addition:', names.includes('house_number_addition')); console.log('no old street:', !names.includes('street')); if(!names.includes('street_name')||names.includes('street')) process.exit(1);"</automated>
  </verify>
  <done>ACF JSON has three new sub-fields replacing street; demo fixtures updated to match.</done>
</task>

<task type="auto">
  <name>Task 2: Update React components to use new address fields</name>
  <files>src/components/AddressEditModal.jsx, src/pages/People/PersonDetail.jsx, src/utils/vcard.js</files>
  <action>
**AddressEditModal.jsx:**
- Replace all `street` references with three fields: `street_name`, `house_number`, `house_number_addition`
- In defaultValues: replace `street: ''` with `street_name: ''`, `house_number: ''`, `house_number_addition: ''`
- In reset calls (both editing and new): same replacement
- In handleFormSubmit data mapping: same replacement
- In the form JSX: replace the single "Straat" input with a row of three inputs:
  - `street_name`: label "Straatnaam", placeholder "bijv. Hoofdstraat", takes ~50% width
  - `house_number`: label "Huisnr.", placeholder "bijv. 123", takes ~25% width
  - `house_number_addition`: label "Toev.", placeholder "bijv. A", takes ~25% width
  Use a `grid grid-cols-4 gap-4` layout: street_name spans `col-span-2`, house_number and house_number_addition each `col-span-1`.

**PersonDetail.jsx:**
- Find the address display code at approximately line 1358 where `address.street` is used in `addressLines`
- Replace `address.street` with a concatenation: `[address.street_name, address.house_number, address.house_number_addition].filter(Boolean).join(' ')` — this produces e.g. "Hoofdstraat 123 A"

**src/utils/vcard.js:**
- Around line 162, replace `const street = escapeVCardValue(address.street || '')` with:
  `const street = escapeVCardValue([address.street_name, address.house_number, address.house_number_addition].filter(Boolean).join(' '))`
  The vCard ADR format expects a single street string, so concatenate the three fields.
  </action>
  <verify>
    <automated>npm run lint && npm run build</automated>
  </verify>
  <done>React address form shows three separate fields; address display and vCard export concatenate them correctly; lint and build pass.</done>
</task>

<task type="auto">
  <name>Task 3: Update all PHP code referencing address street field</name>
  <files>includes/class-vcard-export.php, includes/class-invoice-pdf-generator.php, includes/class-demo-export.php, includes/class-demo-anonymizer.php, includes/class-membership-fees.php, includes/class-wp-cli.php</files>
  <action>
In every PHP file that reads `$address['street']` or `$addr['street']`, replace with a concatenation of the three new fields. Use a helper pattern: `trim(($addr['street_name'] ?? '') . ' ' . ($addr['house_number'] ?? '') . ' ' . ($addr['house_number_addition'] ?? ''))` — or assign to a local `$street` variable for readability.

**class-vcard-export.php** (two vCard generation methods + one CardDAV import method):
- Lines ~286, ~431: Replace `$address['street']` with concatenation of `street_name`, `house_number`, `house_number_addition`
- Line ~605 (CardDAV import parsing): When parsing incoming ADR, store the street component into `street_name` (not `street`), set `house_number` and `house_number_addition` to empty strings (CardDAV doesn't split these)

**class-invoice-pdf-generator.php:**
- Lines ~94: Replace `$first_address['street']` with concatenation of the three fields

**class-demo-export.php:**
- Line ~386: Replace `'street' => $row['street']` with `'street_name' => $row['street_name']`, `'house_number' => $row['house_number']`, `'house_number_addition' => $row['house_number_addition']`

**class-demo-anonymizer.php:**
- Line ~607: Already generates `$fake_address['street']` and `$fake_address['house_number']` separately. Update to use new keys: `'street_name' => $fake_address['street']`, `'house_number' => $fake_address['house_number']`, `'house_number_addition' => ''`. Remove the old `'street' => $fake_address['street'] . ' ' . $fake_address['house_number']` line.

**class-membership-fees.php:**
- The `extract_house_number()` method (line ~1408) and `get_family_group_key()` method (line ~1450):
  - `get_family_group_key()`: Instead of extracting house number from `$primary['street']`, directly use `$primary['house_number']` and `$primary['house_number_addition']`. Replace the `$street = $primary['street']` and `$house_number = $this->extract_house_number($street)` with `$house_number = trim(($primary['house_number'] ?? '') . ($primary['house_number_addition'] ?? ''))`. The check for empty should verify `house_number` is not empty (instead of checking `street`).
  - The `extract_house_number()` method can be kept for backward compatibility or removed — keep it since it's a public method.

**class-wp-cli.php:**
- Line ~312 (migration context): Replace `'street' => $item['contact_value']` with `'street_name' => $item['contact_value']`, `'house_number' => ''`, `'house_number_addition' => ''` (the migration puts full address in what was street, same approach but new key name)
- Line ~694: Replace `$addr['street']` with concatenation of three new fields
  </action>
  <verify>
    <automated>cd /Users/joostdevalk/Code/rondo/rondo-club && grep -rn "\\['street'\\]" includes/ --include="*.php" | grep -v "//.*street" | grep -v "fake_address\['street'\]" ; echo "Exit: $?"</automated>
  </verify>
  <done>No PHP file references `['street']` on address arrays (except the demo anonymizer's internal `$fake_address` which generates street names). All use `street_name`, `house_number`, `house_number_addition`.</done>
</task>

</tasks>

<verification>
- `npm run lint` passes with no warnings
- `npm run build` succeeds
- `grep -rn "address\.street[^_]" src/` returns no matches (no React code uses old field)
- `grep -rn "\['street'\]" includes/` returns no address-related matches
- ACF JSON contains street_name, house_number, house_number_addition sub-fields
</verification>

<success_criteria>
- ACF field definition has three sub-fields replacing the single street field
- AddressEditModal shows three separate inputs for street name, house number, and addition
- PersonDetail displays concatenated address correctly
- All PHP consumers (vCard, invoice PDF, demo export/anonymize, membership fees, WP-CLI) use new field names
- Build and lint pass cleanly
</success_criteria>

<output>
After completion, create `.planning/quick/123-replace-street-field-with-street-name-ho/123-SUMMARY.md`
</output>
