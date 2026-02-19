# Quick Task 102: Fix invoice status on Nog te factureren page

## Problem
The "Nog te factureren" page always showed "Geen factuur" for all members, even when invoices existed.

## Root cause
Three bugs in `get_fee_list()` in `includes/class-rest-api.php`:
1. `post_status => 'publish'` — invoices use custom statuses (`rondo_draft`, `rondo_sent`, etc.)
2. Meta key `_invoice_person_id` doesn't exist — ACF stores person as `person`
3. Meta key `invoice_status` doesn't exist — ACF stores status as `status`

## Fix
Correct all three meta key references and post_status array.
