---
created: 2026-01-14T20:17
title: Make team website link clickable in list
area: ui
files:
  - src/pages/Teams.jsx
---

## Problem

In the teams/teams list view, the website field is displayed as plain text. Users cannot click it to open the website directly.

## Solution

Wrap the website URL in an anchor tag (`<a>`) with `target="_blank"` and `rel="noopener noreferrer"` to make it clickable and open in a new tab.
