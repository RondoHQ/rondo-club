# Project Issues Log

Enhancements discovered during execution. Not critical - address in future phases.

## Open Enhancements

*No open issues.*

## Closed Enhancements

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
