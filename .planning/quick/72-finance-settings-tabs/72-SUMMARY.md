# Quick Task 72: Add tab navigation to Finance Settings

## Summary
Split Finance Settings into 4 tabs to reduce information overload.

## Tabs
1. **Organisatie** — Org name, address, email, BCC, logo, accent color
2. **Betaling** — IBAN, payment term, payment clause
3. **E-mail** — Email template with variable documentation
4. **Rabobank** — Connection status, certificate, API credentials

## Changes
- Added TabButton import and TABS config array
- Added activeTab state (defaults to 'organization')
- Tab bar renders above content with existing TabButton component
- Each section conditionally rendered based on activeTab
- Save button and error/success messages always visible

## File Modified
- `src/pages/Finance/FinanceSettings.jsx`
