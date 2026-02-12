---
created: 2026-02-11T22:49:56.689Z
title: Fix commissie orphan detection deleting freshly-created commissies
area: sync
files:
  - ../rondo-sync/steps/submit-rondo-club-commissies.js:188-250
  - ../rondo-sync/pipelines/sync-functions.js:186-192
  - ../rondo-sync/lib/rondo-club-db.js:2130-2148
---

## Problem

When `sync-functions --all` runs, the commissie sync step (step 2) creates 30 commissies in WordPress and in the `rondo_club_commissies` tracking table, but then immediately deletes all 30 as "orphans". The sync log shows:

```
Commissies synced: 30/30
  Created: 30
  Deleted: 30 (orphan commissies)
```

This leaves `rondo_club_commissies` empty, which causes the subsequent commissie work history sync (step 3) to bail out at line 286-289 of `submit-rondo-club-commissie-work-history.js` ("No commissies found"), resulting in 0 work history entries being pushed to WordPress.

The root cause is in the orphan detection logic in `submit-rondo-club-commissies.js`. The pipeline at lines 186-192 of `sync-functions.js` reads `currentCommissieNames` from the DB and passes them to `runCommissiesSync()`. Inside `runCommissiesSync()`:

1. Lines 156-186: Creates 30 commissies in WordPress and updates tracking DB
2. Lines 188-221: Orphan detection — calls `getOrphanCommissies(db, currentCommissieNames)` which should find 0 orphans since the names match. Yet the log shows 30 were deleted.
3. Lines 223-250: "Untracked WordPress commissies" cleanup — deletes WordPress commissies not in the tracking DB.

Possible causes:
- The `getOrphanCommissies` function (rondo-club-db.js:2130-2148) may behave unexpectedly when `currentNames` is empty or matches all entries
- There may be a race condition or DB connection issue between the pipeline reading names and the sync step modifying the same table
- The newly created commissie entries may have been deleted by the orphan logic if a name mismatch occurred (e.g., encoding differences)

## Solution

1. Add debug logging to `submit-rondo-club-commissies.js` to trace exactly which commissies are identified as orphans and why
2. Verify that `getOrphanCommissies` correctly handles the case where all DB entries match `currentNames`
3. Consider adding a safety check: if all commissies would be deleted as orphans, that's likely a bug — skip the deletion
4. The "untracked WordPress commissies" cleanup (lines 223-250) should also be reviewed — it may be deleting commissies that were manually created in WordPress
5. Fix is in rondo-sync repo, not rondo-club
