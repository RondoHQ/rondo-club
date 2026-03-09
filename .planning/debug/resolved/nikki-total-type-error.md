---
status: resolved
trigger: "REST API returns 400 because acf[_nikki_2025_total] is not of type number,null"
created: 2026-03-09T00:00:00Z
updated: 2026-03-09T00:02:00Z
---

## Current Focus

hypothesis: CONFIRMED - numericFields array in sanitizePersonAcf missing all 8 nikki fields
test: User verified on production
expecting: N/A - resolved
next_action: N/A - resolved

## Symptoms

expected: Editing a person's fields and saving should succeed
actual: REST API returns 400 error for acf[_nikki_2025_total] type mismatch
errors: rest_invalid_param - acf[_nikki_2025_total] is not of type number,null
reproduction: Edit any person in Rondo Club and try to save
started: Likely after recent contact field migration (v31.0 phases 209-210)

## Eliminated

## Evidence

- timestamp: 2026-03-09T00:00:30Z
  checked: sanitizePersonAcf in src/utils/formatters.js
  found: numericFields array only contains 'freescout-id', missing all 8 nikki number fields
  implication: Empty string nikki values pass through unsanitized, REST API rejects them

- timestamp: 2026-03-09T00:00:45Z
  checked: ACF field definitions in acf-json/group_person_fields.json
  found: 9 number-type fields total (freescout-id + 8 nikki fields for years 2022-2025, total and saldo each)
  implication: All 8 nikki fields need same treatment as freescout-id

## Resolution

root_cause: sanitizePersonAcf in formatters.js only listed 'freescout-id' in numericFields. The 8 nikki number fields (_nikki_{2022-2025}_{total,saldo}) were missing, so empty string values were sent to the REST API which expects number|null.
fix: Added all 8 nikki fields to the numericFields array in sanitizePersonAcf
verification: User confirmed on production - editing and saving persons works, reverse sync works too
files_changed: [src/utils/formatters.js]
