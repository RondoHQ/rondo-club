# Quick Task 70: Fix invoice PDF column widths

## Summary
Adjusted column widths so "Schorsing" heading fits on one line.

## Changes
- Kaart: 25% → 15% (only shows short "Geel"/"Rood" text)
- Schorsing: 15% → 20% (heading now fits on one line)
- Bedrag: 15% → 20% (better balance)
- Omschrijving: unchanged at 45%

## File Modified
- `includes/class-invoice-pdf-generator.php`
