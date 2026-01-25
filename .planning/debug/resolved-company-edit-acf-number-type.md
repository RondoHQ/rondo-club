---
status: verifying
trigger: "team-edit-acf-number-type"
created: 2026-01-22T10:00:00Z
updated: 2026-01-22T10:25:00Z
---

## Current Focus

hypothesis: contact_info is a repeater field that receives null from existingAcf, but ACF REST API requires [] for empty repeaters
test: Check ACF field definition for contact_info type
expecting: Repeater field that needs empty array instead of null
next_action: Fix updateRowMutation to convert null to [] for repeater/array-type ACF fields

## Symptoms

expected: Team edit should save successfully via REST API
actual: API returns 400 error with "acf[websites-hosted] is not of type number,null."
errors: rest_invalid_param - acf[websites-hosted] is not of type number,null
reproduction: Edit any team in the list view, payload includes "websites-hosted":"" which fails
started: Likely when inline editing was added to teams (recent commits)

## Eliminated

## Evidence

- timestamp: 2026-01-22T10:05:00Z
  checked: InlineFieldInput.jsx lines 46-62
  found: Number input uses e.target.value directly (always a string), empty input = ""
  implication: Empty number fields are sent as "" instead of null

- timestamp: 2026-01-22T10:06:00Z
  checked: TeamsList.jsx lines 590-610, handleSaveRow function
  found: editedFields passed directly to API without any type conversion
  implication: Empty strings reach the REST API and fail ACF validation for number fields

- timestamp: 2026-01-22T10:20:00Z
  checked: acf-json/group_team_fields.json
  found: contact_info is type "repeater" (line 15), requires array values
  implication: When existingAcf.contact_info is null, it gets sent as null but API requires [] for repeaters

- timestamp: 2026-01-22T10:21:00Z
  checked: updateRowMutation in TeamsList.jsx lines 590-605
  found: Spreads existingAcf directly into API payload without sanitizing null values for array fields
  implication: Null repeater/post_object fields cause REST validation errors

## Resolution

root_cause: Two issues in TeamsList.jsx team inline editing:
1. handleSaveRow passed empty strings for number fields instead of null (ACF requires number or null)
2. updateRowMutation spread existingAcf which could contain null for array-type fields (repeaters, multi-select post_object) - ACF REST API requires [] not null for these

fix:
1. handleSaveRow converts empty strings to null for number-type fields based on listViewFields metadata
2. updateRowMutation now sanitizes merged ACF data, converting null/undefined to [] for known array-type fields (contact_info, investors, _assigned_workspaces)

verification: Build succeeded, deployed to production. Ready for user verification.
files_changed: ["/Users/joostdevalk/Code/stadion/src/pages/Teams/TeamsList.jsx"]
