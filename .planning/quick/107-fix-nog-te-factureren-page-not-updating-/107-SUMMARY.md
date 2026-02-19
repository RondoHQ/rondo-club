# Quick Task 107: Fix Nog te factureren page not updating after Maak factuur

## Summary
Changed `invalidateQueries` to `resetQueries` for both single and bulk invoice creation on the Nog te factureren page. This clears cached data and forces a fresh network request.

## Changes
- `src/pages/Contributie/NogTeFactureren.jsx`: Replaced `invalidateQueries` with `resetQueries` in single and bulk creation handlers
