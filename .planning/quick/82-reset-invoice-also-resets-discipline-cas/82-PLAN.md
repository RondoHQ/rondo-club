---
phase: quick-82
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - includes/class-rest-invoices.php
autonomous: true
---

<objective>
When resetting an invoice, also reset the `is_charged` field on all linked discipline cases back to empty string (= "Nee").
</objective>

<context>
@includes/class-rest-invoices.php
</context>

<tasks>

<task type="auto">
  <name>Task 1: Reset discipline cases doorbelast on invoice reset</name>
  <files>includes/class-rest-invoices.php</files>
  <action>
  In `reset_payment_state()` method (around line 1007, after setting status to draft), add code to:

  1. Get the invoice's `line_items` repeater via `get_field('line_items', $invoice_id)`
  2. Loop through each item, and if `$item['discipline_case']` is set:
     - Call `update_field('is_charged', '', (int) $item['discipline_case'])` to reset doorbelast to "Nee"

  This mirrors the inverse of what `send_invoice()` does at line 783 where it sets `is_charged` to `'rondo'`.

  Place this code BEFORE the "Return updated invoice" comment block.
  </action>
  <verify>
  - grep for 'is_charged.*reset_payment_state\|reset.*is_charged' in class-rest-invoices.php
  - npm run build succeeds
  </verify>
  <done>
  - All discipline cases linked to the invoice have is_charged reset to "" (Nee) when invoice is reset
  </done>
</task>

</tasks>

<output>
After completion, create `.planning/quick/82-reset-invoice-also-resets-discipline-cas/82-SUMMARY.md`
</output>
