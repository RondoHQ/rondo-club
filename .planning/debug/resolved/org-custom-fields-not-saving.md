---
status: resolved
trigger: "org-custom-fields-not-saving - When setting a custom field value for an team via the edit team page, no error occurs but the value is not persisted."
created: 2026-01-21T12:00:00Z
updated: 2026-01-21T12:30:00Z
---

## Current Focus

hypothesis: CONFIRMED - Two issues: (1) Wrong payload structure, (2) Missing ACF data sanitization
test: Apply both fixes and verify custom field values persist
expecting: After fix, saving custom fields should work correctly
next_action: User verification

## Symptoms

expected: Custom field value should save and persist when team is saved
actual: No error shown, but the custom field value is not saved/persisted
errors: None visible (later: ACF validation error after first fix)
reproduction: Open team edit page, edit a custom field, save the team - value disappears
started: Recently broke (used to work)

## Eliminated

## Evidence

- timestamp: 2026-01-21T12:05:00Z
  checked: TeamDetail.jsx updateTeam mutation definition (lines 110-118)
  found: |
    mutationFn: (data) => wpApi.updateTeam(id, data)
    - The mutation expects the payload DIRECTLY (just `data`)
    - It captures `id` from closure, no need to pass it in payload
  implication: Mutation signature is `(data)` not `({id, data})`

- timestamp: 2026-01-21T12:06:00Z
  checked: TeamDetail.jsx onUpdate callback for CustomFieldsSection (lines 536-541)
  found: |
    onUpdate={(newAcfValues) => {
      updateTeam.mutateAsync({
        id,
        data: { acf: { ...team?.acf, ...newAcfValues } },
      });
    }}
  implication: |
    BUG: Passes { id, data: { acf: ... } } but mutation expects just { acf: ... }
    Result: wpApi.updateTeam receives { id: X, data: {...} } instead of { acf: {...} }
    WordPress REST API doesn't recognize 'id' and 'data' as valid fields to update

- timestamp: 2026-01-21T12:25:00Z
  checked: After first fix, user reported ACF validation error
  found: |
    "acf[contact_info] must be of type array or null."
    The fix correctly sends ACF data now, but merging team?.acf with newAcfValues
    includes repeater fields like contact_info which may not be in valid array format.
  implication: |
    Need to sanitize ACF data before sending, similar to sanitizePersonAcf for PersonDetail

- timestamp: 2026-01-21T12:28:00Z
  checked: PersonDetail.jsx pattern for custom fields
  found: |
    Uses sanitizePersonAcf(person.acf, newAcfValues) which ensures:
    - Repeater fields are arrays (contact_info, addresses, work_history, etc.)
    - Enum fields with empty strings become null
  implication: Need similar sanitizeTeamAcf function for teams

## Resolution

root_cause: |
  Two issues:
  1. TeamDetail.jsx onUpdate callback passed wrong payload structure
  2. Missing sanitization for ACF repeater fields (contact_info)

fix: |
  1. Fixed payload structure in TeamDetail.jsx onUpdate callback
  2. Created sanitizeTeamAcf() function in formatters.js
  3. Updated TeamDetail.jsx to use sanitizeTeamAcf before sending ACF data

  New code:
    onUpdate={(newAcfValues) => {
      const acfData = sanitizeTeamAcf(team?.acf, newAcfValues);
      updateTeam.mutateAsync({ acf: acfData });
    }}

verification: |
  - Build passes successfully (npm run build)
  - Deployed to production: https://cael.is/
  - Awaiting user verification to confirm fix works

files_changed:
  - src/pages/Teams/TeamDetail.jsx
  - src/utils/formatters.js
