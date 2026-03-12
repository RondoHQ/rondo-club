---
estimated_steps: 5
estimated_files: 1
---

# T01: Add Credit badge, filter option, and custom filterFn to Facturen.jsx

**Slice:** S01 — Credit badge and filter on Facturen list
**Milestone:** M008

## Description

Add credit invoice visual distinction and filtering to the Facturen list page. Credit invoices (identified by `invoice_kind === 'credit'` in the API response) should display a rose "Credit" badge instead of the cyan "Handmatig" badge. The Type filter dropdown should include a "Credit" option, and the "Handmatig" filter should exclude credit invoices.

## Steps

1. Add `credit: 'Credit'` to the `typeLabels` map (after the `manual` entry at line 37)
2. Add `credit: 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400'` to the `typeColors` map (after the `manual` entry at line 42)
3. Modify the type column's `cell` renderer to compute an effective type: `const effectiveType = row.original.invoice_kind === 'credit' ? 'credit' : row.original.invoice_type;` — then use `effectiveType` for both the `typeLabels` lookup and the `typeColors` lookup instead of `row.original.invoice_type`
4. Add `{ value: 'credit', label: 'Credit' }` to the type column's `filterOptions` array (after the `manual` entry)
5. Add a custom `filterFn` to the type column definition that follows the status column pattern: when filter value is `'credit'`, check `row.original.invoice_kind === 'credit'`; when filter value is `'manual'`, check `row.original.invoice_type === 'manual' && row.original.invoice_kind !== 'credit'`; when no value, return true; otherwise default to exact match on `row.getValue(colId)`

## Must-Haves

- [ ] `typeLabels` includes `credit: 'Credit'`
- [ ] `typeColors` includes rose color classes for `credit`
- [ ] Type column cell renderer uses `effectiveType` derived from `invoice_kind`
- [ ] Non-credit manual invoices still show "Handmatig" with cyan color
- [ ] Type filter dropdown includes "Credit" option
- [ ] Custom `filterFn` routes `credit` filter to `invoice_kind` check
- [ ] Custom `filterFn` excludes credit invoices from `manual` filter
- [ ] `npm run build` exits 0
- [ ] `npm run lint` exits 0 with zero warnings

## Verification

- Run `npm run build` — must exit 0 with no errors
- Run `npm run lint` — must exit 0 with zero errors and zero warnings
- Grep `Facturen.jsx` for `rose-` classes to confirm credit color is present
- Grep `Facturen.jsx` for `invoice_kind` to confirm the cell renderer and filterFn use it

## Observability Impact

- Signals added/changed: None — pure presentational change
- How a future agent inspects this: Read `typeLabels`, `typeColors` maps and the type column definition in `Facturen.jsx`
- Failure state exposed: None — visual-only, no runtime errors possible

## Inputs

- `src/pages/Finance/Facturen.jsx` — current file with typeLabels/typeColors maps, type column definition, and status column filterFn as reference pattern
- S01-RESEARCH.md — confirmed `invoice_kind` field availability in list API response and exact line references

## Expected Output

- `src/pages/Finance/Facturen.jsx` — modified with: credit entries in type maps, effectiveType computation in cell renderer, "Credit" filter option, custom filterFn on type column
