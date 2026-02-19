---
phase: quick-94
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - includes/class-bulk-invoice-creator.php
  - includes/class-rest-invoices.php
autonomous: true

must_haves:
  truths:
    - "Bulk-created membership invoices with disabled installments have no betaling token and no payment_link"
    - "Toggling installments off clears the payment token and payment_link from existing invoices"
    - "Toggling installments on generates a betaling token for the invoice"
  artifacts:
    - path: "includes/class-bulk-invoice-creator.php"
      provides: "Conditional token generation — skips generate_token when _disable_installments is set"
      contains: "if ( ! get_post_meta( $post_id, '_disable_installments', true ) )"
    - path: "includes/class-rest-invoices.php"
      provides: "Toggle installments clears/generates token accordingly"
      contains: "delete_post_meta( $invoice_id, '_payment_token' )"
  key_links:
    - from: "class-bulk-invoice-creator.php create_membership_invoice()"
      to: "PublicPaymentPage::generate_token()"
      via: "conditional — only called when installments NOT disabled"
    - from: "class-rest-invoices.php toggle_installments()"
      to: "PublicPaymentPage::generate_token()"
      via: "called when re-enabling installments"
---

<objective>
Membership invoices with installments disabled bypass the /betaling/{token} page and use a direct Mollie payment link instead (identical to discipline invoices). Currently, generate_token() is always called during bulk creation, creating an unnecessary betaling page for installment-disabled invoices.

Purpose: Eliminate the extra "Volledig betalen" click on the betaling page for membership invoices where there is only one option.
Output: Two minimal PHP edits — conditional token in BulkInvoiceCreator, token lifecycle management in toggle_installments.
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
  <name>Task 1: Skip token generation in BulkInvoiceCreator when installments disabled</name>
  <files>includes/class-bulk-invoice-creator.php</files>
  <action>
In `create_membership_invoice()` (around line 269), reorder and make token generation conditional:

1. Move `update_post_meta( $post_id, '_disable_installments', '1' )` BEFORE the token generation line.
2. Wrap `PublicPaymentPage::generate_token( $post_id )` in a conditional that only runs when installments are NOT disabled.

Result (replace the current two lines):
```php
// Default: disable installments for bulk-created membership invoices.
update_post_meta( $post_id, '_disable_installments', '1' );

// Only generate betaling page token if installments are enabled.
// When disabled, send_invoice() creates a direct Mollie link instead.
if ( ! get_post_meta( $post_id, '_disable_installments', true ) ) {
    PublicPaymentPage::generate_token( $post_id );
}
```

Note: `PublicPaymentPage` is already imported in this file — check the existing `use` statements; if not present, add `use Rondo\Finance\PublicPaymentPage;` under the existing `use Rondo\Fees\MembershipFees;`.
  </action>
  <verify>Search the file for `generate_token` — it must be inside an `if ( ! get_post_meta` block. Search for `_disable_installments` — it must appear BEFORE the `if` block.</verify>
  <done>create_membership_invoice() sets _disable_installments first, then conditionally skips generate_token — meaning newly created bulk membership invoices have no payment token and no payment_link ACF value.</done>
</task>

<task type="auto">
  <name>Task 2: Manage payment token lifecycle in toggle_installments</name>
  <files>includes/class-rest-invoices.php</files>
  <action>
In `toggle_installments()` (around line 1107), extend the existing if/else to manage the payment token:

Current code to replace:
```php
if ( $disabled ) {
    update_post_meta( $invoice_id, '_disable_installments', '1' );
} else {
    delete_post_meta( $invoice_id, '_disable_installments' );
}
```

New code:
```php
if ( $disabled ) {
    update_post_meta( $invoice_id, '_disable_installments', '1' );
    // Clear betaling page token — send_invoice() creates a direct Mollie link instead.
    delete_post_meta( $invoice_id, '_payment_token' );
    update_field( 'payment_link', '', $invoice_id );
} else {
    delete_post_meta( $invoice_id, '_disable_installments' );
    // Generate betaling page token so member can choose a payment plan.
    \Rondo\Finance\PublicPaymentPage::generate_token( $invoice_id );
}
```

Use a fully-qualified class reference `\Rondo\Finance\PublicPaymentPage::generate_token()` since `class-rest-invoices.php` is in namespace `Rondo\REST` and does not currently import `PublicPaymentPage`. Alternatively, add `use Rondo\Finance\PublicPaymentPage;` to the existing `use` block at the top of the file (after line 17) and call `PublicPaymentPage::generate_token( $invoice_id )`. Use whichever approach is cleaner — adding the `use` import is preferred for consistency with the other imports.
  </action>
  <verify>Search the file for `_payment_token` — it must appear inside the `toggle_installments` function. Search for `PublicPaymentPage` — it must appear in toggle_installments. Run `npm run build` to confirm no PHP-visible JS errors (build verifies the PHP layer is intact by loading the theme).</verify>
  <done>Disabling installments via the toggle API removes the payment token and clears payment_link. Re-enabling generates a fresh token. The betaling page becomes accessible only when installments are on.</done>
</task>

</tasks>

<verification>
After both tasks:
1. Grep `class-bulk-invoice-creator.php` for `generate_token` — must be inside `if ( ! get_post_meta` conditional.
2. Grep `class-rest-invoices.php` for `_payment_token` — must be inside `toggle_installments`.
3. Grep `class-rest-invoices.php` for `PublicPaymentPage` — must be present (either as `use` import or fully-qualified).
4. `npm run build` must succeed with no errors.
</verification>

<success_criteria>
- Newly bulk-created membership invoices (which always have _disable_installments=1) have no payment token and no payment_link ACF field.
- Calling the toggle endpoint with disabled=true on an existing invoice clears its _payment_token and payment_link.
- Calling the toggle endpoint with disabled=false on an existing invoice generates a new betaling token via PublicPaymentPage::generate_token().
- Build passes. No ESLint errors.
</success_criteria>

<output>
After completion, create `.planning/quick/94-use-direct-mollie-payment-link-for-membe/94-SUMMARY.md` following the summary template.

Then update STATE.md quick tasks table with entry #94.
</output>
