# Quick Task 102: Fix invoice status on Nog te factureren page

## What changed
- Fixed `post_status` from `'publish'` to `['rondo_draft', 'rondo_sent', 'rondo_paid', 'rondo_overdue']`
- Fixed person lookup from `_invoice_person_id` to `person` (ACF meta key)
- Fixed status lookup from `invoice_status` to `status` (ACF meta key)

## Files changed
- `includes/class-rest-api.php` — 3 line changes in `get_fee_list()` method

## Commit
- `ab7a1d2a` — fix(quick-102): fix invoice status lookup on Nog te factureren page
