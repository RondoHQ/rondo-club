---
id: M008
provides:
  - Rose "Credit" badge on Facturen list for credit invoices (distinct from cyan "Handmatig")
  - "Credit" filter option in Type filter dropdown on Facturen list
  - Custom filterFn that routes credit filter to invoice_kind check and excludes credit from manual filter
key_decisions:
  - "[M008-S01] Single slice — ~15 lines in one file (Facturen.jsx), no backend changes, no unknowns; splitting would add overhead with zero risk reduction"
  - "[M008-S01] Custom filterFn on type column follows status column's filterFn pattern — intercept synthetic 'credit' value, check invoice_kind instead of invoice_type"
  - "[M008-S01] 'Handmatig' filter must exclude credit invoices — even though credit invoices have invoice_type='manual', they should only appear under 'Credit' filter"
  - "[M008-S01] Rose color for Credit badge — visually distinct from existing purple (contributie), amber (tuchtzaken), cyan (handmatig)"
patterns_established:
  - effectiveType derivation in cell renderer — check invoice_kind before falling back to invoice_type for badge display
  - Custom filterFn intercepting synthetic filter values and routing to different data fields
observability_surfaces:
  - none — pure frontend presentational change
requirement_outcomes: []
duration: ~15m
verification_result: passed
completed_at: 2026-03-12T12:27:00.000Z
---

# M008: Credit Invoice Type Badge

**Credit invoices on the Facturen list now show a distinct rose "Credit" badge and are filterable via a dedicated "Credit" type filter option.**

## What Happened

Single-file change in `src/pages/Finance/Facturen.jsx` (~15 lines). The type column cell renderer was updated to compute an `effectiveType` from `invoice_kind === 'credit'` before falling back to `invoice_type`, so credit invoices display "Credit" with rose styling instead of "Handmatig" with cyan. A custom `filterFn` was added to the type column (following the existing status column pattern) that: matches credit invoices when "Credit" is selected, excludes credit invoices from the "Handmatig" filter, and falls back to exact `invoice_type` matching for all other filter values. No backend changes were needed — `invoice_kind` was already available in the REST API response.

Version bumped from 31.13.1 to 31.14.0 (minor, since this is a new feature), changelog updated, and deployed to production with cache clear.

## Cross-Slice Verification

Single slice (S01) — no cross-slice integration needed.

**Success criteria verification:**

| Criterion | Status | Evidence |
|-----------|--------|----------|
| Credit invoices show "Credit" badge with rose color | ✅ | `typeLabels.credit = 'Credit'`, `typeColors.credit = 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400'`, effectiveType computed at line 113 |
| Non-credit manual invoices still show "Handmatig" cyan | ✅ | effectiveType falls back to `invoice_type` when `invoice_kind !== 'credit'` |
| Type filter includes "Credit" option | ✅ | `{ value: 'credit', label: 'Credit' }` at line 134 |
| "Credit" filter shows only credit invoices | ✅ | filterFn line 126: `return row.original.invoice_kind === 'credit'` |
| "Handmatig" filter excludes credit invoices | ✅ | filterFn line 127: `invoice_type === 'manual' && invoice_kind !== 'credit'` |
| Build passes with zero errors | ✅ | `npm run build` exits 0, 5960 modules |
| Lint passes with zero warnings | ✅ | `npm run lint` exits 0 |
| Deployed to production | ✅ | Production style.css shows Version: 31.14.0 |

## Requirement Changes

None — this milestone did not introduce or change any tracked requirements. It completed a visual distinction item scoped from M003 that was not tied to a formal requirement.

## Forward Intelligence

### What the next milestone should know
- The type column in Facturen.jsx now has a custom `filterFn`, joining the status column as the second column with custom filter logic. Any future filter additions to the type column should extend this function.
- The `effectiveType` pattern (deriving display type from `invoice_kind` before falling back to `invoice_type`) can be reused if more invoice kind variants are added in the future.

### What's fragile
- The filterFn has three branches (credit, manual, default) — if new invoice types are added that also have a `manual` invoice_type but different invoice_kind, the exclusion logic on line 127 would need updating.

### Authoritative diagnostics
- `src/pages/Finance/Facturen.jsx` lines 113, 124-128, 134 — the complete implementation in one file.

### What assumptions changed
- None — the implementation matched the plan exactly. `invoice_kind` was confirmed available in the REST API response as expected.

## Files Created/Modified

- `src/pages/Finance/Facturen.jsx` — Added credit entries to typeLabels/typeColors maps, effectiveType computation in cell renderer, Credit filter option, and custom filterFn on type column
- `package.json` — Version bumped to 31.14.0
- `style.css` — Version bumped to 31.14.0
- `CHANGELOG.md` — Added [31.14.0] entry with Credit badge and filter description
