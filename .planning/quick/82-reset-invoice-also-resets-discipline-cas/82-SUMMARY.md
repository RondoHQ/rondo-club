# Quick Task 82 Summary

## Reset discipline cases doorbelast when resetting invoice

**Commit:** 942b3054
**Files:** includes/class-rest-invoices.php
**Duration:** <1 min

### What Changed

In `reset_payment_state()`, added a loop through the invoice's `line_items` repeater that resets `is_charged` to `""` (Nee) on all linked discipline cases. This is the inverse of `send_invoice()` line 783 which sets `is_charged` to `"rondo"` when sending.

### Verification

- Build passes
- Deployed to production
