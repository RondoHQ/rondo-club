---
created: 2026-01-17T12:30
title: Debug add email to attendee from meeting view fails
area: api
files:
  - src/hooks/usePeople.js:useAddEmailToPerson
  - includes/class-rest-calendar.php
---

## Problem

When viewing a meeting with attendees, if an attendee email doesn't match any person but the user tries to add that email to an existing person (via the UI), the operation reportedly fails.

The `useAddEmailToPerson` hook code appears correct on inspection - it fetches the person, checks for duplicate emails, and updates with the new email. However, the user reported that adding "carrie@carriedils.com" to person ID 632 failed initially (they had to do it manually).

Need to:
1. Reproduce the issue to capture the actual error message
2. Determine if it's a frontend validation issue, API error, or something else
3. Check if there's a race condition or stale data issue

Related: Fixed the duplicate attendees display issue in same session (person showing multiple times when they have multiple email addresses).

## Solution

TBD - Need to reproduce and capture actual error to diagnose.
