---
phase: quick-90
plan: 01
type: execute
---

## Objective

Show the club name (from FinanceConfig org_name) and club logo on the /betaling public payment page instead of the WordPress site title. Logo appears to the left of the club name.

## Tasks

### Task 1: Add branding helpers and update all 3 page renders

**File:** `includes/class-public-payment-page.php`

- Add `get_club_branding()` helper to fetch org_name and logo URL from FinanceConfig
- Add `render_header_card()` helper to render logo + club name + heading consistently
- Replace inline header HTML in payment page, success page, and error page
- Add CSS for `.club-brand` (flex row), `.club-logo` (32px), update `.club-name`
