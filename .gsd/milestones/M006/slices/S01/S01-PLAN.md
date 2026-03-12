# S01: Manual paid audit trail + display

**Goal:** When a user manually marks an invoice as paid, an audit trail (who + when) is stored and displayed in the Betaalgegevens card.
**Demo:** Mark an invoice as paid via the UI → Betaalgegevens card shows "Handmatig gemarkeerd als betaald" with date/time and user name. Mollie-paid invoices still show their Mollie details unchanged.

## Must-Haves

- `_manually_marked_paid_at` and `_manually_marked_paid_by` post meta stored on manual paid transition
- `manually_marked_paid_at` and `manually_marked_paid_by` fields returned in `format_invoice_detail()` REST response
- Betaalgegevens card renders for manually-paid invoices (not just Mollie-paid)
- Mollie-paid invoices still display Mollie details as before (no regression)
- Betaalgegevens card prioritizes Mollie data when both exist
- Version bump, changelog, deploy to production

## Proof Level

- This slice proves: integration (backend stores meta → REST returns it → UI renders it)
- Real runtime required: yes (deployed to production)
- Human/UAT required: yes (user verifies on production)

## Verification

- `npm run build` — frontend compiles without errors
- `npm run lint` — zero warnings
- Deploy to production and mark a test invoice as paid → verify Betaalgegevens card appears with correct data
- Verify a Mollie-paid invoice still shows Mollie payment details unchanged

## Observability / Diagnostics

- Runtime signals: `_manually_marked_paid_at` and `_manually_marked_paid_by` post meta on invoice posts
- Inspection surfaces: REST API response at `/wp/v2/rondo_invoice/{id}` includes `manually_marked_paid_at` and `manually_marked_paid_by` fields; WP-CLI `wp post meta get {id} _manually_marked_paid_at`
- Failure visibility: Fields return `null` when meta not set (distinguishes "not set" from "empty")
- Redaction constraints: none (user display names are not PII in this club context)

## Integration Closure

- Upstream surfaces consumed: `update_invoice_status()` in `class-rest-invoices.php`, `format_invoice_detail()` in same file, Betaalgegevens card in `FactuurDetail.jsx`
- New wiring introduced in this slice: Two post meta keys written on paid transition → returned via REST → rendered in React UI
- What remains before the milestone is truly usable end-to-end: nothing — this is the only slice

## Tasks

- [x] **T01: Store manual-paid audit meta and return in REST response** `est:20m`
  - Why: Backend must store who marked the invoice paid and when, and surface these fields via the REST API so the frontend can display them
  - Files: `includes/class-rest-invoices.php`
  - Do: (1) In `update_invoice_status()`, add `update_post_meta` calls for `_manually_marked_paid_at` (using `current_time('mysql')`) and `_manually_marked_paid_by` (using `get_current_user_id()`) inside the `$status === 'paid'` block, BEFORE the artifact cleanup. (2) In `format_invoice_detail()`, add `manually_marked_paid_at` and `manually_marked_paid_by` fields — timestamp as string-or-null, user via `get_user_summary_by_id()`.
  - Verify: `npm run lint` passes; grep the file for the new meta keys to confirm correct placement
  - Done when: Both meta keys stored on paid transition and both fields present in detail response

- [ ] **T02: Display manual-paid info in Betaalgegevens card, bump version, deploy** `est:25m`
  - Why: The UI must show the audit trail to users, and the change must be deployed for UAT
  - Files: `src/pages/Finance/FactuurDetail.jsx`, `style.css`, `package.json`, `CHANGELOG.md`
  - Do: (1) Widen the Betaalgegevens card condition from `invoice.mollie_payment_method` to `(invoice.mollie_payment_method || invoice.manually_marked_paid_at)`. (2) Add a section for manually-paid display (when no Mollie data) showing "Handmatig gemarkeerd als betaald" with formatted date/time and user name. (3) Bump version to 31.13.0 in style.css and package.json. (4) Add changelog entry. (5) `npm run build` + `npm run lint`. (6) Git commit & push. (7) Deploy to production via `bin/deploy.sh`.
  - Verify: `npm run build` succeeds; `npm run lint` passes; production deploy completes; manually mark a test invoice as paid on production and verify Betaalgegevens card renders
  - Done when: Betaalgegevens card shows manual-paid info on production; Mollie-paid invoices unaffected

## Files Likely Touched

- `includes/class-rest-invoices.php`
- `src/pages/Finance/FactuurDetail.jsx`
- `style.css`
- `package.json`
- `CHANGELOG.md`
