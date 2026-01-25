---
created: 2026-01-14T20:18
title: Remove labels from team list
area: ui
files:
  - src/pages/Teams.jsx
---

## Problem

The team/teams list displays a labels column, but labels for teams aren't actually supported and aren't needed. This creates a confusing UI with an empty or non-functional column.

## Solution

Remove the labels column from the teams list view. This simplifies the UI and removes functionality that isn't implemented or needed.
