# Project Issues Log

Enhancements discovered during execution. Not critical - address in future phases.

## Open Enhancements

None

## Closed Enhancements

### ISS-008: Organizations should use list interface like People

- **Discovered:** Post v2.2 completion (2026-01-13)
- **Closed:** Phase 17 (2026-01-13)
- **Resolution:** Transformed Organizations from card grid to tabular list view with OrganizationListRow and OrganizationListView components. Added columns (checkbox, logo, name, industry, website, workspace, labels), SortableHeader for column sorting, selection state with select all/none, header sort controls (dropdown + direction toggle), and sticky selection toolbar.

### ISS-007: Move person image to its own column without header label

- **Discovered:** Post v2.2 completion (2026-01-13)
- **Closed:** Phase 16 (2026-01-13)
- **Resolution:** Added dedicated image column (w-10 px-2) before First Name. Empty header cell for clean appearance. First Name header now aligns with first name values.

### ISS-006: Remove card view from People, use list view only

- **Discovered:** Post v2.2 completion (2026-01-13)
- **Closed:** Phase 16 (2026-01-13)
- **Resolution:** Deleted PersonCard component (~53 lines), removed viewMode state and localStorage persistence, removed view toggle UI. List view is now the only view.

### ISS-003: Extend bulk edit to support Organizations and Labels

- **Discovered:** Post Phase 13 completion (2026-01-13)
- **Closed:** Phase 15 (2026-01-13)
- **Resolution:** Extended bulk-update endpoint with organization_id, labels_add, labels_remove parameters. Created BulkOrganizationModal with search/filter and clear option. Created BulkLabelsModal with add/remove mode toggle for multi-select label management.

### ISS-001: Add sorting by Organization and Workspace in list view

- **Discovered:** Phase 13 Task 3 checkpoint (2026-01-13)
- **Closed:** Phase 14-01 (2026-01-13)
- **Resolution:** Added Organization, Workspace, and Labels sorting options to dropdown. Empty values sort last.

### ISS-002: Add Label column to list view with sorting

- **Discovered:** Post Phase 13 completion (2026-01-13)
- **Closed:** Phase 14-01 (2026-01-13)
- **Resolution:** Added Labels column displaying up to 3 labels as pills with "+N more" indicator. Sorting by first label name.

### ISS-004: Click table headers to change sorting

- **Discovered:** Post Phase 13 completion (2026-01-13)
- **Closed:** Phase 14-02 (2026-01-13)
- **Resolution:** Created SortableHeader component. Click header to sort, click again to toggle direction. Arrow indicator shows active sort.

### ISS-005: Sticky table header and action bar

- **Discovered:** Post Phase 13 completion (2026-01-13)
- **Closed:** Phase 14-02 (2026-01-13)
- **Resolution:** Made table container scrollable with sticky thead. Selection toolbar sticky at top of page. Uses calc(100vh-12rem) for optimal viewport fill.
