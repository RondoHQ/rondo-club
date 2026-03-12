# T01: 213-sitewide-rollout 01

**Slice:** S02 — **Milestone:** M001

## Description

Apply correct button tier hierarchy to all Finance-related pages and components.

Purpose: Finance pages are the most complex button area — FactuurDetail alone has ~20 buttons with 3 inline color override patterns (green for mark-paid, red for delete, orange for reset) that must be replaced with proper tier classes. This plan eliminates all rogue inline styles and assigns correct tiers based on action weight.

Output: All 8 Finance-related files using only btn-primary/secondary/tertiary/danger with no inline color overrides.

## Must-Haves

- [ ] "On draft invoice detail: Send uses btn-primary, Mark Paid uses btn-secondary, PDF/Regenerate use btn-tertiary, Delete uses btn-danger"
- [ ] "On sent/overdue invoice detail: Mark Paid uses btn-secondary, Resend uses btn-tertiary, PDF actions use btn-tertiary"
- [ ] "On paid invoice detail: PDF actions use btn-tertiary"
- [ ] "No inline color overrides (border-green, border-red, border-orange, bg-green, bg-deep-midnight) remain on any button in Finance files"
- [ ] "Finance list page Create button uses btn-primary, all utility buttons use btn-tertiary"
- [ ] "InvoiceDraftForm submit uses btn-primary, cancel uses btn-secondary, add/remove line item use btn-tertiary"

## Files

- `src/pages/Finance/FactuurDetail.jsx`
- `src/pages/Finance/Facturen.jsx`
- `src/pages/Finance/FinanceSettings.jsx`
- `src/pages/Finance/FinanceDashboard.jsx`
- `src/pages/Finance/FactuurNieuw.jsx`
- `src/components/finance/InvoiceDraftForm.jsx`
- `src/components/FinancesCard.jsx`
- `src/components/DisciplineCaseTable.jsx`
