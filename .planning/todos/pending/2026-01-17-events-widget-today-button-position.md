---
created: 2026-01-17T14:15
title: Move Today button left of navigation arrows
area: ui
files:
  - src/pages/Dashboard.jsx
---

## Problem

On the Events widget (meetings widget), the "Today" button is positioned between the back and forward navigation arrows. User preference is to have "Today" on the left, then the navigation arrows together.

Current order: `< Today >`
Desired order: `Today < >`

This is a small UX polish to make the navigation controls feel more natural.

## Solution

In Dashboard.jsx, reorder the date navigation buttons in the Events card header from `[ChevronLeft] [Today] [ChevronRight]` to `[Today] [ChevronLeft] [ChevronRight]`.
