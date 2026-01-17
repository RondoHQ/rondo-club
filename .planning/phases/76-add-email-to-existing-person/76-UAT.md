---
status: complete
phase: 76-add-email-to-existing-person
source: [76-01-SUMMARY.md]
started: 2026-01-17T12:00:00Z
updated: 2026-01-17T12:15:00Z
---

## Current Test

[testing complete]

## Tests

### 1. Choice Popup Appears
expected: Click Add button on unknown meeting attendee. Popup appears with two options: "Add to existing person" and "Create new person".
result: pass

### 2. Create New Person Flow
expected: Click "Create new person" in popup. PersonEditModal opens with name and email pre-filled from the attendee.
result: pass

### 3. Add to Existing - Search Opens
expected: Click "Add to existing person" in popup. Search mode opens with input field and back button.
result: pass

### 4. Add to Existing - Person Search Works
expected: Type at least 2 characters in search. Person results appear with avatars. Matches filter as you type.
result: pass

### 5. Add to Existing - Select Person Adds Email
expected: Click a person in search results. Popup closes. Email is added to that person's contact_info.
result: issue
reported: "Selecting person gives error: rest_invalid_param - first_name is a required property of acf. Payload only sends contact_info, not preserving existing ACF fields."
severity: blocker

### 6. Attendee Updates After Adding Email
expected: After adding email to existing person, the attendee row updates to show them as "known" (linked to the person you selected).
result: skipped
reason: Blocked by test 5 failure

### 7. Dark Mode Styling
expected: Popup renders correctly in dark mode with proper contrast for text, buttons, and search results.
result: issue
reported: "Dark mode styling is fine, but popup is too small - search person interface requires scrolling"
severity: minor

## Summary

total: 7
passed: 4
issues: 2
pending: 0
skipped: 1

## Gaps

- truth: "Click a person in search results. Popup closes. Email is added to that person's contact_info."
  status: failed
  reason: "User reported: Selecting person gives error: rest_invalid_param - first_name is a required property of acf. Payload only sends contact_info, not preserving existing ACF fields."
  severity: blocker
  test: 5
  root_cause: "useAddEmailToPerson sends only contact_info in ACF update, but API requires first_name. Need to preserve all existing ACF fields or use a different update approach."
  artifacts:
    - path: "src/hooks/usePeople.js"
      issue: "useAddEmailToPerson only sends contact_info, missing required first_name"
  missing:
    - "Preserve existing ACF fields when updating contact_info"

- truth: "Popup renders correctly in dark mode with proper contrast for text, buttons, and search results."
  status: failed
  reason: "User reported: Dark mode styling is fine, but popup is too small - search person interface requires scrolling"
  severity: minor
  test: 7
  root_cause: "Popup height too constrained for search mode with results"
  artifacts:
    - path: "src/components/AddAttendeePopup.jsx"
      issue: "max-height or height constraint too small for search mode"
  missing:
    - "Increase popup height in search mode to show more results without scrolling"
