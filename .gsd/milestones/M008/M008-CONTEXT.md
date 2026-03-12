# M008: Credit Invoice Type Badge — Context

**Gathered:** 2026-03-12
**Status:** Queued — pending auto-mode execution

## Project Description

On `/financien/facturen`, credit invoices currently show the type badge "Handmatig" (cyan) because their `invoice_type` is `manual`. Replace this with a "Credit" badge in a distinct color so credit invoices are immediately distinguishable. Also add "Credit" as a filter option in the Type filter.

## Why This Milestone

This was scoped as item 3 of M003 (Credit Invoice Improvements) but was not included in the S01 plan. M003 shipped the email template and status fix; this visual distinction on the invoice list is the remaining piece.

## User-Visible Outcome

### When this milestone is complete, the user can:

- See credit invoices labeled "Credit" with a distinct badge color (rose/pink) on the Facturen list instead of "Handmatig"
- Filter the Facturen list by "Credit" type to see only credit invoices

### Entry point / environment

- Entry point: https://rondo.svawc.nl/financien/facturen
- Environment: production WordPress site
- Live dependencies involved: none

## Completion Class

- Contract complete means: credit invoices show "Credit" badge, filter works, build passes
- Integration complete means: deployed to production
- Operational complete means: none

## Final Integrated Acceptance

To call this milestone complete, we must prove:

- Credit invoices on the Facturen list show "Credit" badge with a rose/pink color instead of "Handmatig" cyan
- The Type filter dropdown includes a "Credit" option that filters correctly
- Normal manual invoices still show "Handmatig" as before
- Deployed to production and visually verified

## Risks and Unknowns

- None — pure frontend change, `invoice_kind` is already in the REST API response

## Existing Codebase / Prior Art

- `src/pages/Finance/Facturen.jsx` — `typeLabels` and `typeColors` maps control badge display (line ~32-42); `invoice_type` column cell renderer (line ~110); filter options (line ~122). The cell renderer currently only checks `invoice_type` — needs to also check `invoice_kind === 'credit'` to override.
- `includes/class-rest-invoices.php` line ~2077 — `format_invoice_detail()` already returns `invoice_kind` from `_invoice_kind` post meta. The list endpoint also returns it since it uses `get_fields()` which includes all ACF data.

> See `.gsd/DECISIONS.md` for all architectural and pattern decisions.

## Relevant Requirements

- Follows from M003 Credit Invoice Improvements — completes the visual distinction piece

## Scope

### In Scope

- Override type badge to "Credit" (rose/pink color) when `invoice_kind === 'credit'` in `Facturen.jsx`
- Add "Credit" to type filter dropdown options
- Filter logic: when "Credit" selected, match rows where `invoice_kind === 'credit'`
- Deploy to production

### Out of Scope / Non-Goals

- Backend changes (none needed — `invoice_kind` already in response)
- Changing the invoice_type field value itself (stays `manual` in the database)
- Credit badge on invoice detail page (already shows "Credit" in header via FactuurDetail.jsx)

## Technical Constraints

- The type column uses `accessorKey: 'invoice_type'` for both display and filtering. The cell renderer can read `row.original.invoice_kind` to override the label/color. The filter function needs a custom `filterFn` to handle the synthetic "credit" value.
- Color should be visually distinct from existing types: purple (contributie), amber (tuchtzaken), cyan (handmatig). Rose/pink is a good choice.

## Integration Points

- None — frontend-only change

## Open Questions

- None
