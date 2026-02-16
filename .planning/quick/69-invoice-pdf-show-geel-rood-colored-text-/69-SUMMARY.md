# Quick Task 69: Invoice PDF — colored Geel/Rood card type text

## Summary
Replaced verbose "Gele kaart: waarschuwing" / "Rode kaart: ..." text in the Kaart column
with simple colored text: "Geel" (yellow #ca8a04) or "Rood" (red #dc2626).

## Changes
- Simplified card_type output to just "Geel" or "Rood" with matching color
- Removed charge_description dependency (no longer needed in output)
- Changed card_type output from esc_html to raw HTML (trusted internal content)

## File Modified
- `includes/class-invoice-pdf-generator.php`
