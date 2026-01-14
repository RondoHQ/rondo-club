---
status: complete
phase: 39-api-improvements
source: [.planning/phases/39-api-improvements/39-01-SUMMARY.md]
started: 2026-01-14T14:00:00Z
updated: 2026-01-14T14:05:00Z
---

## Current Test
<!-- OVERWRITE each test - shows where we are -->

[testing complete]

## Tests

### 1. Important date title persists user edits
expected: Edit an important date, change the title to a custom value, save. The custom title should persist (not be overwritten by auto-generated text). Reload and verify it's still there.
result: issue
reported: "when I re-open the edit, the label immediately changes to the auto-generated title"
severity: major

### 2. Auto-title still works for new dates
expected: Create a new important date (e.g., Birthday for a person). The title should auto-generate as "{Person}'s Birthday". This confirms auto-title still works when appropriate.
result: pass

### 3. Search prioritizes first name matches
expected: Search for a first name (e.g., "John"). People with that first name should appear at the top of results, above people who might have "John" elsewhere (like in notes or last name "Johnson").
result: pass

### 4. Dashboard updates after todo creation from PersonDetail
expected: Note the dashboard's open todos count. Go to a person's detail page, create a new todo. Navigate to dashboard - the open todos count should have incremented immediately.
result: pass

### 5. Dashboard updates after todo completion from PersonDetail
expected: From a person's detail page, mark a todo as completed (or mark an "Awaiting" todo as open/completed). Navigate to dashboard - the counts should reflect the change immediately.
result: pass

## Summary

total: 5
passed: 4
issues: 1
pending: 0
skipped: 0

## Issues for /gsd:plan-fix

- ~~UAT-001: Custom title reverts to auto-generated when reopening edit modal (major) - Test 1~~ **RESOLVED in 39-01-FIX**
  - **Root cause:** Backend saves custom title to `custom_label` ACF field, but `format_date()` in class-rest-base.php doesn't include this field in the API response. Without `custom_label` data, the frontend modal doesn't know the title was customized and auto-regenerates it on open.
  - **Fix:** 1) Add `custom_label` to `format_date()` response. 2) In ImportantDateModal, set `hasUserEditedTitle.current = true` when `custom_label` is present.
  - **Commit:** e84622d
