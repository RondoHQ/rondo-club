---
created: 2026-01-13T20:56
title: Important dates card not updating on edit
area: ui
files:
  - src/pages/People/PersonDetail.jsx
---

## Problem

When editing an important date on the Person Detail page, the Important Dates card does not update to reflect the changes. The user has to refresh the page to see the updated date information.

This is likely a React Query cache invalidation issue - the mutation that updates the important date is not triggering a refetch of the important dates data displayed in the card.

## Solution

TBD - Investigate React Query invalidation for important dates queries after mutation. May need to add `queryClient.invalidateQueries` call after successful date update.
