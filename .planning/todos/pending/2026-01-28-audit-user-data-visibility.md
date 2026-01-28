---
created: 2026-01-28T19:48
title: Audit user data visibility - all users should see all data
area: api
files:
  - includes/class-access-control.php
  - docs/architecture.md
---

## Problem

Currently the system may still have per-user data filtering where users only see posts they created. The requirement has changed: every user with access should see ALL data, not just their own.

Need to audit:
1. Is `STADION_Access_Control` still filtering data per-user?
2. Are there any REST API level filters restricting visibility?
3. Is the documentation updated to reflect shared visibility?
4. Are there UI elements still referencing "your" data vs shared data?

## Solution

1. Review `includes/class-access-control.php` - may need to remove or simplify user-based filtering
2. Check REST API responses for any user-specific filtering
3. Update docs if they still describe per-user isolation
4. Search codebase for remnants of per-user visibility logic
