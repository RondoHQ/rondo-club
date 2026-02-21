---
created: 2026-02-21T13:01:02.099Z
title: Unify table filter UX across all list pages
area: ui
files:
  - src/pages/People/PeopleList.jsx
  - src/pages/VOG/VOGList.jsx
  - src/pages/Tuchtzaken/TuchtzakenList.jsx
  - src/pages/Contributie/ContributieList.jsx
---

## Problem

Every table with filters works slightly differently — /people, /vog, /tuchtzaken, and the finance/contributie pages each have their own filter UI and behavior. This is inconsistent UX and some tables don't support filtering on all columns. Users have to learn different interaction patterns per page.

## Solution

1. Audit all list pages to document what filters each has and what's missing
2. Design a unified filter bar / column filter pattern that works for all tables
3. Ensure every column that can be filtered on actually has a filter control
4. Implement consistently across all list pages (People, VOG, Tuchtzaken, Finance/Contributie)
5. Consider a shared `<FilterBar>` or `useTableFilters` hook to avoid duplication (DRY)
