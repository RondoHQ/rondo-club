# UAT Issues: Phase 31 Plan 01

**Tested:** 2026-01-14
**Source:** .planning/phases/31-person-image-polish/31-01-SUMMARY.md
**Tester:** User via /gsd:verify-work

## Open Issues

[None]

## Resolved Issues

### UAT-001: Timeline endpoint returns wrong todo status/query

**Discovered:** 2026-01-14
**Resolved:** 2026-01-14 - Fixed during UAT session
**Commit:** b91def4
**Phase/Plan:** 31-01
**Severity:** Blocker
**Feature:** Mobile todos FAB / Todos sidebar
**Description:** The timeline endpoint (`/stadion/v1/people/{id}/timeline`) queries todos with `post_status => 'publish'` but todos use custom post statuses (stadion_open, stadion_awaiting, stadion_completed). Also returns deprecated `is_completed` field instead of `status` field expected by frontend.
**Fix:** Updated `get_timeline()` method to query all todo statuses and return proper `status` field + `awaiting_since`.

---

*Phase: 31-person-image-polish*
*Plan: 01*
*Tested: 2026-01-14*
