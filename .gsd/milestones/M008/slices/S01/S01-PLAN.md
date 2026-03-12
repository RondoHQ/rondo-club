# S01: Credit badge and filter on Facturen list

**Goal:** Credit invoices show a distinct "Credit" rose badge on the Facturen list page, and the Type filter includes a working "Credit" option that correctly filters credit invoices while excluding them from "Handmatig".
**Demo:** On `/financien/facturen`, credit invoices display a rose "Credit" badge instead of cyan "Handmatig". Selecting "Credit" in the Type filter shows only credit invoices. Selecting "Handmatig" hides credit invoices. Deployed and visible at https://rondo.svawc.nl/financien/facturen.

## Must-Haves

- Credit invoices (where `invoice_kind === 'credit'`) display "Credit" label with rose/pink badge color
- Non-credit manual invoices still display "Handmatig" with cyan badge color
- Type filter dropdown includes "Credit" option
- Selecting "Credit" filter shows only invoices where `invoice_kind === 'credit'`
- Selecting "Handmatig" filter excludes credit invoices (even though their `invoice_type` is `manual`)
- Build passes (`npm run build`) with zero errors
- Lint passes (`npm run lint`) with zero errors/warnings
- Deployed to production and cache cleared

## Proof Level

- This slice proves: operational
- Real runtime required: yes (production deployment and visual verification)
- Human/UAT required: yes (visual confirmation of badge colors and filter behavior on production)

## Verification

- `npm run build` exits 0
- `npm run lint` exits 0
- `bin/deploy.sh` completes successfully
- Visual verification on https://rondo.svawc.nl/financien/facturen: credit invoices show rose "Credit" badge, Type filter includes "Credit" option

## Observability / Diagnostics

- Runtime signals: None — pure presentational change, no new logging or state transitions
- Inspection surfaces: Browser DevTools on the Facturen page; filter URL param `invoice_type=credit` in address bar
- Failure visibility: Wrong badge color or missing filter option immediately visible in UI
- Redaction constraints: None

## Integration Closure

- Upstream surfaces consumed: `row.original.invoice_kind` from REST API response (`class-rest-invoices.php` `format_invoice()` — already shipping `invoice_kind` field)
- New wiring introduced in this slice: `effectiveType` computation in type column cell renderer routes `invoice_kind === 'credit'` to the new `credit` badge; custom `filterFn` on type column routes `credit` filter value to `invoice_kind` check
- What remains before the milestone is truly usable end-to-end: nothing — this is the only slice in M008

## Tasks

- [x] **T01: Add Credit badge, filter option, and custom filterFn to Facturen.jsx** `est:20m`
  - Why: This is the entire slice — add credit type display and filtering to the Facturen list page
  - Files: `src/pages/Finance/Facturen.jsx`
  - Do: (1) Add `credit: 'Credit'` to `typeLabels` and `credit: 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400'` to `typeColors`. (2) In the type column cell renderer, compute `effectiveType = row.original.invoice_kind === 'credit' ? 'credit' : row.original.invoice_type` and use it for label/color lookup. (3) Add `{ value: 'credit', label: 'Credit' }` to type column `filterOptions`. (4) Add custom `filterFn` to type column: when value is `'credit'`, check `row.original.invoice_kind === 'credit'`; when value is `'manual'`, check `invoice_type === 'manual' && invoice_kind !== 'credit'`; otherwise default exact match on `invoice_type`.
  - Verify: `npm run build && npm run lint` both exit 0
  - Done when: Build and lint pass cleanly with zero errors/warnings

- [ ] **T02: Deploy to production and verify** `est:10m`
  - Why: Milestone requires production deployment and visual verification
  - Files: `style.css`, `package.json`, `CHANGELOG.md`, `bin/deploy.sh`
  - Do: Bump patch version in `style.css` and `package.json`. Add changelog entry for Credit badge. Run `bin/deploy.sh` to deploy. Visually verify on production.
  - Verify: `bin/deploy.sh` exits 0; production site shows credit invoices with rose badge and working filter
  - Done when: Deployed to production, SiteGround cache cleared, credit invoices visible with rose "Credit" badge at https://rondo.svawc.nl/financien/facturen

## Files Likely Touched

- `src/pages/Finance/Facturen.jsx`
- `style.css` (version bump)
- `package.json` (version bump)
- `CHANGELOG.md`
