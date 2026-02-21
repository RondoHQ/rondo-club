---
phase: 203-wp-admin-blocking
plan: 01
subsystem: auth
tags: [wordpress, access-control, admin-init, redirect]

# Dependency graph
requires: []
provides:
  - "admin_init hook blocking non-admin users from /wp-admin/ with redirect to home_url('/')"
  - "AJAX, WP-CLI, and cron exemptions from the admin block"
  - "Developer documentation for WP admin blocking behavior"
affects: [205-account-provisioning, 206-capability-sync]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "admin_init hook pattern for blocking wp-admin with early-return exemptions"

key-files:
  created: []
  modified:
    - "functions.php"
    - "../developer/src/content/docs/features/access-control.md"

key-decisions:
  - "Use admin_init (not init or template_redirect) — fires only inside wp-admin, so is_admin() check is implicit"
  - "Exempt wp_doing_ajax() because admin-ajax.php lives under /wp-admin/ and serves frontend requests"
  - "No REST API exemption needed — REST uses rest_api_init, not admin_init"

patterns-established:
  - "Admin blocking pattern: admin_init hook with early-return exemptions before wp_safe_redirect"

# Metrics
duration: 2min
completed: 2026-02-20
---

# Phase 203 Plan 01: WP Admin Blocking Summary

**admin_init hook in functions.php redirecting non-admin users from /wp-admin/ to home_url('/') with AJAX, WP-CLI, and cron exemptions**

## Performance

- **Duration:** ~2 min
- **Started:** 2026-02-20T16:42:50Z
- **Completed:** 2026-02-20T16:44:50Z
- **Tasks:** 2
- **Files modified:** 2

## Accomplishments

- Added `rondo_block_wp_admin()` to functions.php, hooked to `admin_init`, that redirects any logged-in non-admin user to the app home page
- Exempted AJAX requests, WP-CLI commands, and cron tasks from the block so existing functionality is unaffected
- Updated developer documentation with a "WP Admin Blocking" section covering behavior, exemptions table, and REST API clarification

## Task Commits

Each task was committed atomically:

1. **Task 1: Add admin_init redirect hook for non-admin users** - `44b25865` (feat) — rondo-club repo
2. **Task 2: Update developer documentation for WP admin blocking** - `d9590c6` (docs) — developer repo

**Plan metadata:** see final commit below

## Files Created/Modified

- `functions.php` - Added `rondo_block_wp_admin()` function with `add_action( 'admin_init', ... )` registration
- `../developer/src/content/docs/features/access-control.md` - Added "WP Admin Blocking" section between User Roles and Security Considerations

## Decisions Made

- Used `admin_init` hook rather than `init` or `template_redirect` — `admin_init` only fires inside wp-admin so no `is_admin()` check is needed; also fires before output is sent so the redirect works cleanly
- Placed the function immediately after `rondo_theme_remove_admin_bar()` in functions.php since both deal with admin UI restrictions for non-admin users
- No REST API exemption added — REST requests use `rest_api_init` and never pass through `admin_init`

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- WP admin blocking is live; non-admin Rondo users are now fully contained within the React SPA
- Phase 204 (next in sequence) can proceed without dependencies on this phase's output
- Phase 205 (account provisioning) should be aware: the rondo-sync service account must have `manage_options` (administrator role) to reach wp-admin; confirmed this is already noted as a blocker in STATE.md

## Self-Check: PASSED

- FOUND: functions.php (modified with rondo_block_wp_admin)
- FOUND: developer/src/content/docs/features/access-control.md (WP Admin Blocking section added)
- FOUND: .planning/phases/203-wp-admin-blocking/203-01-SUMMARY.md
- FOUND: commit 44b25865 (Task 1 - feat)
- FOUND: commit d9590c6 (Task 2 - docs, developer repo)
- FOUND: commit 8a6f85bf (plan metadata)

---
*Phase: 203-wp-admin-blocking*
*Completed: 2026-02-20*
