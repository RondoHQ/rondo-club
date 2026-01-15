---
created: 2026-01-15T15:46
title: Soften PersonDetail delete button style
area: ui
files:
  - src/pages/PersonDetail.jsx
---

## Problem

The delete button on the PersonDetail page is too harsh visually. Currently it's a solid red button which draws too much attention and feels aggressive for a secondary action.

## Solution

Change the delete button from solid red to outline red style:
- Use `border-red-500` or similar for the border
- Use `text-red-500` for the text color
- Remove the solid background
- Consider adding hover state that fills with subtle red background
