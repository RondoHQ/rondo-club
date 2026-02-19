# Quick Task 107: Fix Nog te factureren page not updating after Maak factuur

## Problem
After clicking "Maak factuur" on the Nog te factureren page, the invoice is created but the page shows stale data until a hard refresh.

## Root Cause
`invalidateQueries` marks queries as stale and triggers a background refetch, but the cached data remains visible. If the refetch response comes from a browser or server cache, the UI never updates.

## Solution
Use `resetQueries` instead of `invalidateQueries`. This clears the cached data entirely and triggers a fresh fetch, showing a loading state while the new data loads — equivalent to a hard refresh.

Applied to both single invoice creation and bulk invoice creation mutations.
