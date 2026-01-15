---
created: 2026-01-15T14:23
title: Re-run meetings matching when email address added
area: api
files:
  - includes/class-post-types.php
  - includes/class-calendar-sync.php
---

## Problem

When a user adds an email address to a person, existing calendar meetings that have that email as an attendee should be linked to that person. Currently, meeting-to-person matching only happens during calendar sync, so previously imported meetings won't be associated with the person until the next sync.

Example scenario:
1. Calendar syncs and imports a meeting with attendee@example.com
2. Meeting is not linked to any person (email not in system)
3. User adds attendee@example.com to an existing person
4. Meeting should now be linked to that person (but currently isn't until next sync)

## Solution

TBD - Hook into the person save/update action and trigger a re-matching of meetings when email addresses change:
1. Detect when email addresses are added to a person
2. Query calendar meetings for matching attendee emails
3. Link matching meetings to the person
4. Consider performance implications for users with many meetings
