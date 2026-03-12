# M008: Credit Invoice Type Badge

**Vision:** Credit invoices are visually distinct on the Facturen list with a "Credit" badge in rose/pink, and filterable via a "Credit" type filter option.

## Success Criteria

- Credit invoices on `/financien/facturen` show a "Credit" badge with rose/pink color instead of "Handmatig" cyan
- Non-credit manual invoices still show "Handmatig" with cyan styling
- The Type filter dropdown includes a "Credit" option
- Selecting the "Credit" filter shows only credit invoices (where `invoice_kind === 'credit'`)
- Selecting the "Handmatig" filter excludes credit invoices (even though their `invoice_type` is `manual`)
- Build and lint pass with zero errors/warnings
- Deployed to production and visually verified

## Key Risks / Unknowns

None. Pure frontend change in a single file. `invoice_kind` is already in the REST API response. The status column's custom `filterFn` provides the exact pattern to follow.

## Verification Classes

- Contract verification: `npm run build` succeeds, `npm run lint` returns 0 errors/warnings
- Integration verification: none (frontend-only, data already in API)
- Operational verification: deployed to production, SiteGround cache cleared
- UAT / human verification: visually confirm credit invoices show "Credit" badge on production Facturen page

## Milestone Definition of Done

This milestone is complete only when all are true:

- Credit invoices display "Credit" badge with rose color on the Facturen list
- Normal manual invoices still display "Handmatig" with cyan color
- "Credit" filter option works correctly in the Type dropdown
- "Handmatig" filter excludes credit invoices
- Build and lint pass cleanly
- Deployed to production and visually verified at https://rondo.svawc.nl/financien/facturen

## Requirement Coverage

- Covers: none (no Active requirements — all are Validated from prior milestones)
- Partially covers: none
- Leaves for later: none
- Orphan risks: none

## Slices

- [x] **S01: Credit badge and filter on Facturen list** `risk:low` `depends:[]`
  > After this: credit invoices show "Credit" rose badge on the production Facturen page, the Type filter includes "Credit" as an option that correctly filters credit invoices, and "Handmatig" excludes credit invoices — verified live at https://rondo.svawc.nl/financien/facturen

## Boundary Map

### S01 (single slice — no downstream dependencies)

Produces:
- `typeLabels.credit` and `typeColors.credit` entries in Facturen.jsx for badge display
- Custom `filterFn` on the type column that routes `credit` filter value to `invoice_kind` check and excludes credit invoices from `manual` filter
- "Credit" option in type column `filterOptions` array

Consumes:
- `row.original.invoice_kind` from existing REST API response (already available, no backend changes)
- Status column `filterFn` pattern (existing code in same file, used as reference)
