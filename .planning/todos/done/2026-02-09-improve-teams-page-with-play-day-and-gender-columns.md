---
created: 2026-02-09T20:11:07.540Z
title: Improve teams page with play day and gender columns
area: ui
files:
  - src/pages/Teams/TeamList.jsx
  - includes/class-post-types.php
---

## Problem

The teams page currently lacks useful information at a glance. Users need to see what day each team plays and the gender category directly in the list view, without opening each team. The filter on the teams page should also be simplified to only filter on these two new columns (play day and gender), removing any other filter options.

## Solution

1. Add "play day" and "gender" columns to the teams list table
2. Ensure the data is available from ACF fields on team posts (or add fields if missing)
3. Update the filter to work exclusively on play day and gender — remove other filter criteria
4. Expose the new fields via REST API if not already available
