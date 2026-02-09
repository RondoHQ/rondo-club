---
phase: 166-backend-foundation
plan: 01
subsystem: database
tags: [acf, rest-api, wordpress, field-management, sync]

# Dependency graph
requires:
  - phase: none (foundation work)
    provides: baseline ACF person fields
provides:
  - former_member boolean field on person records
  - REST API exposure of former_member at acf.former_member
  - rondo-sync marks removed members as former instead of deleting
affects: [167-filtering, 168-ui, future-reporting]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - ACF field groups for WordPress custom post types
    - rondo-sync marking pattern (PUT with status field instead of DELETE)

key-files:
  created: []
  modified:
    - acf-json/group_person_fields.json
    - ../rondo-sync/steps/prepare-rondo-club-members.js
    - ../rondo-sync/steps/submit-rondo-club-sync.js
    - ../developer/src/content/docs/api/people.md

key-decisions:
  - "Mark former members with PUT instead of DELETE to preserve history"
  - "Keep former members in tracking DB to detect rejoining"
  - "Set readonly=1 on field (system-managed by sync, not manual)"

patterns-established:
  - "Former member marking: PUT with acf.former_member: true, keep in tracking DB"
  - "Active members explicitly set former_member: false on every sync"

# Metrics
duration: 4min
completed: 2026-02-09
---

# Phase 166 Plan 01: Backend Foundation Summary

**Added former_member boolean field to person records, exposed via REST API, and updated rondo-sync to mark removed members as former instead of deleting them**

## Performance

- **Duration:** 3 min 49 sec
- **Started:** 2026-02-09T19:35:57Z
- **Completed:** 2026-02-09T19:39:46Z
- **Tasks:** 3
- **Files modified:** 4 across 3 repositories

## Accomplishments

- ACF field `former_member` added to person records with default value false
- Field automatically exposed via WordPress REST API at `acf.former_member`
- rondo-sync updated to mark removed Sportlink members as former (PUT with `acf.former_member: true`) instead of deleting them
- Former members kept in tracking database to detect if they rejoin
- Active members explicitly set to `former_member: false` during sync
- API documentation updated with field reference, usage examples, and curl commands

## Task Commits

Each task was committed atomically:

1. **Task 1: Add former_member ACF field to person records** - `039fc548` (feat) - rondo-club
2. **Task 2: Update rondo-sync to mark former members instead of deleting** - `20e910a` (feat) - rondo-sync
3. **Task 3: Update API documentation for former_member field** - `63c29ae` (docs) - developer

## Files Created/Modified

**rondo-club:**
- `acf-json/group_person_fields.json` - Added former_member true_false field with readonly=1, UI toggle, default 0

**rondo-sync:**
- `steps/prepare-rondo-club-members.js` - Active members explicitly set acf.former_member = false
- `steps/submit-rondo-club-sync.js` - Renamed deleteRemovedMembers to markFormerMembers, sends PUT instead of DELETE, keeps members in tracking DB

**developer:**
- `src/content/docs/api/people.md` - Added Membership Status section, documented former_member field, added curl example, updated response examples

## Decisions Made

1. **Marking instead of deleting** - Former members are marked with `former_member: true` instead of being deleted from WordPress. This preserves member history and allows the UI to show/hide former members in later phases.

2. **Keep in tracking DB** - Former members remain in the rondo-sync tracking database (not deleted via `deleteMember()`). This allows detecting if they rejoin the club later and automatically setting `former_member: false`.

3. **Readonly field** - Set `readonly: 1` on the ACF field so it appears in WordPress admin but cannot be manually changed. The field is system-managed by rondo-sync only.

4. **Explicit active status** - Active members being synced are explicitly set to `former_member: false` in the prepare step. This ensures all synced members have a clear status (not null/undefined).

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None - all tasks completed without issues. Field registration, REST API exposure, and cross-repo changes worked as expected.

## User Setup Required

None - no external service configuration required. Changes are automatic once rondo-sync runs on production.

## Next Phase Readiness

✅ Data foundation complete for v23.0 Former Members milestone
✅ REST API can read/write former_member field
✅ rondo-sync will mark removed members on next run
✅ Ready for Phase 167 (filtering) and Phase 168 (UI)

**Next steps:**
- Phase 167: Add filtering to exclude former members from default views
- Phase 168: Add "Former Members" toggle/filter in UI
- Phase 169: Test end-to-end workflow with real Sportlink data

## Self-Check: PASSED

All claimed files exist:
- ✓ acf-json/group_person_fields.json
- ✓ ../rondo-sync/steps/prepare-rondo-club-members.js
- ✓ ../rondo-sync/steps/submit-rondo-club-sync.js
- ✓ ../developer/src/content/docs/api/people.md

All claimed commits exist:
- ✓ 039fc548 (rondo-club)
- ✓ 20e910a (rondo-sync)
- ✓ 63c29ae (developer)

---
*Phase: 166-backend-foundation*
*Completed: 2026-02-09*
