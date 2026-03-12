---
id: T01
parent: S01
milestone: M008
provides:
  - Credit badge display for credit invoices on Facturen list
  - Credit filter option in Type filter dropdown
  - Custom filterFn separating credit from manual invoices
key_files:
  - src/pages/Finance/Facturen.jsx
key_decisions:
  - Used effectiveType pattern to override display type based on invoice_kind without changing the underlying data
patterns_established:
  - effectiveType derivation in cell renderer: check invoice_kind before falling back to invoice_type
  - Custom filterFn on type column following the same pattern as the status column filterFn
observability_surfaces:
  - none
duration: 5m
verification_result: passed
completed_at: 2026-03-12
blocker_discovered: false
---

# T01: Add Credit badge, filter option, and custom filterFn to Facturen.jsx

**Added rose "Credit" badge for credit invoices and working "Credit" filter option to the Facturen list Type column.**

## What Happened

Made five changes to `src/pages/Finance/Facturen.jsx`:

1. Added `credit: 'Credit'` to `typeLabels` map
2. Added `credit: 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400'` to `typeColors` map
3. Modified the type column cell renderer to compute `effectiveType` from `invoice_kind === 'credit'` before falling back to `invoice_type`, ensuring credit invoices show "Credit" with rose styling instead of "Handmatig" with cyan
4. Added `{ value: 'credit', label: 'Credit' }` to the type column's `filterOptions` array
5. Added a custom `filterFn` to the type column that: returns `invoice_kind === 'credit'` check for the `'credit'` filter; excludes credit invoices from the `'manual'` filter; defaults to exact match on `invoice_type` for other values

## Verification

- `npm run build` — exits 0, all 5960 modules transformed successfully
- `npm run lint` — exits 0, zero errors and zero warnings
- `grep 'rose-' Facturen.jsx` — confirms rose color classes present at line 41
- `grep 'invoice_kind' Facturen.jsx` — confirms usage in cell renderer (line 113) and filterFn (lines 126-127)

### Slice-level checks (partial — T01 is not the final task):
- ✅ `npm run build` exits 0
- ✅ `npm run lint` exits 0
- ⏳ `bin/deploy.sh` — deferred to T02
- ⏳ Visual verification on production — deferred to T02

## Diagnostics

None — pure presentational change. Inspect by reading `typeLabels`, `typeColors` maps and the `invoice_type` column definition in `Facturen.jsx`.

## Deviations

None.

## Known Issues

None.

## Files Created/Modified

- `src/pages/Finance/Facturen.jsx` — Added credit entries to type maps, effectiveType computation in cell renderer, Credit filter option, and custom filterFn on type column
