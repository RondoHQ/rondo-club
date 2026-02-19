---
phase: 108-fix-termijnbetaling-uitschakelen-default
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - includes/class-bulk-invoice-creator.php
autonomous: true

must_haves:
  truths:
    - "Bulk-created membership invoices have installments enabled by default (Termijnbetaling uitschakelen is unchecked)"
    - "The public payment page token is always generated for bulk-created invoices"
  artifacts:
    - path: "includes/class-bulk-invoice-creator.php"
      provides: "Bulk invoice creation without forced _disable_installments meta"
  key_links:
    - from: "includes/class-bulk-invoice-creator.php"
      to: "PublicPaymentPage::generate_token"
      via: "direct call (no conditional)"
      pattern: "PublicPaymentPage::generate_token"
---

<objective>
Remove the forced `_disable_installments` default from bulk invoice creation so installments are enabled by default, and always generate the public payment page token.

Purpose: The "Termijnbetaling uitschakelen" checkbox in FactuurDetail.jsx is currently pre-checked for all bulk-created membership invoices because the backend forces `_disable_installments = '1'`. The default should be off (installments enabled).
Output: Updated `class-bulk-invoice-creator.php` where the forced disable is removed and token generation is unconditional.
</objective>

<execution_context>
@/Users/joostdevalk/.claude/get-shit-done/workflows/execute-plan.md
@/Users/joostdevalk/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/STATE.md
</context>

<tasks>

<task type="auto">
  <name>Task 1: Remove forced installment disable and unconditional token generation</name>
  <files>includes/class-bulk-invoice-creator.php</files>
  <action>
In `includes/class-bulk-invoice-creator.php`, replace lines 299-306:

BEFORE:
```php
// Default: disable installments for bulk-created membership invoices.
update_post_meta( $post_id, '_disable_installments', '1' );

// Only generate betaling page token if installments are enabled.
// When disabled, send_invoice() creates a direct Mollie link instead.
if ( ! get_post_meta( $post_id, '_disable_installments', true ) ) {
    PublicPaymentPage::generate_token( $post_id );
}
```

AFTER:
```php
// Generate betaling page token for the public payment page.
PublicPaymentPage::generate_token( $post_id );
```

This removes the forced `_disable_installments = '1'` meta and the conditional guard around token generation. Installments will now be enabled by default (no meta set = enabled), and the payment page token is always created.
  </action>
  <verify>
1. Search the file to confirm `_disable_installments` is no longer set in `create_invoice_for_person()`:
   `grep -n '_disable_installments' includes/class-bulk-invoice-creator.php`
   Should return no results (or only results outside this function if any).
2. Confirm `PublicPaymentPage::generate_token` is called unconditionally:
   `grep -n 'generate_token' includes/class-bulk-invoice-creator.php`
   Should show a direct call with no surrounding `if` block.
3. Run `npm run build` to verify no build errors.
  </verify>
  <done>
- `update_post_meta( $post_id, '_disable_installments', '1' )` is removed from `create_invoice_for_person()`
- `PublicPaymentPage::generate_token( $post_id )` is called directly without a conditional
- Build passes clean
  </done>
</task>

</tasks>

<verification>
After the fix, bulk-created membership invoices should open in FactuurDetail.jsx with the "Termijnbetaling uitschakelen" checkbox unchecked (installments enabled by default). The public payment page link should still be available for all bulk-created invoices.
</verification>

<success_criteria>
New bulk-created membership invoices have `_disable_installments` unset (installments on by default), and `PublicPaymentPage::generate_token` is always called during bulk invoice creation.
</success_criteria>

<output>
After completion, create `.planning/quick/108-fix-termijnbetaling-uitschakelen-default/108-SUMMARY.md`
</output>
