# UAT Issues: Phase 31 Plan 01

**Tested:** 2026-01-14
**Source:** .planning/phases/31-person-image-polish/31-01-SUMMARY.md
**Tester:** User via /gsd:verify-work

## Open Issues

### UAT-001: Timeline endpoint returns wrong todo status/query

**Discovered:** 2026-01-14
**Phase/Plan:** 31-01
**Severity:** Blocker
**Feature:** Mobile todos FAB / Todos sidebar
**Description:** The timeline endpoint (`/prm/v1/people/{id}/timeline`) queries todos with `post_status => 'publish'` but todos use custom post statuses (prm_open, prm_awaiting, prm_completed). Also returns deprecated `is_completed` field instead of `status` field expected by frontend.
**Expected:** Timeline should query all todo statuses and return `status: 'open'|'awaiting'|'completed'` field
**Actual:** No todos returned because query uses wrong status; frontend expects `status` field but receives `is_completed`
**Repro:**
1. Navigate to any person with todos
2. Check sidebar (desktop) or FAB (mobile)
3. No todos shown despite person having todos

**Root cause:** Timeline endpoint in `includes/class-comment-types.php:454-477` wasn't updated when Phase 28 migrated todos to WordPress post statuses.

**Fix location:** `includes/class-comment-types.php`, `get_timeline()` method, lines 454-477

## Resolved Issues

[None yet]

---

*Phase: 31-person-image-polish*
*Plan: 01*
*Tested: 2026-01-14*
