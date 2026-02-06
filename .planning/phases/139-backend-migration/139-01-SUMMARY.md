---
phase: 139-backend-migration
plan: 01
subsystem: access-control
tags: [tasks, user-isolation, wp-query, rest-api, wp-cli, migration]

# Dependencies
requires:
  - phase-24: Tasks CPT foundation (stadion_todo post type)
  - phase-111: User isolation pattern established
provides:
  - task-author-filtering: WP_Query and REST API filter by post_author
  - task-user-counts: Dashboard counts filtered by current user
  - task-ownership-cli: WP-CLI command to verify/fix task ownership
affects:
  - phase-140: Frontend needs to handle user-isolated task data

# Tech Stack
tech-stack:
  added: []
  patterns:
    - "WP_Query author parameter for user isolation"
    - "REST API rest_{post_type}_query filter hook"
    - "Direct SQL COUNT queries for custom post statuses"
    - "WP-CLI migration with --verify and --dry-run flags"

# Files
key-files:
  created: []
  modified:
    - includes/class-access-control.php: "Added author filtering for stadion_todo in both filter_queries() and filter_rest_query()"
    - includes/class-rest-api.php: "Replaced wp_count_posts() with prepared SQL queries filtering by post_author"
    - includes/class-wp-cli.php: "Added RONDO_Tasks_CLI_Command with verify_ownership method"

# Decision Log
decisions:
  - id: TASK-ISOLATION-ALL-USERS
    made: 2026-02-04
    context: "Should task isolation apply to admins?"
    decision: "Yes - all users see only their own tasks, including admins"
    rationale: "Simpler implementation, consistent UX, admins can use WP-CLI if needed"
    alternatives: ["Admin exemption with separate view"]

# Metrics
metrics:
  duration: "2m 45s"
  completed: 2026-02-04
---

# Phase 139 Plan 01: Backend & Migration Summary

**One-liner:** User isolation for tasks via post_author filtering in WP_Query, REST API, and dashboard counts with WP-CLI migration command

## What Was Built

Implemented complete backend user isolation for tasks (stadion_todo custom post type):

1. **Access Control Filtering:**
   - Extended `RONDO_Access_Control::filter_queries()` to set `author` parameter for stadion_todo queries
   - Extended `RONDO_Access_Control::filter_rest_query()` to filter REST API queries by current user
   - Filtering applies after approval check, before VOG-only filtering (logical order)
   - No special admin exemption - all users see only their own tasks

2. **Dashboard Counts:**
   - Replaced `wp_count_posts('stadion_todo')` with prepared SQL queries
   - Both `count_open_todos()` and `count_awaiting_todos()` now filter by `post_author = get_current_user_id()`
   - Custom SQL necessary because `count_user_posts()` doesn't support custom post statuses

3. **WP-CLI Migration Command:**
   - Created `RONDO_Tasks_CLI_Command` class with `verify_ownership` method
   - Supports `--verify` flag (report only, no fixes)
   - Supports `--dry-run` flag (show what would be fixed)
   - Without flags, fixes invalid ownership by inferring from `related_persons` field
   - Registered as `wp stadion tasks verify_ownership`

## Tasks Completed

| Task | Name | Commit | Key Files |
|------|------|--------|-----------|
| 1 | Add author filtering to AccessControl for stadion_todo | 64f9059b | class-access-control.php |
| 2 | Update dashboard todo counts to filter by current user | 2eaf9b6a | class-rest-api.php |
| 3 | Create WP-CLI migration command for task ownership | df684830 | class-wp-cli.php |

## Success Criteria Met

All Phase 139 requirements covered:

- ✅ **TASK-01:** Tasks list filtered by post_author (via AccessControl.filter_queries)
- ✅ **TASK-02:** PersonDetail sidebar filtered (via AccessControl.filter_queries, get_posts respects filters)
- ✅ **TASK-03:** GlobalTodoModal filtered (via AccessControl.filter_rest_query)
- ✅ **TASK-04:** Dashboard open todos count filtered (count_open_todos uses post_author)
- ✅ **TASK-05:** Dashboard awaiting todos count filtered (count_awaiting_todos uses post_author)
- ✅ **MIG-01:** Existing tasks keep their post_author (no migration needed - tasks already have correct author)
- ✅ **MIG-02:** WP-CLI command to verify/fix ownership (verify_ownership command)

## Decisions Made

### Decision: Task Isolation Applies to All Users
**Context:** Should administrators be exempt from task isolation?

**Decision:** No - task isolation applies to all users including admins.

**Rationale:**
- **Simpler implementation:** Single code path, no special cases
- **Consistent UX:** All users have same experience
- **Admin tools available:** Admins can use WP-CLI with `suppress_filters` if needed for debugging
- **Future flexibility:** Easier to add admin view later than remove inconsistent exemption

**Impact:**
- Admins see only their own tasks in UI
- Admins can use `wp stadion tasks verify_ownership` with SSH access to see all tasks
- Frontend doesn't need admin detection logic

### Decision: Direct SQL for Dashboard Counts
**Context:** How to count tasks by author for custom post statuses?

**Decision:** Use direct `$wpdb->get_var()` with prepared queries.

**Alternatives Considered:**
- `count_user_posts()` - Doesn't support custom post statuses
- `WP_Query` with `fields => 'ids'` then count - More overhead than necessary for simple COUNT

**Rationale:**
- Simple COUNT query on indexed columns (post_type, post_status, post_author)
- Prepared statement prevents SQL injection
- More efficient than WP_Query for this use case
- Precedent: Similar approach used elsewhere in codebase

## Technical Implementation Notes

### Author Filtering Pattern

The filtering happens in two places with identical logic:

**WP_Query (filter_queries):**
```php
if ( $post_type === 'stadion_todo' ) {
    $query->set( 'author', get_current_user_id() );
}
```

**REST API (filter_rest_query):**
```php
$current_filter = current_filter();
if ( $current_filter === 'rest_stadion_todo_query' ) {
    $args['author'] = get_current_user_id();
}
```

The REST API filter uses `current_filter()` to detect the post type because the filter hook name is `rest_{post_type}_query`.

### Dashboard Counts Pattern

Both count methods use the same prepared query pattern:

```php
global $wpdb;
return (int) $wpdb->get_var( $wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->posts}
     WHERE post_type = %s
     AND post_status = %s
     AND post_author = %d",
    'stadion_todo',
    $status, // 'stadion_open' or 'stadion_awaiting'
    get_current_user_id()
) );
```

### WP-CLI Command Pattern

The command follows established migration patterns from the codebase:

1. **Verification mode (`--verify`):** Report issues without fixing
2. **Dry-run mode (`--dry-run`):** Show what would be fixed
3. **Fix mode (no flags):** Actually fix invalid ownership
4. **Inference logic:** Uses `related_persons` field to determine correct author
5. **Graceful failure:** Reports tasks that can't be fixed (no related persons, invalid person author)

### Query Flow

The filtering cascade works as follows:

1. **User makes request** (WP_Query or REST API)
2. **Access control checks approval** (`is_user_approved()`)
   - Unapproved users get `post__in = [0]` (show nothing)
3. **Task isolation filter** (new in this phase)
   - For `stadion_todo`, set `author = get_current_user_id()`
4. **VOG filtering** (existing, person post type only)
   - VOG-only users see only current volunteers
5. **Query executes** with all filters applied

This order ensures:
- Unapproved users blocked first (security)
- Task isolation applied before other filters (correctness)
- VOG filtering only affects person queries (specificity)

## Testing Performed

### Local Testing
- ✅ Linting passed (pre-existing warnings in other files)
- ✅ Build succeeded (production assets generated)

### Production Testing
- ✅ Deployment successful (all files synced, caches cleared)
- ✅ WP-CLI command tested: `wp stadion tasks verify_ownership --verify`
- ✅ Command output: "Found 1 task(s) to check. Valid: 1, Invalid: 0"
- ✅ Existing tasks have valid post_author

### Verification Needed (Manual UAT)

The following should be verified with multiple user accounts:

1. **Tasks List Page:**
   - User A creates task → User B should NOT see it
   - User A sees task → User A can edit/delete it

2. **PersonDetail Sidebar:**
   - User A's task on Person X → User B should NOT see it in Person X's sidebar
   - User B creates task on Person X → User B sees it, User A does not

3. **GlobalTodoModal:**
   - User A opens modal → Only sees own tasks
   - User B opens modal → Only sees own tasks

4. **Dashboard Counts:**
   - User A dashboard → Shows count of User A's tasks only
   - User B dashboard → Shows count of User B's tasks only

5. **WP-CLI Command:**
   - Run `wp stadion tasks verify_ownership --verify` periodically to check task ownership
   - If invalid tasks found, run without `--verify` to fix them

## Deviations from Plan

None - plan executed exactly as written.

## Next Phase Readiness

**Phase 140 can proceed immediately.**

The frontend will automatically respect the new filtering because:

1. **React components use standard queries:**
   - `useTodos()` hook calls REST API → `rest_stadion_todo_query` filter applies
   - `PersonDetail` uses `get_posts()` → `pre_get_posts` filter applies
   - Dashboard uses `/rondo/v1/dashboard` → calls updated `count_open_todos()`

2. **No frontend changes required** for basic functionality

3. **Potential frontend enhancements** (Phase 140 can decide):
   - Add "my tasks" vs "all tasks" toggle for admins (future)
   - Show task owner name in task list (clarify who created it)
   - Add task assignment feature (assign tasks to other users)

**Blockers:** None

**Concerns:** None

## Rollback Plan

If issues arise, rollback is safe:

1. **Revert commits:**
   ```bash
   git revert df684830 2eaf9b6a 64f9059b
   ```

2. **Redeploy:**
   ```bash
   bin/deploy.sh
   ```

3. **Effect:**
   - Users will see all tasks again (shared visibility restored)
   - Dashboard counts will show all users' tasks again
   - WP-CLI command will be removed

4. **Data safety:**
   - No data loss (post_author field unchanged)
   - No database migration needed
   - Purely code change

## Performance Impact

**Expected:** Minimal to positive

**Reasons:**
1. **Author filtering is indexed:** WordPress has index on `post_author` column
2. **Smaller result sets:** Queries return fewer rows (only current user's tasks)
3. **Dashboard counts:** Direct COUNT query more efficient than `wp_count_posts()` for filtered data

**Monitoring:**
- Watch for slow queries in production logs
- Compare dashboard load times before/after
- Monitor MySQL slow query log for stadion_todo queries

## Lessons Learned

### What Went Well

1. **Existing patterns applied cleanly:** User isolation pattern from Phase 111 extended easily to tasks
2. **WP-CLI command reused structure:** Migration pattern from other commands saved time
3. **Testing on production worked:** Command verified all tasks have valid ownership

### What Could Be Improved

1. **Documentation of WP-CLI conventions:** Spent time learning WP-CLI uses underscores not hyphens
2. **Earlier production testing:** Could have deployed and tested WP-CLI earlier in development

### Patterns to Reuse

1. **Dual filtering (WP_Query + REST API):** Always filter both paths for complete coverage
2. **current_filter() detection:** Clean way to determine post type in REST API filters
3. **Prepared SQL for custom post statuses:** `count_user_posts()` doesn't work, direct SQL is acceptable
4. **WP-CLI --verify and --dry-run:** Safety pattern for migration commands

---

**Summary created:** 2026-02-04
**Phase 139 Plan 01:** COMPLETE ✅
