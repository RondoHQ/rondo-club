---
phase: 173-import-command
plan: 03
subsystem: demo-fixtures
tags: [wp-cli, data-management, idempotent-import]
dependencies:
  requires: [173-01]
  provides: [clean-import-capability]
  affects: [demo-import-pipeline]
tech_stack:
  added: []
  patterns: [idempotent-operations, clean-slate-import]
key_files:
  created: []
  modified:
    - includes/class-demo-import.php
    - includes/class-wp-cli.php
decisions: []
metrics:
  duration_seconds: 214
  completed_at: 2026-02-11T12:59:02Z
---

# Phase 173 Plan 03: Clean Flag for Demo Import Summary

**One-liner:** Idempotent demo import with --clean flag that wipes all Rondo data before importing fresh fixtures.

## What Was Built

Added a `--clean` flag to the `wp rondo demo import` WP-CLI command that removes all existing Rondo data before importing, enabling:
- **Idempotent imports:** Run import multiple times without duplicate data
- **Fresh demo environments:** Start with clean slate for testing/demos
- **Safe data wipe:** Removes only Rondo-specific data, preserves WordPress core and user accounts

## Implementation Details

### 1. DemoImport::clean() Method

Added public `clean()` method to `DemoImport` class that executes deletion in correct dependency order:

1. **Comments first** (3 custom types): `rondo_note`, `rondo_activity`, `rondo_email`
   - Deleted before posts to avoid foreign key issues
   - Force delete (bypasses trash)

2. **Custom post types** (5 types): `rondo_todo`, `discipline_case`, `person`, `team`, `commissie`
   - Special handling for `rondo_todo` with custom statuses: `rondo_open`, `rondo_awaiting`, `rondo_completed`
   - Force delete (bypasses trash)
   - Logs count for each post type

3. **Taxonomies** (2 types): `relationship_type`, `seizoen`
   - Includes hidden terms (`hide_empty: false`)

4. **WordPress options**:
   - **Static keys** (10 options): `rondo_club_name`, `rondo_player_roles`, `rondo_excluded_roles`, VOG settings, etc.
   - **Dynamic season keys** (variable): `rondo_membership_fees_*`, `rondo_family_discount_*`
   - Uses SQL query to find dynamic options by pattern matching

### 2. WP-CLI --clean Flag

Updated `RONDO_Demo_CLI_Command::import()` method:

- **Docblock:** Added `[--clean]` option documentation
- **Examples:** Added usage with and without `--input` flag
- **Execution flow:**
  1. Validate fixture file exists
  2. Instantiate DemoImport
  3. **If --clean flag set:** Call `clean()` before `import()`
  4. Run import
  5. Show success message

Visual separator (empty log line) between clean and import output improves readability.

## Key Decisions

### Why Force Delete Instead of Trash

Comments and posts use `wp_delete_comment($id, true)` and `wp_delete_post($id, true)` to bypass trash. Rationale:
- Demo imports are intentional bulk operations
- Trash would accumulate unnecessary data
- Clean means truly clean, not "soft deleted"

### Why Custom Status Array for rondo_todo

Standard `'post_status' => 'any'` doesn't include custom statuses in WordPress. Explicitly listing `['rondo_open', 'rondo_awaiting', 'rondo_completed', 'any']` ensures all todos are found and deleted.

### Why SQL Query for Dynamic Options

Season-specific options use dynamic keys like `rondo_membership_fees_2025-2026`. Querying the options table with LIKE patterns is more efficient than:
- Hardcoding all season keys (breaks when new seasons added)
- Iterating all options and filtering (wasteful for large databases)

## Deviations from Plan

None - plan executed exactly as written.

## Testing Notes

Manual testing should verify:
1. `wp rondo demo import --clean` wipes existing data
2. Import works correctly on cleaned database (no orphaned references)
3. Running import twice with --clean produces identical results (idempotent)
4. User accounts and WordPress core data are preserved after clean
5. `wp rondo demo import` without --clean still works (additive import)

## Files Changed

| File | Changes | Lines Modified |
|------|---------|----------------|
| `includes/class-demo-import.php` | Added `clean()` method | +123 |
| `includes/class-wp-cli.php` | Added `--clean` flag support | +11 |

## Commits

| Task | Commit | Description |
|------|--------|-------------|
| 1 | 944d0d5a | Implement clean() method in DemoImport |
| 2 | 70dd4334 | Add --clean flag to WP-CLI import command |

## Self-Check: PASSED

**Files exist:**
- FOUND: includes/class-demo-import.php (clean() method present)
- FOUND: includes/class-wp-cli.php (--clean flag documented and implemented)

**Commits exist:**
- FOUND: 944d0d5a (Task 1 commit)
- FOUND: 70dd4334 (Task 2 commit)

**Verification:**
- clean() method deletes 5 CPTs ✓
- clean() method deletes 2 taxonomies ✓
- clean() method deletes static + dynamic options ✓
- --clean flag documented in WP-CLI help ✓
- clean() called before import() when flag present ✓
