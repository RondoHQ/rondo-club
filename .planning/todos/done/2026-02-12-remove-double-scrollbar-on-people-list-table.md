---
created: 2026-02-12T15:44:40.019Z
title: Remove double scrollbar on people list table
area: ui
files:
  - src/pages/People/PeopleList.jsx
---

## Problem

The people list page has two scrollbars: one on the table itself (horizontal/vertical overflow) and one on the page body that allows scrolling to the pagination controls below the table. This creates a confusing UX where users have to scroll the table AND then scroll the page to reach pagination.

## Solution

Remove the overflow/scroll on the table container so the table flows naturally within the page. Pagination should be visible by scrolling the page itself, not hidden behind a separate scroll context. Likely involves removing `overflow-auto` or similar from the table wrapper in PeopleList.jsx.
