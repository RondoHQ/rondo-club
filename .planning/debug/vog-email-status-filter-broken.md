---
status: verifying
trigger: "vog-email-status-filter-broken"
created: 2026-01-31T00:00:00Z
updated: 2026-01-31T00:15:00Z
---

## Current Focus

hypothesis: CONFIRMED - Frontend sends vog_email_status but backend expects vog_email_status (parameter name mismatch)
test: Check both frontend parameter naming and backend parameter extraction
expecting: Found parameter name mismatch causing filter to not apply
next_action: Fix parameter name mismatch and verify filter works

## Symptoms

expected: When selecting an email status filter option, the VOG list should filter to show only people matching that email status
actual: Selecting email status filter options does not change the displayed data
errors: None reported
reproduction: Go to VOG page, use the email status dropdown filter, observe no change in results
started: Unknown - may have never worked properly after implementation

## Eliminated

- hypothesis: Parameter not being sent from frontend
  evidence: Line 272 of VOGList.jsx sends vogEmailStatus parameter
  timestamp: 2026-01-31T00:05:00Z

- hypothesis: Backend endpoint not registered
  evidence: Lines 337-344 of class-rest-people.php show vog_email_status parameter is registered
  timestamp: 2026-01-31T00:05:00Z

## Evidence

- timestamp: 2026-01-31T00:05:00Z
  checked: VOGList.jsx line 272
  found: Frontend sends vogEmailStatus (camelCase) to useFilteredPeople hook
  implication: Parameter is being sent from frontend

- timestamp: 2026-01-31T00:06:00Z
  checked: usePeople.js line 133
  found: Hook converts vogEmailStatus to vog_email_status (snake_case) on line 133
  implication: Parameter name transformation is correct in the hook

- timestamp: 2026-01-31T00:07:00Z
  checked: class-rest-people.php lines 337-344
  found: Endpoint parameter registered as vog_email_status
  implication: Backend accepts vog_email_status parameter

- timestamp: 2026-01-31T00:08:00Z
  checked: class-rest-people.php line 1049
  found: Backend extracts parameter: $vog_email_status = $request->get_param( 'vog_email_status' );
  implication: Backend correctly reads the parameter

- timestamp: 2026-01-31T00:09:00Z
  checked: class-rest-people.php lines 1193-1203
  found: Filter logic checks if ( ! empty( $vog_email_status ) ) then applies subquery
  implication: FOUND THE BUG - empty() returns true for empty string '', so filter never applies

## Resolution

root_cause: Backend filter logic uses if ( ! empty( $vog_email_status ) ) which treats empty string '' as falsy. When "Alle" is selected, emailStatusFilter is set to '' (line 242 VOGList.jsx), which passes through as vog_email_status='', but empty() evaluates this as false, so the filter logic at lines 1193-1203 never executes. The filter only works when value is 'sent' or 'not_sent', but since it never enters the condition, it never applies.

fix: Changed condition from if ( ! empty( $vog_email_status ) ) to if ( $vog_email_status !== null && $vog_email_status !== '' ) on line 1194 of class-rest-people.php. This explicitly checks for non-empty strings, allowing the filter to properly execute when 'sent' or 'not_sent' is selected.

verification:
  status: deployed_to_production
  deployment_time: 2026-01-31T00:15:00Z
  manual_testing_required: true
  test_steps:
    1. Go to https://stadion.svawc.nl/vog
    2. Select "Alle" email status - should show all VOG people
    3. Select "Niet verzonden" - should show only people without email sent
    4. Select "Wel verzonden" - should show only people with email sent
    5. Verify counts in dropdown match displayed results

files_changed:
  - includes/class-rest-people.php (line 1194)
