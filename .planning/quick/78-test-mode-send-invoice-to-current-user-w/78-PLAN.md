---
phase: quick-78
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - includes/class-invoice-email-sender.php
  - includes/class-rest-invoices.php
  - src/pages/Finance/FactuurDetail.jsx
autonomous: true

must_haves:
  truths:
    - "In test mode, sending an invoice delivers the email to the current logged-in user, not the person on the invoice"
    - "In test mode, no BCC address is included in the outgoing email"
    - "In test mode, the email subject has a [TEST] prefix"
    - "In test mode, the 'Verstuur factuur' button shows 'Verstuur factuur (test)'"
    - "In test mode, the 'Opnieuw versturen' button shows 'Opnieuw versturen (test)'"
    - "In production mode, all existing behavior is unchanged"
  artifacts:
    - path: "includes/class-invoice-email-sender.php"
      provides: "send() accepts optional $options array with override_email and skip_bcc"
    - path: "includes/class-rest-invoices.php"
      provides: "send_invoice() and resend_invoice() pass test-mode options to InvoiceEmailSender"
    - path: "src/pages/Finance/FactuurDetail.jsx"
      provides: "Button labels reflect test mode state"
  key_links:
    - from: "includes/class-rest-invoices.php"
      to: "includes/class-invoice-email-sender.php"
      via: "InvoiceEmailSender::send($invoice_id, $options)"
      pattern: "InvoiceEmailSender::send\\("
---

<objective>
In test mode (Mollie test / Rabobank sandbox), invoice emails must be redirected to the current logged-in user to prevent accidental emails to real club members during testing. BCC is suppressed and a [TEST] prefix is added to the subject. The UI buttons show "(test)" labels so administrators know they are in test mode before clicking.

Purpose: Prevent test invoice emails from reaching real members while preserving all production behavior.
Output: Modified InvoiceEmailSender, REST send/resend methods, and FactuurDetail button labels.
</objective>

<execution_context>
@/Users/joostdevalk/.claude/get-shit-done/workflows/execute-plan.md
@/Users/joostdevalk/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/PROJECT.md
@.planning/STATE.md
</context>

<tasks>

<task type="auto">
  <name>Task 1: Add $options support to InvoiceEmailSender::send()</name>
  <files>includes/class-invoice-email-sender.php</files>
  <action>
    Change the method signature from `public static function send( int $invoice_id )` to `public static function send( int $invoice_id, array $options = [] )`.

    Apply options at the appropriate points:

    1. After resolving $person_email (line ~85), check for override:
       ```php
       $recipient_email = $options['override_email'] ?? $person_email;
       ```

    2. After building $subject (line ~143), check for test prefix:
       ```php
       if ( ! empty( $options['override_email'] ) || ! empty( $options['skip_bcc'] ) ) {
           $subject = '[TEST] ' . $subject;
       }
       ```

    3. In the BCC block (lines ~151-155), wrap with skip_bcc check:
       ```php
       if ( empty( $options['skip_bcc'] ) ) {
           $bcc_email = $config->get_bcc_email();
           if ( ! empty( $bcc_email ) ) {
               $headers[] = 'Bcc: ' . $bcc_email;
           }
       }
       ```

    4. Change the wp_mail call to use $recipient_email instead of $person_email (line ~179):
       ```php
       $result = wp_mail( $recipient_email, $subject, $email_body, $headers, $attachments );
       ```

    Update the PHPDoc block to document the $options parameter:
    ```
    @param array $options Optional. Associative array of options:
     *                         - override_email (string) Send to this address instead of the person's email.
     *                         - skip_bcc (bool)         When true, omit the BCC header.
    ```
  </action>
  <verify>Run `php -l includes/class-invoice-email-sender.php` — no syntax errors.</verify>
  <done>send() accepts $options array; override_email redirects recipient; skip_bcc suppresses BCC; [TEST] prefix added to subject when either option is active.</done>
</task>

<task type="auto">
  <name>Task 2: Pass test-mode options from REST send/resend endpoints</name>
  <files>includes/class-rest-invoices.php</files>
  <action>
    In `send_invoice()` (around line 742) and `resend_invoice()` (around line 814), replace the bare `InvoiceEmailSender::send( $invoice_id )` calls with test-mode-aware calls.

    For both methods, add before the InvoiceEmailSender call:
    ```php
    // Build email options — redirect to current user in test mode
    $email_options = [];
    if ( $this->is_test_mode_active() ) {
        $current_user = wp_get_current_user();
        if ( ! empty( $current_user->user_email ) ) {
            $email_options['override_email'] = $current_user->user_email;
            $email_options['skip_bcc']       = true;
        }
    }
    ```

    Then change the call to:
    ```php
    $email_result = InvoiceEmailSender::send( $invoice_id, $email_options );
    ```

    No other changes to either method.
  </action>
  <verify>Run `php -l includes/class-rest-invoices.php` — no syntax errors.</verify>
  <done>In test mode, send_invoice() and resend_invoice() pass override_email (current user's email) and skip_bcc=true to InvoiceEmailSender::send(). In production mode, $email_options is empty and behavior is unchanged.</done>
</task>

<task type="auto">
  <name>Task 3: Show (test) suffix on send/resend buttons in test mode</name>
  <files>src/pages/Finance/FactuurDetail.jsx</files>
  <action>
    Two button labels need to reflect test mode. isTestMode already exists (lines 52-58).

    1. "Verstuur factuur" button (around line 434):
       Change text from `Verstuur factuur` to:
       ```jsx
       {isTestMode ? 'Verstuur factuur (test)' : 'Verstuur factuur'}
       ```

    2. "Opnieuw versturen" button (around line 501):
       Change text from `Opnieuw versturen` to:
       ```jsx
       {isTestMode ? 'Opnieuw versturen (test)' : 'Opnieuw versturen'}
       ```

    No other UI changes.
  </action>
  <verify>Run `npm run build` from /Users/joostdevalk/Code/rondo/rondo-club — build completes without errors.</verify>
  <done>Both send buttons display "(test)" suffix when isTestMode is true; no change in production mode.</done>
</task>

</tasks>

<verification>
After all tasks:
- `php -l includes/class-invoice-email-sender.php` — no errors
- `php -l includes/class-rest-invoices.php` — no errors
- `npm run build` — no errors
- In test mode environment: click "Verstuur factuur (test)" on a draft invoice → email arrives in current user's inbox, not the person's inbox, no BCC, subject prefixed with [TEST]
- In production mode: existing behavior unchanged
</verification>

<success_criteria>
- InvoiceEmailSender::send() accepts and respects $options['override_email'] and $options['skip_bcc']
- send_invoice() and resend_invoice() detect test mode and pass correct options
- FactuurDetail shows "(test)" suffix on both send buttons when isTestMode is true
- Production mode: no behavioral change
</success_criteria>

<output>
After completion, create `.planning/quick/78-test-mode-send-invoice-to-current-user-w/78-SUMMARY.md`
</output>
