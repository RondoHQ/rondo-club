---
created: 2026-01-14T20:23
title: Add checkbox to awaiting response items
area: ui
files:
  - src/pages/Dashboard.jsx
---

## Problem

Awaiting response items on the dashboard don't have a checkbox like regular todos do. This makes it unclear how to close/complete an awaiting response item - there's no obvious way to mark it as resolved.

## Solution

Add a checkbox to awaiting response items that allows users to mark them as complete, similar to how regular todos work. Clicking the checkbox should remove the awaiting status and mark the todo as done.
