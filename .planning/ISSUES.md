# Project Issues Log

Enhancements discovered during execution. Not critical - address in future phases.

## Open Enhancements

### ISS-001: Add sorting by Organization and Workspace in list view

- **Discovered:** Phase 13 Task 3 checkpoint (2026-01-13)
- **Type:** UX
- **Description:** Currently can only sort by Name, Created, and Last Modified. Users want to sort by Organization column (company name) and Workspace column (assigned workspaces) for better list organization.
- **Impact:** Low (works correctly, this would enhance usability)
- **Effort:** Medium (need to add sorting logic for Organization which requires lookup, Workspace sorting needs multi-value handling)
- **Suggested phase:** Future

### ISS-002: Add Label column to list view with sorting

- **Discovered:** Post Phase 13 completion (2026-01-13)
- **Type:** UX
- **Description:** Add a Labels column to the list view showing person_label taxonomy terms. Enable sorting by label for grouping contacts by classification.
- **Impact:** Low (works correctly, this would enhance usability)
- **Effort:** Medium (need to display multiple labels in column, sorting by multi-value field)
- **Suggested phase:** Future

### ISS-003: Extend bulk edit to support Organizations and Labels

- **Discovered:** Post Phase 13 completion (2026-01-13)
- **Type:** Feature
- **Description:** Allow bulk actions to assign people to Organizations (set current company) and add/remove Labels. Currently only visibility and workspace assignment are supported.
- **Impact:** Low (current bulk actions work, this expands capabilities)
- **Effort:** Medium (need new modals, extend REST endpoint to handle org/label updates)
- **Suggested phase:** Future

### ISS-004: Click table headers to change sorting

- **Discovered:** Post Phase 13 completion (2026-01-13)
- **Type:** UX
- **Description:** Make table column headers clickable to sort by that column. Click once for ascending, again for descending. Show sort indicator arrow in active header.
- **Impact:** Low (sorting works via dropdown, this is more intuitive)
- **Effort:** Quick (add onClick handlers to th elements, reuse existing sort logic)
- **Suggested phase:** Future

### ISS-005: Sticky table header and action bar

- **Discovered:** Post Phase 13 completion (2026-01-13)
- **Type:** UX
- **Description:** Make the filter/action buttons and table header row sticky so they remain visible when scrolling down long lists. Users shouldn't need to scroll back up to perform bulk actions on selected items.
- **Impact:** Low (works correctly, significant usability improvement for long lists)
- **Effort:** Quick (CSS position: sticky on header elements)
- **Suggested phase:** Future

## Closed Enhancements

[Moved here when addressed]
