---
status: resolved
trigger: "Custom field '# Websites hosted' is being displayed as '# WEBSITES HOSTED' (fully uppercased) on the company list page"
created: 2026-01-21T12:00:00Z
updated: 2026-01-21T12:00:00Z
---

## Current Focus

hypothesis: CSS `uppercase` class on `<th>` elements forces all column headers to uppercase
test: Verify this is applied to custom field headers (line 473)
expecting: Confirm that removing `uppercase` class from custom field headers fixes the issue
next_action: Fix the CSS class on custom field column headers

## Symptoms

expected: Label should display as "# Websites hosted" (preserving original case)
actual: Label displays as "# WEBSITES HOSTED" (fully uppercased)
errors: None reported
reproduction: View the company list page, look at the "# Websites hosted" column header
started: Recently changed - it used to work correctly

## Eliminated

## Evidence

- timestamp: 2026-01-21T12:05:00Z
  checked: CompaniesList.jsx line 469-477 - custom field column headers
  found: The <th> element for custom fields has CSS class "uppercase" which transforms text
  implication: This is the root cause - CSS text-transform: uppercase is applied to all table headers including custom field labels

- timestamp: 2026-01-21T12:05:00Z
  checked: CompaniesList.jsx line 409 - SortableHeader component
  found: Standard headers also use "uppercase" class by design (Name, Industry, Website, Workspace)
  implication: The design intentionally uses uppercase for standard columns, but custom field labels should preserve original case

- timestamp: 2026-01-21T12:06:00Z
  checked: PeopleList.jsx line 190-198 - custom field column headers
  found: Same pattern - <th> element for custom fields has CSS class "uppercase"
  implication: Both list views need to be fixed for consistency

## Resolution

root_cause: CSS class "uppercase" on custom field <th> elements transforms text to all-uppercase, overriding the original label casing
fix: Remove "uppercase" class from custom field column headers in both CompaniesList.jsx and PeopleList.jsx
verification: Build successful, deployed to production at https://cael.is/
files_changed:
  - src/pages/Companies/CompaniesList.jsx (line 473)
  - src/pages/People/PeopleList.jsx (line 194)
