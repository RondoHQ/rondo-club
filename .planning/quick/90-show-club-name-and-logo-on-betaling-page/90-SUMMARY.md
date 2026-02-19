---
phase: quick-90
status: complete
---

## Summary

Updated the /betaling public payment page to show the club name (from FinanceConfig `org_name`) with the club logo to its left, replacing the WordPress site title (`get_bloginfo('name')`).

## Changes

- **`includes/class-public-payment-page.php`**
  - Added `get_club_branding()` — fetches org_name and club_logo_url from FinanceConfig, falls back to site title
  - Added `render_header_card($heading)` — renders logo + club name + page heading consistently
  - Updated payment page, success page, and error page to use `render_header_card()`
  - Added CSS: `.club-brand` (flex layout), `.club-logo` (32px), updated `.club-name`

## Commit

- `cff97229`: feat(quick-90): show club name and logo on /betaling page
