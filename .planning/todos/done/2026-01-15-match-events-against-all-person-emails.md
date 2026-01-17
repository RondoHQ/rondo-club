---
created: 2026-01-15T14:26
title: Match events against all person email addresses
area: api
files:
  - includes/class-calendar-sync.php
---

## Problem

Currently, event/meeting matching may only check against a person's primary email address. A person can have multiple email addresses (work, personal, etc.), and the matching logic should check all of them when linking calendar events to people.

Example scenario:
1. Person has work email work@company.com and personal email personal@gmail.com
2. Calendar event has attendee personal@gmail.com
3. Matching should find this person even though personal@gmail.com isn't their primary email

## Solution

TBD - Update the event matching logic in calendar sync to:
1. Retrieve all email addresses for each person (from ACF repeater field)
2. Build a lookup map of email → person_id for all emails
3. Match event attendees against this complete email list
4. Consider caching the email lookup for performance during bulk sync
