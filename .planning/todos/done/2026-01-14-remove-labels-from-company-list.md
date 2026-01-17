---
created: 2026-01-14T20:18
title: Remove labels from company list
area: ui
files:
  - src/pages/Organizations.jsx
---

## Problem

The company/organizations list displays a labels column, but labels for companies aren't actually supported and aren't needed. This creates a confusing UI with an empty or non-functional column.

## Solution

Remove the labels column from the organizations list view. This simplifies the UI and removes functionality that isn't implemented or needed.
