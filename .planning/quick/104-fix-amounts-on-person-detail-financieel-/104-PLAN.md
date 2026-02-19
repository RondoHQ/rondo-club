# Quick Task 104: Fix amounts on Person Detail Financieel tab to use two decimals

## Problem
On the Person Detail page's Financieel tab (FinancesCard component), the base fee, family discount, pro-rata, and final fee amounts display without decimal places (e.g., "€ 250" instead of "€ 250,00"). This is because `formatCurrency()` in `src/utils/formatters.js` defaults to `0` decimals, and these 4 calls don't pass the `2` argument.

## Root Cause
`formatCurrency(amount, decimals = 0)` in `src/utils/formatters.js:12` defaults to 0 decimals. The FinancesCard calls on lines 177, 188, 224, 233 don't pass `2`, while all other callers in the codebase do.

## Solution
Change the default parameter in `formatCurrency` from `decimals = 0` to `decimals = 2`. This:
- Fixes all 4 FinancesCard calls automatically
- Has no effect on the ~40 other callers that already pass `2` explicitly
- Makes the function default to the expected financial formatting

## Tasks

### Task 1: Change formatCurrency default decimals to 2
**File:** `src/utils/formatters.js`
**Change:** Line 12: `export function formatCurrency(amount, decimals = 0)` → `export function formatCurrency(amount, decimals = 2)`

### Task 2: Build verification
Run `npm run build` to verify the change compiles without errors.
