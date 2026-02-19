# Quick Task 97: Show family discount and pro-rata discount on invoice

## Goal
Show discount breakdowns on membership invoices instead of just the final amount. Use clear Dutch wording (especially for pro-rata → "Instapkorting").

## Context
Currently, `BulkInvoiceCreator::create_membership_invoice()` creates a single line item with `final_fee` (all discounts already applied). The `$fee_data` array already contains:
- `base_fee` — original category fee
- `family_discount_rate` — 0.0, 0.25, or 0.50
- `family_discount_amount` — discount in euros
- `fee_after_discount` — fee after family discount (before pro-rata)
- `prorata_percentage` — 1.0, 0.75, 0.50, or 0.25 (fraction they PAY)
- `final_fee` — final amount after both discounts

The PDF (`class-invoice-pdf-generator.php`) and email renderers already iterate over `line_items`, so adding more line items automatically shows them.

## Plan

### Task 1: Add discount line items to BulkInvoiceCreator
**File:** `includes/class-bulk-invoice-creator.php`

Change `create_membership_invoice()` lines 256-264. Instead of one line item with `final_fee`, build a multi-line breakdown:

```
Line items:
1. "Contributie {season}" → base_fee (always present)
2. "Gezinskorting ({rate}%)" → -family_discount_amount (only if family_discount_amount > 0)
3. "Instapkorting ({discount}%)" → -(fee_after_discount - final_fee) (only if prorata_percentage < 1.0)
```

The `total_amount` field stays `final_fee` (unchanged).

Pro-rata discount percentage for the label = `round((1 - prorata_percentage) * 100)` (e.g., prorata_percentage 0.75 → "Instapkorting (25%)").

Family discount label example: "Gezinskorting (25%)" for rate 0.25.

### Task 2: Handle negative amounts in PDF renderer
**File:** `includes/class-invoice-pdf-generator.php`

The PDF renders amounts as `€ X,XX`. Negative discount amounts need to display correctly. Currently `number_format()` on a negative float produces `-X,XX`. The formatted string would be `€ -25,00` which is acceptable but `- € 25,00` reads better in Dutch financial context.

Add a small formatting adjustment: if amount < 0, format as `- € {abs}`.

### Task 3: Verify email renderer handles negative amounts
**File:** `includes/class-invoice-email-sender.php`

Check that the email line-item rendering works with negative amounts and adjust if needed (same `- € X,XX` pattern).

## Verification
- Build frontend: `npm run build`
- Review that existing discipline invoices (single positive line item) still render correctly
- Review that membership invoices with no discounts show a single line
- Review that membership invoices with family discount show 2 lines
- Review that membership invoices with pro-rata show 2 lines
- Review that membership invoices with both discounts show 3 lines
