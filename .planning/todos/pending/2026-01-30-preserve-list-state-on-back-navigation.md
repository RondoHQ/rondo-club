---
created: 2026-01-30T11:20
title: Preserve list state on browser back navigation
area: ui
files:
  - src/pages/PeopleList.jsx
  - src/pages/TeamsList.jsx
  - src/pages/CommissiesList.jsx
  - src/App.jsx
---

## Problem

When navigating from a list view (People, Teams, Commissies) to a detail page and then hitting the browser back button, the list view resets to its initial state. This loses:

- Current scroll position
- Applied filters
- Search query
- Sort order
- Selected columns
- Pagination position

Users expect the list to return to exactly where they left off.

## Solution

Options to preserve state on back navigation:

1. **URL state**: Store filters/sort/page in URL query params so back button restores them
2. **Session storage**: Cache list state in sessionStorage keyed by route
3. **React Router scroll restoration**: Use ScrollRestoration component for scroll position
4. **TanStack Query cache**: Query results are cached, but UI state (filters, scroll) needs separate handling

URL state is most robust as it also enables shareable filtered views.
