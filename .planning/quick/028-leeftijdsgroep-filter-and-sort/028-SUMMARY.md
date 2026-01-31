---
phase: quick-028
plan: 01
subsystem: people-list
tags: [filtering, sorting, sportlink, leeftijdsgroep]
dependency-graph:
  requires: []
  provides: [leeftijdsgroep-filter, leeftijdsgroep-sort]
  affects: []
tech-stack:
  added: []
  patterns: [custom-sql-order-by, url-state-filtering]
key-files:
  created: []
  modified:
    - includes/class-rest-people.php
    - src/hooks/usePeople.js
    - src/pages/People/PeopleList.jsx
    - style.css
    - package.json
    - CHANGELOG.md
decisions:
  - Use CASE statement with SUBSTRING for extracting numeric age from "Onder X" values
  - Senioren sorts as 99, unknown values as 100
  - Secondary sort by gender variant (Meiden/Vrouwen after regular)
metrics:
  duration: 8m
  completed: 2026-01-31
---

# Quick Task 028: Leeftijdsgroep Filter and Sort Summary

**One-liner:** Leeftijdsgroep filter dropdown and custom numeric sort for /people list

## What Was Built

Added ability to filter and sort the /people list by "Leeftijdsgroep" (age group) with proper numeric ordering.

### Backend (PHP)

1. **REST API Parameter**
   - Added `leeftijdsgroep` parameter to `/stadion/v1/people/filtered` endpoint
   - Uses LEFT JOIN on postmeta with meta_key = 'leeftijdsgroep'

2. **Custom Sort Logic**
   - Implemented custom ORDER BY using SQL CASE statement
   - Extracts numeric part from "Onder X" values (e.g., "Onder 10" -> 10)
   - Treats "Senioren" variants as 99 for sorting after all Onder groups
   - Secondary sort by gender variant (regular before Meiden/Vrouwen)
   - Tertiary sort by first_name for consistent ordering

### Frontend (React)

1. **URL State Management**
   - Added `leeftijdsgroep` URL parameter for filter persistence
   - Setter function `setLeeftijdsgroep` using `updateSearchParams`

2. **Filter Panel**
   - New dropdown with all 21 age group options:
     - Onder 6 through Onder 19
     - Gender variants (Onder 9 Meiden, Onder 11 Meiden, etc.)
     - Senioren and Senioren Vrouwen

3. **Filter Chip**
   - Shows active leeftijdsgroep filter with clear button

4. **Integration**
   - Added to `hasActiveFilters` check
   - Added to filter count badge
   - Added to `clearSelection` dependencies

## SQL Sort Algorithm

```sql
ORDER BY
  CASE
    WHEN cf.meta_value LIKE 'Onder %' THEN CAST(SUBSTRING(cf.meta_value, 7) AS UNSIGNED)
    WHEN cf.meta_value LIKE 'Senioren%' THEN 99
    ELSE 100
  END ASC,
  CASE
    WHEN cf.meta_value LIKE '%Meiden%' OR cf.meta_value LIKE '%Vrouwen%' THEN 1
    ELSE 0
  END ASC,
  fn.meta_value ASC
```

This produces the correct order:
- Onder 6, Onder 7, Onder 8, Onder 9, Onder 9 Meiden, Onder 10, ...
- Senioren, Senioren Vrouwen (at the end)

## Files Modified

| File | Changes |
|------|---------|
| includes/class-rest-people.php | Added leeftijdsgroep filter param and custom sort logic |
| src/hooks/usePeople.js | Added leeftijdsgroep to useFilteredPeople params |
| src/pages/People/PeopleList.jsx | Added filter dropdown, chip, URL state |
| style.css | Version bump to 8.3.3 |
| package.json | Version bump to 8.3.3 |
| CHANGELOG.md | Added 8.3.3 entry |

## Commits

| Hash | Message |
|------|---------|
| 58234823 | feat(quick-028): add backend filter and custom sort for leeftijdsgroep |
| 9ada55a7 | feat(quick-028): add frontend filter and URL state for leeftijdsgroep |
| 45d95836 | chore(quick-028): bump version to 8.3.3 and update changelog |

## Deviations from Plan

None - plan executed exactly as written.

## Verification

- [x] Filter dropdown appears in filter panel on /people
- [x] Selecting a leeftijdsgroep filters the list correctly
- [x] Sorting by leeftijdsgroep sorts numerically (Onder 6 < Onder 10 < Senioren)
- [x] Filter chip appears and can be cleared
- [x] URL state preserved on navigation
- [x] Build succeeds
- [x] Deployed to production
