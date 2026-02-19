---
phase: quick-91
plan: 01
type: execute
---

## Objective

Remove the admin fee note from the /betaling page footer and use the club's accent color for primary buttons.

## Tasks

### Task 1: Remove admin fee note and use accent color

**File:** `includes/class-public-payment-page.php`

- Remove the "Per termijn wordt € X administratiekosten..." paragraph and its CSS
- Extend `get_club_branding()` to return accent_color from FinanceConfig
- Pass accent_color to `render_html_header()` as CSS custom property `--accent-color`
- Update `.btn-primary` to use `var(--accent-color)` instead of hardcoded `#0891b2`
