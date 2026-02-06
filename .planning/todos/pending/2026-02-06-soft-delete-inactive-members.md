---
created: 2026-02-06T09:10
title: Soft-delete inactive members instead of hard delete
area: data-model
files:
  - includes/class-post-types.php
  - includes/class-rest-people.php
  - src/pages/PeopleList.jsx
  - src/pages/ContributieList.jsx
  - src/pages/TeamDetail.jsx
  - src/pages/CommissieDetail.jsx
---

## Problem

Currently, when members leave the club, they are deleted entirely. This loses valuable historical data:
- Relationship history with other members
- Work history (roles, positions held)
- Important dates associated with them
- VOG history and other compliance records

We need a way to mark members as "inactive" or "departed" while retaining their data for historical purposes.

## Solution

TBD - Options to consider:

1. **Custom post status** (e.g., `inactive`, `departed`)
   - Pro: WordPress-native, works with WP_Query
   - Con: May require custom admin UI, default queries exclude non-publish

2. **ACF field** (e.g., `member_status` or `is_active`)
   - Pro: Easy to implement, flexible
   - Con: Requires meta query in all list views

**Regardless of approach, need to:**
- End all active `work_history` entries (set end date)
- Exclude from:
  - Member fees/contributie tables
  - Team member lists
  - Commissie member lists
  - Important dates (or show with "departed" indicator)
- Keep visible in:
  - Person detail page (with clear "inactive" status)
  - Search results (optional, with filter)
  - Relationship views (historical context)
  - Reports/exports (with status column)
