# M008: Credit Invoice Type Badge — Research

**Date:** 2026-03-12

## Summary

This is a small, self-contained frontend change in `src/pages/Finance/Facturen.jsx`. Credit invoices already have `invoice_kind: 'credit'` in the REST API list response (confirmed at `format_invoice()` line 2082 of `class-rest-invoices.php`), so no backend work is needed. The change requires modifying three things in the same file: (1) the type column cell renderer to check `invoice_kind` before falling back to `invoice_type`, (2) adding "Credit" to `typeLabels`/`typeColors` maps with a rose color, and (3) adding a "Credit" filter option with a custom `filterFn` that intercepts the synthetic `credit` value.

The existing code patterns are clear and well-established. The status column already demonstrates the exact pattern needed — a custom `filterFn` that handles a synthetic value (`STATUS_FILTER_UNPAID`) differently from real column values. The type column just needs the same treatment. Risk is near-zero: single file, no backend changes, existing data, established patterns.

## Recommendation

Implement in a single slice. The changes are ~15 lines of modifications in one file. Override the type badge display when `invoice_kind === 'credit'` regardless of `invoice_type`, add rose-colored badge, add "Credit" filter option with custom filter function. Build, lint, deploy, verify.

## Don't Hand-Roll

| Problem | Existing Solution | Why Use It |
|---------|------------------|------------|
| Custom filter for synthetic value | Status column `filterFn` in Facturen.jsx (line ~134) | Exact same pattern — intercept synthetic value, check different field |
| Badge color styling | `typeColors`/`statusColors` pattern in Facturen.jsx | Consistent badge rendering across the page |
| Column definition with custom filter | `createColumn()` with `filterFn` override in DataTable | Standard infrastructure, no need to modify DataTable internals |

## Existing Code and Patterns

- `src/pages/Finance/Facturen.jsx` lines 30-42 — `typeLabels` and `typeColors` maps. Add `credit` entry to each. Rose color: `bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400` (follows exact pattern of existing type colors).
- `src/pages/Finance/Facturen.jsx` line 108-116 — Type column `cell` renderer. Currently reads `row.original.invoice_type`. Needs to compute effective type: if `row.original.invoice_kind === 'credit'` → use `'credit'`, else use `invoice_type`.
- `src/pages/Finance/Facturen.jsx` line 117-125 — Type column `filterOptions` and default `SELECT` filter. Needs "Credit" option added and a custom `filterFn` to route `credit` filter value to `invoice_kind` check.
- `src/pages/Finance/Facturen.jsx` line 134-139 — Status column `filterFn` demonstrates the pattern for synthetic filter values (checking `STATUS_FILTER_UNPAID` against derived logic rather than raw column value).
- `includes/class-rest-invoices.php` line 2082 — `format_invoice()` already returns `invoice_kind` from `_invoice_kind` post meta with `'normal'` default. No backend changes needed.
- `src/pages/Finance/FactuurDetail.jsx` line 452 — Detail page already shows "· Credit" suffix. List page badge is the missing piece.

## Constraints

- **Tailwind v4 with OKLCH tokens** — Rose colors are built-in to Tailwind v4, no custom token needed. `bg-rose-100`, `text-rose-700`, etc. are standard utilities.
- **URL filter persistence** — The `invoice_type` URL param will hold `credit` when the Credit filter is active. This works because `handleFilterChange` simply sets/deletes URL params by column ID.
- **ESLint zero-tolerance** — Pre-commit hook enforces zero errors/warnings. Run `npm run lint` before committing.
- **No existing rose usage** — No `rose-*` or `pink-*` classes in the current codebase, so no visual conflict.

## Common Pitfalls

- **Forgetting the filterFn override** — The default `SELECT` filterFn does exact match on `row.getValue(colId)` which returns `invoice_type`. Without a custom `filterFn`, selecting "Credit" would try to match `invoice_type === 'credit'` which would match nothing. Must provide custom `filterFn` that checks `invoice_kind` when filter value is `credit`.
- **Breaking existing manual invoice display** — Manual invoices that are NOT credit should still show "Handmatig" with cyan. The cell renderer must only override when `invoice_kind === 'credit'`, not when `invoice_type === 'manual'`.
- **Filter interaction with other type values** — When filtering by "Handmatig", credit invoices (which have `invoice_type: 'manual'`) should NOT appear. The custom `filterFn` for non-credit values should exclude rows where `invoice_kind === 'credit'`.

## Open Risks

- None. Pure frontend, single file, established patterns, data already in API response.

## Skills Discovered

| Technology | Skill | Status |
|------------|-------|--------|
| WordPress/PHP | N/A | Not needed — no backend changes |
| React/Tailwind | frontend-design | installed (available skill) — not needed for this small change |

## Sources

- `invoice_kind` field confirmed in REST response (source: `includes/class-rest-invoices.php` line 2082)
- Custom filterFn pattern confirmed working (source: status column in `src/pages/Finance/Facturen.jsx` lines 134-139)
- DataTable createColumn API (source: `src/components/DataTable/columnHelpers.js`)
