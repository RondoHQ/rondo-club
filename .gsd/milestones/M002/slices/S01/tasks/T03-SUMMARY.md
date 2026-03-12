---
id: T03
parent: S01
milestone: M002
provides:
  - "Betaalgegevens" card section on FactuurDetail page showing Mollie payment method, paid-at timestamp, consumer details, and Mollie Dashboard link
  - Installment timeline table enhanced with "Methode" and "Mollie" link columns
  - Dutch label mapping for 15 common Mollie payment methods with fallback
key_files:
  - src/pages/Finance/FactuurDetail.jsx
key_decisions:
  - Placed "Betaalgegevens" card after installment timeline and before action buttons for logical reading order
  - Used empty th for Mollie link column (icon-only column, matches pattern used in other tables)
  - Fallback for unknown methods uses charAt(0).toUpperCase() + slice(1) with final 'Onbekend' fallback for null
patterns_established:
  - mollieMethodLabels mapping object for reuse across any future Mollie method display
  - getMollieMethodLabel() helper with two-level fallback (dictionary → capitalize → 'Onbekend')
observability_surfaces:
  - none (pure frontend rendering; API data verified via curl)
duration: 15m
verification_result: passed
completed_at: 2026-03-12
blocker_discovered: false
---

# T03: Render payment details UI on invoice detail page

**Added "Betaalgegevens" card and enriched installment table with Mollie payment method and dashboard link columns on FactuurDetail page.**

## What Happened

Added three pieces to `FactuurDetail.jsx`:

1. **`mollieMethodLabels` mapping + `getMollieMethodLabel()` helper** — 15 Mollie payment methods with Dutch labels (iDEAL, Creditcard, Bankoverschrijving, etc.) and two-level fallback: capitalize raw string → 'Onbekend'.

2. **"Betaalgegevens" card section** — Conditionally rendered only when `invoice.mollie_payment_method` is truthy. Shows payment method label, paid-at timestamp (formatted with `d MMM yyyy HH:mm`), consumer name (when present), consumer IBAN (when present), and "Bekijk in Mollie" external link to dashboard URL. Uses the existing card/heading pattern with `CreditCard` icon.

3. **Installment table enhancement** — Added two columns: "Methode" (shows `getMollieMethodLabel(inst.mollie_method)` or '-') and an icon-only column with `ExternalLink` linking to `inst.mollie_dashboard_url` (or '-'). Table now has 7 columns (was 5).

## Verification

- `npm run lint` — exits 0 with zero warnings/errors ✅
- `npm run build` — exits 0, `FactuurDetail-B6UY3I0T.js` built at 30.94 kB ✅
- `php -l includes/class-mollie-webhook.php` — no syntax errors ✅
- `php -l includes/class-rest-invoices.php` — no syntax errors ✅
- Deployed to production via `bin/deploy.sh` ✅
- REST API `GET /wp-json/rondo/v1/invoices/6464` returns all 5 Mollie fields (null for pre-deployment invoices) ✅
- REST API `GET /wp-json/rondo/v1/invoices/6466` (unpaid) returns all 5 Mollie fields as null ✅
- Code review: "Betaalgegevens" section conditionally rendered on `invoice.mollie_payment_method` ✅
- Code review: installment table has 7 `<th>` elements (Termijn, Vervaldatum, Bedrag, Status, Betaald op, Methode, [Mollie link]) ✅

### Slice-level verification (partial — T03 is intermediate task):
- ✅ `npm run build` — frontend compiles without errors
- ✅ `npm run lint` — zero ESLint warnings/errors
- ✅ PHP syntax checks pass for both webhook and invoices classes
- ✅ REST API returns `mollie_payment_method`, `mollie_paid_at`, `mollie_dashboard_url` fields
- ⏳ Browser visual verification of "Betaalgegevens" card (requires logged-in browser session — deferred to T04 UAT)
- ⏳ Mollie test-mode payment trigger (T04 scope)

## Diagnostics

- Browser DevTools Network tab: invoice detail API response includes `mollie_payment_method`, `mollie_paid_at`, `mollie_dashboard_url`, `mollie_consumer_name`, `mollie_consumer_account`
- React DevTools: "Betaalgegevens" section renders only when `mollie_payment_method` is truthy
- For invoices paid before T01 deployment: all Mollie fields return null → card absent (graceful degradation)

## Deviations

None.

## Known Issues

None.

## Files Created/Modified

- `src/pages/Finance/FactuurDetail.jsx` — Added `mollieMethodLabels` mapping, `getMollieMethodLabel()` helper, "Betaalgegevens" card section, and two new columns in installment timeline table
