---
estimated_steps: 7
estimated_files: 5
---

# T02: Display manual-paid info in Betaalgegevens card, bump version, deploy

**Slice:** S01 — Manual paid audit trail + display
**Milestone:** M006

## Description

Update the Betaalgegevens card in `FactuurDetail.jsx` to render for manually-paid invoices (not just Mollie-paid), showing "Handmatig gemarkeerd als betaald" with date/time and user name. Bump version, update changelog, build, commit, push, and deploy to production.

## Steps

1. In `FactuurDetail.jsx` (line ~832), change the Betaalgegevens card condition from `invoice.mollie_payment_method` to `(invoice.mollie_payment_method || invoice.manually_marked_paid_at)`.
2. Inside the card, wrap the existing Mollie content in `{invoice.mollie_payment_method && ( ... )}` so it only renders for Mollie-paid invoices.
3. Add a new section for manually-paid display, gated on `{!invoice.mollie_payment_method && invoice.manually_marked_paid_at && ( ... )}`:
   - Show "Handmatig gemarkeerd als betaald" as a label
   - Show formatted date/time using `format(new Date(invoice.manually_marked_paid_at), 'd MMM yyyy HH:mm')`
   - Show user name from `invoice.manually_marked_paid_by?.name` (with "Onbekend" fallback)
4. Import `UserCheck` from lucide-react (for the manual-paid icon variant) — or reuse `CheckCircle` which is already imported.
5. Bump version from 31.12.0 to 31.13.0 in `style.css` and `package.json`.
6. Add changelog entry under `## [31.13.0]` with `### Added` section: "Audit trail bij handmatig betaald markeren — toont wie en wanneer in de Betaalgegevens kaart".
7. Run `npm run build` and `npm run lint`, commit, push, deploy via `bin/deploy.sh`.

## Must-Haves

- [ ] Betaalgegevens card renders for both Mollie-paid and manually-paid invoices
- [ ] Mollie-paid invoices still show all Mollie details (no regression)
- [ ] Manual-paid section shows "Handmatig gemarkeerd als betaald" with date and user
- [ ] Betaalgegevens card prioritizes Mollie data when both exist (Mollie section renders, manual doesn't)
- [ ] `npm run build` succeeds
- [ ] `npm run lint` passes with zero warnings
- [ ] Version bumped to 31.13.0
- [ ] Changelog updated
- [ ] Deployed to production

## Verification

- `npm run build` completes without errors
- `npm run lint` passes with zero warnings
- On production: mark a test invoice as paid → Betaalgegevens card shows manual-paid info
- On production: view a Mollie-paid invoice → Betaalgegevens card still shows Mollie details

## Observability Impact

- Signals added/changed: None (UI-only change consuming existing REST fields)
- How a future agent inspects this: View any paid invoice detail page — Betaalgegevens card visible
- Failure state exposed: Card absent = fields not returned by API or condition wrong

## Inputs

- `includes/class-rest-invoices.php` — T01 added `manually_marked_paid_at` and `manually_marked_paid_by` to REST response
- `src/pages/Finance/FactuurDetail.jsx` — existing Betaalgegevens card at line ~832 with Mollie display

## Expected Output

- `src/pages/Finance/FactuurDetail.jsx` — Betaalgegevens card renders for both payment types
- `style.css` — version 31.13.0
- `package.json` — version 31.13.0
- `CHANGELOG.md` — new 31.13.0 entry
- Production deploy completed
