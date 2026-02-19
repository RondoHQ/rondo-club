# Quick Task 101: Replace hardcoded membership invoice payment text

## Objective
Remove the fixed text from the membership invoice PDF template and use the betalingsclausule contributie setting instead.

## Task
1. In `includes/class-invoice-pdf-generator.php`, replace the hardcoded `<p>` with the `$membership_payment_clause` text rendered in the same style
