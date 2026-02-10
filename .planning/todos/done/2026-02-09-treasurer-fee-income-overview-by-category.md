---
created: 2026-02-09T13:28
title: Treasurer fee income overview by category
area: ui
files:
  - src/pages/Contributie/ContributieList.jsx
  - src/pages/Settings/FeeCategorySettings.jsx
  - includes/class-rest-api.php
---

## Problem

The treasurer needs a high-level overview of fee income aggregated by category (age group), not just the per-member breakdown that the current Contributie page shows. They want to see totals like "Senioren: 45 members, EUR X total" for budget planning.

Currently the only view is the per-member contributie list, which is useful for individual fee checking but doesn't give the treasurer a quick financial summary.

## Solution

1. **Create a Treasurer section** in the sidebar navigation (or restructure Contributie):
   - Default tab: **Overview** — fee income summary per category for current season
   - Second tab: **Next season** — same overview using forecast data
   - Third tab: **Per lid** — the existing per-member contributie list (moved here)

2. **Overview page** should show:
   - Total members per fee category
   - Total fee income per category
   - Grand total
   - Current season as default, next season as tab

3. **Future enhancement** (depends on soft-delete for inactive members):
   - "Lost fee income" report showing members who left and what fee income that represents
   - Blocked by: `soft-delete-inactive-members` todo must be done first
