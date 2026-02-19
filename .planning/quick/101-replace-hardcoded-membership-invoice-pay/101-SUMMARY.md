# Quick Task 101: Replace hardcoded membership invoice payment text

## What changed
- Removed hardcoded text "Je ontvangt per e-mail een betaallink waarmee je direct kunt betalen of een betaalplan kunt kiezen." from membership invoice PDF
- Replaced with the `membership_payment_clause` setting text (from Financien > Instellingen > Betalingsclausule contributie)
- Text renders in the same `<p>` style with `margin: 0; line-height: 1.6`
- If the setting is empty, no text is shown at all

## Files changed
- `includes/class-invoice-pdf-generator.php` — replaced hardcoded paragraph with dynamic setting

## Commit
- `6cb4e297` — feat(quick-101): replace hardcoded membership payment text with betalingsclausule setting
