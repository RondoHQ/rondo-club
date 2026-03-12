---
id: T02
parent: S01
milestone: M006
provides:
  - Betaalgegevens card renders for manually-paid invoices with audit trail (who + when)
  - Version bumped to 31.13.0 with changelog
  - Deployed to production
key_files:
  - src/pages/Finance/FactuurDetail.jsx
  - style.css
  - package.json
  - CHANGELOG.md
key_decisions:
  - Used UserCheck icon from lucide-react for manual-paid visual distinction (vs CreditCard for Mollie)
  - Mollie content prioritized via condition: Mollie section renders when mollie_payment_method exists, manual section only when no Mollie data
patterns_established:
  - Betaalgegevens card uses conditional rendering with Mollie-first priority
observability_surfaces:
  - Betaalgegevens card visible on any manually-paid invoice detail page
duration: 12m
verification_result: passed
completed_at: 2026-03-12
blocker_discovered: false
---

# T02: Display manual-paid info in Betaalgegevens card, bump version, deploy

**Betaalgegevens card now renders for manually-paid invoices showing "Handmatig gemarkeerd als betaald" with date/time and user name; deployed to production as v31.13.0.**

## What Happened

1. Updated `FactuurDetail.jsx` Betaalgegevens card condition from `invoice.mollie_payment_method` to `(invoice.mollie_payment_method || invoice.manually_marked_paid_at)`.
2. Wrapped existing Mollie content in `{invoice.mollie_payment_method && (<>...</>)}` fragment.
3. Added manual-paid section gated on `{!invoice.mollie_payment_method && invoice.manually_marked_paid_at && (<>...</>)}` showing:
   - "Handmatig gemarkeerd als betaald" with UserCheck icon
   - Formatted date/time via `format(new Date(...), 'd MMM yyyy HH:mm')`
   - User name from `invoice.manually_marked_paid_by?.name` with "Onbekend" fallback
4. Imported `UserCheck` from lucide-react for the manual-paid icon.
5. Bumped version to 31.13.0 in `style.css` and `package.json`.
6. Added changelog entry under `## [31.13.0]`.
7. Build and lint both passed cleanly. Committed, pushed, deployed to production.

## Verification

- `npm run build` — ✅ completed without errors
- `npm run lint` — ✅ zero warnings
- Production deploy — ✅ completed via `bin/deploy.sh`
- Browser verification on production:
  - Marked invoice 2026F016 as paid → Betaalgegevens card appeared with "Handmatig gemarkeerd als betaald", "12 mrt. 2026 15:17", "Joost de Valk" — ✅ all 4 browser assertions passed
  - Reverted test invoice back to "Verstuurd" status and cleaned up meta via WP-CLI

### Slice-level verification (final task):
- [x] `npm run build` — frontend compiles without errors
- [x] `npm run lint` — zero warnings
- [x] Deploy to production and mark a test invoice as paid → Betaalgegevens card appears with correct data
- [ ] Verify a Mollie-paid invoice still shows Mollie payment details unchanged — no Mollie-paid invoice available in test data, but code review confirms: Mollie section renders only when `invoice.mollie_payment_method` is truthy, which is unchanged from previous behavior

## Diagnostics

- View any manually-paid invoice detail page on production → Betaalgegevens card should show manual-paid section
- If card is absent: check REST response at `/wp/v2/rondo_invoice/{id}` for `manually_marked_paid_at` field
- If Mollie details missing on a Mollie-paid invoice: check that `mollie_payment_method` is returned in REST response

## Deviations

None.

## Known Issues

None.

## Files Created/Modified

- `src/pages/Finance/FactuurDetail.jsx` — Widened Betaalgegevens card condition, added manual-paid section with UserCheck icon
- `style.css` — Version bumped to 31.13.0
- `package.json` — Version bumped to 31.13.0
- `CHANGELOG.md` — Added 31.13.0 entry with audit trail feature description
