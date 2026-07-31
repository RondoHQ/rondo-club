---
created: 2026-07-10T00:00:00.000Z
title: Scoped Kaderlijst endpoint, then remove suppress_age_group
area: access-control
files:
  - src/pages/Teams/Kaderlijst.jsx
  - includes/class-access-control.php
  - includes/class-rest-people.php
---

## Problem

`suppress_age_group` is the last place a coordinator can widen their view to the entire club. It was
tightened in v33.28.2 so a plain member can no longer abuse it, but it still, by design, lets a
coordinator (a user with a non-empty `rondo_age_group_access` list) fetch every person — because
`Kaderlijst.jsx` pulls *all* people with `_fields=id,acf` across every page and filters client-side.

Four trusted accounts, so this is low urgency, but it is the reason the flag cannot simply be
deleted, and it leaves a full-club read reachable from a scoped account.

## Solution

1. A dedicated endpoint that returns only people with a current `work_history` functie (the kader),
   with only the fields Kaderlijst renders — name, functie, team, photo, contact.
2. Enforce visibility server-side on that endpoint via `AccessControl::can_view_person()` /
   `person_scope()` like every other person surface. No `suppress_age_group`.
3. Point `Kaderlijst.jsx` at the new endpoint; drop the client-side full-club fetch.
4. Delete `suppress_age_group` entirely — the query var, `can_suppress_age_group()`, the branches in
   `filter_rest_query()` / `apply_age_group_filter()` / the raw-SQL people endpoint, and the
   `AgeGroupAccessTest` cases that assert it. Grep for `suppress_age_group` to be exhaustive.

## Notes

- Verify on production afterwards, the same way as v33.28.2: as a coordinator, confirm the kader list
  still renders AND that no path returns the full 4,095 people.
- `AgeGroupAccessTest` currently has coverage asserting the flag works for coordinators — those tests
  invert once the flag is gone; update, don't just delete.
- Tracked in `docs/prd/member-login-rollout.md` as work item 12.
