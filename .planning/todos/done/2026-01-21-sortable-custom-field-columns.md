---
created: 2026-01-21T14:30
title: Make custom field columns sortable
area: ui
files:
  - src/pages/Companies/CompaniesList.jsx
  - src/pages/People/PeopleList.jsx
---

## Problem

Custom field columns in the company and people list views cannot currently be sorted. Standard columns (Name, Industry, etc.) support sorting, but user-defined custom fields added via ACF do not have sorting functionality.

## Solution

TBD - Need to:
1. Determine how sorting works for standard columns
2. Extend sorting logic to handle custom field meta values
3. Consider data type (text, number, date) for proper sort order
4. May need backend support for meta_query ordering
