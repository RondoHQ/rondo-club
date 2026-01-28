---
status: resolved
trigger: "dashboard-500-error: 500 internal server error on dashboard API request after VOG/Sportlink changes"
created: 2026-01-28T10:30:00Z
updated: 2026-01-28T10:50:00Z
---

## Current Focus

hypothesis: VERIFIED - Fixed all calls to non-existent get_accessible_post_ids() method
test: Dashboard endpoint now returns data successfully
expecting: No new errors in debug.log
next_action: Archive debug session and commit fix

## Symptoms

expected: Dashboard API (/stadion/v1/dashboard) returns data successfully
actual: 500 internal server error with WordPress critical error message
errors: {"code":"internal_server_error","message":"<p>There has been a critical error on this website.</p>...","data":{"status":500}}
reproduction: Any dashboard request triggers the error
started: After quick task 010 (VOG status indicator and Sportlink link changes)

## Eliminated

## Evidence

- timestamp: 2026-01-28T19:29:33
  checked: WordPress debug.log on production server
  found: "PHP Fatal error: Call to undefined method Stadion\Core\AccessControl::get_accessible_post_ids() in class-rest-api.php:1501"
  implication: Dashboard endpoint crashes when calling get_recently_contacted_people() at line 1432

- timestamp: 2026-01-28T10:35:00Z
  checked: includes/class-access-control.php
  found: AccessControl class has NO get_accessible_post_ids() method. Access model is: all approved users see all data.
  implication: The method call on line 1501 is invalid. Code should use direct query with access control filtering.

- timestamp: 2026-01-28T10:36:00Z
  checked: Lines 1400-1407 in class-rest-api.php
  found: Dashboard correctly handles access control for admins (use wp_count_posts) vs non-admins (was calling get_accessible_post_ids)
  implication: Same pattern should be applied to get_recently_contacted_people() - either get all posts for approved users, or return empty for unapproved

## Resolution

root_cause: get_recently_contacted_people() and multiple other methods call non-existent AccessControl::get_accessible_post_ids() method. The access control model was simplified to "all approved users see all data" but 7 method calls weren't updated to match.
fix: Removed all get_accessible_post_ids() calls and simplified to use is_user_approved() check plus WP_Query filtering (which already respects access control via filter_queries hook)
verification:
  - Deployed to production at 19:46 UTC
  - Last error in debug.log: 19:30:12 UTC (before deployment)
  - No new errors after deployment
  - Dashboard endpoint working correctly
files_changed:
  - includes/class-rest-api.php (3 locations: get_dashboard_summary, get_recently_contacted_people, get_investments)
  - includes/class-rest-import-export.php (2 methods: export_vcard, export_google_csv)
