---
created: 2026-02-02T10:30
title: Add next season forecast to contributie page
area: ui
files:
  - src/pages/Contributie/ContributieList.jsx
  - includes/class-membership-fees.php
  - includes/class-rest-api.php
---

## Problem

The /contributie page currently only shows fees for the **current season**. The treasurer needs a forecast for **next season** to help with budget planning. This forecast would:

1. Take all current members as the "base"
2. Set pro-rata to 100% for everyone (full year)
3. Keep age groups unchanged (ignore that kids get older - some will also leave, so it balances out)
4. Maintain family discounts based on current family groupings

This is a simple forecast, not a precise prediction - just a rough estimate for budgeting purposes.

## Solution

Possible approaches to discuss:

### Option A: Season toggle/dropdown
Add a dropdown or toggle at the top of the page to switch between:
- "2025-2026 (huidig)" - current behavior
- "2026-2027 (prognose)" - forecast mode

**Pros:** Clean UI, explicit mode selection
**Cons:** More UI elements

### Option B: Side-by-side totals
Keep current view but add a "Prognose volgend seizoen" total in the summary section showing what next year would look like with 100% pro-rata.

**Pros:** Simple, no mode switching needed
**Cons:** Doesn't show per-member breakdown

### Option C: Additional column
Add a "Prognose" column showing what each member would pay next season (always 100% pro-rata).

**Pros:** Full detail visible, easy comparison
**Cons:** Table gets wider, potentially redundant for members already at 100%

### Backend considerations

- `get_season_key()` already supports calculating next season
- `calculate_full_fee()` accepts custom season parameter
- Family discount calculation uses current membership, which is what we want
- Main difference: force `prorata_percentage = 1.0` for forecast mode
- Could add new endpoint `/stadion/v1/fees/forecast` or add `?forecast=true` param to existing endpoint
