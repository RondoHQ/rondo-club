# Quick Task 98: Improve membership invoice PDF

## Goal
Three improvements to membership invoice PDFs:
1. Show category label in line item: "Contributie - Pupillen" instead of "Contributie 2025-2026"
2. Remove "Kaart" and "Schorsing" columns (discipline-only) from membership invoices
3. Remove "Vervaldatum" and use a different "Betaalgegevens" section for membership invoices

## Plan

### Task 1: Add category label to invoice line item description
**File:** `includes/class-bulk-invoice-creator.php`

In `create_membership_invoice()`, the `$fee_data` already contains `category` (slug). Look up the label:

```php
$fees = new MembershipFees(); // already exists on line 171
$categories = $fees->get_categories_for_season( $season );
$category_label = $categories[ $fee_data['category'] ]['label'] ?? $fee_data['category'];
```

Then change the line item description from:
```php
'description' => 'Contributie ' . $season,
```
to:
```php
'description' => 'Contributie - ' . $category_label,
```

### Task 2: Differentiate membership vs discipline PDF layout
**File:** `includes/class-invoice-pdf-generator.php`

**2a. Read invoice_type in `generate()`:**
After line 46, add:
```php
$invoice_type = get_field( 'invoice_type', $invoice_id );
```
Pass `$invoice_type` to `build_html()` as a new parameter.

**2b. Update `build_html()` signature:**
Add `$invoice_type = 'discipline'` as the last parameter (with default for backward compat).

**2c. Conditional table headers:**
For membership invoices, use a simpler 2-column table:
```html
<th style="width: 70%;">Omschrijving</th>
<th style="width: 30%; text-align: right;">Bedrag</th>
```
For discipline, keep the existing 4-column layout.

**2d. Conditional line item rows:**
For membership invoices, the line items loop should output 2-column rows (no card type, no suspension). The total row should use `colspan="1"` instead of `colspan="3"`.

For discipline, keep current 4-column rows.

**2e. Remove Vervaldatum for membership:**
Wrap the vervaldatum table row in a condition:
```php
if ($invoice_type !== 'membership') {
    // show vervaldatum row
}
```

**2f. Different Betaalgegevens section for membership:**
For membership invoices, replace the IBAN-based payment section with:
```html
<div class="payment-section">
    <h2>Betaalgegevens</h2>
    <table><tr>
        <td>
            <p>Je ontvangt per e-mail een betaallink waarmee je direct kunt betalen of een betaalplan kunt kiezen.</p>
        </td>
        {QR code of payment_link if available}
    </tr></table>
</div>
```

To get the QR code for the payment link, read the `qr_code_path` field (it may already be set). If set, show it alongside the text. The QR code for the payment_link would need to be generated if not already present. Check if `qr_code_path` is set; if so, use it. The payment_link QR code is generated separately from the IBAN QR code.

Actually, simpler: pass `$payment_link` to `build_html()`. In the membership section, if there's a QR code path, show it (it will be the payment link QR). If not, just show the text.

For discipline, keep current IBAN + payment clause + QR code.

## Verification
- Run `npm run build` to verify frontend compiles
- Regenerate PDFs for invoices 6188 and 6189 on production to verify
