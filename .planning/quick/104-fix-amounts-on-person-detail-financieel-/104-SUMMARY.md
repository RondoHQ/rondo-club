# Quick Task 104: Fix amounts on Person Detail Financieel tab to use two decimals

## Summary
Changed the default `decimals` parameter in `formatCurrency()` from `0` to `2` so all currency amounts display with two decimal places by default (e.g., "€ 250,00" instead of "€ 250").

## Changes
- `src/utils/formatters.js`: Changed `formatCurrency(amount, decimals = 0)` to `formatCurrency(amount, decimals = 2)`

## Impact
- Fixes 4 calls in `FinancesCard.jsx` (base fee, family discount, pro-rata, final fee) that didn't pass explicit decimals
- No effect on ~40 other callers that already pass `2` explicitly
