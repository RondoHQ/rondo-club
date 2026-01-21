---
status: resolved
trigger: "org-custom-fields-not-saving - When setting a custom field value for an organization via the edit organization page, no error occurs but the value is not persisted."
created: 2026-01-21T12:00:00Z
updated: 2026-01-21T12:30:00Z
---

## Current Focus

hypothesis: CONFIRMED - Two issues: (1) Wrong payload structure, (2) Missing ACF data sanitization
test: Apply both fixes and verify custom field values persist
expecting: After fix, saving custom fields should work correctly
next_action: User verification

## Symptoms

expected: Custom field value should save and persist when organization is saved
actual: No error shown, but the custom field value is not saved/persisted
errors: None visible (later: ACF validation error after first fix)
reproduction: Open organization edit page, edit a custom field, save the organization - value disappears
started: Recently broke (used to work)

## Eliminated

## Evidence

- timestamp: 2026-01-21T12:05:00Z
  checked: CompanyDetail.jsx updateCompany mutation definition (lines 110-118)
  found: |
    mutationFn: (data) => wpApi.updateCompany(id, data)
    - The mutation expects the payload DIRECTLY (just `data`)
    - It captures `id` from closure, no need to pass it in payload
  implication: Mutation signature is `(data)` not `({id, data})`

- timestamp: 2026-01-21T12:06:00Z
  checked: CompanyDetail.jsx onUpdate callback for CustomFieldsSection (lines 536-541)
  found: |
    onUpdate={(newAcfValues) => {
      updateCompany.mutateAsync({
        id,
        data: { acf: { ...company?.acf, ...newAcfValues } },
      });
    }}
  implication: |
    BUG: Passes { id, data: { acf: ... } } but mutation expects just { acf: ... }
    Result: wpApi.updateCompany receives { id: X, data: {...} } instead of { acf: {...} }
    WordPress REST API doesn't recognize 'id' and 'data' as valid fields to update

- timestamp: 2026-01-21T12:25:00Z
  checked: After first fix, user reported ACF validation error
  found: |
    "acf[contact_info] must be of type array or null."
    The fix correctly sends ACF data now, but merging company?.acf with newAcfValues
    includes repeater fields like contact_info which may not be in valid array format.
  implication: |
    Need to sanitize ACF data before sending, similar to sanitizePersonAcf for PersonDetail

- timestamp: 2026-01-21T12:28:00Z
  checked: PersonDetail.jsx pattern for custom fields
  found: |
    Uses sanitizePersonAcf(person.acf, newAcfValues) which ensures:
    - Repeater fields are arrays (contact_info, addresses, work_history, etc.)
    - Enum fields with empty strings become null
  implication: Need similar sanitizeCompanyAcf function for organizations

## Resolution

root_cause: |
  Two issues:
  1. CompanyDetail.jsx onUpdate callback passed wrong payload structure
  2. Missing sanitization for ACF repeater fields (contact_info)

fix: |
  1. Fixed payload structure in CompanyDetail.jsx onUpdate callback
  2. Created sanitizeCompanyAcf() function in formatters.js
  3. Updated CompanyDetail.jsx to use sanitizeCompanyAcf before sending ACF data

  New code:
    onUpdate={(newAcfValues) => {
      const acfData = sanitizeCompanyAcf(company?.acf, newAcfValues);
      updateCompany.mutateAsync({ acf: acfData });
    }}

verification: |
  - Build passes successfully (npm run build)
  - Deployed to production: https://cael.is/
  - Awaiting user verification to confirm fix works

files_changed:
  - src/pages/Companies/CompanyDetail.jsx
  - src/utils/formatters.js
