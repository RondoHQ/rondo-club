---
created: 2026-07-10T00:00:00.000Z
title: Data-quality screen for member activation gaps
area: users
files:
  - includes/class-rest-users.php
  - includes/class-volunteer-eligibility-service.php
  - src/pages/Settings/
---

## Problem

The self-service activation flow (`/activeren`, shipped v33.33.0) can only reach members who have a
valid email address on file, and volunteer obligations can only be fulfilled where a youth player is
linked to a parent. Two groups fall through, and the ledenadministratie currently has no way to see
who they are:

- **~56 active members with no email address** (`email_1` and `email_2` both empty/invalid). They
  cannot activate an account at all until someone collects an address.
- **27 orphan gezinnen** — a JO16- player with no parent in `relationships` and no adult housemate,
  so nobody can be held responsible for the family's obligation. Already counted in
  `VolunteerEligibilityService::get_eligibility_view()['diagnostics']['gezinnen_orphan']`, but not
  surfaced as a list of *which* players.

These are data-collection tasks (chase the member), not code bugs. The work here is to make the
"who" visible and exportable so the chasing can happen — ideally before the activation flow is
announced club-wide.

## Solution

1. A REST endpoint (admin / ledenadministratie) returning two lists:
   - active, non-former persons with no valid email — name, KNVB-ID, team/leeftijdsgroep
   - the trigger youth players behind each orphan gezin unit — name, leeftijdsgroep, address (so the
     admin can spot the likely parent to link)
2. A screen under Settings (or Leden) rendering both, with a CSV export — reuse the DataTable +
   export pattern already used on `/people`.
3. Numbers to reconcile against at build time (production, 2026-07-10): 56 no-email, 27 orphan
   gezinnen. Recompute live rather than hard-coding.

## Notes

- Orphan data already exists in the eligibility view's `diagnostics`; the missing piece is exposing
  the per-unit `trigger_person_ids`, not recomputing anything.
- Respect the finance read/write capability split precedent (v33.35.0): this is a read-only screen,
  so gate on a *read* capability, not a write/manage one.
- Do NOT trigger any member-facing side effect from this screen — it is a report. Any "email these
  people" action is a separate, explicitly-approved task with `override_email`.
- Tracked in `docs/prd/member-login-rollout.md` as work item 10.
