# Quick Task 68: Center QR code scan text in invoice PDF

## Summary
Fixed alignment of "Scan om te betalen" text under QR code in invoice PDF.

## Changes
- Changed td `text-align` from `right` to `center` so mPDF centers both image and text
- Removed unnecessary wrapper div (td handles centering directly)

## File Modified
- `includes/class-invoice-pdf-generator.php` (1 line changed)
