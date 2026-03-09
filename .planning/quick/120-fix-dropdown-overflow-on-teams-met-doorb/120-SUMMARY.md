# Quick Task 120: Fix dropdown overflow on Tuchtzaken settings card

**Date:** 2026-03-09
**Commit:** e3be4392

## Changes

- Added `overflow-visible` class to the Tuchtzaken card in `FinanceSettings.jsx` so the `SearchableMultiSelect` dropdown for "Teams met doorbelasting-uitzondering" is no longer clipped by the card's overflow.

## Files Modified

- `src/pages/Finance/FinanceSettings.jsx` — 1-word class addition
