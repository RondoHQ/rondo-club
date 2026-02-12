---
status: investigating
trigger: "missing-functies-person-757"
created: 2026-02-12T10:00:00Z
updated: 2026-02-12T11:00:00Z
---

## Current Focus

hypothesis: Commissie sync has chicken-and-egg problem - orphan detection runs BEFORE tracking table is updated, causing all commissies to be deleted
test: Check the order of operations in sync-functions.js pipeline
expecting: Confirms that getAllCommissies is called before commissie sync completes
next_action: Confirm root cause and prepare fix

## Symptoms

expected: Person 757 should display multiple functies (functions/roles) beyond just team roles — things like bestuursfuncties, commissie functies, etc.
actual: Only team roles are visible on the person detail page at https://rondo.svawc.nl/people/757
errors: None reported
reproduction: Visit https://rondo.svawc.nl/people/757 and check the functies/roles section
started: Unknown when this started — may have always been the case or may be a regression

## Eliminated

## Evidence

- timestamp: 2026-02-12T10:15:00Z
  checked: Production WordPress person 757 meta
  found: work_history has 2 entries, both team roles (Teammanager)
  implication: Team sync is working, but commissie/function data is missing

- timestamp: 2026-02-12T10:20:00Z
  checked: Sync server sportlink_member_functions and sportlink_member_committees tables
  found: Person SYQG014 has 9 member functions and 3 committee memberships
  implication: The source data exists on sync server but is not being synced to WordPress

- timestamp: 2026-02-12T10:22:00Z
  checked: rondo_club_commissie_work_history tracking table for SYQG014
  found: No entries in tracking table
  implication: Commissie work history sync has never run for this person, or the data was never upserted to tracking

- timestamp: 2026-02-12T10:40:00Z
  checked: rondo_club_commissies table on sync server
  found: Table is completely empty (0 rows)
  implication: Commissie sync is not populating the tracking table

- timestamp: 2026-02-12T10:42:00Z
  checked: WordPress commissie post type
  found: 0 commissies exist in WordPress
  implication: Commissie sync created 18 but then deleted them all (possibly as orphans)

- timestamp: 2026-02-12T10:43:00Z
  checked: Sync log output
  found: "Commissies synced: 18/18, Created: 18, Deleted: 48 (orphan commissies)"
  implication: Commissies are being created and then immediately deleted as orphans

- timestamp: 2026-02-12T10:50:00Z
  checked: sync-functions.js pipeline code (lines 186-192)
  found: getAllCommissies(db) called BEFORE commissie sync runs to get currentCommissieNames for orphan detection
  implication: Tracking table is consulted before it's updated by sync

- timestamp: 2026-02-12T10:55:00Z
  checked: getOrphanCommissies function (rondo-club-db.js lines 2163-2170)
  found: If currentNames array is empty, returns ALL commissies from tracking table as orphans
  implication: With empty tracking table, all newly-created commissies are immediately marked as orphans

- timestamp: 2026-02-12T11:00:00Z
  checked: Full execution flow
  found: 
    1. Pipeline calls getAllCommissies() → returns []
    2. Passes [] as currentCommissieNames to commissie sync
    3. Commissie sync creates 18 commissies in WordPress
    4. Commissie sync updates tracking table with 18 entries
    5. Commissie sync calls getOrphanCommissies(db, [])
    6. getOrphanCommissies returns ALL 18 (just created) as orphans because currentNames is empty
    7. Commissie sync deletes all 18 commissies
    8. Result: 0 commissies exist, work history sync has nothing to link to
  implication: ROOT CAUSE CONFIRMED - chicken-and-egg timing issue in orphan detection

## Resolution

root_cause: Commissie orphan detection uses stale tracking data (fetched before sync) instead of fresh data (after sync updates tracking table). When tracking table is empty, getAllCommissies returns [], causing getOrphanCommissies to treat ALL commissies as orphans and delete them immediately after creation. This leaves no commissies in WordPress, so commissie work history sync cannot link functies to any commissies, resulting in missing functies display.

fix:
verification:
files_changed: []
