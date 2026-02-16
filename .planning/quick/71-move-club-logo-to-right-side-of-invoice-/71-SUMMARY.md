# Quick Task 71: Move club logo to right side of invoice header

## Summary
Swapped invoice header layout: org name/address on the left, club logo on the right at a larger size.

## Changes
- Header now uses table layout (mPDF compatible) with org info left, logo right
- Logo height increased from 50px to 70px
- Logo cell is right-aligned with 100px width
- Logo only renders in right column when configured (graceful fallback)

## File Modified
- `includes/class-invoice-pdf-generator.php`
