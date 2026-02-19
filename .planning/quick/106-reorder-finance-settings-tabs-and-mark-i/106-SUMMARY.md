# Quick Task 106: Reorder Finance Settings tabs and mark inactive payment provider

## Summary
Reordered tabs to: Organisatie, Betaling, Contributie, E-mail, Mollie, Rabobank. The inactive payment provider tab now shows "(niet in gebruik)" based on the active_payment_provider setting.

## Changes
- `src/pages/Finance/FinanceSettings.jsx`: Reordered TABS array, added dynamic label logic for Mollie/Rabobank tabs
