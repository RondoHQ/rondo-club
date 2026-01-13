# Project Issues Log

Enhancements discovered during execution. Not critical - address in future phases.

## Open Enhancements

### ISS-003: Extend bulk edit to support Organizations and Labels

- **Discovered:** Post Phase 13 completion (2026-01-13)
- **Type:** Feature
- **Description:** Allow bulk actions to assign people to Organizations (set current company) and add/remove Labels. Currently only visibility and workspace assignment are supported.
- **Impact:** Low (current bulk actions work, this expands capabilities)
- **Effort:** Medium (need new modals, extend REST endpoint to handle org/label updates)
- **Suggested phase:** Phase 15

## Closed Enhancements

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
