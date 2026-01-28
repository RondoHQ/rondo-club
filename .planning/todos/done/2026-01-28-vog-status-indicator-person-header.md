---
created: 2026-01-28T20:16
title: VOG status indicator on Person header
area: ui
files:
  - src/pages/PersonDetail.jsx
  - src/components/PersonHeader.jsx
---

## Problem

Members who have a work function (other than "Donateur") need VOG (Verklaring Omtrent Gedrag) certification tracked and visually displayed. Currently there is no indication of VOG status on the Person detail page.

The VOG status needs to be prominently displayed in the top-right of the Person header with color-coded states:
- **VOG OK** (green): VOG date exists and is less than 3 years old
- **VOG verlopen** (orange): VOG date exists but is more than 3 years old
- **Geen VOG** (red): No VOG date recorded

The indicator should only appear for members who have a current work function other than "Donateur".

## Solution

1. Check if person has current work functions (excluding "Donateur")
2. If yes, display VOG status badge based on VOG date field
3. Use appropriate colors: green/orange/red based on status
4. Position in top-right of PersonHeader component
