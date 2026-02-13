---
phase: 175-backend-cleanup
plan: 01
subsystem: backend/taxonomies
tags:
  - dead-feature-removal
  - taxonomies
  - rest-api
  - cleanup
dependency_graph:
  requires: []
  provides:
    - person_label-removed
    - team_label-removed
  affects:
    - class-taxonomies
    - class-rest-base
    - class-rest-people
    - class-rest-teams
    - class-rest-google-sheets
tech_stack:
  added: []
  patterns:
    - one-time-db-cleanup
key_files:
  created: []
  modified:
    - includes/class-taxonomies.php
    - includes/class-rest-base.php
    - includes/class-rest-people.php
    - includes/class-rest-teams.php
    - includes/class-rest-google-sheets.php
    - tests/Wpunit/RelationshipsSharesTest.php
decisions:
  - Use raw SQL for cleanup since taxonomies are unregistered
  - One-time cleanup runs on first page load after deployment
  - Remove teams bulk-update endpoint entirely (was labels-only)
metrics:
  duration_seconds: 282
  tasks_completed: 2
  commits: 2
  files_modified: 6
  lines_removed: 425
  lines_added: 41
completed: 2026-02-13
---

# Phase 175 Plan 01: Backend Cleanup - Remove Label Taxonomies

Removed person_label and team_label taxonomy registrations and all label-related code from REST API endpoints, formatters, and database.

## Tasks Completed

### Task 1: Remove label taxonomy registrations and REST label references
- **Commit:** cc2b8215
- **Changes:**
  - Removed `register_person_label_taxonomy()` and `register_team_label_taxonomy()` methods from Taxonomies class
  - Removed `labels` field from `format_person_summary()` and `format_company_summary()` in REST Base
  - Removed `labels` parameter from people filtered endpoint
  - Removed `labels_add`/`labels_remove` validation and logic from people bulk-update endpoint
  - Removed label filtering from `get_filtered_people()` SQL query (removed JOIN on term_relationships/term_taxonomy)
  - Removed `labels` field from filtered people response
  - Removed entire teams bulk-update endpoint (was labels-only, no other update types)
  - Removed `bulk_update_teams()` and `check_bulk_update_permission()` methods from Teams REST class
  - Removed `labels` case from Google Sheets column value resolver
  - Removed `get_labels()` method from Google Sheets export class
  - Updated people bulk-update to only handle `organization_id` updates

### Task 2: Remove label-related tests and add DB cleanup
- **Commit:** d4eda5f8
- **Changes:**
  - Removed `test_people_bulk_update_add_labels()` test method
  - Removed `test_people_bulk_update_remove_labels()` test method
  - Removed `test_teams_bulk_update_add_labels()` test method
  - Added `cleanup_removed_taxonomies()` private method to Taxonomies class
  - Cleanup uses raw SQL to delete from `term_relationships`, `term_taxonomy`, and orphaned `terms`
  - Runs once via `rondo_labels_cleaned` option flag check
  - Triggered on first load after deployment during `register_taxonomies()`

## Deviations from Plan

None - plan executed exactly as written.

## Impact

**Before:**
- person_label and team_label taxonomies registered and visible in WP admin
- REST API responses included empty `labels` arrays for people and teams
- Bulk-update endpoints accepted label operations
- Filtered people endpoint accepted labels parameter
- Google Sheets export included labels column

**After:**
- No label taxonomies registered
- REST API responses no longer include labels field (cleaner payloads)
- People bulk-update only handles organization_id (still useful for team assignment)
- Teams bulk-update endpoint completely removed (had no other purpose)
- Filtered people endpoint has one fewer filter parameter
- Google Sheets export gracefully handles missing labels column

## Verification

- ✅ No `person_label` references in includes/ or tests/ (except cleanup SQL)
- ✅ No `team_label` references in includes/ or tests/ (except cleanup SQL)
- ✅ All modified PHP files have no syntax errors
- ✅ commissie_label, relationship_type, and seizoen taxonomies remain registered
- ✅ Database cleanup runs once on first page load after deployment

## Technical Notes

**Why raw SQL for cleanup?**
Since the taxonomies are no longer registered, WordPress functions like `get_terms()` and `wp_delete_term()` won't work. Using raw SQL ensures complete cleanup of all three tables (term_relationships, term_taxonomy, terms).

**Why remove teams bulk-update entirely?**
Unlike people bulk-update (which still handles organization_id), teams bulk-update ONLY handled labels. With labels removed, the endpoint serves no purpose. Removing it reduces API surface area.

**Database cleanup safety:**
- Uses proper JOINs to only delete person_label/team_label data
- Cleans up orphaned terms (terms with no taxonomy entries)
- Runs exactly once via option flag
- No risk to other taxonomies (commissie_label, relationship_type, seizoen)

## Self-Check: PASSED

**Files created:** None (cleanup only)

**Files modified:**
- ✅ includes/class-taxonomies.php exists
- ✅ includes/class-rest-base.php exists
- ✅ includes/class-rest-people.php exists
- ✅ includes/class-rest-teams.php exists
- ✅ includes/class-rest-google-sheets.php exists
- ✅ tests/Wpunit/RelationshipsSharesTest.php exists

**Commits:**
- ✅ cc2b8215 exists
- ✅ d4eda5f8 exists
