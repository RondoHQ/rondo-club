---
estimated_steps: 5
estimated_files: 1
---

# T03: Render payment details UI on invoice detail page

**Slice:** S01 — Webhook payment detail extraction + REST API + Invoice detail UI
**Milestone:** M002

## Description

Add a "Betaalgegevens" card section to the FactuurDetail page that displays Mollie payment details for paid invoices, and enhance the installment timeline table with payment method and Mollie Dashboard link columns. The section must be absent (not empty) for invoices without Mollie data.

## Steps

1. Add a Dutch method label mapping object near the top of the file (after the existing `planLabels` or `installmentStatusLabels`):
   ```js
   const mollieMethodLabels = {
     ideal: 'iDEAL',
     creditcard: 'Creditcard',
     bancontact: 'Bancontact',
     sofort: 'SOFORT',
     banktransfer: 'Bankoverschrijving',
     eps: 'EPS',
     giropay: 'Giropay',
     przelewy24: 'Przelewy24',
     kbc: 'KBC',
     belfius: 'Belfius',
     paypal: 'PayPal',
     applepay: 'Apple Pay',
     in3: 'iDEAL in3',
     klarna: 'Klarna',
     twint: 'TWINT',
   };
   ```
   Add a helper: `const getMollieMethodLabel = (method) => mollieMethodLabels[method] || method?.charAt(0).toUpperCase() + method?.slice(1) || 'Onbekend';`

2. Add a "Betaalgegevens" card section, placed AFTER the installment timeline section (or after the line items table if no installments). Render conditionally: only when `invoice.mollie_payment_method` is truthy. Content:
   - `<CreditCard>` icon + "Betaalgegevens" heading (follows existing card pattern)
   - Payment method: `getMollieMethodLabel(invoice.mollie_payment_method)`
   - Paid at: `format(new Date(invoice.mollie_paid_at), 'd MMM yyyy HH:mm')` (uses existing `format` import)
   - Consumer name: shown when `invoice.mollie_consumer_name` truthy
   - Consumer IBAN: shown when `invoice.mollie_consumer_account` truthy
   - "Bekijk in Mollie" link: shown when `invoice.mollie_dashboard_url` truthy, uses `target="_blank" rel="noopener noreferrer"` with `ExternalLink` icon (already imported)

3. Enhance the installment timeline table with two new columns:
   - Add `<th>` for "Methode" after the "Status" column header
   - Add `<th>` for "Mollie" after the "Methode" column (empty header, like image columns in other tables)
   - In each row, add `<td>` for method label (using `getMollieMethodLabel(inst.mollie_method)`) — show only when `inst.mollie_method` is truthy, otherwise show '-'
   - Add `<td>` for Mollie dashboard link — when `inst.mollie_dashboard_url` is truthy, show `ExternalLink` icon wrapped in an `<a>` tag; otherwise show '-'

4. Verify `CreditCard` icon is already imported (line 3 of file). If not, add it to the lucide-react import.

5. Run `npm run lint` and `npm run build` to verify no errors introduced.

## Must-Haves

- [ ] `mollieMethodLabels` object covers all common Mollie payment methods with Dutch labels
- [ ] Fallback for unknown methods: capitalize raw string
- [ ] "Betaalgegevens" card section renders ONLY when `invoice.mollie_payment_method` is truthy
- [ ] Card shows payment method, paid-at timestamp, consumer details (conditional), and Mollie link (conditional)
- [ ] `ExternalLink` icon used for "Bekijk in Mollie" link with `target="_blank" rel="noopener noreferrer"`
- [ ] Installment table gains "Methode" and "Mollie" columns for paid installments
- [ ] Dark mode support using existing `dark:` utility classes (following existing card patterns)
- [ ] `npm run lint` passes with zero errors
- [ ] `npm run build` succeeds

## Verification

- `npm run lint` exits 0 with zero warnings
- `npm run build` exits 0 with no errors
- Code review: "Betaalgegevens" section is conditionally rendered on `invoice.mollie_payment_method`
- Code review: installment table has 7 columns (was 5), new columns show method and link for paid installments
- Visual verification (after deploy): paid Mollie invoice shows the card; non-Mollie invoice does not

## Observability Impact

- Signals added/changed: None — pure frontend rendering of existing API data
- How a future agent inspects this: Browser DevTools Network tab shows `mollie_payment_method` in invoice API response; React DevTools shows component rendering
- Failure state exposed: None — graceful degradation (section absent when data missing)

## Inputs

- `src/pages/Finance/FactuurDetail.jsx` — 1085-line file; card sections pattern; installment table at ~line 748; ExternalLink already imported; format/parseYmd/formatCurrency already imported
- T02 output: REST API returns `mollie_payment_method`, `mollie_paid_at`, `mollie_dashboard_url`, `mollie_consumer_name`, `mollie_consumer_account` at invoice level; `mollie_method`, `mollie_paid_at`, `mollie_dashboard_url` per installment

## Expected Output

- `src/pages/Finance/FactuurDetail.jsx` — enhanced with "Betaalgegevens" card section and enriched installment timeline table
