# S01: Credit badge and filter on Facturen list — Research

**Date:** 2026-03-12

## Summary

This is a ~15-line change in a single file (`src/pages/Finance/Facturen.jsx`). Credit invoices already have `invoice_kind: 'credit'` in the list API response (confirmed in `format_invoice()` at line 2082 of `class-rest-invoices.php`), so zero backend work is needed.

Three modifications are required: (1) add `credit` entries to `typeLabels` and `typeColors` maps, (2) modify the type column's `cell` renderer to check `row.original.invoice_kind === 'credit'` and override the displayed type, and (3) add a "Credit" filter option with a custom `filterFn` that routes the synthetic `credit` value to an `invoice_kind` check and excludes credit invoices from the `manual` filter.

The status column's existing `filterFn` (lines 134-139) demonstrates the exact pattern for custom filter logic on synthetic values. The change is low-risk, well-understood, and follows established conventions in the same file.

## Recommendation

Implement as a single surgical edit to `src/pages/Finance/Facturen.jsx`. The three changes (maps, cell renderer, filter config) are interdependent and should be committed together. No new files, no new dependencies, no backend changes.

## Don't Hand-Roll

| Problem | Existing Solution | Why Use It |
|---------|------------------|------------|
| Custom filter for synthetic value | Status column `filterFn` in Facturen.jsx (line 134-139) | Exact same pattern — intercept synthetic value, check different field |
| Badge color styling | `typeColors` map in Facturen.jsx (line 38-42) | Consistent Tailwind badge pattern with dark mode support |
| Column definition with custom filter | `createColumn()` with `filterFn` override | Standard DataTable infrastructure, no modifications to DataTable needed |

## Existing Code and Patterns

- `src/pages/Finance/Facturen.jsx` lines 34-42 — `typeLabels` and `typeColors` maps. Add `credit: 'Credit'` to typeLabels and `credit: 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400'` to typeColors. Rose follows the exact `bg-{color}-100 text-{color}-700 dark:bg-{color}-900/30 dark:text-{color}-400` pattern used by all existing type colors.
- `src/pages/Finance/Facturen.jsx` lines 108-118 — Type column cell renderer. Currently reads `row.original.invoice_type` directly. Must compute effective type: `const effectiveType = row.original.invoice_kind === 'credit' ? 'credit' : row.original.invoice_type`. Then use `effectiveType` for both the label lookup and color lookup.
- `src/pages/Finance/Facturen.jsx` lines 119-126 — Type column filter config. Currently `filterType: FILTER_TYPES.SELECT` with 3 options and no custom `filterFn`. Needs: (a) add `{ value: 'credit', label: 'Credit' }` to `filterOptions`, (b) add custom `filterFn` that checks `invoice_kind` for the `credit` value and excludes credit invoices from `manual`.
- `src/pages/Finance/Facturen.jsx` lines 134-139 — Status column `filterFn` is the reference pattern: `(row, colId, value) => { if (!value) return true; if (value === SYNTHETIC) return derivedCheck; return row.getValue(colId) === value; }`. The type column filterFn will follow this exact structure.
- `src/components/DataTable/columnHelpers.js` line 73 — When `customFilterFn` is provided, it's assigned directly to `def.filterFn`, bypassing the default exact-match logic. This is already proven by the status column.
- `includes/class-rest-invoices.php` line 2082 — `format_invoice()` returns `invoice_kind` from `_invoice_kind` post meta with `'normal'` default. This is the **list** endpoint formatter (called at line 812), confirming the field is available in list data without any backend changes.

## Constraints

- **Tailwind v4 with OKLCH tokens** — Rose classes (`bg-rose-100`, `text-rose-700`, etc.) are built-in Tailwind v4 utilities. No custom config needed.
- **ESLint zero-tolerance** — Pre-commit hook enforces zero errors/warnings. Must run `npm run lint` before committing.
- **URL filter persistence** — The `invoice_type` URL param will hold `credit` when the Credit filter is active. The `handleFilterChange` function in Facturen.jsx handles this generically (sets/deletes URL params by column ID), so no changes needed there. The `filters` memo at line 192 reads `invoice_type` from search params — also works unchanged.
- **No existing rose usage** — Confirmed no `rose-*` or `pink-*` classes in the current codebase. No visual conflict.

## Common Pitfalls

- **Forgetting the filterFn override** — The default SELECT filterFn does exact match on `row.getValue(colId)` which returns `invoice_type`. Without a custom `filterFn`, selecting "Credit" would match nothing because no invoice has `invoice_type === 'credit'`. Must provide custom `filterFn` that checks `row.original.invoice_kind` when filter value is `'credit'`.
- **Breaking existing manual invoices** — Manual invoices that are NOT credit must still show "Handmatig" with cyan. The cell renderer must only override to "Credit" when `invoice_kind === 'credit'`, not unconditionally for all manual invoices.
- **Credit invoices leaking into "Handmatig" filter** — When filtering by `'manual'`, credit invoices (which have `invoice_type: 'manual'`) must NOT appear. The custom `filterFn` must explicitly exclude rows where `invoice_kind === 'credit'` when the filter value is `'manual'`.
- **getFilterLabel not updated** — The type column doesn't currently have a `getFilterLabel`. Since the filter options include both value and label, the DataTable uses the label from `filterOptions` for the chip display. Adding the "Credit" option with `label: 'Credit'` is sufficient — no `getFilterLabel` function needed.

## Open Risks

- None. Pure frontend, single file, established patterns, data already confirmed in list API response.

## Skills Discovered

| Technology | Skill | Status |
|------------|-------|--------|
| React/Tailwind | frontend-design | installed (available skill) — not needed for this small badge change |
| WordPress/PHP | N/A | Not needed — no backend changes |

## Sources

- `invoice_kind` field confirmed in list API response (source: `includes/class-rest-invoices.php` line 2082, called from line 812)
- Custom filterFn pattern confirmed working (source: status column in `src/pages/Finance/Facturen.jsx` lines 134-139)
- DataTable createColumn API with filterFn override (source: `src/components/DataTable/columnHelpers.js` line 73)
- Type column cell renderer and filter config (source: `src/pages/Finance/Facturen.jsx` lines 108-126)
